<?php

declare(strict_types=1);

namespace Matecat\TestHelpers;

use Controller\API\App\FetchChangeRatesController;
use Exception;
use Model\DataAccess\IDatabase;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockException;
use ReflectionException;
use TypeError;

/**
 * Neutered-ctor subclass that mimics the "Testable*" pattern used throughout
 * the test suite.  Its constructor is intentionally empty and never calls
 * parent::__construct(), so the reflection path must inject $database directly.
 */
class TestableFetchChangeRatesController extends FetchChangeRatesController
{
    public function __construct()
    {
        // intentionally empty — does NOT call parent::__construct()
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }
}

/**
 * Demonstrates both injection paths provided by ControllerDbInjectionTrait:
 *
 *  1. App path  — real controller ctor + Klein App service layer
 *  2. Reflection path — neutered-ctor subclass, property set via reflection
 */
class ControllerDbInjectionTraitDemoTest extends AbstractTest
{
    use ControllerDbInjectionTrait;

    // ------------------------------------------------------------------ App path

    /**
     * @throws ReflectionException
     * @throws MockException
     * @throws NoPreviousThrowableException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function app_path_injects_stub_into_real_controller(): void
    {
        $stub = $this->createStub(IDatabase::class);

        /** @var FetchChangeRatesController $controller */
        $controller = $this->buildControllerWithDb(FetchChangeRatesController::class, $stub);

        self::assertSame($stub, $controller->getDatabase());
    }

    // --------------------------------------------------------- Reflection path

    /**
     * @throws ReflectionException
     * @throws MockException
     * @throws NoPreviousThrowableException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function reflection_path_injects_stub_into_neutered_controller(): void
    {
        $stub = $this->createStub(IDatabase::class);

        /** @var TestableFetchChangeRatesController $controller */
        $controller = $this->buildNeuteredControllerWithDb(
            TestableFetchChangeRatesController::class,
            $stub
        );

        self::assertSame($stub, $controller->getDatabase());
    }

    // ---------------------------------- assertSame identity holds for both paths

    /**
     * Confirm that two distinct stubs injected into two distinct controller
     * instances remain independent (no cross-contamination).
     *
     * @throws ReflectionException
     * @throws MockException
     * @throws NoPreviousThrowableException
     * @throws InvalidArgumentException
     * @throws ExpectationFailedException
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function different_stubs_produce_independent_instances(): void
    {
        $stubA = $this->createStub(IDatabase::class);
        $stubB = $this->createStub(IDatabase::class);

        $controllerA = $this->buildControllerWithDb(FetchChangeRatesController::class, $stubA);
        $controllerB = $this->buildNeuteredControllerWithDb(
            TestableFetchChangeRatesController::class,
            $stubB
        );

        self::assertSame($stubA, $controllerA->getDatabase());
        self::assertSame($stubB, $controllerB->getDatabase());
        self::assertNotSame($controllerA->getDatabase(), $controllerB->getDatabase());
    }
}
