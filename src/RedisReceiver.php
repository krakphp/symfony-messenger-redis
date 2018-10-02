<?php

namespace Krak\SymfonyMessengerRedis;

use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\Serialization\DecoderInterface;

final class RedisReceiver implements ReceiverInterface
{
    private $decoder;
    private $redisConnection;

    public function __construct(DecoderInterface $decoder, RedisConnection $redisConnection) {
        $this->decoder = $decoder;
        $this->redisConnection = $redisConnection;
    }

    /**
     * Receive some messages to the given handler.
     *
     * The handler will have, as argument, the received {@link \Symfony\Component\Messenger\Envelope} containing the message.
     * Note that this envelope can be `null` if the timeout to receive something has expired.
     */
    public function receive(callable $handler): void {
        $message = $this->redisConnection->get();
        if (!$message) {
            $handler(null);
            return;
        }

        try {
            [$body, $headers] = json_decode($message, true);
            $envelope = $this->decoder->decode(['body' => $body, 'headers' => $headers]);
            $handler($envelope);
        } catch (\Throwable $t) {}

        $this->redisConnection->ack($message);
        if ($t ?? null) {
            throw $t;
        }
    }

    /**
     * Stop receiving some messages.
     */
    public function stop(): void {

    }
}
