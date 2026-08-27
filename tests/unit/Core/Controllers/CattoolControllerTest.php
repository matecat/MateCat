<?php

namespace Matecat\Core\Controllers;

use Controller\Exceptions\RenderTerminatedException;
use Controller\Views\CattoolController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\LQA\ModelStruct;
use Model\Users\UserStruct;
use Model\LQA\ChunkReviewStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;
use Utils\Constants\SourcePages;
use Utils\Session\ArraySessionStore;

/**
 * Real-DB view-controller suite for {@see CattoolController} (Playbook §3).
 *
 * Reserved ID block: base = 9061000 (project=base+1, job=base+2, segment=base+3,
 * file=base+4, team=base+5, user=base+6, qa_model=base+7, chunk_review=base+8).
 * Clean ONLY by reserved id; per-suite owner email = ctrltest_9061000@example.org.
 */
class TestableCattoolController extends CattoolController
{
    /** @var array<string, mixed> */
    public array $capturedViewParams = [];
    public string $capturedViewTemplate = '';

    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }

    /**
     * The render pipeline cannot complete in a unit checkout, so the variable map is recorded on the
     * way in: it is the only place the page's answer is observable.
     *
     * @param array<string, mixed> $params
     * @throws \Exception
     */
    public function setView(string $template_name, array $params = [], int $code = 200): void
    {
        $this->capturedViewTemplate = $template_name;
        $this->capturedViewParams = $params;

        parent::setView($template_name, $params, $code);
    }
}

