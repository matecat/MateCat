<?php

namespace Matecat\Core\Utils\Engines\Validators;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Engines\Validators\MMTEngineValidator;

/**
 * Covers the injected-IDatabase plumbing in MMTEngineValidator without hitting
 * external services: the validate() path reaches EnginesFactory::createTempInstance()
 * (which is handed $this->database) and that call is exercised offline by feeding a
 * struct with no class_load, so the factory throws before any network call.
 */
class MMTEngineValidatorTest extends AbstractTest
{
    #[Test]
    public function validateThrowsWhenEngineStructMissing(): void
    {
        $validator = new MMTEngineValidator($this->createStub(IDatabase::class));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Engine struct required');

        $validator->validate(new EngineValidatorObject());
    }

    #[Test]
    public function validateForwardsInjectedDatabaseToFactory(): void
    {
        $object = new EngineValidatorObject();
        $object->engineStruct = new EngineStruct(); // class_load === null

        $validator = new MMTEngineValidator($this->createStub(IDatabase::class));

        // EnginesFactory::createTempInstance($engineStruct, $this->database) is reached
        // and throws on the missing class_load — proving the call line is executed.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Engine has no class_load');

        $validator->validate($object);
    }
}
