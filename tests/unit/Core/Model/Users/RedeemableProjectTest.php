<?php

namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobDao;
use Model\Projects\ProjectDao;
use Model\Teams\TeamDao;
use Model\Users\RedeemableProject;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class RedeemableProjectTest extends AbstractTest
{
    // High fixed id range to avoid collisions with real/other fixtures.
    private const int USER_UID    = 9991001;
    private const int TEAM_ID     = 9991002;
    private const int PROJECT_ID  = 9991003;
    private const int JOB_ID      = 9991004;

    private const string USER_EMAIL = 'redeemable-fixture@matecat.com';

    private IDatabase $database;
    private TeamDao $teamDao;
    private ProjectDao $projectDao;
    private JobDao $jobDao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->database = obtainTestDatabase(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );

        $this->teamDao    = new TeamDao($this->database);
        $this->projectDao = new ProjectDao($this->database);
        $this->jobDao     = new JobDao($this->database);

        $this->cleanFixtures();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanFixtures();

        $flusher = (new RedisHandler())->getConnection();
        $flusher->flushdb();

        parent::tearDown();
    }

    /**
     * Seed the minimal chain required by RedeemableProject:
     *  - users  : the real redeeming user (provides email / uid)
     *  - teams  : the user's personal team -> TeamDao::getPersonalByUser()
     *  - projects : ProjectDao::findById() + updateStruct() target
     *  - jobs   : JobDao::updateOwner() target (WHERE id_project)
     */
    private function seedFixtures(): void
    {
        $conn = $this->database->getConnection();

        $conn->prepare(
            "INSERT INTO users (uid, email, create_date, first_name, last_name)
             VALUES (?, ?, NOW(), 'Red', 'Eemer')"
        )->execute([self::USER_UID, self::USER_EMAIL]);

        $conn->prepare(
            "INSERT INTO teams (id, name, created_by, created_at, `type`)
             VALUES (?, 'Personal', ?, NOW(), 'personal')"
        )->execute([self::TEAM_ID, self::USER_UID]);
    }

    /**
     * Insert the seeded project. Done per-test (not in setUp) so the "not present"
     * cases can run against a project id that does not exist.
     */
    private function seedProject(): void
    {
        $this->database->getConnection()->prepare(
            "INSERT INTO projects (id, password, id_customer, name, create_date, status_analysis)
             VALUES (?, 'redeempwd', 'old-customer@matecat.com', 'Redeemable Project', NOW(), 'DONE')"
        )->execute([self::PROJECT_ID]);
    }

    private function seedJob(): void
    {
        $this->database->getConnection()->prepare(
            "INSERT INTO jobs (id, password, id_project, job_first_segment, job_last_segment, tm_keys, create_date, disabled, owner)
             VALUES (?, 'jobpwd', ?, 1, 10, '', NOW(), 0, 'old-owner@matecat.com')"
        )->execute([self::JOB_ID, self::PROJECT_ID]);
    }

    private function cleanFixtures(): void
    {
        $conn = $this->database->getConnection();
        $conn->exec("DELETE FROM jobs WHERE id = " . self::JOB_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::PROJECT_ID);
        $conn->exec("DELETE FROM teams WHERE id = " . self::TEAM_ID);
        $conn->exec("DELETE FROM users WHERE uid = " . self::USER_UID);
    }

    /**
     * A lightweight stub user for the pure-session tests that never touch the DB.
     */
    private function stubUser(): UserStruct
    {
        return $this->createStub(UserStruct::class);
    }

    /**
     * The real seeded user, used by tests that exercise getPersonalTeam() / updateOwner().
     */
    private function realUser(): UserStruct
    {
        $user = new UserStruct();
        $user->uid        = self::USER_UID;
        $user->email      = self::USER_EMAIL;
        $user->first_name = 'Red';
        $user->last_name  = 'Eemer';

        return $user;
    }

    /**
     * @param array<string, mixed> $session
     */
    private function make(array &$session, ?UserStruct $user = null): RedeemableProject
    {
        return new RedeemableProject($user ?? $this->stubUser(), $session, $this->teamDao);
    }

    private function refetchProjectCustomer(): ?string
    {
        $stmt = $this->database->getConnection()->prepare("SELECT id_customer FROM projects WHERE id = ?");
        $stmt->execute([self::PROJECT_ID]);
        $val = $stmt->fetchColumn();

        return $val === false ? null : (string)$val;
    }

    private function refetchProjectTeam(): ?int
    {
        $stmt = $this->database->getConnection()->prepare("SELECT id_team FROM projects WHERE id = ?");
        $stmt->execute([self::PROJECT_ID]);
        $val = $stmt->fetchColumn();

        return $val === false || $val === null ? null : (int)$val;
    }

    private function refetchProjectAssignee(): ?int
    {
        $stmt = $this->database->getConnection()->prepare("SELECT id_assignee FROM projects WHERE id = ?");
        $stmt->execute([self::PROJECT_ID]);
        $val = $stmt->fetchColumn();

        return $val === false || $val === null ? null : (int)$val;
    }

    private function refetchJobOwner(): ?string
    {
        $stmt = $this->database->getConnection()->prepare("SELECT owner FROM jobs WHERE id = ?");
        $stmt->execute([self::JOB_ID]);
        $val = $stmt->fetchColumn();

        return $val === false ? null : (string)$val;
    }

    // --- isRedeemable() (pure session, no DB) ---

    public function testIsRedeemableReturnsFalseByDefault(): void
    {
        $session = [];
        $rp = $this->make($session);

        $this->assertFalse($rp->isRedeemable());
    }

    public function testIsRedeemableReturnsTrueWhenSessionFlagSet(): void
    {
        $session = ['redeem_project' => true];
        $rp = $this->make($session);

        $this->assertTrue($rp->isRedeemable());
    }

    public function testIsRedeemableReturnsFalseWhenFlagNotTrue(): void
    {
        $session = ['redeem_project' => 'yes'];
        $rp = $this->make($session);

        $this->assertFalse($rp->isRedeemable());
    }

    // --- clear() (pure session, no DB) ---

    public function testClearRemovesSessionKeys(): void
    {
        $session = ['redeem_project' => true, 'last_created_pid' => 1, 'other' => 'kept'];
        $rp = $this->make($session);

        $rp->clear();

        $this->assertArrayNotHasKey('redeem_project', $session);
        $this->assertArrayNotHasKey('last_created_pid', $session);
        $this->assertArrayHasKey('other', $session);
    }

    // --- isPresent() ---

    public function testIsPresentReturnsFalseWhenNoSessionPid(): void
    {
        $session = [];
        $rp = $this->make($session);

        // No 'last_created_pid' -> never queries the DB.
        $this->assertFalse($rp->isPresent());
    }

    public function testIsPresentReturnsFalseWhenProjectNotFound(): void
    {
        // Point the session at an id that does not exist in the projects table.
        $session = ['last_created_pid' => 9999999];
        $rp = $this->make($session);

        $this->assertFalse($rp->isPresent());
        $this->assertNull($rp->getProject());
    }

    public function testIsPresentReturnsTrueWhenProjectFound(): void
    {
        $this->seedProject();

        $session = ['last_created_pid' => self::PROJECT_ID];
        $rp = $this->make($session);

        $this->assertTrue($rp->isPresent());
    }

    public function testGetProjectReturnsProjectAfterIsPresent(): void
    {
        $this->seedProject();

        $session = ['last_created_pid' => self::PROJECT_ID];
        $rp = $this->make($session);

        $rp->isPresent();
        $project = $rp->getProject();

        $this->assertNotNull($project);
        $this->assertSame(self::PROJECT_ID, $project->id);
    }

    public function testGetProjectReturnsNullInitially(): void
    {
        $session = [];
        $rp = $this->make($session);

        $this->assertNull($rp->getProject());
    }

    // --- redeem() / tryToRedeem() against real persistence ---

    public function testRedeemWritesProjectAndJobWhenPresentAndRedeemable(): void
    {
        $this->seedProject();
        $this->seedJob();

        $session = ['last_created_pid' => self::PROJECT_ID, 'redeem_project' => true];
        $rp = $this->make($session, $this->realUser());

        $rp->redeem();

        // In-memory struct mutated.
        $project = $rp->getProject();
        $this->assertNotNull($project);
        $this->assertSame(self::USER_EMAIL, $project->id_customer);
        $this->assertSame(self::TEAM_ID, $project->id_team);
        $this->assertSame(self::USER_UID, $project->id_assignee);

        // Real UPDATE landed in the DB (ProjectDao::updateStruct).
        $this->assertSame(self::USER_EMAIL, $this->refetchProjectCustomer());
        $this->assertSame(self::TEAM_ID, $this->refetchProjectTeam());
        $this->assertSame(self::USER_UID, $this->refetchProjectAssignee());

        // Real UPDATE landed in the DB (JobDao::updateOwner).
        $this->assertSame(self::USER_EMAIL, $this->refetchJobOwner());

        // Session keys cleared by redeem().
        $this->assertArrayNotHasKey('redeem_project', $session);
        $this->assertArrayNotHasKey('last_created_pid', $session);
    }

    public function testTryToRedeemWritesWhenEligible(): void
    {
        $this->seedProject();
        $this->seedJob();

        $session = ['last_created_pid' => self::PROJECT_ID, 'redeem_project' => true];
        $rp = $this->make($session, $this->realUser());

        $rp->tryToRedeem();

        $this->assertSame(self::USER_EMAIL, $this->refetchProjectCustomer());
        $this->assertSame(self::TEAM_ID, $this->refetchProjectTeam());
        $this->assertSame(self::USER_UID, $this->refetchProjectAssignee());
        $this->assertSame(self::USER_EMAIL, $this->refetchJobOwner());
    }

    public function testRedeemSkipsWhenNotPresent(): void
    {
        // No project row, session redeemable but pointing at a missing pid.
        $this->seedJob();
        $session = ['redeem_project' => true, 'last_created_pid' => 9999999];
        $rp = $this->make($session, $this->realUser());

        $rp->redeem();

        // Job owner must NOT have been rewritten.
        $this->assertSame('old-owner@matecat.com', $this->refetchJobOwner());
        // Session still cleared.
        $this->assertArrayNotHasKey('redeem_project', $session);
    }

    public function testRedeemSkipsWhenNotRedeemable(): void
    {
        $this->seedProject();
        $this->seedJob();

        // Present but redeem flag absent.
        $session = ['last_created_pid' => self::PROJECT_ID];
        $rp = $this->make($session, $this->realUser());

        $rp->redeem();

        // No mutation to project or job.
        $this->assertSame('old-customer@matecat.com', $this->refetchProjectCustomer());
        $this->assertNull($this->refetchProjectTeam());
        $this->assertNull($this->refetchProjectAssignee());
        $this->assertSame('old-owner@matecat.com', $this->refetchJobOwner());
    }

    public function testTryToRedeemDoesNothingWhenNotEligible(): void
    {
        $this->seedProject();
        $this->seedJob();

        $session = [];
        $rp = $this->make($session, $this->realUser());

        $rp->tryToRedeem();

        $this->assertSame('old-customer@matecat.com', $this->refetchProjectCustomer());
        $this->assertSame('old-owner@matecat.com', $this->refetchJobOwner());
    }

    // --- getDestinationURL() ---

    public function testGetDestinationURLReturnsNullWhenNotPresent(): void
    {
        $session = [];
        $rp = $this->make($session);

        $this->assertNull($rp->getDestinationURL());
    }

    public function testGetDestinationURLReturnsUrlWhenPresent(): void
    {
        $this->seedProject();

        $session = ['last_created_pid' => self::PROJECT_ID];
        $rp = $this->make($session);

        $url = $rp->getDestinationURL();

        $this->assertNotNull($url);
        $this->assertStringContainsString('/analyze/', $url);
        $this->assertStringContainsString(self::PROJECT_ID . '-redeempwd', $url);
    }

    // --- caching / memoization ---

    public function testGetProjectIsMemoizedAcrossIsPresentCalls(): void
    {
        $this->seedProject();

        $session = ['last_created_pid' => self::PROJECT_ID];
        $rp = $this->make($session);

        $rp->isPresent();
        $first = $rp->getProject();
        $rp->isPresent();
        $second = $rp->getProject();

        // The $this->project guard memoizes the struct on the instance:
        // both calls return the very same object.
        $this->assertNotNull($first);
        $this->assertSame($first, $second);
    }
}
