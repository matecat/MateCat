<?php

namespace Matecat\Core\Controllers;

use Controller\API\V2\ChangePasswordController;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Logger\MatecatLogger;

/**
 * Reserved ID block (Playbook §4): base = 9_032_000 (task N=32).
 *   base+1 project, base+2 job, base+3 segment, base+4 file,
 *   base+5 team, base+6 user/uid, base+12 teams_users row.
 * Per-suite owner email: ctrltest_9032000@example.org (never shared test@example.org).
 */
class TestableChangePasswordController extends ChangePasswordController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class ChangePasswordControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_032_000;
    private const string PROJECT_PASSWORD = 'cp_projpw';

    /** @var ReflectionClass<ChangePasswordController> */
    private ReflectionClass $reflector;
    private TestableChangePasswordController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTestData();

        $this->controller = new TestableChangePasswordController();
        $this->reflector = new ReflectionClass(ChangePasswordController::class);

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
        $this->setProp('featureSet', new FeatureSet());
    }

    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    private const string JOB_PASSWORD = 'cp_jobpw';

    private function seedTestData(): void
    {
        $owner = $this->ownerEmail(self::BASE);
        $this->seedUser(self::BASE, $owner);
        $this->seedTeam(self::BASE);
        $this->seedMembership(self::BASE);
        $this->seedProject(self::BASE, $owner, self::PROJECT_PASSWORD);
        $this->seedFile(self::BASE);
        $this->seedSegment(self::BASE);
        $this->seedJob(self::BASE, $owner, self::JOB_PASSWORD);
        $this->seedSegmentTranslation(self::BASE);
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
        $serverParams = ['REQUEST_URI' => '/api/v2/jobs/change-password', 'REQUEST_METHOD' => 'POST'];
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

    // ─── changePassword() validation failures ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changePassword_throws_when_id_is_empty(): void
    {
        $this->setRequestParams(['password' => 'somepass']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required parameters [`id `, `password`]');

        $this->controller->changePassword();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changePassword_throws_when_password_is_empty(): void
    {
        $this->setRequestParams(['id' => '123']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required parameters [`id `, `password`]');

        $this->controller->changePassword();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changePassword_throws_when_undo_without_new_password(): void
    {
        $this->setRequestParams([
            'id' => '123',
            'password' => 'oldpass',
            'undo' => 'true',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required parameters [`new_password`]');

        $this->controller->changePassword();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changePassword_throws_on_invalid_revision_number(): void
    {
        $this->setRequestParams([
            'id' => '123',
            'password' => 'oldpass',
            'revision_number' => '9',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for parameter `revision_number`. Allowed values [1, 2]');

        $this->controller->changePassword();
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changePassword_throws_on_invalid_res(): void
    {
        $this->setRequestParams([
            'id' => '123',
            'password' => 'oldpass',
            'res' => 'bogus',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for parameter `res`. Allowed values [`prj`, `job`]');

        $this->controller->changePassword();
    }

    // ─── changePassword() happy path: project password change ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changePassword_project_returns_payload_with_id_and_new_pwd(): void
    {
        $projectId = $this->projectId(self::BASE);

        $this->setRequestParams([
            'res' => 'prj',
            'id' => (string) $projectId,
            'password' => self::PROJECT_PASSWORD,
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->changePassword();

        $this->assertIsArray($captured);
        $this->assertSame((string) $projectId, $captured['id']);
        $this->assertSame(self::PROJECT_PASSWORD, $captured['old_pwd']);
        $this->assertArrayHasKey('new_pwd', $captured);
        $this->assertNotEmpty($captured['new_pwd']);
        $this->assertNotSame(self::PROJECT_PASSWORD, $captured['new_pwd']);

        // Verify the password was actually persisted under the new value.
        $reloaded = (new ProjectDao(\Model\DataAccess\Database::obtain()))
            ->findByIdAndPassword($projectId, $captured['new_pwd']);
        $this->assertInstanceOf(ProjectStruct::class, $reloaded);
        $this->assertSame($projectId, $reloaded->id);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changePassword_project_undo_uses_supplied_new_password(): void
    {
        $projectId = $this->projectId(self::BASE);
        $explicitNewPwd = 'cp_undo_pwd';

        $this->setRequestParams([
            'res' => 'prj',
            'id' => (string) $projectId,
            'password' => self::PROJECT_PASSWORD,
            'undo' => 'true',
            'new_password' => $explicitNewPwd,
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->changePassword();

        $this->assertIsArray($captured);
        $this->assertSame($explicitNewPwd, $captured['new_pwd']);
        $this->assertSame(self::PROJECT_PASSWORD, $captured['old_pwd']);
    }

    // ─── changePassword() happy path: job password change ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changePassword_job_returns_payload_and_persists_new_password(): void
    {
        $jobId = $this->jobId(self::BASE);

        $this->setRequestParams([
            'res' => 'job',
            'id' => (string) $jobId,
            'password' => self::JOB_PASSWORD,
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->changePassword();

        $this->assertIsArray($captured);
        $this->assertSame((string) $jobId, $captured['id']);
        $this->assertSame(self::JOB_PASSWORD, $captured['old_pwd']);
        $this->assertNotEmpty($captured['new_pwd']);

        $reloaded = (new \Model\Jobs\JobDao(\Model\DataAccess\Database::obtain()))
            ->getByIdAndPassword($jobId, $captured['new_pwd']);
        $this->assertInstanceOf(\Model\Jobs\JobStruct::class, $reloaded);
        $this->assertSame($jobId, $reloaded->id);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changePassword_job_revision_updates_review_password(): void
    {
        $jobId = $this->jobId(self::BASE);
        $this->seedChunkReview(self::BASE, self::JOB_PASSWORD, 'cp_revpw', 2);

        $this->setRequestParams([
            'res' => 'job',
            'id' => (string) $jobId,
            'password' => 'cp_revpw',
            'revision_number' => '1',
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->changePassword();

        $this->assertIsArray($captured);
        $this->assertSame((string) $jobId, $captured['id']);
        $this->assertSame('cp_revpw', $captured['old_pwd']);
        $this->assertNotEmpty($captured['new_pwd']);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changeThePassword_job_throws_when_job_not_found(): void
    {
        $user = $this->controller->getUser();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Job not found');

        $this->invokePrivate('changeThePassword', [
            $user,
            'job',
            $this->jobId(self::BASE),
            'wrong_job_password_zzz',
            'whatever_new',
            null,
        ]);
    }

    // ─── changeThePassword() / checkUserPermissions() failure branches ───

    /**
     * @throws \Throwable
     */
    #[Test]
    public function changeThePassword_project_throws_not_found_for_wrong_password(): void
    {
        $user = $this->controller->getUser();

        $this->expectException(\Model\Exceptions\NotFoundException::class);

        $this->invokePrivate('changeThePassword', [
            $user,
            'prj',
            $this->projectId(self::BASE),
            'wrong_password_zzz',
            'whatever_new',
            null,
        ]);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function checkUserPermissions_throws_when_user_not_in_team(): void
    {
        $projectId = $this->projectId(self::BASE);
        $project = (new ProjectDao(\Model\DataAccess\Database::obtain()))
            ->findByIdAndPassword($projectId, self::PROJECT_PASSWORD);

        $stranger = new UserStruct();
        $stranger->uid = self::BASE + 999;
        $stranger->email = 'stranger_' . self::BASE . '@example.org';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The logged user does not belong to the right team');

        $this->invokePrivate('checkUserPermissions', [$project, $stranger]);
    }

    /**
     * @throws \Throwable
     */
    #[Test]
    public function checkUserPermissions_passes_for_team_member(): void
    {
        $projectId = $this->projectId(self::BASE);
        $project = (new ProjectDao(\Model\DataAccess\Database::obtain()))
            ->findByIdAndPassword($projectId, self::PROJECT_PASSWORD);

        $user = $this->controller->getUser();

        // No exception => member permitted. Reflection invoke returns null (void).
        $result = $this->invokePrivate('checkUserPermissions', [$project, $user]);
        $this->assertNull($result);
    }
}
