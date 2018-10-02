<?php

namespace Krak\SymfonyMessengerRedis;

interface MetricsRepository
{
    public function getSizeOfQueue(): int;
}
