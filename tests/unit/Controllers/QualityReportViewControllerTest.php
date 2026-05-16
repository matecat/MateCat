<?php

namespace unit\Controllers;

use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Controller\Exceptions\RenderTerminatedException;
use Controller\Views\QualityReportController;
use Klein\DataCollection\DataCollection;
use Klein\Request;
use Klein\Response;
use Model\DataAccess\Database;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

class TestableQualityReportViewController extends QualityReportController
{
    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }

    public string $lastTemplate = '';
    /** @var array<string, mixed> */
    public array $lastViewData = [];
    public int $lastViewCode = 200;
    /** @var array<string, mixed> */
    public array $addedParams = [];

    public function setView(string $template_name, array $params = [], int $code = 200): void
    {
        $this->lastTemplate = $template_name;
        $this->lastViewData = $params;
        $this->lastViewCode = $code;
    }

    public function addParamsToView(array $params): void
    {
        $this->addedParams = array_merge($this->addedParams, $params);
    }

    public function render(?int $code = null): never
    {
        throw new RenderTerminatedException();
    }
}

#[AllowMockObjectsWithoutExpectations]
class QualityReportViewControllerTest extends AbstractTest
{
    private const int JOB_ID_VALID = 99887766;
    private const int JOB_ID_MISSING = 99999999;
    private const int PROJECT_ID = 99887765;
    private const string JOB_PASSWORD_VALID = 'testpass123';

    private ReflectionClass $reflector;
    private TestableQualityReportViewController $controller;
    /** @var Request&MockObject */
    private Request&MockObject $requestStub;

    /** @throws ReflectionException */
    protected function setUp(): void
    {
        parent::setUp();

        Database::obtain()->begin();
        $this->seedFixtures();

        $this->reflector = new ReflectionClass(TestableQualityReportViewController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $this->requestStub = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $response);

        $this->setControllerUser($this->buildUser(), true);
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

        $conn->exec("DELETE FROM jobs WHERE id = " . self::JOB_ID_VALID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::PROJECT_ID);

        $conn->exec(
            "INSERT INTO projects (id, id_customer, password, name, create_date, status_analysis)
             VALUES (" . self::PROJECT_ID . ", 'view@test.com', 'proj-pass', 'Quality Report Project', NOW(), 'DONE')"
        );

        $conn->exec(
            "INSERT INTO jobs (
                id, password, id_project, job_first_segment, job_last_segment, source, target,
                tm_keys, create_date, disabled, owner, status_owner
            ) VALUES (
                " . self::JOB_ID_VALID . ",
                '" . self::JOB_PASSWORD_VALID . "',
                " . self::PROJECT_ID . ",
                1,
                10,
                'en-US',
                'it-IT',
                '[]',
                NOW(),
                0,
                'test@test.com',
                'active'
            )"
        );
    }

    private function buildUser(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 12345;
        $user->email = 'test@test.com';
        $user->first_name = 'Test';
        $user->last_name = 'User';

        return $user;
    }

    /** @throws ReflectionException */
    private function setControllerUser(UserStruct $user, bool $isLogged): void
    {
        $authReflector = new ReflectionClass($this->controller);
        while (!$authReflector->hasProperty('user') && $authReflector->getParentClass() !== false) {
            $authReflector = $authReflector->getParentClass();
        }

        $authReflector->getProperty('user')->setValue($this->controller, $user);
        $authReflector->getProperty('userIsLogged')->setValue($this->controller, $isLogged);
    }

    private function setNamedParams(array $params): void
    {
        $paramsNamed = $this->createStub(DataCollection::class);
        $paramsNamed->method('all')->willReturn($params);
        $this->requestStub->method('paramsNamed')->willReturn($paramsNamed);
    }

    #[Test]
    public function afterConstructAppendsViewLoginRedirectValidator(): void
    {
        $realReflector = new ReflectionClass(QualityReportController::class);
        /** @var QualityReportController $realController */
        $realController = $realReflector->newInstanceWithoutConstructor();

        $request = $this->createStub(Request::class);
        $response = $this->createMock(Response::class);

        $realReflector->getProperty('request')->setValue($realController, $request);
        $realReflector->getProperty('response')->setValue($realController, $response);

        $realReflector->getMethod('afterConstruct')->invoke($realController);

        /** @var list<mixed> $validators */
        $validators = $realReflector->getProperty('validators')->getValue($realController);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(ViewLoginRedirectValidator::class, $validators[0]);
    }

    #[Test]
    public function validateTheRequestReturnsSanitizedNamedParams(): void
    {
        $this->setNamedParams([
            'jid' => '99887766abc',
            'password' => " test\npass\x01 ",
        ]);

        $method = $this->reflector->getMethod('validateTheRequest');
        $result = $method->invoke($this->controller);

        $this->assertSame('99887766', $result['jid']);
        $this->assertSame(' testpass ', $result['password']);
    }

    #[Test]
    public function searchableStatusesReturnsNonEmptyValueLabelObjects(): void
    {
        $method = $this->reflector->getMethod('searchableStatuses');
        $result = $method->invoke($this->controller);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $first = $result[0];
        $this->assertIsObject($first);
        $this->assertObjectHasProperty('value', $first);
        $this->assertObjectHasProperty('label', $first);
    }

    #[Test]
    public function renderViewSetsProjectNotFoundTemplateAnd404WhenJobDoesNotExist(): void
    {
        $this->setNamedParams([
            'jid' => (string)self::JOB_ID_MISSING,
            'password' => 'does-not-exist',
        ]);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('project_not_found.html', $this->controller->lastTemplate);
            $this->assertSame(404, $this->controller->lastViewCode);
        }
    }

    #[Test]
    public function renderViewSetsReviseSummaryTemplateWhenJobExists(): void
    {
        $previousEnv = AppConfig::$ENV;
        AppConfig::$ENV = 'testing';

        $this->setNamedParams([
            'jid' => (string)self::JOB_ID_VALID,
            'password' => self::JOB_PASSWORD_VALID,
        ]);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('revise_summary.html', $this->controller->lastTemplate);
            $this->assertSame(self::JOB_ID_VALID, $this->controller->lastViewData['jid']);
            $this->assertSame(self::JOB_PASSWORD_VALID, $this->controller->lastViewData['password']);
            $this->assertArrayHasKey('searchable_statuses', $this->controller->lastViewData);
        } finally {
            AppConfig::$ENV = $previousEnv;
        }
    }

    #[Test]
    public function renderViewAddsArchivedJobParamsWhenJobIsArchived(): void
    {
        $conn = Database::obtain()->getConnection();
        $conn->exec(
            "UPDATE jobs SET status_owner = 'archived' WHERE id = " . self::JOB_ID_VALID
        );

        $this->setNamedParams([
            'jid' => (string)self::JOB_ID_VALID,
            'password' => self::JOB_PASSWORD_VALID,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Trying to get an undefined property job_owner');

        $this->controller->renderView();
    }
}
