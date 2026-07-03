<?php

namespace Matecat\Core\Controllers;

use Controller\API\V3\QAModelTemplateController;
use Exception;
use Klein\DataCollection\HeaderDataCollection;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\LQA\QAModelTemplate\QAModelTemplateDao;
use Model\LQA\QAModelTemplate\QAModelTemplateStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Swaggest\JsonSchema\InvalidValue;
use TypeError;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;

/**
 * Testable subclass: bypasses ctor wiring so we can inject mocks via reflection.
 */
class TestableQAModelTemplateController extends QAModelTemplateController
{
    public function __construct()
    {
        // do NOT call parent – ctor wires Klein + DB + session
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }

    public function refreshClientSessionIfNotApi(): void
    {
    }
}

/**
 * Variant whose getQaModelSchema() returns a non-JSON string, causing
 * JSONValidator ctor to throw RuntimeException — exercises the
 * catch(Exception) branch (lines 273-278) inside validate().
 */
class TestableQAModelTemplateControllerBrokenSchema extends QAModelTemplateController
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

    public function refreshClientSessionIfNotApi(): void
    {
    }

    /**
     * Returns invalid JSON — JSONValidator::getValidJSONSchema() throws RuntimeException.
     */
    protected function getBrokenSchemaForTest(): string
    {
        return 'NOT VALID JSON {{{';
    }
}

#[AllowMockObjectsWithoutExpectations]
class QAModelTemplateControllerTest extends AbstractTest
{
    /**
     * Reserved ID block: base = 9_946_000
     * No DB rows are seeded (all DAO calls are mocked).
     */

    private ReflectionClass $reflector;
    private TestableQAModelTemplateController $controller;

    /** @var Request&MockObject */
    private Request&MockObject $requestStub;

    /** @var Response&MockObject */
    private Response&MockObject $responseMock;

    /** @var QAModelTemplateDao&MockObject */
    private QAModelTemplateDao&MockObject $daoMock;

    /**
     * A minimal valid qa_model JSON that satisfies qa_model.json schema.
     */
    private const string VALID_JSON = '{"model":{"version":1,"label":"Test Template","categories":[{"code":"ACC","label":"Accuracy","severities":[{"code":"MIN","label":"Minor","penalty":1}]}],"passfail":{"type":"points_per_thousand","thresholds":[{"label":"R1","value":0},{"label":"R2","value":10}]}}}';

    /** @throws ReflectionException */
    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new TestableQAModelTemplateController();
        $this->reflector  = new ReflectionClass(QAModelTemplateController::class);

        $this->requestStub = $this->createMock(Request::class);
        $this->responseMock = $this->createMock(Response::class);
        $this->daoMock = $this->createMock(QAModelTemplateDao::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        // Inject the mocked DAO via the private property
        $this->reflector->getProperty('qaModelTemplateDao')->setValue($this->controller, $this->daoMock);

        $this->setControllerUser(42);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function setControllerUser(?int $uid): void
    {
        $user      = new UserStruct();
        $user->uid = $uid;

        // Walk up the hierarchy to find where 'user' and 'userIsLogged' live
        $ref = new ReflectionClass($this->controller);
        while ($ref !== false) {
            if ($ref->hasProperty('user')) {
                $ref->getProperty('user')->setValue($this->controller, $user);
            }
            if ($ref->hasProperty('userIsLogged')) {
                $ref->getProperty('userIsLogged')->setValue($this->controller, true);
            }
            $ref = $ref->getParentClass();
        }
    }

    private function setJsonContentType(): void
    {
        $headers = $this->createMock(HeaderDataCollection::class);
        $headers->method('get')->with('Content-Type')->willReturn('application/json');
        $this->requestStub->method('headers')->willReturn($headers);
    }

    private function setNonJsonContentType(): void
    {
        $headers = $this->createMock(HeaderDataCollection::class);
        $headers->method('get')->with('Content-Type')->willReturn('text/plain');
        $this->requestStub->method('headers')->willReturn($headers);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // index()
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function indexReturnsPaginatedResults(): void
    {
        $expected = ['items' => [], 'page' => 1, 'total' => 0];

        $this->requestStub->method('param')->willReturnCallback(
            static fn(string $key) => match ($key) {
                'page'    => 1,
                'perPage' => 20,
                default   => null,
            }
        );

        $this->daoMock->expects($this->once())
            ->method('getAllPaginated')
            ->with(42, '/api/v3/qa_model_template?page=', 1, 20)
            ->willReturn($expected);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($expected);

        $this->controller->index();
    }

    #[Test]
    public function indexCapsPerPageAt200(): void
    {
        $this->requestStub->method('param')->willReturnCallback(
            static fn(string $key) => match ($key) {
                'page'    => 1,
                'perPage' => 999,
                default   => null,
            }
        );

        $this->daoMock->expects($this->once())
            ->method('getAllPaginated')
            ->with(42, '/api/v3/qa_model_template?page=', 1, 200)
            ->willReturn([]);

        $this->controller->index();
    }

    #[Test]
    public function indexMinimumPageIsOne(): void
    {
        $this->requestStub->method('param')->willReturnCallback(
            static fn(string $key) => match ($key) {
                'page'    => -5,
                'perPage' => 0,
                default   => null,
            }
        );

        $this->daoMock->expects($this->once())
            ->method('getAllPaginated')
            ->with(42, '/api/v3/qa_model_template?page=', 1, 1)
            ->willReturn([]);

        $this->controller->index();
    }

    #[Test]
    public function indexReturns500OnDaoException(): void
    {
        $this->requestStub->method('param')->willReturn(null);

        $this->daoMock->method('getAllPaginated')
            ->willThrowException(new Exception('DB error', 500));

        $statusMock = $this->createMock(\Klein\HttpStatus::class);
        $statusMock->expects($this->once())->method('setCode')->with(500);
        $this->responseMock->method('status')->willReturn($statusMock);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'DB error']);

        $this->controller->index();
    }

