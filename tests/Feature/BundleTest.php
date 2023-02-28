<?php

namespace Krak\SymfonyMessengerRedis\Tests\Feature;

use Krak\SymfonyMessengerRedis\MessengerRedisBundle;
use Krak\SymfonyMessengerRedis\Tests\Feature\Fixtures\KrakRedisMessage;
use Krak\SymfonyMessengerRedis\Tests\Feature\Fixtures\SfRedisMessage;
use Krak\SymfonyMessengerRedis\Transport\RedisTransport;
use Krak\SymfonyMessengerRedis\Transport\RedisTransportFactory;
use Nyholm\BundleTest\BaseBundleTestCase;
use Nyholm\BundleTest\CompilerPass\PublicServicePass;
use Symfony\Component\Messenger\MessageBusInterface;

final class BundleTest extends BaseBundleTestCase
{
    use RedisSteps;

    protected function setUp(): void {
        parent::setUp();
        $this->addCompilerPass(new PublicServicePass('/(Krak.*|messenger.default_serializer|.*MessageBus.*)/'));
        $this->given_a_redis_client_is_configured_with_a_fresh_redis_db();
    }

    protected function getBundleClass() {
        return MessengerRedisBundle::class;
    }

    /** @test */
    public function registers_the_redis_transport_factory_as_a_service() {
        $this->bootKernel();
        $container = $this->getContainer();
        $this->assertInstanceOf(RedisTransportFactory::class, $container->get(RedisTransportFactory::class));
    }

    /** @test */
    public function registers_the_redis_message_bus_integration() {
        // Arrange: boot kernel with redis-config.yaml
        $this->given_the_kernel_is_booted_with_redis_config();

        // Act: dispatch the krak redis message on the bus
        /** @var MessageBusInterface $bus */
        $bus = $this->messageBus();
        $bus->dispatch(new KrakRedisMessage());

        // Assert: verify the message was pushed to krak redis transport
        $transport = $this->createKrakRedisTransport();
        $res = $transport->get();
        $this->assertCount(1, $res);
        $this->assertInstanceOf(KrakRedisMessage::class, $res[0]->getMessage());
    }

    /** @test */
    public function allows_sf_redis_transport() {
        $this->given_the_kernel_is_booted_with_redis_config();

        // Act: dispatch the sf message on the bus
        /** @var MessageBusInterface $bus */
        $bus = $this->messageBus();
        $bus->dispatch(new SfRedisMessage());

        // Assert: verify the message was not pushed to krak redis transport
        $transport = $this->createKrakRedisTransport();
        $this->assertEquals(0, $transport->getMessageCount());
    }

    private function given_the_kernel_is_booted_with_redis_config() {
        $kernel = $this->createKernel();
        $kernel->addConfigFile(__DIR__ . '/Fixtures/redis-config.yaml');
        $this->bootKernel();
    }

    private function createKrakRedisTransport(): RedisTransport {
        /** @var RedisTransportFactory $transportFactory */
        $transportFactory = $this->getContainer()->get(RedisTransportFactory::class);
        return $transportFactory->createTransport(getenv('REDIS_DSN'), [
            'blocking_timeout' => 1,
        ], $this->getContainer()->get('messenger.default_serializer'));
    }

    private function messageBus(): MessageBusInterface {
        return $this->getContainer()->get(MessageBusInterface::class);
    }
}
