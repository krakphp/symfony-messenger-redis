<?php

namespace Krak\SymfonyMessengerRedis\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Apply this stamp to debounce delivery of your message on a transport.
 */
final class DebounceStamp implements StampInterface
{
    private $id;
    private $delay;

    /**
     * @param int $delay The delay in milliseconds
     * @param string|null $id unique identifier
     */
    public function __construct(int $delay, ?string $id = null)
    {
        $this->id = $id;
        $this->delay = $delay;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }
}
