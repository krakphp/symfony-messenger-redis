<?php

namespace Krak\SymfonyMessengerRedis\Tests\Unit;

use Krak\SymfonyMessengerRedis\Stamp\UniqueStamp;
use PHPUnit\Framework\TestCase;

final class UniqueStampTest extends TestCase
{
    /** @test */
    public function stamp_creation() {
        $stamp = new UniqueStamp('1234');
        $this->assertEquals('1234', $stamp->getId());
    }

    /**
     * @test
     * @dataProvider provide_stamps_for_serialization
     */
    public function stamp_is_serializable(UniqueStamp $stamp) {
        $this->assertEquals($stamp, unserialize(serialize($stamp)));
    }

    public function provide_stamps_for_serialization() {
        yield 'Stamp without id' => [new UniqueStamp()];
        yield 'Stamp with id' => [new UniqueStamp()];
    }
}
