<?php

namespace Krak\SymfonyMessengerRedis\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

final class UniqueStamp implements StampInterface
{
    private $id;

    public function __construct(?string $id = null)
    {
        $this->id = $id;
    }

    public function getId(): ?string
    {
        return $this->id;
    }
}
