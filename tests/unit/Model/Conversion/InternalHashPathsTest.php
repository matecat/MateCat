<?php

namespace unit\Model\Conversion;

use DomainException;
use Model\Conversion\InternalHashPaths;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class InternalHashPathsTest extends AbstractTest
{

    #[Test]
    public function constructWithBothHashes(): void
    {
        $hp = new InternalHashPaths(['cacheHash' => 'abc123', 'diskHash' => 'def456']);

        $this->assertEquals('abc123', $hp->getCacheHash());
        $this->assertEquals('def456', $hp->getDiskHash());
    }

    #[Test]
    public function isEmptyReturnsFalseWhenPopulated(): void
    {
        $hp = new InternalHashPaths(['cacheHash' => 'abc', 'diskHash' => 'def']);
        $this->assertFalse($hp->isEmpty());
    }

    #[Test]
    public function isEmptyReturnsTrueForEmptyArray(): void
    {
        $hp = new InternalHashPaths([]);
        $this->assertTrue($hp->isEmpty());
    }

    #[Test]
    public function setUnknownPropertyThrowsDomainException(): void
    {
        $hp = new InternalHashPaths(['cacheHash' => 'a', 'diskHash' => 'b']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown property foo');
        $hp->foo = 'bar';
    }

    #[Test]
    public function constructWithUnknownKeyThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Unknown property unknown');
        new InternalHashPaths(['cacheHash' => 'a', 'diskHash' => 'b', 'unknown' => 'x']);
    }

    #[Test]
    public function isEmptyReturnsTrueWhenOnlyCacheHashIsEmpty(): void
    {
        $hp = new InternalHashPaths(['cacheHash' => '', 'diskHash' => '']);
        $this->assertTrue($hp->isEmpty());
    }

    #[Test]
    public function isEmptyReturnsFalseWhenOnlyOneHashIsSet(): void
    {
        $hp = new InternalHashPaths(['cacheHash' => 'abc', 'diskHash' => '']);
        $this->assertFalse($hp->isEmpty());
    }
}

