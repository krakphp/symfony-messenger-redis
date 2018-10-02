<?php

namespace Krak\SymfonyMessengerRedis\Enhancers\AutoScale;

class PcntlForkProcessManager implements ProcessManager
{
    /** @return mixed a process ref */
    public function createProcess(CreateProcessArgs $createProcessArgs) {
        $pid = pcntl_fork();
        if ($pid) {
            return $pid;
        } else {
            $sigHandler = function() use ($createProcessArgs) {
                $createProcessArgs->receiver->stop();
            };
            pcntl_signal(SIGTERM, $sigHandler);
            pcntl_signal(SIGINT, $sigHandler);
            $createProcessArgs->receiver->receive($createProcessArgs->handler);
            exit;
        }
    }

    public function killProcess($processRef) {
        posix_kill($processRef, SIGTERM);
        pcntl_waitpid($processRef, $status);
    }
}
