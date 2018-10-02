<?php

use Krak\SymfonyMessengerRedis\Enhancers\AutoScalingReceiver;
use Krak\SymfonyMessengerRedis\Enhancers\SymfonyProcessAutoScalingReceiver;

require_once __DIR__ . '/../../../vendor/autoload.php';

class AcmeMessage {
    public $num;

    public function __construct(int $num) {
        $this->num = $num;
    }
}

function createTransportFactory() {
    $serializer = new \Symfony\Component\Messenger\Transport\Serialization\Serializer(
        new \Symfony\Component\Serializer\Serializer(
            [new \Symfony\Component\Serializer\Normalizer\ObjectNormalizer()],
            [new \Symfony\Component\Serializer\Encoder\JsonEncoder()]
        )
    );
    return new \Krak\SymfonyMessengerRedis\RedisTransportFactory($serializer, $serializer);
}

function createConnection(): \Krak\SymfonyMessengerRedis\RedisConnection {
    return \Krak\SymfonyMessengerRedis\RedisConnection::fromDsn('redis://localhost:6379', ['queue' => 'messenger_functional_transport_test']);
}

function createTransport(string $dsn = 'redis://localhost:6379', array $options = ['blocking_timeout' => 1, 'queue' => 'messenger_functional_transport_test']) {
    return createTransportFactory()->createTransport($dsn, $options);
}

function createReceiver(string $cmd) {
    return new AutoScalingReceiver(
        createTransport(),
        createConnection(),
        new \Krak\SymfonyMessengerRedis\Enhancers\AutoScale\PcntlForkProcessManager(),
        createLogger()
    );
}

function createLogger(): \Psr\Log\LoggerInterface {
    return new \Monolog\Logger('Transport', [new \Monolog\Handler\StreamHandler('php://stdout')]);
}

function createLoggingTransport() {
    return new \Krak\SymfonyMessengerRedis\Enhancers\LoggingTransport(createTransport(), createLogger());
}
