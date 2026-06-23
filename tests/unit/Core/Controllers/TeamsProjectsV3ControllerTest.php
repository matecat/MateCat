<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\NotFoundException as ApiNotFoundException;
use Controller\API\V3\TeamsProjectsController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Logger\MatecatLogger;

/**
 * Reserved ID block: base = 9_050_000 (Wave 8 / N=50).
 *   9050001 project, 9050002 job, 9050003 segment, 9050004 file,
 *   9050005 team, 9050006 user. Cleaned ONLY by reserved id (Playbook §4).
 *   Owner email: ctrltest_9050000@example.org (per-suite unique).
 */
class TestableTeamsProjectsV3Controller extends TeamsProjectsController
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
}

#[AllowMockObjectsWithoutExpectations]
class TeamsProjectsV3ControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_050_000;

    /** @var ReflectionClass<TeamsProjectsController> */
    private ReflectionClass $reflector;
    private TestableTeamsProjectsV3Controller $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableTeamsProjectsV3Controller();
        $this->reflector = new ReflectionClass(TeamsProjectsController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', Database::obtain());

        $user = new UserStruct();
        $user->uid = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $user->first_name = 'Ctrl';
        $user->last_name = 'Tester';
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));

        $team = new TeamStruct();
        $team->id = $this->teamId(self::BASE);
        $this->controller->setTeam($team);

        $_SERVER['REQUEST_URI'] = '/api/v3/teams/' . $this->teamId(self::BASE) . '/projects';
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @throws \PDOException
     */
    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedTeam(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner);

        // Project view renders the job chunk; force a known status.
        $this->seedConnection()->exec(
            "UPDATE jobs SET status_owner = 'active' WHERE id = " . $this->jobId(self::BASE)
        );
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = [
            'REQUEST_URI'    => '/api/v3/teams/' . $this->teamId(self::BASE) . '/projects',
            'REQUEST_METHOD' => 'GET',
        ];
        $_SERVER['REQUEST_URI'] = $serverParams['REQUEST_URI'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->controller, ...$args);
    }

    // ─── getPaginated() happy path ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function getPaginated_returns_json_with_seeded_project_and_links(): void
    {
        $this->setRequestParams([
            'id_team' => (string) $this->teamId(self::BASE),
            'page'    => 1,
            'step'    => 20,
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('_links', $data);
                $this->assertArrayHasKey('projects', $data);
                $this->assertSame(1, $data['_links']['page']);
                $this->assertSame(20, $data['_links']['step']);
                $this->assertSame(1, $data['_links']['totals']);
                $ids = array_map(static fn(array $p): int => $p['id'], $data['projects']);
                $this->assertContains($this->projectId(self::BASE), $ids);
                return true;
            }));

        $this->controller->getPaginated();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function getPaginated_caps_step_at_50_when_step_exceeds_limit(): void
    {
        $this->setRequestParams([
            'id_team' => (string) $this->teamId(self::BASE),
            'page'    => 1,
            'step'    => 200,
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertSame(50, $data['_links']['step']);
                return true;
            }));

        $this->controller->getPaginated();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function getPaginated_throws_not_found_when_page_exceeds_total_pages(): void
    {
        $this->setRequestParams([
            'id_team' => (string) $this->teamId(self::BASE),
            'page'    => 99,
            'step'    => 20,
        ]);

        $this->expectException(ApiNotFoundException::class);
        $this->expectExceptionCode(404);

        $this->controller->getPaginated();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function getPaginated_honours_search_name_filter(): void
    {
        $this->setRequestParams([
            'id_team' => (string) $this->teamId(self::BASE),
            'page'    => 1,
            'step'    => 20,
            'search'  => ['name' => 'CtrlTestProject_' . self::BASE],
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $ids = array_map(static fn(array $p): int => $p['id'], $data['projects']);
                $this->assertContains($this->projectId(self::BASE), $ids);
                return true;
            }));

        $this->controller->getPaginated();
    }

    // ─── _getPaginationLinks() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function getPaginationLinks_includes_next_when_more_pages_exist(): void
    {
        $links = $this->invokePrivate('_getPaginationLinks', [1, 100, 20, []]);

        $this->assertIsArray($links);
        $this->assertSame(1, $links['page']);
        $this->assertSame(20, $links['step']);
        $this->assertSame(100, $links['totals']);
        $this->assertSame(5, $links['total_pages']);
        $this->assertArrayHasKey('next', $links);
        $this->assertArrayNotHasKey('prev', $links);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function getPaginationLinks_includes_prev_on_last_page(): void
    {
        $links = $this->invokePrivate('_getPaginationLinks', [5, 100, 20, []]);

        $this->assertArrayHasKey('prev', $links);
        $this->assertArrayNotHasKey('next', $links);
        $this->assertStringContainsString('page=4', $links['prev']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function getPaginationLinks_appends_search_and_step_query_when_present(): void
    {
        $links = $this->invokePrivate('_getPaginationLinks', [1, 100, 30, ['name' => 'Foo', 'id' => 7]]);

        $this->assertSame(30, $links['step']);
        $this->assertStringContainsString('&step=30', $links['next']);
        $this->assertStringContainsString('&search[name]=Foo', $links['next']);
        $this->assertStringContainsString('&search[id]=7', $links['next']);
    }

    // ─── getOffset() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function getOffset_returns_zero_for_first_page(): void
    {
        $this->assertSame(0, $this->invokePrivate('getOffset', [1, 20]));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function getOffset_returns_step_times_page_minus_one(): void
    {
        $this->assertSame(40, $this->invokePrivate('getOffset', [3, 20]));
    }

    // ─── getTotalPages() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function getTotalPages_rounds_up(): void
    {
        $this->assertSame(3, $this->invokePrivate('getTotalPages', [20, 41]));
        $this->assertSame(1, $this->invokePrivate('getTotalPages', [20, 20]));
        $this->assertSame(0, $this->invokePrivate('getTotalPages', [20, 0]));
    }

    // ─── setTeam() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function setTeam_assigns_team_struct(): void
    {
        $team = new TeamStruct();
        $team->id = $this->teamId(self::BASE);
        $this->controller->setTeam($team);

        $teamProp = $this->reflector->getProperty('team');
        $this->assertSame($team, $teamProp->getValue($this->controller));
    }
}
