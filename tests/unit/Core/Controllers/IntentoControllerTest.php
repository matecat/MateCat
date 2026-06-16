<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\IntentoController;
use Controller\API\Commons\Validators\LoginValidator;
use Exception;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\Engines\Structs\EngineStruct;
use PDO;
use PDOStatement;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TypeError;
use UnexpectedValueException;
use Utils\Constants\EngineConstants;
use Utils\Engines\Intento;

/**
 * Mock-seam suite for {@see IntentoController}.
 *
 * Pattern: mock (Playbook §2). The controller instantiates EngineDAO directly from
 * $this->db(); we swap the Database singleton with a stub whose PDOStatement::fetchAll()
 * yields a controlled EngineStruct, so EngineDAO::read() returns a deterministic row
 * with no real DB / Redis access. SQL cache is disabled for the duration of each test.
 *
 * Reserved ID block (Playbook §4): base = 9_021_000 (task N=21).
 * Owner email: ctrltest_9021000@example.org (never the shared test@example.org).
 * No rows are persisted (pure mock seam); nothing to clean.
 */
class TestableIntentoController extends IntentoController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class IntentoControllerTest extends AbstractTest
{
    private const int BASE = 9_021_000;
    private const int ENGINE_ID = 9_021_001;

    /** @var ReflectionClass<IntentoController> */
    private ReflectionClass $reflector;
    private TestableIntentoController $controller;
    private Response&MockObject $responseMock;
    private bool $previousSkipSqlCache;

    /**
     * @throws ReflectionException
     * @throws MockObjectException
     * @throws Exception
     * @throws TypeError
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Disable SQL cache so EngineDAO::read() always falls through to the stubbed
        // PDOStatement instead of consulting Redis.
        $this->previousSkipSqlCache              = \Utils\Registry\AppConfig::$SKIP_SQL_CACHE;
        \Utils\Registry\AppConfig::$SKIP_SQL_CACHE = true;

        $this->controller = new TestableIntentoController();
        $this->reflector  = new ReflectionClass(IntentoController::class);

        $this->responseMock = $this->createMock(Response::class);
        $this->setProp('response', $this->responseMock);
    }

    protected function tearDown(): void
    {
        \Utils\Registry\AppConfig::$SKIP_SQL_CACHE = $this->previousSkipSqlCache;
        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @throws ReflectionException
     */
    private function setRequestWithEngineId(string $engineId): void
    {
        $request = new Request(['engineId' => $engineId]);
        $this->setProp('request', $request);
    }

    /**
     * Install a Database stub whose PDOStatement::fetchAll() yields the supplied rows.
     *
     * @param list<EngineStruct> $rows
     *
     * @throws MockObjectException
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     */
    private function installDbWithRows(array $rows): void
    {
        $stmtStub            = $this->createStub(PDOStatement::class);
        $stmtStub->queryString = '';
        $stmtStub->method('fetchAll')->willReturn($rows);

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $dbStub = $this->createStub(IDatabase::class);
        $dbStub->method('getConnection')->willReturn($pdoStub);

        $this->setDatabaseInstance($dbStub);
    }

    /**
     * Build a struct as if hydrated by PDO::FETCH_CLASS: scalar columns only, JSON-encoded
     * blobs for `others`/`extra_parameters` (EngineDAO::_buildResult json_decodes them).
     */
    private function makeEngineStruct(string $classLoad, string $type, ?string $apiKey): EngineStruct
    {
        $struct             = new EngineStruct();
        $struct->id         = self::ENGINE_ID;
        $struct->name       = 'IntentoTest';
        $struct->type       = $type;
        $struct->description = 'desc';
        $struct->base_url   = 'https://example.org';
        $struct->translate_relative_url  = '';
        $struct->contribute_relative_url = '';
        $struct->update_relative_url     = '';
        $struct->delete_relative_url     = '';
        $struct->others           = '[]';
        $struct->class_load       = $classLoad;
        $struct->extra_parameters = $apiKey === null ? '[]' : '{"apikey":"' . $apiKey . '"}';
        $struct->google_api_compliant_version = 0;
        $struct->penalty          = 0;
        $struct->active           = true;
        $struct->uid              = self::BASE;

        return $struct;
    }

    // ─── routingList — happy path ───

    /**
     * A valid Intento engine record with no apikey drives getRoutingList() down its
     * empty-key short-circuit, yielding the empty-list payload — no external HTTP call.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws MockObjectException
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function routingList_returns_empty_list_for_intento_engine_without_apikey(): void
    {
        $this->setRequestWithEngineId((string) self::ENGINE_ID);
        $this->installDbWithRows([
            $this->makeEngineStruct('Intento', EngineConstants::MT, null),
        ]);

        $captured     = null;
        $responseMock = $this->responseMock;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->willReturnCallback(function (array $data) use (&$captured, $responseMock): Response {
                $captured = $data;

                return $responseMock;
            });

        $this->controller->routingList();

        $this->assertSame([], $captured);
    }

    // ─── routingList — failure paths ───

    /**
     * An empty result set from EngineDAO::read() means the engine id is not valid.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws MockObjectException
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     */
    #[Test]
    public function routingList_throws_when_engine_id_not_found(): void
    {
        $this->setRequestWithEngineId((string) self::ENGINE_ID);
        $this->installDbWithRows([]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Engine ID is not valid');

        $this->controller->routingList();
    }

    /**
     * A non-Intento engine record must be rejected after instantiation.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws MockObjectException
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     */
    #[Test]
    public function routingList_throws_when_engine_is_not_intento(): void
    {
        $this->setRequestWithEngineId((string) self::ENGINE_ID);
        // GoogleTranslate is a valid MT engine class but not an Intento instance.
        $this->installDbWithRows([
            $this->makeEngineStruct('GoogleTranslate', EngineConstants::MT, null),
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Engine is not of Intento type');

        $this->controller->routingList();
    }

    // ─── registerValidators ───

    /**
     * @throws ReflectionException
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function registerValidators_appends_the_login_validator(): void
    {
        $controller = $this->reflector->newInstanceWithoutConstructor();

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($controller, new Request());

        $method = $this->reflector->getMethod('registerValidators');
        $method->invoke($controller);

        /** @var array<int, object> $validators */
        $validators = $this->reflector->getProperty('validators')->getValue($controller);

        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }
}
