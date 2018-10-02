<?php

namespace Krak\SymfonyMessengerRedis;

use Symfony\Component\Messenger\Transport\Serialization\DecoderInterface;
use Symfony\Component\Messenger\Transport\Serialization\EncoderInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class RedisTransportFactory implements TransportFactoryInterface
{
    private $encoder;
    private $decoder;

    public function __construct(EncoderInterface $encoder, DecoderInterface $decoder) {
        $this->encoder = $encoder;
        $this->decoder = $decoder;
    }

    public function createTransport(string $dsn, array $options): TransportInterface {
        $redisConnection = RedisConnection::fromDsn($dsn, $options);
        return new LazyCompositeTransport(
            function() use ($redisConnection) {
                return new Enhancers\InfiniteLoopReceiver(new RedisReceiver($this->decoder, $redisConnection));
            },
            function() use ($redisConnection) { return new RedisSender($this->encoder, $redisConnection); }
        );
    }

    public function supports(string $dsn, array $options): bool {
        return strpos($dsn, "redis://") === 0;
    }
}
