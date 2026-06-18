<?php

namespace Matecat\Core\Controllers;

use Controller\API\V3\XliffConfigTemplateController;
use Exception;
use Klein\DataCollection\HeaderDataCollection;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use Model\Xliff\XliffConfigTemplateDao;
use Model\Xliff\XliffConfigTemplateStruct;
use PDOException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TypeError;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;
use Utils\Validator\JSONSchema\Errors\JsonValidatorGenericException;

class TestableXliffConfigTemplateController extends XliffConfigTemplateController
{
    public function __construct()
    {
    }

    /**
     * @param string $json
     * @throws \Swaggest\JsonSchema\Exception
     */
    protected function validateJSON(string $json): void
    {
        // no-op: JSON validation is tested separately, bypass file loading
    }
}

#[AllowMockObjectsWithoutExpectations]
class XliffConfigTemplateControllerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableXliffConfigTemplateController $controller;
    /** @var Request&MockObject */
    private Request&MockObject $requestStub;
    /** @var Response&MockObject */
    private Response&MockObject $responseMock;
    /** @var XliffConfigTemplateDao&MockObject */
    private XliffConfigTemplateDao&MockObject $daoMock;

    /** @throws ReflectionException */
    protected function setUp(): void
    {
        parent::setUp();

        $this->reflector = new ReflectionClass(TestableXliffConfigTemplateController::class);
        $this->controller = new TestableXliffConfigTemplateController();

        $this->requestStub = $this->createMock(Request::class);
        $this->responseMock = $this->createMock(Response::class);

        $this->daoMock = $this->createMock(XliffConfigTemplateDao::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $parentRef = new ReflectionClass(XliffConfigTemplateController::class);
        $parentRef->getProperty('xliffConfigTemplateDao')->setValue($this->controller, $this->daoMock);

        $this->setControllerUser(99);
    }

    private const VALID_JSON = '{"name":"Test","rules":{"data_fuzzy_matches":true,"sort_parts":false}}';

    private function setControllerUser(?int $uid): void
    {
        $user = new UserStruct();
        $user->uid = $uid;

        $ref = new ReflectionClass($this->controller);
        while (!$ref->hasProperty('user') && $ref->getParentClass() !== false) {
            $ref = $ref->getParentClass();
        }

        $ref->getProperty('user')->setValue($this->controller, $user);
        $ref->getProperty('userIsLogged')->setValue($this->controller, true);
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

    // ─── all() ───────────────────────────────────────────────────────────

    #[Test]
    public function allReturnsPaginatedResults(): void
    {
        $expected = ['items' => [], 'page' => 1];

        $this->requestStub->method('param')->willReturnCallback(
            static fn (string $key) => match ($key) {
                'page' => 1,
                'perPage' => 10,
                default => null,
            }
        );

        $this->daoMock->expects($this->once())
            ->method('getAllPaginated')
            ->with(99, '/api/v3/xliff-config-template?page=', 1, 10)
            ->willReturn($expected);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($expected);

        $this->controller->all();
    }

    #[Test]
    public function allCapsPerPageAt200(): void
    {
        $this->requestStub->method('param')->willReturnCallback(
            static fn (string $key) => match ($key) {
                'page' => 1,
                'perPage' => 500,
                default => null,
            }
        );

        $this->daoMock->expects($this->once())
            ->method('getAllPaginated')
            ->with(99, '/api/v3/xliff-config-template?page=', 1, 200);

        $this->controller->all();
    }

    #[Test]
    public function allThrowsTypeErrorWhenUidIsNull(): void
    {
        $this->setControllerUser(null);

        $this->requestStub->method('param')->willReturn(null);

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('User not authenticated');

        $this->controller->all();
    }

    #[Test]
    public function allReturns500OnDaoException(): void
    {
        $this->requestStub->method('param')->willReturn(null);

        $this->daoMock->method('getAllPaginated')
            ->willThrowException(new Exception('DB error', 500));

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'DB error']);

        $this->controller->all();
    }

    // ─── get() ───────────────────────────────────────────────────────────

    #[Test]
    public function getReturnsModel(): void
    {
        $model = new XliffConfigTemplateStruct();

        $this->requestStub->method('param')->with('id')->willReturn('42');

        $this->daoMock->expects($this->once())
            ->method('getByIdAndUser')
            ->with(42, 99)
            ->willReturn($model);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($model);

        $this->controller->get();
    }

    #[Test]
    public function getReturns404WhenModelNotFound(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('999');

        $this->daoMock->method('getByIdAndUser')->willReturn(null);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(404);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Model not found']);

        $this->controller->get();
    }

    #[Test]
    public function getThrowsTypeErrorWhenUidIsNull(): void
    {
        $this->setControllerUser(null);

        $this->requestStub->method('param')->with('id')->willReturn('1');

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('User not authenticated');

        $this->controller->get();
    }

    // ─── create() ────────────────────────────────────────────────────────

    #[Test]
    public function createReturns400WhenNotJson(): void
    {
        $this->setNonJsonContentType();

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Bad Get']);

        $this->controller->create();
    }

    #[Test]
    public function createReturns400WhenBodyIsNull(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(null);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Request body is empty']);

        $this->controller->create();
    }

    #[Test]
    public function createReturns201OnSuccess(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $struct = new XliffConfigTemplateStruct();
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
    public function createReturns400OnDuplicateName(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $pdo = new PDOException("Duplicate entry", '23000');
        $this->daoMock->method('createFromJSON')->willThrowException($pdo);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(400);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Invalid unique template name']);

        $this->controller->create();
    }

    #[Test]
    public function createReturns500OnNonDuplicatePDOException(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $pdo = new PDOException("Connection lost", '08001');
        $this->daoMock->method('createFromJSON')->willThrowException($pdo);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(500);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Connection lost']);

        $this->controller->create();
    }

    #[Test]
    public function createReturns400OnJSONValidatorException(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $error = new \Swaggest\JsonSchema\Exception\Error();
        $error->error = 'validation failed';
        $exception = new JSONValidatorException($error);

        $this->daoMock->method('createFromJSON')
            ->willThrowException($exception);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(400);

        $this->controller->create();
    }

    #[Test]
    public function createReturns400OnJsonValidatorGenericException(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $exception = new JsonValidatorGenericException('Invalid schema', 400);
        $this->daoMock->method('createFromJSON')
            ->willThrowException($exception);

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

        // The TypeError is thrown before the try block (at param inference time),
        // so it propagates uncaught
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('User not authenticated');

        $this->controller->create();
    }

    // ─── update() ────────────────────────────────────────────────────────

    #[Test]
    public function updateReturns400WhenNotJson(): void
    {
        $this->setNonJsonContentType();

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Bad Get']);

        $this->controller->update();
    }

    #[Test]
    public function updateReturns400WhenBodyIsNull(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('param')->willReturn('5');
        $this->requestStub->method('body')->willReturn(null);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Request body is empty']);

        $this->controller->update();
    }

    #[Test]
    public function updateReturns404WhenModelNotFound(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('param')->willReturn('999');
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $this->daoMock->method('getByIdAndUser')->willReturn(null);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Model not found']);

        $this->controller->update();
    }

    #[Test]
    public function updateEditsExistingModel(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('param')->willReturn('5');
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $existing = new XliffConfigTemplateStruct();
        $updated = new XliffConfigTemplateStruct();

        $this->daoMock->method('getByIdAndUser')->with(5, 99)->willReturn($existing);
        $this->daoMock->expects($this->once())
            ->method('editFromJSON')
            ->willReturn($updated);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(200);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($updated);

        $this->controller->update();
    }

    #[Test]
    public function updateReturns500OnGenericException(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('param')->willReturn('5');
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $this->daoMock->method('getByIdAndUser')
            ->willThrowException(new Exception('Unexpected', 500));

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(500);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Unexpected']);

        $this->controller->update();
    }

    #[Test]
    public function updateThrowsTypeErrorWhenUidIsNull(): void
    {
        $this->setControllerUser(null);
        $this->setJsonContentType();
        $this->requestStub->method('param')->willReturn('1');

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('User not authenticated');

        $this->controller->update();
    }

    // ─── delete() ────────────────────────────────────────────────────────

    #[Test]
    public function deleteReturnsIdOnSuccess(): void
    {
        $paramsNamed = $this->createMock(\Klein\DataCollection\DataCollection::class);
        $paramsNamed->method('get')->with('id')->willReturn('42');
        $this->requestStub->method('paramsNamed')->willReturn($paramsNamed);

        $this->daoMock->expects($this->once())
            ->method('remove')
            ->with(42, 99)
            ->willReturn(1);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['id' => 42]);

        $this->controller->delete();
    }

    #[Test]
    public function deleteReturns404WhenCountIsZero(): void
    {
        $paramsNamed = $this->createMock(\Klein\DataCollection\DataCollection::class);
        $paramsNamed->method('get')->with('id')->willReturn('999');
        $this->requestStub->method('paramsNamed')->willReturn($paramsNamed);

        $this->daoMock->method('remove')->willReturn(0);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with(['error' => 'Model not found']);

        $this->controller->delete();
    }

    #[Test]
    public function deleteThrowsTypeErrorWhenUidIsNull(): void
    {
        $this->setControllerUser(null);

        $paramsNamed = $this->createMock(\Klein\DataCollection\DataCollection::class);
        $paramsNamed->method('get')->with('id')->willReturn('1');
        $this->requestStub->method('paramsNamed')->willReturn($paramsNamed);

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('User not authenticated');

        $this->controller->delete();
    }

    // ─── schema() ────────────────────────────────────────────────────────

    #[Test]
    public function schemaReturnsJsonResponse(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->isInstanceOf(\stdClass::class));

        $this->controller->schema();
    }
}