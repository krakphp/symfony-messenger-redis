<?php

namespace Krak\SymfonyMessengerRedis\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Krak\SymfonyMessengerRedis\Stamp\DebounceStamp;

final class DebounceStampTest extends TestCase
{
    /** @test */
    public function stamp_creation() {
        $stamp = new DebounceStamp( 4000, '1234');
        $this->assertEquals(4000, $stamp->getDelay());
        $this->assertEquals('1234', $stamp->getId());
    }

    /**
     * @test
     * @dataProvider provide_stamps_for_serialization
     */
    public function stamp_is_serializable(DebounceStamp $stamp) {
        $this->assertEquals($stamp, unserialize(serialize($stamp)));
    }

    public function provide_stamps_for_serialization() {
        yield 'Stamp without id' => [new DebounceStamp(3000)];
        yield 'Stamp with id' => [new DebounceStamp(100, '765')];
    }
}
