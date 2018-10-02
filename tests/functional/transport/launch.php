<?php

require_once __DIR__ . '/common.php';

$handleSend = function() {
    require __DIR__ . '/send.php';
};

function launchProcs(int $numProcs, callable $handler) {
    foreach (range(1, $numProcs) as $i) {
        $pid = pcntl_fork();

        if (!$pid) {
            $handler(posix_getpid());
            return;
        }
    }

    pcntl_wait($status);
}

if (count($argv) < 3) {
    printf("usage: %s <handler> <num-procs>\n", $argv[0]);
    exit(1);
}

$handler = $argv[1];
$numProcs = (int) ($argv[2] ?? 1);

if ($handler === "send") {
    launchProcs($numProcs, $handleSend);
} else {
    printf("No handler found\n");
    exit(1);
}
