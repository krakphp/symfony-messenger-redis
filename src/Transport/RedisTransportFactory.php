<?php

namespace Krak\SymfonyMessengerRedis\Transport;

use Symfony\Component\Messenger\Transport\{
    TransportInterface,
    TransportFactoryInterface,
    Serialization\SerializerInterface
};

final class RedisTransportFactory implements TransportFactoryInterface
{
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface {
        return RedisTransport::fromDsn($serializer, $dsn, $options);
    }

    /**
     * Symfony's redis factory also matches on the redis:// prefix, so to support using
     * both redis adapters at the same time, the `use_lists` option allows you to opt out
     * of this implementation.
     */
    public function supports(string $dsn, array $options): bool {
        return (strpos($dsn, 'redis://') === 0 || strpos($dsn, 'rediss://') === 0) && boolval($options['use_krak_redis'] ?? true);
    }
}
