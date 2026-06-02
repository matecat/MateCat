<?php

namespace unit\Utils\Templating;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Templating\PHPTalMap;

class PHPTalMapTest extends AbstractTest
{
    #[Test]
    public function constructorConvertsNestedArraysToMaps(): void
    {
        $map = new PHPTalMap(['key' => ['nested' => 'value']]);

        $this->assertInstanceOf(PHPTalMap::class, $map['key']);
        $this->assertSame('value', $map['key']['nested']);
    }

    #[Test]
    public function constructorHandlesNumericKeys(): void
    {
        $map = new PHPTalMap([['a' => 1], ['b' => 2]]);

        $this->assertInstanceOf(PHPTalMap::class, $map[0]);
        $this->assertSame(1, $map[0]['a']);
    }

    #[Test]
    public function constructorHandlesScalarValues(): void
    {
        $map = new PHPTalMap(['name' => 'test', 'count' => 42]);

        $this->assertSame('test', $map['name']);
        $this->assertSame(42, $map['count']);
    }

    #[Test]
    public function toStringReturnsJson(): void
    {
        $map = new PHPTalMap(['key' => 'value']);

        $this->assertSame('{"key":"value"}', (string)$map);
    }

    #[Test]
    public function jsonSerializeReturnsStorage(): void
    {
        $map = new PHPTalMap(['a' => 1]);

        $serialized = $map->jsonSerialize();

        $this->assertIsArray($serialized);
        $this->assertSame(1, $serialized['a']);
    }

    #[Test]
    public function arrayAccessSetAndGet(): void
    {
        $map = new PHPTalMap();
        $map['key'] = 'value';

        $this->assertSame('value', $map['key']);
    }

    #[Test]
    public function arrayAccessUnset(): void
    {
        $map = new PHPTalMap(['key' => 'value']);

        unset($map['key']);

        $this->assertNull($map['key']);
    }

    #[Test]
    public function magicGetSet(): void
    {
        $map = new PHPTalMap();
        $map->foo = 'bar';

        $this->assertSame('bar', $map->foo);
    }

    #[Test]
    public function magicGetReturnsNullForMissing(): void
    {
        $map = new PHPTalMap();

        $this->assertNull($map->nonexistent);
    }

    #[Test]
    public function emptyMapToString(): void
    {
        $map = new PHPTalMap();

        $this->assertSame('[]', (string)$map);
    }
}
