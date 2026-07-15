<?php

declare(strict_types=1);

namespace Matecat\Core\Engines;

use Matecat\TestHelpers\AbstractTest;
use Model\Engines\Structs\EngineStruct;
use Model\Exceptions\NotFoundException;
use PHPUnit\Framework\Attributes\Test;
use Utils\Engines\EngineInterface;
use Utils\Engines\EnginesFactory;
use Utils\Engines\NONE;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

class EnginesFactoryTypeSafetyTest extends AbstractTest
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
        // Tightened from \Exception::class: callers need to distinguish "misconfigured" (park)
        // from other failures (retry) — see report §11.4.
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Engine Class');
        EnginesFactory::getFullyQualifiedClassName('CompletelyNonExistentEngineClass12345');
    }

    #[Test]
    public function getInstanceThrowsNotFoundExceptionWhenEngineRowDoesNotExist(): void
    {
        $this->expectException(NotFoundException::class);
        EnginesFactory::getInstance(-999999, obtainTestDatabase());
    }

    #[Test]
    public function createTempInstanceThrowsNotFoundExceptionWhenClassLoadIsMissing(): void
    {
        // Same failure family as getFullyQualifiedClassName's "class not found": a struct with no
        // class_load is a misconfigured engine record, not a generic runtime error (report §11.4).
        $struct = EngineStruct::getStruct();
        $struct->class_load = null;

        $this->expectException(NotFoundException::class);
        EnginesFactory::createTempInstance($struct, $this->createStub(\Model\DataAccess\IDatabase::class));
    }

    #[Test]
    public function getInstanceGracefullyDegradesToNoneEngineWhenRecordHasNoClassLoad(): void
    {
        // Regression guard: EngineDAO::_buildResult() has a pre-existing `catch (Exception) ->
        // substitute NONEStruct` fallback for a broken engine record, but a null class_load used to
        // reach getFullyQualifiedClassName(string $_className) — a non-nullable param — raising an
        // uncatchable TypeError instead of an Exception, crashing every read of a NULL-class_load row.
        // Now that getFullyQualifiedClassName() accepts ?string and throws NotFoundException (an
        // Exception) for a missing class_load, the DAO's existing fallback catches it as designed.
        $database   = obtainTestDatabase();
        $connection = $database->getConnection();
        $connection->exec("INSERT INTO engines (base_url) VALUES ('http://test.invalid')");
        $id = (int)$connection->lastInsertId();

        try {
            $engine = EnginesFactory::getInstance($id, $database);
            self::assertInstanceOf(EngineInterface::class, $engine);
            self::assertInstanceOf(NONE::class, $engine);
        } finally {
            $connection->exec("DELETE FROM engines WHERE id = $id");
        }
    }

    #[Test]
    public function createTempInstanceReturnsEngineInterface(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = 'NONE';

        $engine = EnginesFactory::createTempInstance($struct, $this->createStub(\Model\DataAccess\IDatabase::class));
        self::assertInstanceOf(EngineInterface::class, $engine);
        self::assertInstanceOf(NONE::class, $engine);
    }

    #[Test]
    public function noneEngineDeleteReturnsBool(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = 'NONE';

        $engine = EnginesFactory::createTempInstance($struct, $this->createStub(\Model\DataAccess\IDatabase::class));
        $result = $engine->delete([]);

        self::assertIsBool($result);
    }

    #[Test]
    public function noneEngineGetReturnsGetMemoryResponse(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = 'NONE';

        /** @var NONE $engine */
        $engine = EnginesFactory::createTempInstance($struct, $this->createStub(\Model\DataAccess\IDatabase::class));
        $result = $engine->get([]);

        self::assertInstanceOf(GetMemoryResponse::class, $result);
    }

    #[Test]
    public function noneEngineSetReturnsBool(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = 'NONE';

        $engine = EnginesFactory::createTempInstance($struct, $this->createStub(\Model\DataAccess\IDatabase::class));
        $result = $engine->set([]);

        self::assertIsBool($result);
    }

    #[Test]
    public function noneEngineUpdateReturnsBool(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = 'NONE';

        $engine = EnginesFactory::createTempInstance($struct, $this->createStub(\Model\DataAccess\IDatabase::class));
        $result = $engine->update([]);

        self::assertIsBool($result);
    }
}
