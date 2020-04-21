<?php

namespace Krak\SymfonyMessengerRedis\Tests\Feature;

use Krak\SymfonyMessengerRedis\Stamp\UniqueStamp;
use Krak\SymfonyMessengerRedis\Transport\RedisTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;

final class TransportTest extends TestCase
{
    private $transport;
    /** @var Envelope */
    private $envelope;
    /** @var \Redis */
    private $redis;

    protected function setUp() {
        parent::setUp();
        $this->transport = RedisTransport::fromDsn(Serializer::create(), getenv('REDIS_DSN'));
        $this->redis = new \Redis();
        $this->redis->connect('redis');
        $this->redis->flushAll();
    }

    /** @test */
    public function can_send_a_message() {
        $this->given_there_is_a_wrapped_message();
        $this->when_the_message_is_sent_on_the_transport();
        $this->then_the_queue_contains_the_message();
    }

    /** @test */
    public function can_ack_a_message() {
        $this->given_there_is_a_wrapped_message();
        $this->when_the_message_is_sent_received_and_acked();
        $this->then_the_queues_are_empty();
    }

    /** @test */
    public function can_ack_a_message_with_new_stamps() {
        $this->given_there_is_a_wrapped_message();
        $this->when_the_message_is_sent_received_and_acked(function(Envelope $env) {
            return $env->with(new BusNameStamp('bus'));
        });
        $this->then_the_queues_are_empty();
    }

    /** @test */
    public function can_reject_a_message() {
        $this->given_there_is_a_wrapped_message();
        $this->when_the_message_is_sent_received_and_rejected();
        $this->then_the_queues_are_empty();
    }

    /**
     * @test
     * @dataProvider provide_unique_message_ids
     */
    public function can_handle_unique_messages(?string $id = null) {
        $this->given_there_is_a_wrapped_message();
        $this->given_there_is_a_unique_stamp_on_the_message($id);
        $this->when_the_message_is_sent_on_the_transport(5);
        $this->then_the_queue_has_size(1);
    }

    public function provide_unique_message_ids() {
        yield 'no id' => [null];
        yield 'id 123' => ['123'];
    }

    /** @test */
    public function can_repush_unique_messages_after_they_have_been_processed() {
        $this->given_there_is_a_wrapped_message();
        $this->given_there_is_a_unique_stamp_on_the_message('123');
        $this->given_the_message_has_been_processed_through_the_queue();
        $this->when_the_message_is_sent_on_the_transport(1);
        $this->then_the_queue_has_size(1);
    }

    /** @test */
    public function test_can_delay_messages() {
        $this->given_there_is_a_wrapped_message();
        $this->given_there_is_a_delay_stamp_on_the_message(100);
        $this->when_the_message_is_sent_on_the_transport(1);
        $this->then_the_queue_has_size(1);
        $this->then_the_message_is_not_available_immediately();
    }

    public function test_can_delay_messages_and_wait_for_receiving() {
        $this->given_there_is_a_wrapped_message();
        $this->given_there_is_a_delay_stamp_on_the_message(100);
        $this->when_the_message_is_sent_on_the_transport(1);
        $this->then_the_message_is_available_after(100);
        $this->then_the_queue_has_size(0);
    }

    private function given_there_is_a_wrapped_message() {
        $this->envelope = Envelope::wrap(new Fixtures\KrakRedisMessage());
    }

    private function given_there_is_a_unique_stamp_on_the_message(?string $id = null) {
        $this->envelope = $this->envelope->with(new UniqueStamp($id));
    }

    private function given_there_is_a_delay_stamp_on_the_message(int $delayMs) {
        $this->envelope = $this->envelope->with(new DelayStamp($delayMs));
    }

    public function given_there_is_a_wrapped_message_in_the_queue() {
        $this->given_there_is_a_wrapped_message();
        $this->when_the_message_is_sent_on_the_transport();
    }

    private function given_the_message_has_been_processed_through_the_queue() {
        $this->when_the_message_is_sent_received_and_acked();
    }

    private function when_the_message_is_sent_on_the_transport(int $numberOfTimes = 1) {
        for ($i = 0; $i < $numberOfTimes; $i++) {
            $this->transport->send($this->envelope);
        }
    }

    private function when_the_message_is_sent_received_and_acked(callable $stampEnv = null) {
        $this->transport->send($this->envelope);
        [$env] = $this->transport->get();
        $env = $stampEnv ? $stampEnv($env) : $env;
        $this->transport->ack($env);
    }

    private function when_the_message_is_sent_received_and_rejected() {
        $this->transport->send($this->envelope);
        [$env] = $this->transport->get();
        $this->transport->reject($env);
    }

    private function then_the_queues_are_empty() {
        $this->assertEquals(0, $this->redis->lLen('messenger'));
        $this->assertEquals(0, $this->redis->lLen('messenger_processing'));
        $this->assertEquals(0, $this->redis->zCard('messenger:delayed'));
        $this->assertEquals(0, $this->redis->sCard('messenger:unique'));
    }

    private function then_the_queue_contains_the_message() {
        $encodedMessage = $this->redis->lPop('messenger');
        $this->assertEquals(
            <<<'CONTENT'
{"body":"{\"id\":null}","headers":{"type":"Krak\\SymfonyMessengerRedis\\Tests\\Feature\\Fixtures\\KrakRedisMessage","Content-Type":"application\/json"},"uniqueId":null}
CONTENT
            , $encodedMessage
        );
    }

    public function then_the_queue_has_size(int $size): void {
        $this->assertEquals($size, $this->redis->lLen('messenger') + $this->redis->zCard('messenger:delayed'));
    }

    public function then_the_message_is_not_available_immediately() {
        $res = $this->transport->get();
        $this->assertCount(0, $res);
    }

    public function then_the_message_is_available_after(int $delayMs) {
        usleep($delayMs * 1000);
        $res = $this->transport->get();
        $this->assertCount(1, $res);
    }
}
