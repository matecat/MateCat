<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\NotFoundException;
use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\V3\ProjectTemplateController;
use Exception;
use Klein\DataCollection\HeaderDataCollection;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Projects\ProjectTemplateDao;
use Model\Projects\ProjectTemplateStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TypeError;

class TestableProjectTemplateController extends ProjectTemplateController
{
    public function __construct()
    {
    }

}

#[AllowMockObjectsWithoutExpectations]
class ProjectTemplateControllerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableProjectTemplateController $controller;
    /** @var Request&MockObject */
    private Request&MockObject $requestStub;
    /** @var Response&MockObject */
    private Response&MockObject $responseMock;
    /** @var ProjectTemplateDao&MockObject */
    private ProjectTemplateDao&MockObject $daoMock;

    /** @throws ReflectionException */
    protected function setUp(): void
    {
        parent::setUp();

        $this->reflector = new ReflectionClass(TestableProjectTemplateController::class);
        $this->controller = new TestableProjectTemplateController();

        $this->requestStub = $this->createMock(Request::class);
        $this->responseMock = $this->createMock(Response::class);
        $this->responseMock->method('json')->willReturnSelf();
        $this->responseMock->method('code')->willReturnSelf();

        $this->daoMock = $this->createMock(ProjectTemplateDao::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $parentRef = new ReflectionClass(ProjectTemplateController::class);
        $parentRef->getProperty('projectTemplateDao')->setValue($this->controller, $this->daoMock);

        $this->setControllerUser(99);
    }

    private const VALID_JSON = '{"name":"Test","id_team":1,"pretranslate_100":true,"get_public_matches":true}';

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

    #[Test]
    public function allReturnsPaginatedResults(): void
    {
        $expected = ['items' => [], 'page' => 1];

        $this->requestStub->method('param')->willReturnCallback(
            static fn(string $key) => match ($key) {
                'page' => 1,
                'perPage' => 10,
                default => null,
            }
        );

        $this->daoMock->expects($this->once())
            ->method('getAllPaginated')
            ->with(99, '/api/v3/project-template?page=', 1, 10)
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
            static fn(string $key) => match ($key) {
                'page' => 1,
                'perPage' => 500,
                default => null,
            }
        );

        $this->daoMock->expects($this->once())
            ->method('getAllPaginated')
            ->with(99, '/api/v3/project-template?page=', 1, 200);

        $this->controller->all();
    }

    #[Test]
    public function allThrowsWhenUidIsNull(): void
    {
        $this->setControllerUser(null);

        $this->requestStub->method('param')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User UID must not be null');

        $this->controller->all();
    }

    #[Test]
    public function getReturnsModel(): void
    {
        $model = new ProjectTemplateStruct();

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
    public function getThrowsWhenModelNotFound(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('999');

        $this->daoMock->method('getByIdAndUser')->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Model not found');
        $this->expectExceptionCode(404);

        $this->controller->get();
    }

    #[Test]
    public function getThrowsWhenUidIsNull(): void
    {
        $this->setControllerUser(null);

        $this->requestStub->method('param')->with('id')->willReturn('1');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User UID must not be null');

        $this->controller->get();
    }

    #[Test]
    public function createThrowsValidationErrorWhenNotJson(): void
    {
        $this->setNonJsonContentType();

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Bad Request');

        $this->controller->create();
    }

    #[Test]
    public function createThrowsValidationErrorWhenBodyIsNull(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(null);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Request body is empty');

        $this->controller->create();
    }

    #[Test]
    public function createSets201OnSuccess(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $struct = new ProjectTemplateStruct();
        $this->daoMock->method('createFromJSON')->willReturn($struct);

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(201);

        $this->controller->create();
    }

    #[Test]
    public function createThrowsValidationErrorOnDuplicateName(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $pdo = new \PDOException("Duplicate entry", '23000');
        $this->daoMock->method('createFromJSON')->willThrowException($pdo);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid unique template name');

        $this->controller->create();
    }

    #[Test]
    public function createRethrowsNonDuplicatePDOException(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $pdo = new \PDOException("Connection lost", '08001');
        $this->daoMock->method('createFromJSON')->willThrowException($pdo);

        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('Connection lost');

        $this->controller->create();
    }

    #[Test]
    public function updateThrowsValidationErrorWhenNotJson(): void
    {
        $this->setNonJsonContentType();

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Bad Request');

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

    #[Test]
    public function updateThrowsValidationErrorWhenBodyIsNull(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('param')->willReturn('5');
        $this->requestStub->method('body')->willReturn(null);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Request body is empty');

        $this->controller->update();
    }

    #[Test]
    public function updateWithIdZeroMarksAllAsNotDefault(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('param')->willReturn('0');
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $defaultTemplate = new ProjectTemplateStruct();

        $this->daoMock->expects($this->once())
            ->method('markAsNotDefault')
            ->with(99, 0);

        $this->daoMock->expects($this->once())
            ->method('getDefaultTemplate')
            ->with(99)
            ->willReturn($defaultTemplate);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($defaultTemplate);

        $this->controller->update();
    }

    #[Test]
    public function updateThrowsNotFoundWhenModelMissing(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('param')->willReturn('999');
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $this->daoMock->method('getByIdAndUser')->willReturn(null);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Model not found');

        $this->controller->update();
    }

    #[Test]
    public function updateEditsExistingModel(): void
    {
        $this->setJsonContentType();
        $this->requestStub->method('param')->willReturn('5');
        $this->requestStub->method('body')->willReturn(self::VALID_JSON);

        $existing = new ProjectTemplateStruct();
        $updated = new ProjectTemplateStruct();

        $this->daoMock->method('getByIdAndUser')->with(5, 99)->willReturn($existing);
        $this->daoMock->expects($this->once())
            ->method('editFromJSON')
            ->willReturn($updated);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($updated);

        $this->controller->update();
    }

    #[Test]
    public function deleteReturnsIdOnSuccess(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('42');

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
    public function deleteThrowsNotFoundWhenCountIsZero(): void
    {
        $this->requestStub->method('param')->with('id')->willReturn('999');

        $this->daoMock->method('remove')->willReturn(0);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Model not found');

        $this->controller->delete();
    }

    #[Test]
    public function deleteThrowsWhenUidIsNull(): void
    {
        $this->setControllerUser(null);

        $this->requestStub->method('param')->with('id')->willReturn('1');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User UID must not be null');

        $this->controller->delete();
    }

    #[Test]
    public function defaultReturnsDefaultTemplate(): void
    {
        $template = new ProjectTemplateStruct();

        $this->daoMock->expects($this->once())
            ->method('getDefaultTemplate')
            ->with(99)
            ->willReturn($template);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($template);

        $this->controller->default();
    }

    #[Test]
    public function defaultThrowsTypeErrorWhenUidIsNull(): void
    {
        $this->setControllerUser(null);

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('User not authenticated');

        $this->controller->default();
    }

    #[Test]
    public function schemaReturnsJsonResponse(): void
    {
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->isInstanceOf(\stdClass::class));

        $this->controller->schema();
    }
}
