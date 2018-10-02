<?php

namespace Krak\SymfonyMessengerRedis\Tests\Unit;

use Krak\SymfonyMessengerRedis\LazyCompositeTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\SenderInterface;

class LazyCompositeTransportTest extends TestCase
{
    public function test_lazily_delegates_receive_to_receiver() {
        $receiver = new class() implements ReceiverInterface {
            public $handlers = [];
            public function receive(callable $handler): void {
                $this->handlers[] = $handler;
            }
            public function stop(): void {}
        };
        $invoked = 0;
        $transport = new LazyCompositeTransport(function() use (&$invoked, $receiver) {
            $invoked += 1;
            return $receiver;
        }, function() { throw new \Exception(); });

        $handler = function() {};

        $this->assertEquals(0, $invoked);
        $transport->receive($handler);
        $transport->receive($handler);
        $this->assertEquals(1, $invoked);
        $this->assertEquals([$handler, $handler], $receiver->handlers);
    }

    public function test_lazily_delegates_stop_to_receiver() {
        $receiver = new class() implements ReceiverInterface {
            public $stopped = 0;
            public function receive(callable $handler): void {}
            public function stop(): void { $this->stopped += 1; }
        };
        $transport = new LazyCompositeTransport(function() use ($receiver) { return $receiver; }, function() {});
        $transport->stop();
        $transport->stop();
        $this->assertEquals(2, $receiver->stopped);
    }

    public function test_lazily_delegates_send_to_sender() {
        $sender = new class() implements SenderInterface {
            public $messages = [];
            public function send(Envelope $envelope) {
                $this->messages[] = $envelope->getMessage();
            }
        };
        $messages = [
            (object) ['a' => 1],
            (object) ['b' => 2]
        ];
        $invoked = 0;

        $transport = new LazyCompositeTransport(function() { throw new \Exception(); }, function() use (&$invoked, $sender) {
            $invoked += 1;
            return $sender;
        });

        $this->assertEquals(0, $invoked);

        foreach ($messages as $message) {
            $transport->send(new Envelope($message));
        }

        $this->assertEquals(1, $invoked);
        $this->assertEquals($messages, $sender->messages);
    }
}
