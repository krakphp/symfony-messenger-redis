<?php

namespace Krak\SymfonyMessengerRedis\Tests\Feature;

use Krak\SymfonyMessengerRedis\Stamp\DebounceStamp;
use Krak\SymfonyMessengerRedis\Stamp\UniqueStamp;
use Krak\SymfonyMessengerRedis\Transport\RedisTransport;
use Krak\SymfonyMessengerRedis\Transport\RedisTransportFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;

final class TransportTest extends TestCase
{
    use RedisSteps;

    /** @var TransportFactoryInterface */
    private $transportFactory;
    private $transport;
    /** @var Envelope */
    private $envelope;

    protected function setUp(): void {
        parent::setUp();
        $this->transportFactory = new RedisTransportFactory();
        $this->given_a_redis_client_is_configured_with_a_fresh_redis_db();
        $this->given_the_redis_transport_is_setup_from_dsn_and_options();
    }

    /**
     * @test
     * @dataProvider provide_custom_redis_urls_with_db
     */
    public function allows_custom_db(string $place) {
        $this->given_the_redis_transport_contains_db('1', $place);
        $this->given_there_is_a_wrapped_message();
        $this->when_the_message_is_sent_on_the_transport();
        $this->then_the_queues_are_empty();
    }

    public function provide_custom_redis_urls_with_db() {
        yield 'db in query params' => ['query_string'];
        yield 'db in path' => ['path'];
        yield 'db in options' => ['options'];
    }

