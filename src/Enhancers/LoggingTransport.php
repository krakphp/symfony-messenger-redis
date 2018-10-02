<?php

namespace Krak\SymfonyMessengerRedis\Enhancers;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

class LoggingTransport implements TransportInterface
{
    private $transport;
    private $logger;

    public function __construct(TransportInterface $transport, LoggerInterface $logger) {

        $this->transport = $transport;
        $this->logger = $logger;
    }

    public function receive(callable $handler): void {
        $this->logger->debug("Receiving...");
        $this->transport->receive(function(?Envelope $envelope) use ($handler) {
            $this->logger->debug("Received Envelope", $envelope ? [
                'null' => false,
                'body' => $envelope->getMessage(),
                'items' => $envelope->all(),
            ] : ['null' => true]);
            $handler($envelope);
        });
    }

    public function stop(): void {
        $this->logger->debug("Stopping Receiver");
        $this->transport->stop();
    }

    public function send(Envelope $envelope) {
        $this->logger->debug("Sending Envelope", [
            'body' => $envelope->getMessage(),
            'items' => $envelope->all(),
        ]);
        $this->transport->send($envelope);
    }
}
