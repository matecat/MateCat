<?php

declare(strict_types=1);

namespace Tests\Unit\DataAccess;

use Model\DataAccess\XFetchEnvelope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class XFetchEnvelopeTest extends TestCase
{
    #[Test]
    public function constructorAssignsAllProperties(): void
    {
        $value = [['id' => 1], ['id' => 2]];
        $storedAt = microtime(true);
        $delta = 0.025;

        $envelope = new XFetchEnvelope($value, $storedAt, $delta);

        self::assertSame($value, $envelope->value);
        self::assertSame($storedAt, $envelope->storedAt);
        self::assertSame($delta, $envelope->delta);
    }

    #[Test]
    public function isReadonly(): void
    {
        $envelope = new XFetchEnvelope([], 1000.0, 0.01);

        $reflection = new \ReflectionClass($envelope);

        self::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function isFinal(): void
    {
        $reflection = new \ReflectionClass(XFetchEnvelope::class);

        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function acceptsEmptyArray(): void
    {
        $envelope = new XFetchEnvelope([], 0.0, 0.0);

        self::assertSame([], $envelope->value);
        self::assertSame(0.0, $envelope->storedAt);
        self::assertSame(0.0, $envelope->delta);
    }
}
