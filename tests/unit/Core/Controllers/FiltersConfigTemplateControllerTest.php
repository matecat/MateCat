<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\V3\FiltersConfigTemplateController;
use Exception;
use Klein\DataCollection\DataCollection;
use Klein\DataCollection\HeaderDataCollection;
use Klein\HttpStatus;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Filters\FiltersConfigTemplateDao;
use Model\Filters\FiltersConfigTemplateStruct;
use Model\Users\UserStruct;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestableFiltersConfigTemplateController extends FiltersConfigTemplateController
{
    public FiltersConfigTemplateDao $stubDao;

    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function getFiltersConfigTemplateDao(): FiltersConfigTemplateDao
    {
        return $this->stubDao;
    }
}

class ValidatorTestableFiltersConfigTemplateController extends FiltersConfigTemplateController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }
}

class FiltersConfigTemplateControllerTest extends AbstractTest
{
    private const VALID_JSON = '{}';

    private ReflectionClass $reflector;
    private TestableFiltersConfigTemplateController $controller;
    private Stub&FiltersConfigTemplateDao $daoStub;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        $this->controller = new TestableFiltersConfigTemplateController();
        $this->reflector  = new ReflectionClass(FiltersConfigTemplateController::class);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));

        $this->daoStub = $this->createStub(FiltersConfigTemplateDao::class);
        $this->controller->stubDao = $this->daoStub;

        $this->setUser(1);
    }

    protected function tearDown(): void
    {
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function setUser(?int $uid): void
    {
        $user        = new UserStruct();
        $user->uid   = $uid;
        $user->email = 'test@example.org';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);
    }

    /**
     * @param array<string, string|null> $params
     */
    private function setRequest(array $params = [], ?string $body = null, string $contentType = 'application/json'): void
    {
        $headers = $this->createStub(HeaderDataCollection::class);
        $headers->method('get')->willReturn($contentType);

        $named = $this->createStub(DataCollection::class);
        $named->method('get')->willReturnCallback(static fn(string $key, $default = null) => $params[$key] ?? $default);

        $request = $this->createStub(Request::class);
        $request->method('param')->willReturnCallback(static fn(string $key, $default = null) => $params[$key] ?? $default);
        $request->method('body')->willReturn($body);
        $request->method('headers')->willReturn($headers);
        $request->method('paramsNamed')->willReturn($named);

        $this->reflector->getProperty('request')->setValue($this->controller, $request);
    }

    private function responseMock(): Response&MockObject
    {
        $mock = $this->createMock(Response::class);
        $mock->method('status')->willReturn($this->createStub(HttpStatus::class));
        $mock->method('code')->willReturnSelf();
        $mock->method('json')->willReturnSelf();
        $this->reflector->getProperty('response')->setValue($this->controller, $mock);
        return $mock;
    }

    // ── all ──────────────────────────────────────────────────────────────

    #[Test]
    public function all_returns_paginated_json(): void
    {
        $this->setRequest(['page' => '1', 'perPage' => '20']);
        $this->daoStub->method('getAllPaginated')->willReturn(['templates' => []]);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['templates' => []]);

        $this->controller->all();
    }

    #[Test]
    public function all_caps_pagination_and_handles_exception(): void
    {
        $this->setRequest(['page' => '1', 'perPage' => '9999']);
        $this->daoStub->method('getAllPaginated')->willThrowException(new Exception('boom', 503));

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'boom']);

        $this->controller->all();
    }

    #[Test]
    public function all_returns_401_when_unauthenticated(): void
    {
        $this->setUser(null);
        $this->setRequest(['page' => '1']);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'User not authenticated']);

        $this->controller->all();
    }

    // ── get ──────────────────────────────────────────────────────────────

    #[Test]
    public function get_returns_model_when_found(): void
    {
        $this->setRequest(['id' => '42']);
        $struct = new FiltersConfigTemplateStruct();
        $this->daoStub->method('getByIdAndUser')->willReturn($struct);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with($struct);

        $this->controller->get();
    }

    #[Test]
    public function get_returns_404_when_missing(): void
    {
        $this->setRequest(['id' => '42']);
        $this->daoStub->method('getByIdAndUser')->willReturn(null);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Model not found']);

        $this->controller->get();
    }

    // ── create ───────────────────────────────────────────────────────────

    #[Test]
    public function create_returns_400_when_not_json(): void
    {
        $this->setRequest([], self::VALID_JSON, 'text/html');

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Bad Get']);

        $this->controller->create();
    }

    #[Test]
    public function create_returns_400_when_body_null(): void
    {
        $this->setRequest([], null, 'application/json');

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Missing request body']);

        $this->controller->create();
    }

    #[Test]
    public function create_returns_struct_on_valid_payload(): void
    {
        $this->setRequest([], self::VALID_JSON, 'application/json');
        $struct = new FiltersConfigTemplateStruct();
        $this->daoStub->method('createFromJSON')->willReturn($struct);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with($struct);

        $this->controller->create();
    }

    #[Test]
    public function create_returns_400_with_formatted_error_on_invalid_schema(): void
    {
        // additionalProperties:false → unknown key fails JSON-schema validation
        $this->setRequest([], '{"unknown_field":1}', 'application/json');

        $response = $this->responseMock();
        $response->expects(self::once())
            ->method('json')
            ->with(self::callback(static fn(array $data): bool => isset($data['error'])));

        $this->controller->create();
    }

    #[Test]
    public function create_returns_400_on_duplicate_name_pdo_exception(): void
    {
        $this->setRequest([], self::VALID_JSON, 'application/json');
        $this->daoStub->method('createFromJSON')->willThrowException(new PDOException('dup', 23000));

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Invalid unique template name']);

        $this->controller->create();
    }

    // ── update ───────────────────────────────────────────────────────────

    #[Test]
    public function update_returns_400_when_not_json(): void
    {
        $this->setRequest(['id' => '42'], self::VALID_JSON, 'text/html');

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Bad Get']);

        $this->controller->update();
    }

    #[Test]
    public function update_returns_404_when_model_missing(): void
    {
        $this->setRequest(['id' => '42'], self::VALID_JSON, 'application/json');
        $this->daoStub->method('getByIdAndUser')->willReturn(null);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Model not found']);

        $this->controller->update();
    }

    #[Test]
    public function update_returns_struct_on_valid_payload(): void
    {
        $this->setRequest(['id' => '42'], self::VALID_JSON, 'application/json');
        $existing = new FiltersConfigTemplateStruct();
        $edited   = new FiltersConfigTemplateStruct();
        $this->daoStub->method('getByIdAndUser')->willReturn($existing);
        $this->daoStub->method('editFromJSON')->willReturn($edited);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with($edited);

        $this->controller->update();
    }

    #[Test]
    public function update_returns_400_when_body_null(): void
    {
        $this->setRequest(['id' => '42'], null, 'application/json');
        $this->daoStub->method('getByIdAndUser')->willReturn(new FiltersConfigTemplateStruct());

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Missing request body']);

        $this->controller->update();
    }

    #[Test]
    public function update_returns_formatted_error_on_invalid_schema(): void
    {
        $this->setRequest(['id' => '42'], '{"unknown_field":1}', 'application/json');
        $this->daoStub->method('getByIdAndUser')->willReturn(new FiltersConfigTemplateStruct());

        $response = $this->responseMock();
        $response->expects(self::once())
            ->method('json')
            ->with(self::callback(static fn(array $data): bool => isset($data['error'])));

        $this->controller->update();
    }

    // ── delete ───────────────────────────────────────────────────────────

    #[Test]
    public function delete_returns_id_on_success(): void
    {
        $this->setRequest(['id' => '42']);
        $this->daoStub->method('remove')->willReturn(1);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['id' => 42]);

        $this->controller->delete();
    }

    #[Test]
    public function delete_returns_404_when_nothing_removed(): void
    {
        $this->setRequest(['id' => '42']);
        $this->daoStub->method('remove')->willReturn(0);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Model not found']);

        $this->controller->delete();
    }

    // ── schema ───────────────────────────────────────────────────────────

    #[Test]
    public function schema_returns_decoded_schema(): void
    {
        $this->setRequest();

        $response = $this->responseMock();
        $response->expects(self::once())->method('json');

        $this->controller->schema();
    }

    // ── registerValidators ───────────────────────────────────────────────

    #[Test]
    public function registerValidators_registers_login_validator(): void
    {
        $controller = new ValidatorTestableFiltersConfigTemplateController();
        $ref = new ReflectionClass(FiltersConfigTemplateController::class);

        $ref->getProperty('request')->setValue($controller, $this->createStub(Request::class));
        $ref->getProperty('response')->setValue($controller, $this->createStub(Response::class));
        $ref->getProperty('params')->setValue($controller, []);

        $ref->getMethod('registerValidators')->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);
        self::assertCount(1, $validators);
    }
}
