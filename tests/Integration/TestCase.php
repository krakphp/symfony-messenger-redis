<?php

namespace Krak\SymfonyMessengerRedis\Tests\Integration;

use Krak\SymfonyMessengerRedis\RedisConnection;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function createRedisConnection(?string $dsn = null): RedisConnection {
        return RedisConnection::fromDsn($dsn ?? getenv('REDIS_DSN'), ['queue' => 'messenger', 'blocking_timeout' => 1]);
    }

    protected function flushRedis() {
        $this->createRedisClient()->flushAll();
    }

    protected function createRedisClient(?string $dsn = null): \Redis {
        $url = parse_url($dsn ?? getenv('REDIS_DSN'));
        $redis = new \Redis();
        $redis->connect($url['host'], $url['port']);
        return $redis;
    }
}
