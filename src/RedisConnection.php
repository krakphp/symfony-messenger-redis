<?php

namespace Krak\SymfonyMessengerRedis;

use Redis;

final class RedisConnection implements MetricsRepository
{
    private $redis;
    private $queue;
    private $connectParams;
    private $blockingTimeout;
    private $isConnected;

    public function __construct(Redis $redis, string $queue, array $connectParams = ['127.0.0.1', 6379], int $blockingTimeout = 30) {
        $this->redis = $redis;
        $this->queue = $queue;
        $this->connectParams = $connectParams;
        $this->blockingTimeout = $blockingTimeout;
        $this->isConnected = false;
    }

    public static function fromDsn(string $dsn, array $options = []): self {
        $parsedUrl = \parse_url($dsn);
        if (!$parsedUrl) {
            throw new \InvalidArgumentException(sprintf('The given Redis DSN "%s" is invalid.', $dsn));
        }

        \parse_str($parsedUrl['query'] ?? '', $query);

        if ($options['queue'] ?? $query['queue'] ?? null) {
            throw new \InvalidArgumentException(sprintf('The queue option must be included in the query parameters or configuration options.'));
        }

        return new self(
            new Redis(),
            $options['queue'] ?? $query['queue'],
            [$parsedUrl['host'] ?? '127.0.0.1', intval($parsedUrl['port'] ?? 6379)],
            $options['blocking_timeout'] ?? $query['blocking_timeout'] ?? 30
        );
    }

    public function get(): ?string {
        $this->connect();
        $message = $this->redis->bRPopLPush($this->queue, $this->getProcessingQueue(), $this->blockingTimeout);
        return $message === false ? null : $message;
    }

    public function publish(string $message): void {
        $this->connect();
        $this->addMessageToQueue($this->redis, $message);
    }

    public function ack(string $message): void {
        $this->connect();
        $this->clearMessageFromProcessingQueue($this->redis, $message);
    }

    public function reject(string $message): void {
        $this->connect();
        $client = $this->clearMessageFromProcessingQueue($this->redis->multi(), $message);
        $client = $this->addMessageToQueue($client, $message);
        $client->exec();
    }

    public function getSizeOfQueue(): int {
        $this->connect();
        return (int) $this->redis->lLen($this->queue);
    }

    private function connect(): void {
        if ($this->isConnected) {
            return;
        }

        $this->isConnected = true;
        $this->redis->connect(...$this->connectParams);
    }

    private function clearMessageFromProcessingQueue(Redis $redis, string $message) {
        return $redis->lRem($this->getProcessingQueue(), $message, 1);
    }

    private function addMessageToQueue(Redis $redis, string $message) {
        $res = $redis->lPush($this->queue, $message);
        if ($res === false) {
            throw new \RuntimeException('Failed to push message onto queue.');
        }
        return $res;
    }

    private function getProcessingQueue(): string {
        return $this->queue . '_processing';
    }
}
