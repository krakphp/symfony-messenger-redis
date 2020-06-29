<?php

namespace Krak\SymfonyMessengerRedis\Tests\Feature;

trait RedisSteps
{
    /** @var \Redis */
    private $redis;

    private function given_a_redis_client_is_configured_with_a_fresh_redis_db() {
        $this->redis = new \Redis();
        $this->redis->connect('redis');
        $this->redis->auth('password123');
        $this->redis->flushAll();
    }
}
