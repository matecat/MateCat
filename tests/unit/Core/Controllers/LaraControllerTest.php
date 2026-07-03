<?php

namespace Matecat\Core\Controllers;

use Controller\API\V3\LaraController;
use Klein\HttpStatus;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Utils\Engines\Lara;

/**
 * A minimal testable subclass: no-arg constructor so the controller
 * can be instantiated without Klein's full dispatch lifecycle.
 */
class TestableLaraController extends LaraController
{
    public function __construct()
    {
    }

    /**
     * Exposes registerValidators() as public so tests can call it directly
     * after injecting all required properties via reflection.
     */
    public function callRegisterValidators(): void
    {
        $this->registerValidators();
    }
}

class LaraControllerTest extends AbstractTest
{
    private TestableLaraController $controller;
    private ReflectionClass $reflector;
    private Request $requestStub;
    private Response $responseMock;
    private mixed $lastJsonResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lastJsonResponse = null;
        $this->controller       = new TestableLaraController();
        $this->reflector        = new ReflectionClass(LaraController::class);

        $this->requestStub = $this->createStub(Request::class);
        $this->responseMock = $this->createStub(Response::class);

        // Inject request
        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($this->controller, $this->requestStub);

        // Inject response
        $resProp = $this->reflector->getProperty('response');
        $resProp->setValue($this->controller, $this->responseMock);

        // Inject user (uid=42 so EngineOwnershipValidator uid-check passes)
        $user      = new UserStruct();
        $user->uid = 42;
        $userProp  = $this->reflector->getProperty('user');
        $userProp->setValue($this->controller, $user);

        // Inject userIsLogged = true so LoginValidator passes
        $loggedProp = $this->reflector->getProperty('userIsLogged');
        $loggedProp->setValue($this->controller, true);

        // Inject featureSet stub
        $fsProp = $this->reflector->getProperty('featureSet');
        $fsProp->setValue($this->controller, $this->createStub(FeatureSet::class));

        // Wire response mock: status() returns an HttpStatus stub, json() captures the payload
        $statusStub = $this->createStub(HttpStatus::class);
        $this->responseMock->method('status')->willReturn($statusStub);
        $this->responseMock->method('json')->willReturnCallback(function (mixed $data): Response {
            $this->lastJsonResponse = $data;

            return $this->responseMock;
        });
    }

    // -------------------------------------------------------------------------
    // glossaries() — happy path
    // -------------------------------------------------------------------------

    #[Test]
    public function glossaries_returns_engine_glossaries(): void
    {
        $expectedGlossaries = [
            ['id' => 1, 'name' => 'Test Glossary'],
            ['id' => 2, 'name' => 'Another Glossary'],
        ];

        $laraStub = $this->createStub(Lara::class);
        $laraStub->method('getGlossaries')->willReturn($expectedGlossaries);

        // Inject the lara engine directly (bypasses the validator flow for this test)
        $laraEngineProp = $this->reflector->getProperty('laraEngine');
        $laraEngineProp->setValue($this->controller, $laraStub);

        $this->controller->glossaries();

        self::assertSame($expectedGlossaries, $this->lastJsonResponse);
    }

    #[Test]
    public function glossaries_returns_empty_array_when_no_glossaries(): void
    {
        $laraStub = $this->createStub(Lara::class);
        $laraStub->method('getGlossaries')->willReturn([]);

        $laraEngineProp = $this->reflector->getProperty('laraEngine');
        $laraEngineProp->setValue($this->controller, $laraStub);

        $this->controller->glossaries();

        self::assertSame([], $this->lastJsonResponse);
    }

    // -------------------------------------------------------------------------
    // registerValidators() — the onSuccess closure chain
    //
    // Strategy: use a mocked DB so that EngineOwnershipValidator._validate()
    // succeeds (EngineDAO::read() returns an EngineStruct with matching uid=42,
    // type=MT, class_load=Lara), which allows both onSuccess closures to fire:
    //   • closure 0: $engineOwnerValidator->validate()         (line 23)
    //   • closure 1: $this->laraEngine = ...->getEngine()     (line 25)
    // -------------------------------------------------------------------------

    /**
     * Build a mock PDO infrastructure that makes EngineDAO::read() return the
     * supplied EngineStruct without touching a real database.
     *
     * @return IDatabase
     */
    private function buildMockDatabaseForEngine(EngineStruct $engineStruct): IDatabase
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        // queryString is read directly as a property by AbstractDao internals
        $stmtStub->queryString = '';
        // fetchAll() returns the pre-built struct; FETCH_CLASS mode is a no-op on the stub
        $stmtStub->method('fetchAll')->willReturn([$engineStruct]);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $dbStub = $this->createStub(IDatabase::class);
        $dbStub->method('getConnection')->willReturn($pdoStub);

        return $dbStub;
    }

    /**
     * Create an EngineStruct that EngineDAO/_buildResult/EnginesFactory will
     * accept as a valid Lara MT engine owned by uid=42.
     *
     * @return EngineStruct
     */
    private function buildLaraEngineStruct(): EngineStruct
    {
        $engineStruct              = new EngineStruct();
        $engineStruct->id          = 1;
        $engineStruct->uid         = 42; // matches $user->uid injected in setUp
        $engineStruct->type        = 'MT'; // EngineConstants::MT
        $engineStruct->class_load  = 'Lara'; // EnginesFactory resolves to Utils\Engines\Lara
        $engineStruct->active      = true;
        $engineStruct->name        = 'Test Lara Engine';
        // others/extra_parameters must be JSON strings (not arrays) for json_decode in _buildResult
        $engineStruct->others          = '{}';
        $engineStruct->extra_parameters = '{}';

        return $engineStruct;
    }

    #[Test]
    public function registerValidators_onSuccess_closures_set_lara_engine(): void
    {
        $engineStruct = $this->buildLaraEngineStruct();
        $dbStub       = $this->buildMockDatabaseForEngine($engineStruct);

        // Inject the mock database so EngineOwnershipValidator._validate() uses it
        $dbProp = $this->reflector->getProperty('database');
        $dbProp->setValue($this->controller, $dbStub);

        // Inject engineId param so EngineOwnershipValidator receives id=1
        $this->requestStub->method('param')->willReturn('1');

        // Call registerValidators() — registers LoginValidator + two onSuccess closures
        $this->controller->callRegisterValidators();

        // Now run the full validator chain (LoginValidator → onSuccess closures)
        // validateRequest() is protected; call it via reflection
        $validateMethod = new \ReflectionMethod(LaraController::class, 'validateRequest');
        $validateMethod->invoke($this->controller);

        // If both closures ran: $this->laraEngine is set on the controller
        $laraEngineProp = $this->reflector->getProperty('laraEngine');
        $laraEngine     = $laraEngineProp->getValue($this->controller);

        self::assertInstanceOf(Lara::class, $laraEngine);
    }

    #[Test]
    public function registerValidators_registers_login_validator(): void
    {
        // Without injecting a DB, just verify that calling registerValidators()
        // appends exactly one validator to the validators list.
        $this->controller->callRegisterValidators();

        $validatorsProp = $this->reflector->getProperty('validators');
        $validators     = $validatorsProp->getValue($this->controller);

        self::assertCount(1, $validators);
    }
}
