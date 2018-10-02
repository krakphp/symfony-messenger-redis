<?php

namespace Krak\SymfonyMessengerRedis\Enhancers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Krak\SymfonyMessengerRedis\Enhancers\AutoScale\CreateProcessArgs;
use Krak\SymfonyMessengerRedis\Enhancers\AutoScale\ProcessManager;
use Krak\SymfonyMessengerRedis\MetricsRepository;
use Symfony\Component\Messenger\Transport\ReceiverInterface;

class AutoScalingReceiver implements ReceiverInterface
{
    const SLEEP_INTERVAL = 1;
    const SCALE_THRESHOLD = 5;

    private $receiver;
    private $metricsRepository;
    private $processManager;
    private $logger;
    private $maxProcs;
    private $procs;
    private $shouldStop;

    public function __construct(ReceiverInterface $receiver, MetricsRepository $metricsRepository, ProcessManager $processManager, LoggerInterface $logger = null, int $maxProcs = 50) {
        $this->receiver = $receiver;
        $this->metricsRepository = $metricsRepository;
        $this->processManager = $processManager;
        $this->logger = $logger ?: new NullLogger();
        $this->maxProcs = $maxProcs;
        $this->procs = [];
        $this->shouldStop = false;
    }

    public function receive(callable $handler): void {
        // first scale up to start scheduling
        $this->scaleUp($handler);

        $lastSize = $this->metricsRepository->getSizeOfQueue();
        sleep(self::SLEEP_INTERVAL);

        $hasBeenEmpty = 0;
        $hasBeenFull = 0;

        while (!$this->shouldStop) {
            $currentSize = $this->metricsRepository->getSizeOfQueue();
            $messagesPerSecond = ($currentSize - $lastSize) / self::SLEEP_INTERVAL;

            if ($currentSize > 0 && $hasBeenFull > self::SCALE_THRESHOLD) {
                $hasBeenEmpty = 0;
                $hasBeenFull += 1;
                if ($messagesPerSecond >= 0) {
                    $this->scaleUp($handler);
                }
            } else if ($currentSize > 0) {
                $hasBeenEmpty = 0;
                $hasBeenFull += 1;
            } else if (count($this->procs) > 1 && $hasBeenEmpty > self::SCALE_THRESHOLD) {
                $hasBeenEmpty = 1;
                $hasBeenFull = 0;
                $this->scaleDown();
            } else {
                $hasBeenEmpty += 1;
                $hasBeenFull = 0;
            }

            sleep(self::SLEEP_INTERVAL);
            $lastSize = $currentSize;

            if (\function_exists('pcntl_signal_dispatch')) {
                \pcntl_signal_dispatch();
            }
        }
    }

    public function stop(): void {
        $this->receiver->stop();

        $this->logger->info("Stopping recevier, scaling down");
        $this->shouldStop = true;

        while ($this->procs) {
            $this->scaleDown();
        }
    }

    private function scaleUp(callable $handler): void {
        if ($this->maxProcs <= count($this->procs)) {
            return;
        }

        $this->procs[] = $this->processManager->createProcess(new CreateProcessArgs($this->receiver, $handler));
        $this->logger->info(sprintf("Scaling up: %d procs", count($this->procs)));
    }

    private function scaleDown() {
        $procRef = array_pop($this->procs);
        $this->processManager->killProcess($procRef);
        $this->logger->info(sprintf("Scaling down: %d procs", count($this->procs)));
    }
}
