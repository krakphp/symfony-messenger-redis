<?php

namespace Krak\SymfonyMessengerRedis;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\ReceiverInterface;
use Symfony\Component\Messenger\Transport\SenderInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

final class LazyCompositeTransport implements TransportInterface
{
    private $createReceiver;
    private $createSender;
    private $receiver;
    private $sender;

    public function __construct(callable $createReceiver, callable $createSender) {
        $this->createReceiver = $createReceiver;
        $this->createSender = $createSender;
    }

    /**
     * Receive some messages to the given handler.
     *
     * The handler will have, as argument, the received {@link \Symfony\Component\Messenger\Envelope} containing the message.
     * Note that this envelope can be `null` if the timeout to receive something has expired.
     */
    public function receive(callable $handler): void {
        $this->getReceiver()->receive($handler);
    }

    /**
     * Stop receiving some messages.
     */
    public function stop(): void {
        $this->getReceiver()->stop();
    }

    /**
     * Sends the given envelope.
     *
     * @param Envelope $envelope
     */
    public function send(Envelope $envelope) {
        $this->getSender()->send($envelope);
    }

    private function getReceiver(): ReceiverInterface {
        return $this->receiver ?? ($this->receiver = (($this->createReceiver)()));
    }

    private function getSender(): SenderInterface {
        return $this->sender ?? ($this->sender = (($this->createSender)()));
    }
}
