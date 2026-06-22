<?php

namespace Matecat\Core\Controllers;

use Controller\API\V2\TeamMembersController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Utils\Logger\MatecatLogger;

/**
 * Real-DB suite for {@see TeamMembersController} (Wave 5 N=34).
 * Reserved ID block base 9034000 (base+5 team, base+6 uid). Clean ONLY by reserved id.
 * Per-suite owner email: ctrltest_9034000@example.org.
 */
class TestableTeamMembersController extends TeamMembersController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class TeamMembersControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9034000;

    /** @var ReflectionClass<TeamMembersController> */
    private ReflectionClass $reflector;
    private TestableTeamMembersController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     * @throws \Exception
     * @throws \TypeError
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws \PHPUnit\Framework\InvalidArgumentException
     * @throws \PHPUnit\Event\NoPreviousThrowableException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedData();

        $this->controller = new TestableTeamMembersController();
        $this->reflector  = new ReflectionClass(TeamMembersController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);

        $user        = new UserStruct();
        $user->uid   = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet());
        // skip the session-refresh path in update()/delete()
        $this->setProp('api_key', 'ctrltest-api-key');
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private function seedData(): void
    {
        $this->seedUser(self::BASE, $this->ownerEmail(self::BASE));
        $this->seedTeam(self::BASE);
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
     * @param array<string, string> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams      = ['REQUEST_URI' => '/api/v2/teams/x/members', 'REQUEST_METHOD' => 'GET'];
        $this->requestStub = new Request($params, $params, [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    // ─── index() ───

    /**
     * @throws \Exception
     * @throws \TypeError
     * @throws ReflectionException
     */
    #[Test]
    public function index_returns_members_and_pending_invitations_keys(): void
    {
        $this->setRequestParams(['id_team' => (string) $this->teamId(self::BASE)]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('members', $data);
                $this->assertArrayHasKey('pending_invitations', $data);
                $this->assertSame([], $data['members']);
                $this->assertIsArray($data['pending_invitations']);
                return true;
            }));

        $this->controller->index();
    }

    /**
     * @throws \Exception
     * @throws \TypeError
     * @throws ReflectionException
     */
    #[Test]
    public function index_throws_when_team_not_found(): void
    {
        $this->setRequestParams(['id_team' => '99999999']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Team not found');

        $this->controller->index();
    }

    // ─── update() ───

    /**
     * @throws \Exception
     * @throws \TypeError
     * @throws ReflectionException
     */
    #[Test]
    public function update_with_no_members_returns_unchanged_member_list(): void
    {
        $this->setRequestParams(['id_team' => (string) $this->teamId(self::BASE)]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('members', $data);
                $this->assertArrayHasKey('pending_invitations', $data);
                $this->assertSame([], $data['members']);
                return true;
            }));

        $this->controller->update();
    }

    /**
     * @throws \Exception
     * @throws \TypeError
     * @throws ReflectionException
     */
    #[Test]
    public function update_throws_when_team_not_found(): void
    {
        $this->setRequestParams(['id_team' => '99999999']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Team not found');

        $this->controller->update();
    }

    // ─── delete() ───

    /**
     * @throws \Exception
     * @throws \TypeError
     * @throws ReflectionException
     */
    #[Test]
    public function delete_throws_when_team_not_found(): void
    {
        $this->setRequestParams(['id_team' => '99999999', 'uid_member' => '123']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Team not found');

        $this->controller->delete();
    }

    /**
     * @throws \Exception
     * @throws \TypeError
     * @throws ReflectionException
     */
    #[Test]
    public function delete_removing_absent_member_returns_member_list(): void
    {
        $this->setRequestParams([
            'id_team'    => (string) $this->teamId(self::BASE),
            'uid_member' => '88888888',
        ]);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('members', $data);
                $this->assertArrayHasKey('pending_invitations', $data);
                $this->assertIsArray($data['members']);
                return true;
            }));

        $this->controller->delete();
    }
}
