<?php

namespace Krak\SymfonyMessengerRedis;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\SenderInterface;
use Symfony\Component\Messenger\Transport\Serialization\EncoderInterface;

final class RedisSender implements SenderInterface
{
    private $encoder;
    private $redisConnection;

    public function __construct(EncoderInterface $encoder, RedisConnection $redisConnection) {
        $this->encoder = $encoder;
        $this->redisConnection = $redisConnection;
    }

    /**
     * Sends the given envelope.
     *
     * @param Envelope $envelope
     */
    public function send(Envelope $envelope) {
        $encoded = $this->encoder->encode($envelope);
        $this->redisConnection->publish(\json_encode([$encoded['body'], $encoded['headers']]));
    }
}
