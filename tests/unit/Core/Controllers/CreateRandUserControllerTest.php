<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\CreateRandUserController;
use Controller\API\Commons\Validators\LoginValidator;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use ReflectionClass;
use ReflectionException;
use Utils\Engines\MyMemory;

/**
 * Overrides the {@see CreateRandUserController::getEngine()} seam so the
 * MyMemory engine can be replaced with a stub — create() otherwise fires a
 * live outbound call to provision a real MyMemory key. When no override is set
 * it falls back to the real factory.
 */
class TestableCreateRandUserController extends CreateRandUserController
{
    public ?MyMemory $engineOverride = null;

    public function __construct()
    {
    }

    protected function getEngine(): MyMemory
    {
        return $this->engineOverride ?? parent::getEngine();
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * @return array<int, mixed>
     */
    public function getValidatorsForTest(): array
    {
        return $this->validators;
    }
}

/**
 * Bare subclass (no seam override) used to exercise the real getEngine() body.
 */
class RealCreateRandUserController extends CreateRandUserController
{
    public function __construct()
    {
    }
}

class CreateRandUserControllerTest extends AbstractTest
{

    /**
     * @throws ReflectionException
     */
    private function setProp(object $object, string $name, mixed $value): void
    {
        $reflection = new ReflectionClass($object);

        while (!$reflection->hasProperty($name)) {
            $reflection = $reflection->getParentClass();
        }

        $property = $reflection->getProperty($name);
        $property->setValue($object, $value);
    }

    /**
     * @param array<mixed> $args
     *
     * @throws ReflectionException
     */
    private function callMethod(object $object, string $method, array $args = []): mixed
    {
        $reflectionMethod = (new ReflectionClass($object))->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($object, $args);
    }

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     */
    public function testCreateRespondsWithGeneratedKey(): void
    {
        $engine = $this->createStub(MyMemory::class);
        $engine->method('createMyMemoryKey')->willReturn('RANDKEY-123');

        $controller = new TestableCreateRandUserController();
        $controller->engineOverride = $engine;

        $this->setProp($controller, 'request', new Request());
        $this->setProp($controller, 'response', new Response());
        $this->setProp($controller, 'database', obtainTestDatabase());

        // create() calls Response::json(), which send()s the body to stdout and pollutes
        // the test-runner output. Swallow that echo; the body is still recorded on the
        // Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $controller->create();
        } finally {
            ob_end_clean();
        }

        $response = $controller->getResponse();

        self::assertSame(200, $response->code());

        $decoded = json_decode($response->body(), true);

        self::assertIsArray($decoded);
        self::assertSame('RANDKEY-123', $decoded['data']);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetEngineReturnsMyMemory(): void
    {
        $controller = new RealCreateRandUserController();

        $this->setProp($controller, 'database', obtainTestDatabase());

        $engine = $this->callMethod($controller, 'getEngine');

        self::assertInstanceOf(MyMemory::class, $engine);
    }

    /**
     * @throws ReflectionException
     */
    public function testRegisterValidatorsAppendsLoginValidator(): void
    {
        $controller = new TestableCreateRandUserController();

        $this->setProp($controller, 'request', new Request());
        $this->setProp($controller, 'response', new Response());
        $this->setProp($controller, 'database', obtainTestDatabase());

        $this->callMethod($controller, 'registerValidators');

        $validators = $controller->getValidatorsForTest();

        self::assertCount(1, $validators);
        self::assertInstanceOf(LoginValidator::class, $validators[0]);
    }
}
