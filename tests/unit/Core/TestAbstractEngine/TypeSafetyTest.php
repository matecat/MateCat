<?php


namespace Matecat\Core\TestAbstractEngine;

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use Utils\Constants\EngineConstants;
use Utils\Engines\NONE;

/**
 * @group   regression
 * @covers  \Utils\Engines\AbstractEngine
 */
class TypeSafetyTest extends AbstractTest
{
    private NONE $engine;

    public function setUp(): void
    {
        parent::setUp();
        $struct = new EngineStruct();
        $struct->type = EngineConstants::MT;
        $struct->name = "TestEngine";
        $struct->others = ['key1' => 'val1'];
        $struct->extra_parameters = ['key2' => 'val2'];

        $this->engine = new NONE($struct, $this->createStub(\Model\DataAccess\IDatabase::class));
    }

    #[Test]
    public function getNameReturnsStringWhenNameIsSet(): void
    {
        $this->assertSame('TestEngine', $this->engine->getName());
    }

    #[Test]
    public function getNameReturnsEmptyStringWhenNameIsNull(): void
    {
        $struct = new EngineStruct();
        $struct->type = EngineConstants::MT;
        $struct->name = null;
        $struct->others = [];
        $struct->extra_parameters = [];

        $engine = new NONE($struct, $this->createStub(\Model\DataAccess\IDatabase::class));
        $this->assertSame('', $engine->getName());
    }

    #[Test]
    public function magicGetReturnsNullWhenOthersIsString(): void
    {
        $struct = new EngineStruct();
        $struct->type = EngineConstants::MT;
        $struct->name = "TestEngine";
        $struct->others = 'some_string';
        $struct->extra_parameters = [];

        $engine = new NONE($struct, $this->createStub(\Model\DataAccess\IDatabase::class));
        $this->assertNull($engine->nonExistentKey);
    }

    #[Test]
    public function magicGetReturnsNullWhenExtraParametersIsNull(): void
    {
        $struct = new EngineStruct();
        $struct->type = EngineConstants::MT;
        $struct->name = "TestEngine";
        $struct->others = [];
        $struct->extra_parameters = null;

        $engine = new NONE($struct, $this->createStub(\Model\DataAccess\IDatabase::class));
        $this->assertNull($engine->nonExistentKey);
    }

    #[Test]
    public function magicSetThrowsWhenOthersIsStringAndKeyNotProperty(): void
    {
        $struct = new EngineStruct();
        $struct->type = EngineConstants::MT;
        $struct->name = "TestEngine";
        $struct->others = 'some_string';
        $struct->extra_parameters = [];

        $engine = new NONE($struct, $this->createStub(\Model\DataAccess\IDatabase::class));
        $this->expectException(DomainException::class);
        $engine->nonExistentKey = 'value';
    }

    #[Test]
    public function getCurlFileThrowsOnNonExistentFile(): void
    {
        $method = new ReflectionMethod($this->engine, 'getCurlFile');

        $this->expectException(RuntimeException::class);
        $method->invoke($this->engine, '/nonexistent/path/to/file.tmx');
    }
}
