<?php

namespace Matecat\Core\Controllers;

/*
 * Reserved ID block (Playbook §4): base = 9063000
 *   project = 9063001, job = 9063002, segment = 9063003, file = 9063004,
 *   user = 9063006. activity_log row id = 9063020 (outside fragment offsets).
 * Per-suite owner email: ctrltest_9063000@example.org
 * Clean ONLY by reserved id; never by shared keys.
 */

use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Controller\API\Commons\Validators\ProjectPasswordValidator;
use Controller\Exceptions\RenderTerminatedException;
use Controller\Views\ActivityLogController;
use Klein\DataCollection\DataCollection;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;

class TestableActivityLogViewController extends ActivityLogController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }

    public string $lastTemplate = '';
    /** @var array<string, mixed> */
    public array $lastViewData = [];
    public int $lastViewCode = 200;

    public function setView(string $template_name, array $params = [], int $code = 200): void
    {
        $this->lastTemplate = $template_name;
        $this->lastViewData = $params;
        $this->lastViewCode = $code;
    }

    /**
     * @throws RenderTerminatedException
     */
    public function render(?int $code = null): never
    {
        throw new RenderTerminatedException();
    }
}

#[AllowMockObjectsWithoutExpectations]
class ActivityLogViewControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9063000;
    private const int ACTIVITY_ID = 9063020;
    private const string PROJECT_PASSWORD = 'projpw';

    /** @var ReflectionClass<TestableActivityLogViewController> */
    private ReflectionClass $reflector;
    private TestableActivityLogViewController $controller;
    /** @var Request&MockObject */
    private Request&MockObject $requestStub;

    /**
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();
        $this->seedTestData();

        $this->reflector = new ReflectionClass(TestableActivityLogViewController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $this->requestStub = $this->createMock(Request::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->createMock(Response::class));
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->reflector->getProperty('user')->setValue($this->controller, $user);
    }

    /**
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    /**
     * @throws \Throwable
     */
    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);

        $this->seedUser(self::BASE);
        $this->seedProject(self::BASE, $owner, self::PROJECT_PASSWORD);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner);
        $this->seedSegment(self::BASE);
    }

    /**
     * @throws \PDOException
     */
    private function seedActivityLog(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec(
            "INSERT IGNORE INTO activity_log (ID, id_project, id_job, action, ip, uid, event_date) VALUES ("
            . self::ACTIVITY_ID . ", " . $this->projectId(self::BASE) . ", " . $this->jobId(self::BASE)
            . ", 14, '127.0.0.1', " . $this->userId(self::BASE) . ", NOW())"
        );
    }

    /**
     * @throws \PDOException
     */
    private function cleanTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("DELETE FROM activity_log WHERE ID = " . self::ACTIVITY_ID);
        $this->cleanFragments(self::BASE);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws \Throwable
     */
    private function setNamedParams(array $params): void
    {
        $paramsNamed = $this->createStub(DataCollection::class);
        $paramsNamed->method('all')->willReturn($params);
        $this->requestStub->method('paramsNamed')->willReturn($paramsNamed);
    }

    // ─── validateTheRequest ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function validateTheRequestReturnsSanitizedNamedParams(): void
    {
        $this->setNamedParams([
            'id_project' => '9063001abc',
            'password' => " test\npass\x01 ",
        ]);

        $method = $this->reflector->getMethod('validateTheRequest');
        $result = $method->invoke($this->controller);

        $this->assertIsArray($result);
        $this->assertSame('9063001', $result['id_project']);
        $this->assertSame(' testpass ', $result['password']);
    }

    // ─── renderView ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function renderViewSetsActivityLogTemplateWhenLogExists(): void
    {
        $this->seedActivityLog();

        $this->setNamedParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'password' => self::PROJECT_PASSWORD,
        ]);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('activity_log.html', $this->controller->lastTemplate);
            $this->assertSame((string) $this->projectId(self::BASE), $this->controller->lastViewData['project_id']);
            $this->assertSame(self::PROJECT_PASSWORD, $this->controller->lastViewData['password']);
        }
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function renderViewSetsNotFoundTemplateWhenNoActivityLog(): void
    {
        $this->setNamedParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'password' => self::PROJECT_PASSWORD,
        ]);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('activity_log_not_found.html', $this->controller->lastTemplate);
            $this->assertSame((string) $this->projectId(self::BASE), $this->controller->lastViewData['projectID']);
        }
    }

    // ─── registerValidators (production hook) ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function registerValidatorsAppendsViewLoginRedirectAndProjectPasswordValidators(): void
    {
        $realReflector = new ReflectionClass(ActivityLogController::class);
        /** @var ActivityLogController $realController */
        $realController = $realReflector->newInstanceWithoutConstructor();

        $realReflector->getProperty('request')->setValue($realController, $this->createMock(Request::class));
        $realReflector->getProperty('response')->setValue($realController, $this->createMock(Response::class));
        $realController->params = ['id_project' => (string) $this->projectId(self::BASE), 'password' => self::PROJECT_PASSWORD];

        $realReflector->getMethod('registerValidators')->invoke($realController);

        /** @var list<mixed> $validators */
        $validators = $realReflector->getProperty('validators')->getValue($realController);
        $this->assertCount(2, $validators);
        $this->assertInstanceOf(ViewLoginRedirectValidator::class, $validators[0]);
        $this->assertInstanceOf(ProjectPasswordValidator::class, $validators[1]);
    }
}
