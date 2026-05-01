<?php

declare(strict_types=1);

namespace unit\Structs;

use DomainException;
use Model\Engines\Structs\AltlangStruct;
use Model\Engines\Structs\DeepLStruct;
use Model\Engines\Structs\EngineStruct;
use Model\Engines\Structs\GoogleTranslateStruct;
use Model\Engines\Structs\LaraStruct;
use Model\Engines\Structs\MMTStruct;
use Model\Engines\Structs\SmartMATEStruct;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EngineStructTypeSafetyTest extends TestCase
{
    #[Test]
    public function getStructReturnsCorrectInstanceForParent(): void
    {
        $struct = EngineStruct::getStruct();
        self::assertInstanceOf(EngineStruct::class, $struct);
    }

    #[Test]
    #[DataProvider('subclassProvider')]
    public function getStructReturnsCorrectInstanceForSubclass(string $class): void
    {
        $struct = $class::getStruct();
        self::assertInstanceOf($class, $struct);
    }

    public static function subclassProvider(): array
    {
        return [
            'DeepL'           => [DeepLStruct::class],
            'Lara'            => [LaraStruct::class],
            'MMT'             => [MMTStruct::class],
            'SmartMATE'       => [SmartMATEStruct::class],
            'GoogleTranslate' => [GoogleTranslateStruct::class],
            'Altlang'         => [AltlangStruct::class],
        ];
    }

    #[Test]
    public function offsetSetThrowsDomainExceptionForUnknownProperty(): void
    {
        $struct = EngineStruct::getStruct();

        $this->expectException(DomainException::class);
        $struct['nonexistent_property'] = 'value';
    }

    #[Test]
    public function offsetUnsetThrowsDomainExceptionForUnknownProperty(): void
    {
        $struct = EngineStruct::getStruct();

        $this->expectException(DomainException::class);
        unset($struct['nonexistent_property']);
    }

    #[Test]
    public function getEngineTypeReturnsNullWhenClassLoadIsNull(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = null;

        self::assertNull($struct->getEngineType());
    }

    #[Test]
    public function getEngineTypeExtractsLastSegmentFromNamespace(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = 'Utils\\Engines\\DeepL';

        self::assertSame('DeepL', $struct->getEngineType());
    }

    #[Test]
    public function arrayRepresentationReturnsExpectedKeys(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->id = 1;
        $struct->name = 'TestEngine';
        $struct->description = 'A test engine';
        $struct->type = 'MT';
        $struct->extra_parameters = ['key' => 'val'];
        $struct->class_load = 'Utils\\Engines\\TestEngine';

        $result = $struct->arrayRepresentation();

        self::assertIsArray($result);
        self::assertSame(1, $result['id']);
        self::assertSame('TestEngine', $result['name']);
        self::assertSame('A test engine', $result['description']);
        self::assertSame('MT', $result['type']);
        self::assertSame(['key' => 'val'], $result['extra']);
        self::assertSame('TestEngine', $result['engine_type']);
    }

    #[Test]
    public function othersPropertyAcceptsStringKeyedArray(): void
    {
        $struct = new DeepLStruct();

        self::assertIsArray($struct->others);
        self::assertArrayHasKey('relative_glossaries_url', $struct->others);
        self::assertIsString($struct->others['relative_glossaries_url']);
    }

    #[Test]
    public function extraParametersPropertyAcceptsStringKeyedArray(): void
    {
        $struct = new DeepLStruct();

        self::assertIsArray($struct->extra_parameters);
        self::assertArrayHasKey('DeepL-Auth-Key', $struct->extra_parameters);
    }
}
