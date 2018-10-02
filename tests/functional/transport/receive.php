<?php

require_once __DIR__ . '/common.php';

$transport = ($argv[1] ?? null) === "worker" ? createTransport() : createReceiver('php ' . $argv[0] . ' worker');
$handler = function() use ($transport) {
    $transport->stop();
};

pcntl_signal(SIGTERM, $handler);
pcntl_signal(SIGINT, $handler);

$transport->receive(function(?\Symfony\Component\Messenger\Envelope $envelope) {

});

echo "Exiting...\n";
