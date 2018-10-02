<?php

namespace Krak\SymfonyMessengerRedis\Enhancers\AutoScale;

use Symfony\Component\Messenger\Transport\ReceiverInterface;

class CreateProcessArgs
{
    public $receiver;
    public $handler;

    public function __construct(ReceiverInterface $receiver, callable $handler) {
        $this->receiver = $receiver;
        $this->handler = $handler;
    }
}