    #[Test]
    public function indexReturnsCustomCodeOnCodedDaoException(): void
    {
        $this->requestStub->method('param')->willReturn(null);

        $this->daoMock->method('getAllPaginated')
            ->willThrowException(new Exception('Not found', 404));

        $statusMock = $this->createMock(\Klein\HttpStatus::class);
        $statusMock->expects($this->once())->method('setCode')->with(404);
        $this->responseMock->method('status')->willReturn($statusMock);

        $this->controller->index();
    }

    #[Test]
    public function indexThrowsTypeErrorWhenUidIsNull(): void
    {
        $this->setControllerUser(null);
        $this->requestStub->method('param')->willReturn(null);

        $this->expectException(TypeError::class);

        $this->controller->index();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // create()
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function createReturns405WhenNotJsonRequest(): void
    {
        $this->setNonJsonContentType();

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(405);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Method not allowed']);

        $this->controller->create();
    }

    #[Test]
    public function createReturns201OnSuccess(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $struct = new QAModelTemplateStruct();
        $this->daoMock->method('createFromJSON')->willReturn($struct);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(201);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($struct);

        $this->controller->create();
    }

    #[Test]
    public function createReturns400OnJSONValidatorException(): void
    {
        $this->setJsonContentType();

        // Body is invalid JSON that will fail schema validation
        $this->requestStub->method('body')->willReturn('{"model":{}}');

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(400);

        $this->controller->create();
    }

    #[Test]
    public function createReturns400OnJsonValidatorGenericExceptionThrownByDao(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $exception = new JsonValidatorGenericException('Generic schema error', 400);
        $this->daoMock->method('createFromJSON')->willThrowException($exception);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(400);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Generic schema error']);

        $this->controller->create();
    }

    #[Test]
    public function createReturns500OnGenericException(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $this->daoMock->method('createFromJSON')
            ->willThrowException(new Exception('Unexpected error'));

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(500);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Unexpected error']);

        $this->controller->create();
    }

    #[Test]
    public function createReturns400OnCodedHttpException(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $this->daoMock->method('createFromJSON')
            ->willThrowException(new Exception('Bad request data', 400));

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(400);

        $this->controller->create();
    }

    #[Test]
    public function createThrowsTypeErrorWhenUidIsNull(): void
    {
        $this->setControllerUser(null);
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $this->expectException(TypeError::class);

        $this->controller->create();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // delete()
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function deleteReturnsIdOnSuccess(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('99');

        $this->daoMock->expects($this->once())
            ->method('remove')
            ->with(99, 42)
            ->willReturn(1);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['id' => 99]);

        $this->controller->delete();
    }

    #[Test]
    public function deleteReturns404WhenModelNotFound(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('999');

        $this->daoMock->method('remove')->willReturn(0);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(404);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Model not found']);

        $this->controller->delete();
    }

    #[Test]
    public function deleteReturns500OnDaoException(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('1');

        $this->daoMock->method('remove')
            ->willThrowException(new Exception('DB failure'));

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(500);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'DB failure']);

