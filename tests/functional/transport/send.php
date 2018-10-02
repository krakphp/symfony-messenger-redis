<?php

require_once __DIR__ . '/common.php';

$transport = createTransport();

$i = 0;
while (true) {
    $transport->send(new \Symfony\Component\Messenger\Envelope(new AcmeMessage($i)));
    $i += 1;
}
