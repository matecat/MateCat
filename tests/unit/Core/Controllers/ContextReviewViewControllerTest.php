<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\ViewValidators\ViewLoginRedirectValidator;
use Controller\Exceptions\RenderTerminatedException;
use Controller\Views\ContextReviewController;
use Klein\DataCollection\DataCollection;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;

class TestableContextReviewViewController extends ContextReviewController
{
    public function __construct()
    {
    }

    public bool $forceInvalidRequest = false;

    public ?JobStruct $jobToFind = null;
    /** @var list<array{int, string}> */
    public array $findJobCalls = [];
    public ?JobStruct $featuresLoadedFor = null;
    /** @var list<string> */
    public array $callOrder = [];

    public string $lastTemplate = '';
    /** @var array<string, mixed> */
    public array $lastViewData = [];
    public int $lastViewCode = 200;

    public function setView(string $template_name, array $params = [], int $code = 200): void
    {
        $this->lastTemplate = $template_name;
        $this->lastViewData = $params;
        $this->lastViewCode = $code;
        $this->callOrder[] = 'setView:' . $template_name;
    }

    protected function findJob(int $idJob, string $password): ?JobStruct
    {
        $this->findJobCalls[] = [$idJob, $password];

        return $this->jobToFind;
    }

    protected function loadProjectFeatures(JobStruct $job): void
    {
        $this->featuresLoadedFor = $job;
        $this->callOrder[] = 'loadProjectFeatures';
    }

    /**
     * @throws RenderTerminatedException
     */
    public function render(?int $code = null): never
    {
        throw new RenderTerminatedException();
    }

    /**
     * @return array<string, mixed>|false|null
     */
    protected function validateTheRequest(): false|array|null
    {
        if ($this->forceInvalidRequest) {
            return null;
        }

        return parent::validateTheRequest();
    }
}

#[AllowMockObjectsWithoutExpectations]
class ContextReviewViewControllerTest extends AbstractTest
{
    /** @var ReflectionClass<TestableContextReviewViewController> */
    private ReflectionClass $reflector;
    private TestableContextReviewViewController $controller;
    /** @var Request&MockObject */
    private Request&MockObject $requestStub;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->reflector = new ReflectionClass(TestableContextReviewViewController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $this->requestStub = $this->createMock(Request::class);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->createMock(Response::class));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function setNamedParams(array $params): void
    {
        $paramsNamed = $this->createStub(DataCollection::class);
        $paramsNamed->method('all')->willReturn($params);
        $this->requestStub->method('paramsNamed')->willReturn($paramsNamed);
    }

    // ─── validateTheRequest ───

    #[Test]
    public function validateTheRequestReturnsSanitizedNamedParams(): void
    {
        $this->setNamedParams([
            'id_project' => '9063001abc',
            'password'   => " test\npass\x01 ",
        ]);

        $method = $this->reflector->getMethod('validateTheRequest');
        $result = $method->invoke($this->controller);

        $this->assertIsArray($result);
        $this->assertSame('9063001', $result['id_project']);
        $this->assertSame(' testpass ', $result['password']);
    }

    // ─── renderView ───

    #[Test]
    public function renderViewSetsContextPreviewTemplateWhenRequestIsValid(): void
    {
        $this->setNamedParams([
            'id_project' => '12345',
            'password'   => 'secretpw',
        ]);
        $this->controller->jobToFind = new JobStruct(['id' => 12345, 'password' => 'secretpw']);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('context_preview.html', $this->controller->lastTemplate);
            $this->assertSame(200, $this->controller->lastViewCode);
            $this->assertSame('12345', $this->controller->lastViewData['id_project']);
            $this->assertSame('secretpw', $this->controller->lastViewData['password']);
        }
    }

    #[Test]
    public function renderViewLooksTheJobUpWithTheIdAndPasswordFromTheUrl(): void
    {
        $this->setNamedParams([
            'id_project' => '12345',
            'password'   => 'r2password',
        ]);
        $this->controller->jobToFind = new JobStruct(['id' => 12345, 'password' => 'translatepw']);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame([[12345, 'r2password']], $this->controller->findJobCalls);
        }
    }

    #[Test]
    public function renderViewLoadsTheProjectFeaturesAfterSettingUpTheView(): void
    {
        $this->setNamedParams([
            'id_project' => '12345',
            'password'   => 'secretpw',
        ]);
        $job = new JobStruct(['id' => 12345, 'password' => 'secretpw']);
        $this->controller->jobToFind = $job;

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame($job, $this->controller->featuresLoadedFor);
            // setView() replaces the feature set with the viewer's own: loading the project's before it
            // would be undone, and the project's plugins would add nothing to the page's policy.
            $this->assertSame(['setView:context_preview.html', 'loadProjectFeatures'], $this->controller->callOrder);
        }
    }

    #[Test]
    public function renderViewAnswers404WhenNoJobMatchesTheIdAndPassword(): void
    {
        $this->setNamedParams([
            'id_project' => '12345',
            'password'   => 'wrongpw',
        ]);
        $this->controller->jobToFind = null;

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('job_not_found.html', $this->controller->lastTemplate);
            $this->assertSame(404, $this->controller->lastViewCode);
            $this->assertNull($this->controller->featuresLoadedFor);
        }
    }

    #[Test]
    public function renderViewAnswersJobNotFound404WhenRequestIsNotArray(): void
    {
        $this->controller->forceInvalidRequest = true;

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('job_not_found.html', $this->controller->lastTemplate);
            $this->assertSame(404, $this->controller->lastViewCode);
        }
    }

    // ─── registerValidators (production hook) ───

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function registerValidatorsAppendsViewLoginRedirectValidator(): void
    {
        $realReflector = new ReflectionClass(ContextReviewController::class);
        /** @var ContextReviewController $realController */
        $realController = $realReflector->newInstanceWithoutConstructor();

        $realReflector->getProperty('request')->setValue($realController, $this->createMock(Request::class));
        $realReflector->getProperty('response')->setValue($realController, $this->createMock(Response::class));

        $realReflector->getMethod('registerValidators')->invoke($realController);

        /** @var list<mixed> $validators */
        $validators = $realReflector->getProperty('validators')->getValue($realController);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(ViewLoginRedirectValidator::class, $validators[0]);
    }
}
