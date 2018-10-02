<?php

namespace Krak\SymfonyMessengerRedis\Enhancers\AutoScale;

interface ProcessManager
{
    /** @return mixed a process ref */
    public function createProcess(CreateProcessArgs $args);
    public function killProcess($processRef);
}
