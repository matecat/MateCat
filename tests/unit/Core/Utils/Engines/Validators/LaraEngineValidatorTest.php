<?php

namespace Matecat\Core\Utils\Engines\Validators;

use Exception;
use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use Utils\Engines\Validators\Contracts\EngineValidatorObject;
use Utils\Engines\Validators\LaraEngineValidator;

/**
 * Covers the injected-IDatabase plumbing in LaraEngineValidator without hitting
 * external services (see MMTEngineValidatorTest for the strategy rationale).
 */
class LaraEngineValidatorTest extends AbstractTest
{
    #[Test]
    public function validateThrowsInvalidArgumentWhenEngineStructMissing(): void
    {
        $validator = new LaraEngineValidator($this->createStub(IDatabase::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Lara engine validator object');

        $validator->validate(new EngineValidatorObject());
    }

    #[Test]
    public function validateForwardsInjectedDatabaseToFactory(): void
    {
        $object = new EngineValidatorObject();
        $object->engineStruct = new EngineStruct(); // class_load === null

        $validator = new LaraEngineValidator($this->createStub(IDatabase::class));

        // createTempInstance() runs before the LaraException try/catch, so the
        // missing-class_load throw propagates verbatim.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Engine has no class_load');

        $validator->validate($object);
    }
}
