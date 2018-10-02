<?php

namespace Krak\SymfonyMessengerRedis\Enhancers\AutoScale;

use Symfony\Component\Process\Process;

class SymfonyProcessProcessManager implements ProcessManager
{
    private $cmd;

    public function __construct(string $cmd) {
        $this->cmd = $cmd;
    }

    /** @return mixed a process ref */
    public function createProcess(CreateProcessArgs $args) {
        $proc = new Process($this->cmd);
        $proc->start();
        return $proc;
    }

    public function killProcess($processRef) {
        /** @var Process $processRef */
        $processRef->stop(10, SIGTERM);
    }
}