        $this->controller->delete();
    }

    #[Test]
    public function deleteThrowsTypeErrorWhenUidIsNull(): void
    {
        $this->setControllerUser(null);
        $this->requestStub->method('param')->with('id')->willReturn('1');

        $this->expectException(TypeError::class);

        $this->controller->delete();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // edit()
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function editReturnsUpdatedModelOnSuccess(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('7');
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $existing = new QAModelTemplateStruct();
        $updated  = new QAModelTemplateStruct();

        $this->daoMock->method('get')
            ->with(['id' => 7, 'uid' => 42])
            ->willReturn($existing);

        $this->daoMock->expects($this->once())
            ->method('editFromJSON')
            ->with($existing, self::VALID_JSON)
            ->willReturn($updated);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(200);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($updated);

        $this->controller->edit();
    }

    #[Test]
    public function editReturns404WhenModelNotFound(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('999');

        $this->daoMock->method('get')->willReturn(null);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(404);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Model not found']);

        $this->controller->edit();
    }

    #[Test]
    public function editReturns400OnInvalidJSON(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('7');

        $existing = new QAModelTemplateStruct();
        $this->daoMock->method('get')->willReturn($existing);

        // Body is invalid against schema → validateJSON throws JSONValidatorException
        $this->requestStub->method('body')->willReturn('{"model":{}}');

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(400);

        $this->controller->edit();
    }

    #[Test]
    public function editReturns400OnJsonValidatorGenericExceptionThrownByDao(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('7');
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $existing = new QAModelTemplateStruct();
        $this->daoMock->method('get')->willReturn($existing);

        $exception = new JsonValidatorGenericException('Bad schema', 400);
        $this->daoMock->method('editFromJSON')->willThrowException($exception);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(400);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Bad schema']);

        $this->controller->edit();
    }

    #[Test]
    public function editReturns500OnGenericException(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('7');
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $existing = new QAModelTemplateStruct();
        $this->daoMock->method('get')->willReturn($existing);

        $this->daoMock->method('editFromJSON')
            ->willThrowException(new Exception('Unexpected'));

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(500);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Unexpected']);

        $this->controller->edit();
    }

    #[Test]
    public function editThrowsTypeErrorWhenUidIsNull(): void
    {
        $this->setControllerUser(null);
        $this->requestStub->method('param')->with('id')->willReturn('1');

        $this->expectException(TypeError::class);

        $this->controller->edit();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // view()
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function viewReturnsModel(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('5');

        $struct = new QAModelTemplateStruct();
        $this->daoMock->method('get')
            ->with(['id' => 5, 'uid' => 42])
            ->willReturn($struct);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(200);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($struct);

        $this->controller->view();
    }

    #[Test]
    public function viewReturns404WhenModelNotFound(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('999');

        $this->daoMock->method('get')->willReturn(null);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(404);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Model not found']);

        $this->controller->view();
    }

    #[Test]
    public function viewReturns500OnDaoException(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('1');

        $this->daoMock->method('get')
            ->willThrowException(new Exception('DB error'));

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(500);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'DB error']);

        $this->controller->view();
    }

    #[Test]
    public function viewThrowsTypeErrorWhenUidIsNull(): void
    {
        $this->setControllerUser(null);
        $this->requestStub->method('param')->with('id')->willReturn('1');

        $this->expectException(TypeError::class);

        $this->controller->view();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // schema()
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function schemaReturnsDecodedJsonSchema(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->isInstanceOf(\stdClass::class));

        $this->controller->schema();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // validate()
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function validateReturns200WithNoErrorsForValidJson(): void
    {
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(200);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['errors' => []]);

        $this->controller->validate();
    }

    #[Test]
    public function validateReturns500WithErrorsForInvalidJson(): void
    {
        // Missing required fields → schema validation fails
        $this->requestStub->method('body')->willReturn('{"model":{}}');

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(500);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $resp): bool {
                return isset($resp['errors']) && is_array($resp['errors']) && count($resp['errors']) > 0;
            }));

        $this->controller->validate();
    }

    #[Test]
    public function validateReturns500WithErrorMessageForMalformedJson(): void
    {
        // Malformed JSON: json_decode throws \JsonException inside schemaContract->in(),
        // which JSONValidator catches and stores as JsonValidatorGenericException.
        // The foreach then hits line 267 ($error->getMessage()).
        $this->requestStub->method('body')->willReturn('{not-valid-json');

        // code(500) is called (isValid() == false); no expectation count limit needed
        $this->responseMock->method('code')->with(500);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $r): bool {
                return isset($r['errors']) && is_array($r['errors']) && count($r['errors']) > 0;
            }));

        $this->controller->validate();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // default()
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function defaultReturnsDefaultTemplate(): void
    {
        $expected = ['id' => 1, 'label' => 'Default', 'version' => 1];

        $this->daoMock->expects($this->once())
            ->method('getDefaultTemplate')
            ->with(42)
            ->willReturn($expected);

        $statusMock = $this->createMock(\Klein\HttpStatus::class);
        $statusMock->expects($this->once())->method('setCode')->with(200);
        $this->responseMock->method('status')->willReturn($statusMock);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($expected);

        $this->controller->default();
    }

    #[Test]
    public function defaultThrowsTypeErrorWhenUidIsNull(): void
    {
        $this->setControllerUser(null);

        $this->expectException(TypeError::class);

        $this->controller->default();
    }
}
