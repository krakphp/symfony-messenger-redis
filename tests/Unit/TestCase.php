<?php

namespace Krak\SymfonyMessengerRedis\Tests\Unit;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\SenderInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function createNullSender(): SenderInterface {
        return new class() implements SenderInterface {
            public function send(Envelope $envelope) {

            }
        };
    }

    protected function createNullReceiver(): ReceiverInterface {
        return new class() implements ReceiverInterface {
            public function receive(callable $handler): void {

            }

            public function stop(): void {

            }
        };
    }
}
