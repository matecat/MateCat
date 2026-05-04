<?php

declare(strict_types=1);

namespace unit\Engines;

use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Utils\Engines\EnginesFactory;
use Utils\Engines\EngineInterface;
use Utils\Engines\NONE;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

class EnginesFactoryTypeSafetyTest extends TestCase
{
    #[Test]
    public function getFullyQualifiedClassNameResolvesKnownEngineClass(): void
    {
        $result = EnginesFactory::getFullyQualifiedClassName('NONE');
        self::assertSame('Utils\Engines\NONE', $result);
    }

    #[Test]
    public function getFullyQualifiedClassNameResolvesAlreadyQualifiedClass(): void
    {
        $result = EnginesFactory::getFullyQualifiedClassName('Utils\Engines\NONE');
        self::assertSame('Utils\Engines\NONE', $result);
    }

    #[Test]
    public function getFullyQualifiedClassNameThrowsOnUnknownClass(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Engine Class');
        EnginesFactory::getFullyQualifiedClassName('CompletelyNonExistentEngineClass12345');
    }

    #[Test]
    public function createTempInstanceReturnsEngineInterface(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = 'NONE';

        $engine = EnginesFactory::createTempInstance($struct);
        self::assertInstanceOf(EngineInterface::class, $engine);
        self::assertInstanceOf(NONE::class, $engine);
    }

    #[Test]
    public function noneEngineDeleteReturnsBool(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = 'NONE';

        $engine = EnginesFactory::createTempInstance($struct);
        $result = $engine->delete([]);

        self::assertIsBool($result);
    }

    #[Test]
    public function noneEngineGetReturnsGetMemoryResponse(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = 'NONE';

        /** @var NONE $engine */
        $engine = EnginesFactory::createTempInstance($struct);
        $result = $engine->get([]);

        self::assertInstanceOf(GetMemoryResponse::class, $result);
    }

    #[Test]
    public function noneEngineSetReturnsBool(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = 'NONE';

        $engine = EnginesFactory::createTempInstance($struct);
        $result = $engine->set([]);

        self::assertIsBool($result);
    }

    #[Test]
    public function noneEngineUpdateReturnsBool(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = 'NONE';

        $engine = EnginesFactory::createTempInstance($struct);
        $result = $engine->update([]);

        self::assertIsBool($result);
    }
}