    /** @test */
    public function supports_tls() {
        $this->given_the_redis_transport_is_setup_from_dsn_and_options('rediss://redis?queue=messenger');
        $this->then_the_redis_transport_connect_params_use_tls();
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

    /** @test */
    public function can_receive_messages_from_legacy_serialization_format() {
        $this->given_there_is_a_message_on_the_queue_with_legacy_serialization();
        $this->when_the_message_is_received_and_acked();
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
    public function can_delay_messages() {
        $this->given_there_is_a_wrapped_message();
        $this->given_there_is_a_delay_stamp_on_the_message(100);
        $this->when_the_message_is_sent_on_the_transport(1);
        $this->then_the_queue_has_size(1);
        $this->then_the_message_is_not_available_immediately();
    }

    /** @test */
    public function can_delay_messages_and_wait_for_receiving() {
        $this->given_there_is_a_wrapped_message();
        $this->given_there_is_a_delay_stamp_on_the_message(100);
        $this->when_the_message_is_sent_on_the_transport(1);
        $this->then_the_message_is_available_after(100);
        $this->then_the_queue_has_size(0);
    }

    /** @test */
    public function can_handle_debounce_stamp_messages() {
        $this->given_there_is_a_wrapped_message();
        $this->given_there_is_a_debounce_stamp_on_the_message(100, '456');
        $this->when_the_message_is_sent_on_the_transport(5);
        $this->then_the_queue_has_size(1);
    }

    /** @test */
    public function can_debounce_messages_and_wait_for_receiving() {
        $this->given_there_is_a_wrapped_message();
        $this->given_there_is_a_debounce_stamp_on_the_message(100, '7890');
        $this->given_the_message_is_sent_on_the_transport();
        $this->when_another_message_is_sent_on_the_transport_with_same_stamp_after_a_delay_of(500);
        $this->then_the_message_is_available_after(100);
        $this->then_the_queue_has_size(0);
    }

    /**
     * @test
     * @dataProvider provide_get_message_count_for_stamps
     */
    public function get_message_count_for_stamps(StampInterface $stamp, int $count) {
        $this->given_there_is_a_wrapped_message();
        $this->given_there_is_a_stamp_on_the_message($stamp);
        $this->when_the_message_is_sent_on_the_transport();
        $this->then_message_count_in_the_queue($count);
    }

    public function provide_get_message_count_for_stamps()
    {
        yield 'unique stamp' => [new UniqueStamp(1), 1];
        yield 'delay stamp' => [new DelayStamp(100), 1];
        yield 'debounce stamp' => [new DebounceStamp(100, 1), 1];
    }

    private function given_there_is_a_message_on_the_queue_with_legacy_serialization() {
        $this->redis->lPush('messenger', json_encode([
            json_encode(['id' => null]),
            ['type' => "Krak\\SymfonyMessengerRedis\\Tests\\Feature\\Fixtures\\KrakRedisMessage"],
        ]));
    }

    private function given_there_is_a_wrapped_message() {
        $this->envelope = Envelope::wrap(new Fixtures\KrakRedisMessage());
    }

    private function given_there_is_a_unique_stamp_on_the_message(?string $id = null) {
        $this->given_there_is_a_stamp_on_the_message(new UniqueStamp($id));
    }

    private function given_there_is_a_delay_stamp_on_the_message(int $delayMs) {
        $this->given_there_is_a_stamp_on_the_message(new DelayStamp($delayMs));
    }

    private function given_there_is_a_debounce_stamp_on_the_message(int $delay, ?string $id = null): void {
        $this->given_there_is_a_stamp_on_the_message(new DebounceStamp($delay, $id));
    }

    private function given_there_is_a_stamp_on_the_message(StampInterface $stamp): void
    {
        $this->envelope = $this->envelope->with($stamp);
    }

    public function given_there_is_a_wrapped_message_in_the_queue() {
        $this->given_there_is_a_wrapped_message();
        $this->when_the_message_is_sent_on_the_transport();
    }

    private function given_the_message_has_been_processed_through_the_queue() {
        $this->when_the_message_is_sent_received_and_acked();
    }

    private function given_the_redis_transport_is_setup_from_dsn_and_options(?string $dsn = null, array $options = []) {
        $this->transport = $this->createTransportFromDSNIfSupported($dsn ?? getenv('REDIS_DSN'), $options);
    }

    private function given_the_redis_transport_contains_db(string $db, string $place) {
        if ($place === 'options') {
            $this->given_the_redis_transport_is_setup_from_dsn_and_options(getenv('REDIS_DSN'), ['db' => $db]);
        } else if ($place === 'query_string') {
            $this->given_the_redis_transport_is_setup_from_dsn_and_options(getenv('REDIS_DSN').'&db='.$db);
        } else if ($place === 'path') {
            [$base, $query] = explode('?', getenv('REDIS_DSN'));
            $this->given_the_redis_transport_is_setup_from_dsn_and_options($base . '/'.$db . '?' . $query);
        } else {
            throw new \LogicException('Invalid place: ' . $place);
        }
    }

    private function given_the_message_is_sent_on_the_transport(int $numberOfTimes = 1): void
    {
        $this->when_the_message_is_sent_on_the_transport($numberOfTimes);
    }

    private function createTransportFromDSNIfSupported(string $dsn, array $options = []): RedisTransport {
        if (!$this->transportFactory->supports($dsn, $options)) {
            throw new \RuntimeException('DSN is not supported.');
        }

        return $this->transportFactory->createTransport($dsn, $options, Serializer::create());
    }

    private function when_the_message_is_sent_on_the_transport(int $numberOfTimes = 1) {
        for ($i = 0; $i < $numberOfTimes; $i++) {
            $this->transport->send($this->envelope);
        }
    }

    private function when_another_message_is_sent_on_the_transport_with_same_stamp_after_a_delay_of(int $delayMs) {
        usleep($delayMs * 1000);
        $this->transport->send($this->envelope);
    }

    private function when_the_message_is_sent_received_and_acked(callable $stampEnv = null) {
        $this->transport->send($this->envelope);
        $this->when_the_message_is_received_and_acked($stampEnv);
    }

    private function when_the_message_is_received_and_acked(callable $stampEnv = null) {
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

    private function then_the_queue_has_size(int $size): void {
        $this->assertEquals($size, $this->redis->lLen('messenger') + $this->redis->zCard('messenger:delayed'));
    }

    private function then_the_message_is_not_available_immediately() {
        $res = $this->transport->get();
        $this->assertCount(0, $res);
    }

    private function then_the_message_is_available_after(int $delayMs) {
        usleep($delayMs * 1000);
        $res = $this->transport->get();
        $this->assertCount(1, $res);
    }

    private function then_the_redis_transport_connect_params_use_tls() {
        // this is NOT a great test, but setting up a redis TLS server is quite a pain, so this is just hack to give
        // some piece of mind regarding the code, but isn't a good test because it's asserting private functionality.
        \Closure::bind(function() {
            TransportTest::assertEquals(['tls://redis', 6379], $this->connectParams);
        }, $this->transport, RedisTransport::class)();
    }

    private function then_message_count_in_the_queue(int $count): void
    {
        $this->assertEquals($count, $this->transport->getMessageCount());
    }
}
