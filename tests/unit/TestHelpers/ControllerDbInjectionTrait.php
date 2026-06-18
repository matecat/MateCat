<?php

declare(strict_types=1);

namespace Matecat\TestHelpers;

use Controller\Abstracts\KleinController;
use Exception;
use Klein\App;
use Klein\Exceptions\DuplicateServiceException;
use Klein\Request;
use Klein\Response;
use Model\DataAccess\IDatabase;
use ReflectionClass;
use ReflectionException;
use TypeError;

/**
 * Provides helpers for injecting a specific IDatabase stub into a controller
 * under test and asserting that the controller's getDatabase() returns that
 * exact instance.
 *
 * Two construction paths are supported:
 *
 *  1. App path — constructs the controller through its real constructor with a
 *     Klein App whose `getDatabase` service returns the supplied stub.  Use
 *     this for controllers whose constructor does NOT skip `parent::__construct`.
 *
 *  2. Reflection (neutered-ctor) path — for "Testable*" subclasses that declare
 *     an empty `__construct()` and never call `parent::__construct()`.
 *     Uses `ReflectionClass::newInstanceWithoutConstructor()` and then sets
 *     the protected `$database` property directly via reflection.
 */
trait ControllerDbInjectionTrait
{

    /**
     * Build a controller through its real constructor, injecting $stub as the
     * database dependency via the Klein App service layer.
     *
     * The App registers a `getDatabase` service that returns $stub.
     * KleinController::__construct() resolves it via `$this->app->getDatabase()`
     * and stores it in `$this->database`.
     *
     * @template T of KleinController
     *
     * @param class-string<T> $controllerClass  Fully-qualified class name of the controller to build
     * @param IDatabase       $stub             The database stub to inject
     * @param Request|null    $request          Optional Request; a blank one is created if omitted
     * @param Response|null   $response         Optional Response; a blank one is created if omitted
     *
     * @return T
     *
     * @throws ReflectionException
     * @throws DuplicateServiceException
     * @throws Exception
     * @throws TypeError
     */
    protected function buildControllerWithDb(
        string $controllerClass,
        IDatabase $stub,
        ?Request $request = null,
        ?Response $response = null
    ): KleinController {
        $app = new App();
        $app->register('getDatabase', static fn() => $stub);

        $request  ??= new Request();
        $response ??= new Response();

        return new $controllerClass($request, $response, null, $app);
    }

    /**
     * Build a neutered-ctor controller subclass (one whose `__construct` is
     * overridden to an empty body and never calls `parent::__construct`) and
     * inject $stub by reflection-setting the protected `$database` property.
     *
     * This mirrors the pattern used throughout the test suite (e.g.
     * TestableCommentController) where the constructor is bypassed entirely.
     *
     * @template T of KleinController
     *
     * @param class-string<T> $controllerClass  Fully-qualified class name of the Testable* controller
     * @param IDatabase       $stub             The database stub to inject
     *
     * @return T
     *
     * @throws ReflectionException
     */
    protected function buildNeuteredControllerWithDb(
        string $controllerClass,
        IDatabase $stub
    ): KleinController {
        $ref        = new ReflectionClass($controllerClass);
        $controller = $ref->newInstanceWithoutConstructor();

        // Walk up the hierarchy to find `$database` (declared on KleinController).
        $prop = $this->findProperty($ref, 'database');
        $prop->setValue($controller, $stub);

        return $controller;
    }

    /**
     * Walk the reflection hierarchy to locate a property by name, since it may
     * be declared on a parent class rather than the concrete class under test.
     *
     * @param ReflectionClass<object> $ref
     *
     * @throws ReflectionException
     */
    private function findProperty(ReflectionClass $ref, string $name): \ReflectionProperty
    {
        $current = $ref;
        while ($current !== false) {
            if ($current->hasProperty($name)) {
                $prop = $current->getProperty($name);
                $prop->setAccessible(true);

                return $prop;
            }
            $current = $current->getParentClass();
        }

        throw new ReflectionException(
            sprintf('Property $%s not found in %s or any of its parents.', $name, $ref->getName())
        );
    }
}
