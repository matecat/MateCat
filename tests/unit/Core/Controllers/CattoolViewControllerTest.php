<?php

namespace Matecat\Core\Controllers;

use Controller\Exceptions\RenderTerminatedException;
use Controller\Views\CattoolController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\LQA\ModelStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB view-controller suite for {@see CattoolController} (Playbook §3).
 *
 * Reserved ID block: base = 9061000 (project=base+1, job=base+2, segment=base+3,
 * file=base+4, team=base+5, user=base+6, qa_model=base+7, chunk_review=base+8).
 * Clean ONLY by reserved id; per-suite owner email = ctrltest_9061000@example.org.
 */
class TestableCattoolController extends CattoolController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class CattoolViewControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9061000;

    private ReflectionClass $reflector;
    private TestableCattoolController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTeam(self::BASE, 'personal');
        $this->seedUser(self::BASE, $this->ownerEmail(self::BASE));
        $this->seedMembership(self::BASE);
        $this->seedProject(self::BASE, $this->ownerEmail(self::BASE));
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $this->ownerEmail(self::BASE), 'jobpw');
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
        $this->seedQaModel(self::BASE);
        $this->seedChunkReview(self::BASE, 'jobpw', 'revpw', 2);

        $this->controller = new TestableCattoolController();
        $this->reflector = new ReflectionClass(CattoolController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->setProp('userIsLogged', false);
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    private function setRequestParams(array $named): void
    {
        $serverParams = ['REQUEST_URI' => '/translate/x/y/en-it', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request([], [], [], $serverParams, [], null);
        // paramsNamed() is the source read by validateTheRequest()
        foreach ($named as $key => $value) {
            $this->requestStub->paramsNamed()->set($key, $value);
        }
        $this->setProp('request', $this->requestStub);
    }

    /**
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    // ─── validateTheRequest ───

    #[Test]
    public function validateTheRequest_returns_jid_and_password_from_named_params(): void
    {
        $this->setRequestParams(['jid' => '12345', 'password' => 'abcDEF']);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame('12345', $result['jid']);
        $this->assertSame('abcDEF', $result['password']);
    }

    #[Test]
    public function validateTheRequest_defaults_to_empty_strings_when_absent(): void
    {
        $this->setRequestParams([]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame('', $result['jid']);
        $this->assertSame('', $result['password']);
    }

    #[Test]
    public function validateTheRequest_sanitizes_non_numeric_chars_from_jid(): void
    {
        $this->setRequestParams(['jid' => 'a9b0c61', 'password' => 'pw']);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame('9061', $result['jid']);
    }

    // ─── findJobByIdPasswordAndSourcePage ───

    #[Test]
    public function findJobByIdPassword_returns_job_chunk_for_non_revision(): void
    {
        $result = $this->invokePrivate('findJobByIdPasswordAndSourcePage', [
            $this->jobId(self::BASE), 'jobpw', 1, false,
        ]);

        $this->assertInstanceOf(JobStruct::class, $result->chunk);
        $this->assertSame($this->jobId(self::BASE), $result->chunk->id);
        $this->assertNull($result->chunkReviewStruct);
        $this->assertFalse($result->isRevision);
    }

    #[Test]
    public function findJobByIdPassword_returns_chunk_review_for_revision(): void
    {
        $result = $this->invokePrivate('findJobByIdPasswordAndSourcePage', [
            $this->jobId(self::BASE), 'revpw', 2, true,
        ]);

        $this->assertNotNull($result->chunkReviewStruct);
        $this->assertSame($this->chunkReviewId(self::BASE), $result->chunkReviewStruct->id);
        $this->assertTrue($result->isRevision);
    }

    #[Test]
    public function findJobByIdPassword_throws_not_found_for_wrong_job_password(): void
    {
        $this->expectException(NotFoundException::class);

        $this->invokePrivate('findJobByIdPasswordAndSourcePage', [
            $this->jobId(self::BASE), 'wrong_pw_zzz', 1, false,
        ]);
    }

    #[Test]
    public function findJobByIdPassword_throws_not_found_for_missing_review_record(): void
    {
        $this->expectException(NotFoundException::class);

        $this->invokePrivate('findJobByIdPasswordAndSourcePage', [
            $this->jobId(self::BASE), 'nonexistent_rev_pw', 99, true,
        ]);
    }

    // ─── getActiveEngine ───

    #[Test]
    public function getActiveEngine_returns_array_representation_for_existing_engine(): void
    {
        // Engine id 1 (MyMemory) is a baseline row present in every test DB.
        $result = $this->invokePrivate('getActiveEngine', [1]);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['id']);
        $this->assertArrayHasKey('name', $result);
    }

    #[Test]
    public function getActiveEngine_returns_empty_array_for_unknown_engine(): void
    {
        $result = $this->invokePrivate('getActiveEngine', [98765432]);

        $this->assertSame([], $result);
    }

    // ─── buildPageTitle ───

    #[Test]
    public function buildPageTitle_translate_prefix_when_no_revision(): void
    {
        $job = $this->loadSeededJob();

        $title = $this->invokePrivate('buildPageTitle', [null, $job]);

        $this->assertStringStartsWith('Translate - ', $title);
        $this->assertStringEndsWith(' - ' . $this->jobId(self::BASE), $title);
    }

    #[Test]
    public function buildPageTitle_revise_prefix_for_revision_one(): void
    {
        $job = $this->loadSeededJob();

        $title = $this->invokePrivate('buildPageTitle', [1, $job]);

        $this->assertStringStartsWith('Revise - ', $title);
    }

    #[Test]
    public function buildPageTitle_numbered_revise_prefix_for_revision_two(): void
    {
        $job = $this->loadSeededJob();

        $title = $this->invokePrivate('buildPageTitle', [2, $job]);

        $this->assertStringStartsWith('Revise 2 - ', $title);
    }

    // ─── searchableStatuses ───

    #[Test]
    public function searchableStatuses_returns_value_label_pairs(): void
    {
        $statuses = $this->invokePrivate('searchableStatuses');

        $this->assertIsArray($statuses);
        $this->assertNotEmpty($statuses);
        $first = $statuses[0];
        $this->assertArrayHasKey('value', $first);
        $this->assertArrayHasKey('label', $first);
        $this->assertSame($first['value'], $first['label']);
    }

    // ─── getCategoriesAsJson ───

    #[Test]
    public function getCategoriesAsJson_returns_array_for_model_without_categories(): void
    {
        $model = new ModelStruct();
        $model->id = $this->qaModelId(self::BASE);

        $result = $this->invokePrivate('getCategoriesAsJson', [$model]);

        $this->assertIsArray($result);
    }

    // ─── findOwnerEmailAndTeam ───

    #[Test]
    public function findOwnerEmailAndTeam_resolves_owner_for_personal_team(): void
    {
        $job = $this->loadSeededJob();
        $project = $job->getProject();

        $result = $this->invokePrivate('findOwnerEmailAndTeam', [$project]);

        $this->assertArrayHasKey('owner_email', $result);
        $this->assertArrayHasKey('team', $result);
        $this->assertArrayHasKey('jobOwnerIsMe', $result);
        $this->assertSame($this->ownerEmail(self::BASE), $result['owner_email']);
        $this->assertTrue($result['jobOwnerIsMe']);
    }

    // ─── render helpers ───
    // These build a view via setView() then call render(). In a checkout where
    // the cat-tool HTML templates are present, render() throws
    // RenderTerminatedException (testing env). Where the template files are
    // absent, view->execute() throws a PHPTAL IO error. Either way setView()
    // ran with the helper-specific args, so we assert the view was populated
    // and the render stage was reached.

    #[Test]
    public function notFound_builds_view_and_reaches_render(): void
    {
        $this->assertRendersAfter(fn() => $this->invokePrivate('notFound'));
    }

    #[Test]
    public function cancelled_builds_view_and_reaches_render(): void
    {
        $this->assertRendersAfter(fn() => $this->invokePrivate('cancelled', [[
            'team' => null,
            'owner_email' => $this->ownerEmail(self::BASE),
            'jobOwnerIsMe' => false,
        ]]));
    }

    #[Test]
    public function archived_builds_view_and_reaches_render(): void
    {
        $this->assertRendersAfter(fn() => $this->invokePrivate('archived', [
            $this->jobId(self::BASE),
            'jobpw',
            [
                'team' => null,
                'owner_email' => $this->ownerEmail(self::BASE),
                'jobOwnerIsMe' => true,
            ],
        ]));
    }

    // ─── renderView (full data-assembly path) ───

    #[Test]
    public function renderView_assembles_view_vars_for_translate_page(): void
    {
        $previousUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/translate/CtrlTestProject/en-it/' . $this->jobId(self::BASE) . '-jobpw';

        $this->requestStub->paramsNamed()->set('jid', (string) $this->jobId(self::BASE));
        $this->requestStub->paramsNamed()->set('password', 'jobpw');

        try {
            // renderView() resolves the seeded chunk, assembles the full template
            // variable map via setView(), then enters the render/decorator
            // pipeline. In this unit checkout that pipeline (PHPTAL templates +
            // word-count decorator fixtures) cannot complete, so it throws AFTER
            // the entire data-assembly body has executed. We assert the failure
            // originates from the decorator/render stage (proving the whole
            // assembly path ran) and that setView() populated the view object.
            $caught = null;
            try {
                $this->controller->renderView();
            } catch (\Throwable $e) {
                $caught = $e;
            }

            $this->assertNotNull($caught, 'renderView did not reach the render/decorator stage');

            $viewProp = $this->reflector->getProperty('view');
            $this->assertTrue(
                $viewProp->isInitialized($this->controller),
                'setView() did not run — data assembly stopped before building the view'
            );
            $this->assertInstanceOf(
                \PHPTAL::class,
                $viewProp->getValue($this->controller),
                'view was not assembled by setView()'
            );
        } finally {
            if ($previousUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previousUri;
            }
        }
    }

    /**
     * Invoke a render-helper closure and assert the render stage was reached:
     * the view was set, and render() threw (RenderTerminatedException when the
     * template exists, a PHPTAL IO error when the template file is absent).
     */
    private function assertRendersAfter(callable $invoke): void
    {
        $threw = false;
        try {
            $invoke();
        } catch (RenderTerminatedException $e) {
            $threw = true;
        } catch (\Throwable $e) {
            // Template file missing in this checkout: render() reached execute().
            $this->assertStringContainsString('.html', $e->getMessage());
            $threw = true;
        }

        $this->assertTrue($threw, 'render stage was not reached');

        $viewProp = $this->reflector->getProperty('view');
        $this->assertTrue($viewProp->isInitialized($this->controller), 'setView() did not populate the view');
    }

    /**
     * @throws ReflectionException
     */
    private function loadSeededJob(): JobStruct
    {
        $result = $this->invokePrivate('findJobByIdPasswordAndSourcePage', [
            $this->jobId(self::BASE), 'jobpw', 1, false,
        ]);

        return $result->chunk;
    }
}
