<?php

namespace Krak\SymfonyMessengerRedis\Transport;

use Krak\SymfonyMessengerRedis\Stamp\{UniqueStamp, DebounceStamp};
use Redis;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Exception\{InvalidArgumentException, TransportException};

final class RedisTransport implements TransportInterface, MessageCountAwareInterface
{
    private $serializer;
    private $redis;
    private $queue;
    private $connectParams;
    private $configureConnection;
    private $isConnected;

    public function __construct(
        SerializerInterface $serializer,
        Redis $redis,
        string $queue,
        array $connectParams = ['127.0.0.1', 6379],
        callable $configureConnection = null
    ) {
        $this->serializer = $serializer;
        $this->redis = $redis;
        $this->queue = $queue;
        $this->connectParams = $connectParams;
        $this->configureConnection = $configureConnection;
        $this->isConnected = false;
    }

    public static function fromDsn(SerializerInterface $serializer, string $dsn, array $options = []) {
        $parsedUrl = \parse_url($dsn);
        if (!$parsedUrl) {
            throw new InvalidArgumentException(sprintf('The given Redis DSN "%s" is invalid.', $dsn));
        }

        \parse_str($parsedUrl['query'] ?? '', $query);

        if (($options['queue'] ?? $query['queue'] ?? null) === null) {
            throw new InvalidArgumentException('The queue option must be included in the query parameters or configuration options.');
        }

        $host = $parsedUrl['host'] ?? '127.0.0.1';
        if ($parsedUrl['scheme'] === 'rediss') {
            $host = 'tls://'.$host;
        }

        return new self(
            $serializer,
            new Redis(),
            $options['queue'] ?? $query['queue'],
            [$host, intval($parsedUrl['port'] ?? 6379)],
            function(Redis $conn) use ($query, $parsedUrl, $options) {
                $auth = $options['password'] ?? $parsedUrl['pass'] ?? null;
                if ($auth) {
                    $conn->auth($auth);
                }
                $db = $parsedUrl['path'] ?? $options['db'] ?? $query['db'] ?? null;
                if ($db !== null) {
                    $db = intval(ltrim($db, '/'));
                    $conn->select($db);
                }
            }
        );
    }

    /** @return Envelope[] */
    public function get(): iterable {
        $this->connect();
        $this->redis->clearLastError();
        $encodedMessage = $this->redis->eval($this->popLuaScript(), [
            $this->getUniqueSetName(),
            $this->getDelayedSetName(),
            $this->queue,
            $this->getProcessingQueue(),
            round(microtime(true) * 1000),
        ], 4);

        if ($this->redis->getLastError()) {
            throw new TransportException('Failed to retrieve message from queue. Redis Error: ' . $this->redis->getLastError());
        }

        if (!$encodedMessage) {
            return [];
        }

        $res = json_decode($encodedMessage, true);
        $message = isset($res[0], $res[1]) ? ['body' => $res[0], 'headers' => $res[1]] : $res;
        $envelope = $this->serializer->decode($message);
        return [$envelope->with(new TransportMessageIdStamp($encodedMessage))];
    }

    public function ack(Envelope $env): void {
        $this->connect();
        $transportIdStamp = $env->last(TransportMessageIdStamp::class);
        $this->clearMessageFromProcessingQueue($this->redis, $transportIdStamp ? $transportIdStamp->getId() : $this->encodeEnvelope($env));
    }

    public function reject(Envelope $env): void {
        $this->connect();
        $transportIdStamp = $env->last(TransportMessageIdStamp::class);
        $this->clearMessageFromProcessingQueue($this->redis, $transportIdStamp ? $transportIdStamp->getId() : $this->encodeEnvelope($env));
    }

    public function send(Envelope $env): Envelope {
        $this->connect();

        [$message, $uniqueId] = $this->encodeEnvelopeWithUniqueId($env);

        $res = $this->redis->eval($this->pushLuaScript(), [
            $this->getUniqueSetName(),
            $this->getDelayedSetName(),
            $this->queue,
            $uniqueId,
            $this->getStampDelayTimestampMs($env),
            $message,
            $this->isDebounceStampExist($env) ? "1" : "0"
        ], 3);

        if ($this->redis->getLastError()) {
            throw new TransportException('Failed to push message onto queue. Redis Error: ' . $this->redis->getLastError());
        }

        return $env;
    }

