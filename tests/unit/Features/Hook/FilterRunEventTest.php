<?php

declare(strict_types=1);

namespace Tests\Unit\Features\Hook;

use Model\FeaturesBase\Hook\FilterEvent;
use Model\FeaturesBase\Hook\RunEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class FilterRunEventTest extends TestCase
{
    #[Test]
    public function filterEventDefinesAbstractStaticHookNameContract(): void
    {
        self::assertTrue(class_exists(FilterEvent::class));

        $reflection = new ReflectionClass(FilterEvent::class);
        self::assertTrue($reflection->isAbstract());

        $method = $reflection->getMethod('hookName');
        self::assertTrue($method->isPublic());
        self::assertTrue($method->isStatic());
        self::assertTrue($method->isAbstract());
        self::assertSame('string', $method->getReturnType()?->getName());
    }

    #[Test]
    public function runEventDefinesAbstractStaticHookNameContract(): void
    {
        self::assertTrue(class_exists(RunEvent::class));

        $reflection = new ReflectionClass(RunEvent::class);
        self::assertTrue($reflection->isAbstract());

        $method = $reflection->getMethod('hookName');
        self::assertTrue($method->isPublic());
        self::assertTrue($method->isStatic());
        self::assertTrue($method->isAbstract());
        self::assertSame('string', $method->getReturnType()?->getName());
    }
}
