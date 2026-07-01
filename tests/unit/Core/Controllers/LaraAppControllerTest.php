<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\App\LaraController;
use Klein\Request;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

#[Group('unit')]
class LaraAppControllerTest extends AbstractTest
{
    // ─── registerValidators(): appends a LoginValidator ───────────────────────

    /** @throws ReflectionException */
    #[Test]
    public function registerValidatorsAppendsLoginValidator(): void
    {
        $reflection = new ReflectionClass(LaraController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        (new ReflectionProperty($controller, 'request'))->setValue($controller, $this->createStub(Request::class));

        $reflection->getMethod('registerValidators')->invoke($controller);

        $validators = (new ReflectionProperty($controller, 'validators'))->getValue($controller);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(\Controller\API\Commons\Validators\LoginValidator::class, $validators[0]);
    }

    // ─── translate(): no-op body ───────────────────────────────────────────────

    /** @throws ReflectionException */
    #[Test]
    public function translateReturnsVoid(): void
    {
        $reflection = new ReflectionClass(LaraController::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        $this->assertNull($controller->translate());
    }
}
