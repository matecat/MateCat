<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\GetProjectsController;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
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
use Utils\Constants\Teams;
use Utils\Logger\MatecatLogger;

class TestableGetProjectsController extends GetProjectsController
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
}

/**
 * Real-DB suite for {@see GetProjectsController}.
 *
 * Reserved ID block (Playbook §4): base = 9_002_000.
 *   9002001 project, 9002002 job, 9002003 segment, 9002004 file,
 *   9002005 team, 9002006 user/uid.
 * Per-suite owner email: ctrltest_9002000@example.org (never shared test@example.org).
 * Clean ONLY by reserved id.
 */
#[AllowMockObjectsWithoutExpectations]
class GetProjectsControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_002_000;

    /** @var ReflectionClass<GetProjectsController> */
    private ReflectionClass $reflector;
    private TestableGetProjectsController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private UserStruct $user;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableGetProjectsController();
        $this->reflector  = new ReflectionClass(GetProjectsController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);

        $this->user            = new UserStruct();
        $this->user->uid       = $this->userId(self::BASE);
        $this->user->email     = $this->ownerEmail(self::BASE);
        $this->user->first_name = 'Ctrl';
        $this->user->last_name  = 'Tester';

        $this->reflector->getProperty('user')->setValue($this->controller, $this->user);
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet(\Model\DataAccess\Database::obtain()));
        $this->reflector->getProperty('database')->setValue($this->controller, \Model\DataAccess\Database::obtain());
    }

    /**
     * @throws Throwable
     */
    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @throws Throwable
     */
    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);

        $this->seedUser(self::BASE, $owner);
        $this->seedTeam(self::BASE, Teams::GENERAL);
        $this->seedMembership(self::BASE);
        $this->seedProject(self::BASE, $owner);
        $this->seedFile(self::BASE);
        $this->seedJob(self::BASE, $owner);
        $this->seedSegment(self::BASE);
        $this->seedSegmentTranslation(self::BASE);
    }

    /**
     * @param array<int|string, mixed> $args
     *
     * @throws Throwable
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invoke($this->controller, ...$args);
    }

    /**
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams      = ['REQUEST_URI' => '/api/app/getprojects', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
    }

    // ─── validateTheRequest ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_throws_when_id_team_is_empty(): void
    {
        $this->setRequestParams(['page' => '1']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_returns_expected_structure(): void
    {
        $this->setRequestParams([
            'id_team'     => (string) $this->teamId(self::BASE),
            'page'        => '2',
            'step'        => '15',
            'project'     => '777',
            'id_assignee' => '55',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame(2, $result['page']);
        $this->assertSame(15, $result['step']);
        $this->assertSame((2 - 1) * 15, $result['start']);
        $this->assertSame('777', $result['project_id']);
        $this->assertSame('55', $result['id_assignee']);
        $this->assertSame((string) $this->teamId(self::BASE), $result['id_team']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_defaults_page_and_step(): void
    {
        $this->setRequestParams(['id_team' => (string) $this->teamId(self::BASE)]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame(1, $result['page']);
        $this->assertSame(10, $result['step']);
        $this->assertSame(0, $result['start']);
        $this->assertNull($result['project_id']);
        $this->assertNull($result['id_assignee']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_caps_step_above_twenty_to_ten(): void
    {
        $this->setRequestParams([
            'id_team' => (string) $this->teamId(self::BASE),
            'step'    => '50',
        ]);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame(10, $result['step']);
    }

    // ─── filterTeam ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function filterTeam_returns_team_for_valid_membership(): void
    {
        /** @var TeamStruct $team */
        $team = $this->invokePrivate('filterTeam', [$this->teamId(self::BASE)]);

        $this->assertInstanceOf(TeamStruct::class, $team);
        $this->assertSame($this->teamId(self::BASE), (int) $team->id);
        $this->assertSame(Teams::GENERAL, $team->type);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function filterTeam_throws_not_found_for_unknown_team(): void
    {
        $this->expectException(NotFoundException::class);

        $this->invokePrivate('filterTeam', [98765432]);
    }

    // ─── filterAssignee ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function filterAssignee_returns_null_when_id_assignee_is_null(): void
    {
        /** @var TeamStruct $team */
        $team = $this->invokePrivate('filterTeam', [$this->teamId(self::BASE)]);

        $result = $this->invokePrivate('filterAssignee', [$team, null]);

        $this->assertNull($result);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function filterAssignee_returns_member_user_for_valid_assignee(): void
    {
        /** @var TeamStruct $team */
        $team = $this->invokePrivate('filterTeam', [$this->teamId(self::BASE)]);

        /** @var UserStruct $assignee */
        $assignee = $this->invokePrivate('filterAssignee', [$team, $this->userId(self::BASE)]);

        $this->assertInstanceOf(UserStruct::class, $assignee);
        $this->assertSame($this->userId(self::BASE), (int) $assignee->uid);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function filterAssignee_throws_not_found_for_assignee_outside_team(): void
    {
        /** @var TeamStruct $team */
        $team = $this->invokePrivate('filterTeam', [$this->teamId(self::BASE)]);

        $this->expectException(NotFoundException::class);

        $this->invokePrivate('filterAssignee', [$team, 12340000]);
    }

    // ─── fetch() public action ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function fetch_returns_json_with_seeded_project_for_general_team(): void
    {
        $this->setRequestParams([
            'id_team' => (string) $this->teamId(self::BASE),
            'page'    => '1',
            'step'    => '20',
        ]);

        $base = self::BASE;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use ($base): bool {
                $this->assertArrayHasKey('data', $data);
                $this->assertArrayHasKey('page', $data);
                $this->assertArrayHasKey('pnumber', $data);
                $this->assertArrayHasKey('pageStep', $data);
                $this->assertSame(1, $data['page']);
                $this->assertSame(20, $data['pageStep']);
                $this->assertGreaterThanOrEqual(1, (int) $data['pnumber']);

                $ids = array_map(static fn(array $p): int => (int) $p['id'], $data['data']);
                $this->assertContains($base + 1, $ids);

                return true;
            }));

        $this->controller->fetch();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function fetch_personal_team_path_returns_paginated_payload(): void
    {
        // Switch the seeded team to PERSONAL so fetch() takes the assignee=user / team=null branch.
        $this->seedConnection()->exec(
            "UPDATE teams SET type = '" . Teams::PERSONAL . "' WHERE id = " . $this->teamId(self::BASE)
        );

        $this->setRequestParams([
            'id_team' => (string) $this->teamId(self::BASE),
            'page'    => '1',
            'step'    => '10',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('data', $data);
                $this->assertSame(1, $data['page']);
                $this->assertSame(10, $data['pageStep']);
                $this->assertIsArray($data['data']);
                return true;
            }));

        $this->controller->fetch();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function fetch_throws_when_id_team_missing(): void
    {
        $this->setRequestParams(['page' => '1']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->controller->fetch();
    }
}
