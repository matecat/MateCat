<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\V3\PayableRateController;
use Exception;
use Klein\DataCollection\HeaderDataCollection;
use Klein\HttpStatus;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\PayableRates\CustomPayableRateDao;
use Model\PayableRates\CustomPayableRateStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestablePayableRateController extends PayableRateController
{
    public CustomPayableRateDao $stubDao;

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

    protected function getCustomPayableRateDao(): CustomPayableRateDao
    {
        return $this->stubDao;
    }
}

class ValidatorTestablePayableRateController extends PayableRateController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }
}

class PayableRateControllerTest extends AbstractTest
{
    private const VALID_JSON = '{"payable_rate_template_name":"Test","breakdowns":{"default":{"NO_MATCH":100,"50%-74%":60,"75%-84%":60,"85%-94%":40,"95%-99%":30,"100%":10,"100%_PUBLIC":10,"REPETITIONS":10,"INTERNAL":10,"MT":10,"ICE":0,"ICE_MT":0}}}';

    private ReflectionClass $reflector;
    private TestablePayableRateController $controller;
    private Stub&CustomPayableRateDao $daoStub;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        $this->controller = new TestablePayableRateController();
        $this->reflector  = new ReflectionClass(PayableRateController::class);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));

        $this->daoStub = $this->createStub(CustomPayableRateDao::class);
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
        $user            = new UserStruct();
        $user->uid       = $uid;
        $user->email     = 'test@example.org';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);
    }

    /**
     * @param array<string, string|null> $params
     */
    private function setRequest(array $params = [], ?string $body = null, string $contentType = 'application/json'): void
    {
        $headers = $this->createStub(HeaderDataCollection::class);
        $headers->method('get')->willReturn($contentType);

        $request = $this->createStub(Request::class);
        $request->method('param')->willReturnCallback(static fn(string $key, $default = null) => $params[$key] ?? $default);
        $request->method('body')->willReturn($body);
        $request->method('headers')->willReturn($headers);

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

    // ── index ────────────────────────────────────────────────────────────

    #[Test]
    public function index_returns_paginated_json(): void
    {
        $this->setRequest(['page' => '1', 'perPage' => '20']);
        $this->daoStub->method('getAllPaginated')->willReturn(['rates' => []]);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['rates' => []]);

        $this->controller->index();
    }

    #[Test]
    public function index_caps_pagination_and_handles_exception(): void
    {
        $this->setRequest(['page' => '1', 'perPage' => '9999']);
        $this->daoStub->method('getAllPaginated')->willThrowException(new Exception('boom', 503));

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'boom']);

        $this->controller->index();
    }

    #[Test]
    public function index_returns_401_when_unauthenticated(): void
    {
        $this->setUser(null);
        $this->setRequest(['page' => '1']);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'User not authenticated']);

        $this->controller->index();
    }

    // ── create ───────────────────────────────────────────────────────────

    #[Test]
    public function create_returns_405_when_not_json(): void
    {
        $this->setRequest([], self::VALID_JSON, 'text/html');

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Method not allowed']);

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
        $struct = new CustomPayableRateStruct();
        $this->daoStub->method('createFromJSON')->willReturn($struct);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with($struct);

        $this->controller->create();
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

    // ── view ─────────────────────────────────────────────────────────────

    #[Test]
    public function view_returns_model_when_found(): void
    {
        $this->setRequest(['id' => '42']);
        $struct = new CustomPayableRateStruct();
        $this->daoStub->method('getByIdAndUser')->willReturn($struct);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with($struct);

        $this->controller->view();
    }

    #[Test]
    public function view_returns_404_when_missing(): void
    {
        $this->setRequest(['id' => '42']);
        $this->daoStub->method('getByIdAndUser')->willReturn(null);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Model not found']);

        $this->controller->view();
    }

    // ── edit ─────────────────────────────────────────────────────────────

    #[Test]
    public function edit_returns_400_when_not_json(): void
    {
        $this->setRequest(['id' => '42'], self::VALID_JSON, 'text/html');

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Bad Get']);

        $this->controller->edit();
    }

    #[Test]
    public function edit_returns_404_when_model_missing(): void
    {
        $this->setRequest(['id' => '42'], self::VALID_JSON, 'application/json');
        $this->daoStub->method('getByIdAndUser')->willReturn(null);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Model not found']);

        $this->controller->edit();
    }

    #[Test]
    public function edit_returns_struct_on_valid_payload(): void
    {
        $this->setRequest(['id' => '42'], self::VALID_JSON, 'application/json');
        $existing = new CustomPayableRateStruct();
        $edited   = new CustomPayableRateStruct();
        $this->daoStub->method('getByIdAndUser')->willReturn($existing);
        $this->daoStub->method('editFromJSON')->willReturn($edited);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with($edited);

        $this->controller->edit();
    }

    // ── schema / default / validate ──────────────────────────────────────

    #[Test]
    public function schema_returns_decoded_schema(): void
    {
        $this->setRequest();

        $response = $this->responseMock();
        $response->expects(self::once())->method('json');

        $this->controller->schema();
    }

    #[Test]
    public function default_returns_default_template(): void
    {
        $this->setRequest();
        $this->daoStub->method('getDefaultTemplate')->willReturn(['default' => true]);

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['default' => true]);

        $this->controller->default();
    }

    #[Test]
    public function validate_returns_empty_errors_for_valid_payload(): void
    {
        $this->setRequest([], self::VALID_JSON, 'application/json');

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['errors' => []]);

        $this->controller->validate();
    }

    #[Test]
    public function validate_returns_400_when_body_null(): void
    {
        $this->setRequest([], null, 'application/json');

        $response = $this->responseMock();
        $response->expects(self::once())->method('json')->with(['error' => 'Missing request body']);

        $this->controller->validate();
    }

    /**
     * @throws \TypeError
     */
    #[Test]
    public function validate_surfaces_non_json_validator_errors(): void
    {
        // Malformed JSON makes the validator collect a JsonValidatorGenericException
        // (not a JSONValidatorException); it must still be surfaced, not dropped.
        $this->setRequest([], '{invalid', 'application/json');

        $response = $this->responseMock();
        $response->expects(self::once())
            ->method('json')
            ->with(self::callback(static function (array $data): bool {
                return isset($data['errors'])
                    && count($data['errors']) === 1
                    && isset($data['errors'][0]['error'])
                    && $data['errors'][0]['error'] !== '';
            }));

        $this->controller->validate();
    }

    // ── registerValidators ───────────────────────────────────────────────

    #[Test]
    public function registerValidators_registers_login_validator(): void
    {
        $controller = new ValidatorTestablePayableRateController();
        $ref = new ReflectionClass(PayableRateController::class);

        $ref->getProperty('request')->setValue($controller, $this->createStub(Request::class));
        $ref->getProperty('response')->setValue($controller, $this->createStub(Response::class));
        $ref->getProperty('params')->setValue($controller, []);

        $ref->getMethod('registerValidators')->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);
        self::assertCount(1, $validators);
    }
}
