<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Controller\Exceptions\RenderTerminatedException;
use Controller\Views\AnalyzeController;
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
use Utils\Registry\AppConfig;

/**
 * Real-DB view-controller suite (Playbook §3) for {@see AnalyzeController}.
 *
 * Reserved ID block base = 9_062_000 (Wave 9 task N=62).
 *   base+1 project, base+2 job, base+3 segment, base+4 file.
 * Owner email: ctrltest_9062000@example.org (per-suite unique, §4).
 * Clean ONLY by reserved id.
 */
class TestableAnalyzeViewController extends AnalyzeController
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

    public string $lastTemplate = '';
    /** @var array<string, mixed> */
    public array $lastViewData = [];
    public int $lastViewCode = 200;
    /** @var array<string, mixed> */
    public array $addedParams = [];
    public bool $rendered = false;

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
        $this->rendered = true;
        throw new RenderTerminatedException();
    }

    public function isLoggedIn(): bool
    {
        return true;
    }
}

#[AllowMockObjectsWithoutExpectations]
class AnalyzeViewControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_062_000;
    private const string JOB_PASSWORD = 'jobpw';
    private const string PROJECT_PASSWORD = 'projpw';

    /** @var ReflectionClass<TestableAnalyzeViewController> */
    private ReflectionClass $reflector;
    private TestableAnalyzeViewController $controller;
    /** @var Request&MockObject */
    private Request&MockObject $requestStub;

    /** @throws ReflectionException */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedFixtures();

        $this->reflector = new ReflectionClass(TestableAnalyzeViewController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $this->requestStub = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

        $this->prop('request')->setValue($this->controller, $this->requestStub);
        $this->prop('response')->setValue($this->controller, $response);
        $this->prop('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->prop('featureSet')->setValue($this->controller, new FeatureSet());

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->prop('user')->setValue($this->controller, $user);
        $this->prop('userIsLogged')->setValue($this->controller, true);
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private function seedFixtures(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedProject(self::BASE, $owner, self::PROJECT_PASSWORD);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);

        // AbstractStatus reads jobs.subject as a LanguageDomains hashmap key.
        $conn = Database::obtain()->getConnection();
        $conn->exec("UPDATE jobs SET subject = 'general', payable_rates = '{}' WHERE id = " . $this->jobId(self::BASE));
    }

    /** @throws ReflectionException */
    private function prop(string $name): \ReflectionProperty
    {
        $r = new ReflectionClass($this->controller);
        while (!$r->hasProperty($name) && $r->getParentClass() !== false) {
            $r = $r->getParentClass();
        }

        return $r->getProperty($name);
    }

    /** @param array<string, mixed> $params */
    private function setNamedParams(array $params): void
    {
        $paramsNamed = $this->createStub(DataCollection::class);
        $paramsNamed->method('all')->willReturn($params);
        $this->requestStub->method('paramsNamed')->willReturn($paramsNamed);
    }

    // ─── registerValidators ───

    #[Test]
    public function registerValidatorsAppendsViewLoginRedirectValidator(): void
    {
        $realReflector = new ReflectionClass(AnalyzeController::class);
        /** @var AnalyzeController $realController */
        $realController = $realReflector->newInstanceWithoutConstructor();

        $realReflector->getProperty('request')->setValue($realController, $this->createStub(Request::class));
        $realReflector->getProperty('response')->setValue($realController, $this->createMock(Response::class));

        $realReflector->getMethod('registerValidators')->invoke($realController);

        /** @var list<mixed> $validators */
        $validators = $realReflector->getProperty('validators')->getValue($realController);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(ViewLoginRedirectValidator::class, $validators[0]);
    }

    // ─── validateTheRequest ───

    /** @throws ReflectionException */
    #[Test]
    public function validateTheRequestSanitizesNamedParams(): void
    {
        $this->setNamedParams([
            'pid' => '9062001abc',
            'jid' => '9062002xyz',
            'password' => " pa\nss\x01word ",
        ]);

        $method = $this->reflector->getMethod('validateTheRequest');
        $result = $method->invoke($this->controller);

        $this->assertSame('9062001', $result['pid']);
        $this->assertSame('9062002', $result['jid']);
        $this->assertSame(' password ', $result['password']);
    }

    // ─── renderView failure / 404 branches ───

    /** @throws ReflectionException */
    #[Test]
    public function renderViewSetsProjectNotFound404WhenProjectMissing(): void
    {
        $this->setNamedParams([
            'pid' => '99999999',
            'jid' => '',
            'password' => 'whatever',
        ]);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('project_not_found.html', $this->controller->lastTemplate);
            $this->assertSame(404, $this->controller->lastViewCode);
        }
    }

    /** @throws ReflectionException */
    #[Test]
    public function renderViewSetsJobNotFound404WhenJobPasswordWrong(): void
    {
        $this->setNamedParams([
            'pid' => (string)$this->projectId(self::BASE),
            'jid' => (string)$this->jobId(self::BASE),
            'password' => 'wrong_job_password',
        ]);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('job_not_found.html', $this->controller->lastTemplate);
            $this->assertSame(404, $this->controller->lastViewCode);
        }
    }

    /** @throws ReflectionException */
    #[Test]
    public function renderViewSetsProjectNotFound404WhenProjectPasswordMismatchAndNoJob(): void
    {
        $this->setNamedParams([
            'pid' => (string)$this->projectId(self::BASE),
            'jid' => '',
            'password' => 'wrong_project_password',
        ]);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('project_not_found.html', $this->controller->lastTemplate);
            $this->assertSame(404, $this->controller->lastViewCode);
        }
    }

    // ─── renderView happy path (chunk view) ───

    /** @throws ReflectionException */
    #[Test]
    public function renderViewBuildsJobAnalysisViewForValidChunk(): void
    {
        $previousEnv = AppConfig::$ENV;
        AppConfig::$ENV = 'testing';

        $this->setNamedParams([
            'pid' => (string)$this->projectId(self::BASE),
            'jid' => (string)$this->jobId(self::BASE),
            'password' => self::JOB_PASSWORD,
        ]);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('jobAnalysis.html', $this->controller->lastTemplate);
            $this->assertSame((string)$this->jobId(self::BASE), $this->controller->lastViewData['jid']);
            $this->assertSame(self::JOB_PASSWORD, $this->controller->lastViewData['job_password']);
            $this->assertArrayHasKey('project_access_token', $this->controller->lastViewData);
            $this->assertSame($this->projectId(self::BASE), $this->controller->addedParams['pid']);
            $this->assertArrayHasKey('num_segments', $this->controller->addedParams);
            $this->assertTrue($this->controller->rendered);
        } finally {
            AppConfig::$ENV = $previousEnv;
        }
    }

    // ─── renderView happy path (project-level analyze view) ───

    /** @throws ReflectionException */
    #[Test]
    public function renderViewBuildsAnalyzeViewForValidProjectWithoutJobId(): void
    {
        $previousEnv = AppConfig::$ENV;
        AppConfig::$ENV = 'testing';

        $this->setNamedParams([
            'pid' => (string)$this->projectId(self::BASE),
            'jid' => '',
            'password' => self::PROJECT_PASSWORD,
        ]);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('analyze.html', $this->controller->lastTemplate);
            $this->assertSame(self::PROJECT_PASSWORD, $this->controller->lastViewData['project_password']);
            $this->assertSame($this->projectId(self::BASE), $this->controller->addedParams['pid']);
            $this->assertSame('DONE', $this->controller->addedParams['project_status']);
            $this->assertTrue($this->controller->rendered);
        } finally {
            AppConfig::$ENV = $previousEnv;
        }
    }
}
