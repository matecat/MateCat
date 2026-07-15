<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\MyMemoryEntryStatusController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use ReflectionClass;
use ReflectionException;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\TMSAbstractResponse;

/**
 * Overrides the {@see MyMemoryEntryStatusController::getMMEngine()} seam so the
 * MyMemory engine can be replaced with a stub — status() otherwise fires a live
 * outbound call. When no override is set it falls back to the real factory.
 */
class TestableMyMemoryEntryStatusController extends MyMemoryEntryStatusController
{
    public ?MyMemory $engineOverride = null;

    public function __construct()
    {
    }

    protected function getMMEngine(FeatureSet $featureSet): MyMemory
    {
        return $this->engineOverride ?? parent::getMMEngine($featureSet);
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
 * Bare subclass (no seam override) used to exercise the real getMMEngine() body.
 */
class RealMyMemoryEntryStatusController extends MyMemoryEntryStatusController
{
    public function __construct()
    {
    }
}

class MyMemoryEntryStatusControllerTest extends AbstractTest
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
    public function testStatusRespondsWithEngineResultOnSuccess(): void
    {
        $tmsResponse = $this->createStub(TMSAbstractResponse::class);

        $engine = $this->createStub(MyMemory::class);
        $engine->method('entryStatus')->willReturn($tmsResponse);

        $controller = new TestableMyMemoryEntryStatusController();
        $controller->engineOverride = $engine;

        $this->setProp($controller, 'request', new Request(['uuid' => 'abc-123']));
        $this->setProp($controller, 'response', new Response());
        $this->setProp($controller, 'database', obtainTestDatabase());
        $this->setProp($controller, 'featureSet', new FeatureSet(obtainTestDatabase()));

        $controller->status();

        $response = $controller->getResponse();

        self::assertSame(200, $response->code());
        self::assertSame(json_encode($tmsResponse), $response->body());
    }

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     */
    public function testStatusReturns500WithErrorMessageWhenEngineThrows(): void
    {
        $engine = $this->createStub(MyMemory::class);
        $engine->method('entryStatus')->willThrowException(new Exception('boom'));

        $controller = new TestableMyMemoryEntryStatusController();
        $controller->engineOverride = $engine;

        $this->setProp($controller, 'request', new Request(['uuid' => 'abc-123']));
        $this->setProp($controller, 'response', new Response());
        $this->setProp($controller, 'database', obtainTestDatabase());
        $this->setProp($controller, 'featureSet', new FeatureSet(obtainTestDatabase()));

        $controller->status();

        $response = $controller->getResponse();

        self::assertSame(500, $response->code());

        $decoded = json_decode($response->body(), true);

        self::assertIsArray($decoded);
        self::assertSame('boom', $decoded['error']);
    }

    /**
     * @throws ReflectionException
     */
    public function testGetMMEngineReturnsConfiguredMyMemory(): void
    {
        $controller = new RealMyMemoryEntryStatusController();

        $this->setProp($controller, 'database', obtainTestDatabase());

        $engine = $this->callMethod($controller, 'getMMEngine', [new FeatureSet(obtainTestDatabase())]);

        self::assertInstanceOf(MyMemory::class, $engine);
    }

    /**
     * @throws ReflectionException
     */
    public function testRegisterValidatorsAppendsLoginValidator(): void
    {
        $controller = new TestableMyMemoryEntryStatusController();

        $this->setProp($controller, 'request', new Request());
        $this->setProp($controller, 'response', new Response());
        $this->setProp($controller, 'database', obtainTestDatabase());

        $this->callMethod($controller, 'registerValidators');

        $validators = $controller->getValidatorsForTest();

        self::assertCount(1, $validators);
        self::assertInstanceOf(LoginValidator::class, $validators[0]);
    }
}
