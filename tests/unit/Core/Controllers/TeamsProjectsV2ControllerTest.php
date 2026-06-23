<?php

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\NotFoundException as ApiNotFoundException;
use Controller\API\V2\TeamsProjectsController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
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
 * Reserved ID block: base = 9_037_000 (Wave 6 / N=37).
 *   9037001 project, 9037002 job, 9037003 segment, 9037004 file,
 *   9037005 team, 9037006 user. Cleaned ONLY by reserved id (Playbook §4).
 *   Owner email: ctrltest_9037000@example.org (per-suite unique).
 */
class TestableTeamsProjectsV2Controller extends TeamsProjectsController
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
class TeamsProjectsV2ControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_037_000;

    /** @var ReflectionClass<TeamsProjectsController> */
    private ReflectionClass $reflector;
    private TestableTeamsProjectsV2Controller $controller;
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

        $this->controller = new TestableTeamsProjectsV2Controller();
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

        // getByName joins jobs on status_owner = 'active'; force the value.
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
     * @param array<string, mixed>      $params
     * @param array<string, mixed>|null $controllerParams
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params, ?array $controllerParams = null): void
    {
        $serverParams = ['REQUEST_URI' => '/api/v2/teams/x/projects', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->setProp('request', $this->requestStub);
        $this->controller->params = $controllerParams ?? $params;
    }

    // ─── get() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function get_returns_json_with_seeded_project_id(): void
    {
        $this->setRequestParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'id_team' => (string) $this->teamId(self::BASE),
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('project', $data);
                $this->assertSame($this->projectId(self::BASE), $data['project']['id']);
                $this->assertSame($this->teamId(self::BASE), $data['project']['id_team']);
                return true;
            }));

        $this->controller->get();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function get_throws_not_found_for_nonexistent_project(): void
    {
        $this->setRequestParams([
            'id_project' => '99999999',
            'id_team' => (string) $this->teamId(self::BASE),
        ]);

        $this->expectException(ApiNotFoundException::class);

        $this->controller->get();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function get_throws_not_found_when_team_does_not_match_project(): void
    {
        $this->setRequestParams([
            'id_project' => (string) $this->projectId(self::BASE),
            'id_team' => '7777777',
        ]);

        $this->expectException(ApiNotFoundException::class);

        $this->controller->get();
    }

    // ─── update() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_changes_name_and_returns_updated_project(): void
    {
        $newName = 'RenamedCtrlProject_' . self::BASE;
        // id_team is required by ProjectExistsInTeamValidator (read from the request),
        // but kept OUT of $this->params so the accepted-fields loop only updates `name`
        // (an id_team update triggers the membership/personal-team branch in ProjectModel).
        $this->setRequestParams(
            [
                'id_project' => (string) $this->projectId(self::BASE),
                'id_team' => (string) $this->teamId(self::BASE),
            ],
            ['name' => $newName]
        );

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($newName): bool {
                $this->assertArrayHasKey('project', $data);
                $this->assertSame($this->projectId(self::BASE), $data['project']['id']);
                $this->assertSame($newName, $data['project']['name']);
                return true;
            }));

        $this->controller->update();

        // verify the change persisted
        $stmt = $this->seedConnection()->query(
            "SELECT name FROM projects WHERE id = " . $this->projectId(self::BASE)
        );
        $this->assertNotFalse($stmt);
        $this->assertSame($newName, $stmt->fetchColumn());
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_throws_not_found_for_nonexistent_project(): void
    {
        $this->setRequestParams([
            'id_project' => '99999999',
            'id_team' => (string) $this->teamId(self::BASE),
            'name' => 'whatever',
        ]);

        $this->expectException(ApiNotFoundException::class);

        $this->controller->update();
    }

    // ─── getByName() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function getByName_returns_projects_for_matching_name(): void
    {
        $this->setRequestParams([
            'project_name' => 'CtrlTestProject_' . self::BASE,
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('projects', $data);
                $this->assertNotEmpty($data['projects']);
                $ids = array_map(static fn(array $p): int => $p['id'], $data['projects']);
                $this->assertContains($this->projectId(self::BASE), $ids);
                return true;
            }));

        $this->controller->getByName();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function getByName_throws_not_found_when_no_project_matches(): void
    {
        $this->setRequestParams([
            'project_name' => 'NoSuchProjectName_' . self::BASE,
        ]);

        $this->expectException(NotFoundException::class);

        $this->controller->getByName();
    }

    // ─── setTeam() ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function setTeam_assigns_team_used_by_getByName(): void
    {
        $team = new TeamStruct();
        $team->id = $this->teamId(self::BASE);
        $this->controller->setTeam($team);

        $this->setRequestParams([
            'project_name' => 'CtrlTestProject_' . self::BASE,
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('projects', $data);
                return true;
            }));

        $this->controller->getByName();
    }
}
