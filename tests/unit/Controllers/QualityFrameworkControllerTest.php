<?php

namespace unit\Controllers;

use Controller\API\App\QualityFrameworkController;
use Controller\API\Commons\Validators\LoginValidator;
use Klein\Request;
use Klein\Response;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TestHelpers\AbstractTest;

class TestableQualityFrameworkController extends QualityFrameworkController
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class QualityFrameworkControllerTest extends AbstractTest
{
    private const int PROJECT_WITHOUT_MODEL = 991101;
    private const int PROJECT_WITH_MISSING_MODEL = 991102;
    private const int PROJECT_WITH_VALID_MODEL = 991103;
    private const int PROJECT_WITH_TEMPLATE_MODEL = 991105;
    private const int VALID_MODEL_ID = 991104;
    private const int TEMPLATE_MODEL_ID = 991106;

    private const string PASSWORD_WITHOUT_MODEL = 'pw991101';
    private const string PASSWORD_WITH_MISSING_MODEL = 'pw991102';
    private const string PASSWORD_WITH_VALID_MODEL = 'pw991103';
    private const string PASSWORD_WITH_TEMPLATE_MODEL = 'pw991105';

    private ReflectionClass $reflector;
    private TestableQualityFrameworkController $controller;
    /** @var Request&MockObject */
    private Request&MockObject $requestStub;
    /** @var Response&MockObject */
    private Response&MockObject $responseMock;

    /** @throws ReflectionException */
    protected function setUp(): void
    {
        parent::setUp();

        Database::obtain()->begin();

        $this->reflector = new ReflectionClass(TestableQualityFrameworkController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $this->requestStub = $this->createMock(Request::class);
        $this->responseMock = $this->createMock(Response::class);
        $this->responseMock->method('json')->willReturnSelf();

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $this->seedFixtures();
        $this->setControllerUser(99999, true);
    }

    protected function tearDown(): void
    {
        $conn = Database::obtain()->getConnection();
        if ($conn->inTransaction()) {
            Database::obtain()->rollback();
        }

        parent::tearDown();
    }

    private function seedFixtures(): void
    {
        $conn = Database::obtain()->getConnection();

        $conn->exec(
            "DELETE FROM projects WHERE id IN ("
            . self::PROJECT_WITHOUT_MODEL . ", "
            . self::PROJECT_WITH_MISSING_MODEL . ", "
            . self::PROJECT_WITH_VALID_MODEL . ", "
            . self::PROJECT_WITH_TEMPLATE_MODEL . ")"
        );
        $conn->exec(
            "DELETE FROM qa_models WHERE id IN (" . self::VALID_MODEL_ID . ", " . self::TEMPLATE_MODEL_ID . ")"
        );

        $conn->exec(
            "INSERT INTO projects (id, password, id_customer, name, create_date, status_analysis, id_qa_model)
             VALUES (" . self::PROJECT_WITHOUT_MODEL . ", '" . self::PASSWORD_WITHOUT_MODEL . "', 'quality@test.com', 'QF no model', NOW(), 'DONE', NULL)"
        );

        $conn->exec(
            "INSERT INTO projects (id, password, id_customer, name, create_date, status_analysis, id_qa_model)
             VALUES (" . self::PROJECT_WITH_MISSING_MODEL . ", '" . self::PASSWORD_WITH_MISSING_MODEL . "', 'quality@test.com', 'QF missing model', NOW(), 'DONE', 99999991)"
        );

        $conn->exec(
            "INSERT INTO qa_models (id, uid, label, pass_type, pass_options, hash)
             VALUES (" . self::VALID_MODEL_ID . ", 99999, 'Test QA model', 'error', '{}', 12345)"
        );

        $conn->exec(
            "INSERT INTO projects (id, password, id_customer, name, create_date, status_analysis, id_qa_model)
             VALUES (" . self::PROJECT_WITH_VALID_MODEL . ", '" . self::PASSWORD_WITH_VALID_MODEL . "', 'quality@test.com', 'QF valid model', NOW(), 'DONE', " . self::VALID_MODEL_ID . ")"
        );

        $conn->exec(
            "INSERT INTO qa_models (id, uid, label, pass_type, pass_options, hash, qa_model_template_id)
             VALUES (" . self::TEMPLATE_MODEL_ID . ", 99999, 'Test QA model with template', 'error', '{}', 12346, 7)"
        );

        $conn->exec(
            "INSERT INTO projects (id, password, id_customer, name, create_date, status_analysis, id_qa_model)
             VALUES (" . self::PROJECT_WITH_TEMPLATE_MODEL . ", '" . self::PASSWORD_WITH_TEMPLATE_MODEL . "', 'quality@test.com', 'QF template model', NOW(), 'DONE', " . self::TEMPLATE_MODEL_ID . ")"
        );
    }

    /** @throws ReflectionException */
    private function setControllerUser(?int $uid, bool $isLogged): void
    {
        $user = new UserStruct();
        $user->uid = $uid;

        $authReflector = new ReflectionClass($this->controller);
        while (!$authReflector->hasProperty('user') && $authReflector->getParentClass() !== false) {
            $authReflector = $authReflector->getParentClass();
        }

        $authReflector->getProperty('user')->setValue($this->controller, $user);
        $authReflector->getProperty('userIsLogged')->setValue($this->controller, $isLogged);
    }

    private function setRequestParams(int $projectId, string $password): void
    {
        $this->requestStub->method('param')->willReturnCallback(
            static fn(string $key) => match ($key) {
                'id_project' => $projectId,
                'password' => $password,
                default => null,
            }
        );
    }

    #[Test]
    public function afterConstructAppendsLoginValidator(): void
    {
        $realReflector = new ReflectionClass(QualityFrameworkController::class);
        /** @var QualityFrameworkController $realController */
        $realController = $realReflector->newInstanceWithoutConstructor();

        $request = $this->createStub(Request::class);
        $response = $this->createMock(Response::class);

        $realReflector->getProperty('request')->setValue($realController, $request);
        $realReflector->getProperty('response')->setValue($realController, $response);

        $afterConstruct = $realReflector->getMethod('afterConstruct');
        $afterConstruct->invoke($realController);

        /** @var list<mixed> $validators */
        $validators = $realReflector->getProperty('validators')->getValue($realController);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }

    #[Test]
    public function projectThrowsNotFoundWhenProjectHasNoQaModelId(): void
    {
        $this->setRequestParams(self::PROJECT_WITHOUT_MODEL, self::PASSWORD_WITHOUT_MODEL);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('QAModel not found');

        $this->controller->project();
    }

    #[Test]
    public function projectThrowsNotFoundWhenQaModelDoesNotExist(): void
    {
        $this->setRequestParams(self::PROJECT_WITH_MISSING_MODEL, self::PASSWORD_WITH_MISSING_MODEL);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('QAModel not found');

        $this->controller->project();
    }

    #[Test]
    public function projectReturnsDecodedQualityFrameworkWhenProjectAndModelAreValid(): void
    {
        $this->setRequestParams(self::PROJECT_WITH_VALID_MODEL, self::PASSWORD_WITH_VALID_MODEL);

        $this->responseMock
            ->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $payload): bool {
                $this->assertArrayHasKey('model', $payload);
                $this->assertIsArray($payload['model']);
                $this->assertSame(self::VALID_MODEL_ID, $payload['model']['id']);
                $this->assertSame('Test QA model', $payload['model']['label']);
                $this->assertArrayHasKey('template_model', $payload);
                $this->assertNull($payload['template_model']);

                return true;
            }));

        $this->controller->project();
    }

    #[Test]
    public function projectThrowsTypeErrorWhenModelHasTemplateAndUserUidIsNull(): void
    {
        $this->setControllerUser(null, true);
        $this->setRequestParams(self::PROJECT_WITH_TEMPLATE_MODEL, self::PASSWORD_WITH_TEMPLATE_MODEL);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('User not authenticated');

        $this->controller->project();
    }
}
