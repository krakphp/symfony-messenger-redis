<?php

require_once __DIR__ . '/common.php';

$connection = createConnection();

function pollQueueStats(\Krak\SymfonyMessengerRedis\RedisConnection $connection, int $pollSeconds = 1) {
    $lastSize = $connection->getSizeOfQueue();
    sleep($pollSeconds);

    while (true) {
        $currentSize = $connection->getSizeOfQueue();
        $messagesPerSecond = ($currentSize - $lastSize) / $pollSeconds;
        printf("Messages Per Second: %.2f\n", $messagesPerSecond);
        sleep($pollSeconds);
        $lastSize = $currentSize;
    }
}

pollQueueStats($connection, 1);