    private function getStampDelayTimestampMs(Envelope $env): ?float {
        /** @var DebounceStamp|DelayStamp|null $stamp */
        $stamp = $env->last(DebounceStamp::class) ?: $env->last(DelayStamp::class);
        if (!$stamp) {
            return null;
        }
        return $stamp->getDelay() + (microtime(true) * 1000);
    }

    private function encodeEnvelope(Envelope $env): string {
        return $this->encodeEnvelopeWithUniqueId($env)[0];
    }

    private function encodeEnvelopeWithUniqueId(Envelope $env): array {
        $encoded = $this->serializer->encode($env);
        /** @var DebounceStamp|UniqueStamp|null $stamp */
        $stamp = $env->last(DebounceStamp::class)?: $env->last(UniqueStamp::class);
        $uniqueId = $stamp ? strval($stamp->getId() ?: md5($encoded['body'])) : null;
        return [\json_encode(array_merge($encoded, [
            'uniqueId' => $uniqueId,
        ])), $uniqueId];
    }

    private function isDebounceStampExist(Envelope $env): bool
    {
        return \boolval($env->last(DebounceStamp::class));
    }

    public function getMessageCount(): int {
        $this->connect();
        $pipe = $this->redis->multi(Redis::PIPELINE);
        $pipe->lLen($this->queue);
        $pipe->zCount($this->getDelayedSetName(), '-inf', '+inf');
        return array_sum(array_map('intval', $pipe->exec()));
    }

    private function connect(): void {
        if ($this->isConnected) {
            return;
        }

        $this->isConnected = true;
        $this->redis->connect(...$this->connectParams);
        if ($configureConnection = $this->configureConnection) {
            $configureConnection($this->redis);
        }
    }

    private function clearMessageFromProcessingQueue(Redis $redis, string $message) {
        return $redis->lRem($this->getProcessingQueue(), $message, 1);
    }

    private function getProcessingQueue(): string {
        return $this->queue . '_processing';
    }

    private function getUniqueSetName(): string {
        return $this->queue . ':unique';
    }

    private function getDelayedSetName(): string {
        return $this->queue . ':delayed';
    }

    /** return the lua script for pushing an item into the queue */
    private function pushLuaScript(): string {
        return <<<LUA
-- Push a message onto the queue and set a unique key in
-- a set if a unique id is present. If the unique id already
-- exists, do nothing
local uniqueSetKey = KEYS[1]
local delaySetKey = KEYS[2]
local queueKey = KEYS[3]
local uniqueId = ARGV[1]
local delayTimestampMs = ARGV[2]
local message = ARGV[3]
local isDebounceStampExist = ARGV[4]

-- do we have a unique id and is it apart of the unique set and isDebounceStampExist is true? 
if isDebounceStampExist == "1" and uniqueId ~= "" and redis.call("SISMEMBER", uniqueSetKey, uniqueId) == 1 then
  redis.call("ZREM", delaySetKey, message)
  redis.call("ZADD", delaySetKey, delayTimestampMs, message)
  return 3
end

-- do we have a unique id and is it apart of the unique set?
if uniqueId ~= "" and redis.call("SISMEMBER", uniqueSetKey, uniqueId) == 1 then
  return 1
end

if uniqueId ~= "" then
  redis.call("SADD", uniqueSetKey, uniqueId)
end

if delayTimestampMs ~= "" then
  redis.call("ZADD", delaySetKey, delayTimestampMs, message)
  return 2
end

redis.call("LPUSH", queueKey, message);

return 2
LUA;
    }

    /** return the lua script for popping items off of the queue */
    private function popLuaScript() {
        return <<<LUA
-- Pop a message from the queue into the processing queue and remove the unique id
-- from the unique set if it exists
local uniqueSetKey = KEYS[1]
local delayedSetKey = KEYS[2]
local queueKey = KEYS[3]
local processingQueueKey = KEYS[4]
local currentTimestampMs = ARGV[1]

-- first, we need to enqueue any delayed messages that are ready to be processed
local readyMessages = redis.call("ZRANGEBYSCORE", delayedSetKey, "-inf", currentTimestampMs)
for key,message in pairs(readyMessages) do
  redis.call("LPUSH", queueKey, message)
  redis.call("ZREM", delayedSetKey, message)
end 

local message = redis.call("RPOPLPUSH", queueKey, processingQueueKey)
if message == false then
  return false
end

local decodedMessage = cjson.decode(message)
if type(decodedMessage["uniqueId"]) == "string" then
  redis.call("SREM", uniqueSetKey, decodedMessage["uniqueId"])
end

return message
LUA;

    }
}
