<?php

namespace Krak\SymfonyMessengerRedis\Tests\Integration;

final class RedisConnectionTest extends TestCase
{
    protected function setUp() {
        $this->flushRedis();
    }

    public function test_can_add_and_pull_messages() {
        $connection = $this->createRedisConnection();
        $connection->publish('abc');
        $connection->publish('def');
        $connection->publish('ghi');
        $this->assertEquals('abc', $connection->get());
        $this->assertEquals('def', $connection->get());
        $this->assertEquals('ghi', $connection->get());
        $this->assertNull($connection->get(1));
    }

    public function test_can_ack_a_message() {
        $connection = $this->createRedisConnection();
        $redisClient = $this->createRedisClient();
        $connection->publish('abc');
        $connection->publish('def');
        $msgs = [
            $connection->get(),
            $connection->get()
        ];

        $this->assertEquals(0, $redisClient->lLen('messenger'));
        $this->assertEquals(2, $redisClient->lLen('messenger_processing'));

        foreach ($msgs as $msg) {
            $connection->ack($msg);
        }

        $this->assertEquals(0, $redisClient->lLen('messenger_processing'));
    }
}
