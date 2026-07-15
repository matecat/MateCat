<?php

namespace Matecat\Core\Utils\Engines\Validators;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Engines\Validators\DeepLEngineValidator;

/**
 * Covers the injected-IDatabase plumbing in DeepLEngineValidator without hitting
 * external services (see MMTEngineValidatorTest for the strategy rationale).
 */
class DeepLEngineValidatorTest extends AbstractTest
{
    #[Test]
    public function validateThrowsWhenEngineStructMissing(): void
    {
        $validator = new DeepLEngineValidator($this->createStub(IDatabase::class));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Engine struct required');

        $validator->validate(new EngineValidatorObject());
    }

    #[Test]
    public function validateForwardsInjectedDatabaseToFactory(): void
    {
        $object = new EngineValidatorObject();
        $object->engineStruct = new EngineStruct(); // class_load === null

        $validator = new DeepLEngineValidator($this->createStub(IDatabase::class));

        // createTempInstance() runs before the try/catch, so its throw propagates
        // verbatim (it is not converted to the "Invalid DeepL API key" message).
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Engine has no class_load');

        $validator->validate($object);
    }
}