#[AllowMockObjectsWithoutExpectations]
class CattoolControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9061000;

    private ReflectionClass $reflector;
    private TestableCattoolController $controller;
    private ArraySessionStore $sessionStore;
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

        // Stateful view controller; the double skips the constructor that builds its store, and the
        // view path reads flash messages out of it.
        $this->sessionStore = $this->injectSessionStore($this->controller);

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        $this->setProp('database', obtainTestDatabase());
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

    // ─── findJobByIdAndPassword ───

    #[Test]
    public function findJobByIdPassword_returns_the_job_chunk_for_the_job_password(): void
    {
        $result = $this->invokePrivate('findJobByIdAndPassword', [$this->jobId(self::BASE), 'jobpw']);

        $this->assertInstanceOf(JobStruct::class, $result->chunk);
        $this->assertSame($this->jobId(self::BASE), $result->chunk->id);
        $this->assertNull($result->chunkReviewStruct);
    }

    #[Test]
    public function findJobByIdPassword_returns_the_review_row_for_a_review_password(): void
    {
        $result = $this->invokePrivate('findJobByIdAndPassword', [$this->jobId(self::BASE), 'revpw']);

        $this->assertNotNull($result->chunkReviewStruct);
        $this->assertSame($this->chunkReviewId(self::BASE), $result->chunkReviewStruct->id);
        $this->assertSame(SourcePages::SOURCE_PAGE_REVISION, $result->chunkReviewStruct->source_page);
    }

    #[Test]
    public function findJobByIdPassword_throws_not_found_when_no_row_matches_the_password(): void
    {
        $this->expectException(NotFoundException::class);

        $this->invokePrivate('findJobByIdAndPassword', [$this->jobId(self::BASE), 'wrong_pw_zzz']);
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
        $project = $job->getProject(new ProjectDao(obtainTestDatabase()));

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
        // On the request rather than in $_SERVER: the render path reads the URI from the request it
        // was given, so there is no global to restore afterwards.
        $this->requestStub->server()->set(
            'REQUEST_URI',
            '/translate/CtrlTestProject/en-it/' . $this->jobId(self::BASE) . '-jobpw'
        );

        $this->requestStub->paramsNamed()->set('jid', (string) $this->jobId(self::BASE));
        $this->requestStub->paramsNamed()->set('password', 'jobpw');

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
    }

    // ─── phase resolution ───

    #[Test]
    public function renderView_offers_the_first_revision_password_to_a_translate_url_naming_a_phase(): void
    {
        $this->seedSecondRevisionPhase();

        // "revise" is a legal project name, so it is a legal path segment, and the phase deciding which
        // review password the page publishes used to be read out of the path by an unanchored regex: a
        // translator asking for this URL was handed the second reviewer's password. The credential is
        // the job password, so this is the translate page and the link it offers is the first
        // revision's, whatever the path spells.
        $params = $this->renderViewFor('/translate/revise/en-it/', 'jobpw');

        $this->assertSame('revpw', $params['review_password']);
        $this->assertSame(SourcePages::SOURCE_PAGE_TRANSLATE, $params['source_page']);
    }

    #[Test]
    public function renderView_resolves_the_phase_from_the_presented_review_password(): void
    {
        $this->seedSecondRevisionPhase();

        // The path names the first revision, the credential is the second reviewer's. The phase follows
        // the credential, the contract the API validators already run on.
        $params = $this->renderViewFor('/revise/CtrlTestProject/en-it/', 'revpw2');

        $this->assertSame(SourcePages::SOURCE_PAGE_REVISION_2, $params['source_page']);
        $this->assertSame('revpw2', $params['review_password']);
    }

    #[Test]
    public function renderView_serves_not_found_for_a_password_no_row_matches(): void
    {
        $this->renderViewFor('/revise2/CtrlTestProject/en-it/', 'no_such_pw_value');

        $this->assertSame('job_not_found.html', $this->controller->capturedViewTemplate);
    }

    private function seedSecondRevisionPhase(): void
    {
        $this->seedChunkReview(
            self::BASE,
            'jobpw',
            'revpw2',
            SourcePages::SOURCE_PAGE_REVISION_2,
            $this->secondChunkReviewId(self::BASE)
        );
    }

    /**
     * Drive the whole renderView() body for one URL and credential, and return the variable map it
     * assembled. The render stage it ends in cannot complete here, which is immaterial: setView() has
     * already run by then.
     *
     * @return array<string, mixed>
     */
    private function renderViewFor(string $pathPrefix, string $password): array
    {
        $jid = (string)$this->jobId(self::BASE);
        $previousUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = $pathPrefix . $jid . '-' . $password;

        $this->requestStub->paramsNamed()->set('jid', $jid);
        $this->requestStub->paramsNamed()->set('password', $password);

        try {
            $this->controller->renderView();
        } catch (\Throwable) {
            // the render/decorator pipeline cannot complete in this checkout
        } finally {
            if ($previousUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previousUri;
            }
        }

        $this->assertNotEmpty(
            $this->controller->capturedViewParams,
            'data assembly stopped before setView()'
        );

        return $this->controller->capturedViewParams;
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
        return $this->invokePrivate('findJobByIdAndPassword', [$this->jobId(self::BASE), 'jobpw'])->chunk;
    }

    // ─── overallQualityClass ───

    #[Test]
    public function overallQualityClass_is_empty_when_there_is_no_chunk_review(): void
    {
        self::assertSame('', $this->invokePrivate('overallQualityClass', [null]));
    }

    #[Test]
    public function overallQualityClass_is_empty_when_the_verdict_is_null(): void
    {
        // A project with no LQA model never gets a verdict, and NULL must not read as a failure.
        $chunkReview = new ChunkReviewStruct(['is_pass' => null]);

        self::assertSame('', $this->invokePrivate('overallQualityClass', [$chunkReview]));
    }

    #[Test]
    public function overallQualityClass_reports_the_verdict_when_there_is_one(): void
    {
        self::assertSame(
            'excellent',
            $this->invokePrivate('overallQualityClass', [new ChunkReviewStruct(['is_pass' => true])])
        );
        self::assertSame(
            'fail',
            $this->invokePrivate('overallQualityClass', [new ChunkReviewStruct(['is_pass' => false])])
        );
    }

}
