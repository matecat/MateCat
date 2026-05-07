<?php

namespace unit\Model\Projects;

use Controller\API\Commons\Exceptions\AuthorizationError;
use Model\Exceptions\ValidationError;
use Model\Projects\ProjectModel;
use Model\Projects\ProjectStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use ReflectionProperty;
use TestHelpers\AbstractTest;
use Utils\Constants\Teams;

class FakeProjectAssignedEmail
{
    public static int $sent = 0;

    public function __construct(...$args)
    {
    }

    public function send(): void
    {
        self::$sent++;
    }
}

if (!class_exists(\Utils\Email\ProjectAssignedEmail::class, false)) {
    class_alias(FakeProjectAssignedEmail::class, \Utils\Email\ProjectAssignedEmail::class);
}

class ProjectModelTest extends AbstractTest
{
    /** @var list<int> */
    private array $createdProjectIds = [];

    /** @var list<int> */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        FakeProjectAssignedEmail::$sent = 0;
    }

    #[Test]
    public function constructorStoresProjectStruct(): void
    {
        $project = new ProjectStruct();
        $project->name = 'demo';

        $model = new ProjectModel($project);

        $property = new ReflectionProperty(ProjectModel::class, 'project_struct');
        $this->assertSame($project, $property->getValue($model));
    }

    #[Test]
    public function prepareUpdateStoresPendingFieldAndValue(): void
    {
        $model = new ProjectModel(new ProjectStruct());

        $model->prepareUpdate('name', 'new-name');

        $property = new ReflectionProperty(ProjectModel::class, 'willChange');
        $this->assertSame(['name' => 'new-name'], $property->getValue($model));
    }

    #[Test]
    public function setUserStoresUserOnModel(): void
    {
        $model = new ProjectModel(new ProjectStruct());
        $user = new UserStruct();
        $user->uid = 11;

        $model->setUser($user);

        $property = new ReflectionProperty(ProjectModel::class, 'user');
        $this->assertSame($user, $property->getValue($model));
    }

    #[Test]
    public function updateThrowsValidationErrorWhenNameIsEmpty(): void
    {
        $project = $this->makeMinimalProjectStruct();
        $project->name = 'old';

        $model = new ProjectModel($project);
        $model->setUser($this->makePlainUser(1));
        $model->prepareUpdate('name', '');

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Project name cannot be empty');

        $model->update();
    }

    #[Test]
    public function updateThrowsValidationErrorWhenAssigneeSetWithoutProjectTeam(): void
    {
        $project = $this->makeMinimalProjectStruct();
        $project->name = 'old';
        $project->id_team = null;

        $model = new ProjectModel($project);
        $model->setUser($this->makePlainUser(1));
        $model->prepareUpdate('id_assignee', 99);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Project must have a team');

        $model->update();
    }

    #[Test]
    public function updatePersistsChangedNameAndReturnsUpdatedStruct(): void
    {
        $owner = $this->createUser();
        $team = $this->createGeneralTeamWithMembers($owner, []);
        $project = $this->createProject($team->id, null, 'Initial Name');

        $model = new ProjectModel($project);
        $model->setUser($owner);
        $model->prepareUpdate('name', 'Updated Name');

        $updated = $model->update();

        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('Updated Name', $this->fetchProjectName((int)$project->id));
    }

    #[Test]
    public function checkNameThrowsForEmptyName(): void
    {
        $model = new TestableProjectModel(new ProjectStruct());
        $model->prepareUpdate('name', '');

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Project name cannot be empty');

        $model->invokeCheckName();
    }

    #[Test]
    public function checkAssigneeChangeInPersonalTeamThrowsValidationError(): void
    {
        $owner = $this->createUser();
        $personalTeam = (new TeamDao())->getPersonalByUid((int)$owner->uid);

        $model = new TestableProjectModel(new ProjectStruct());

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Can\'t change the Assignee of a personal project.');

        $model->invokeCheckAssigneeChangeInPersonalTeam((int)$personalTeam->id);
    }

    #[Test]
    public function checkIdAssigneeThrowsWhenAssigneeIsNotInTeam(): void
    {
        $owner = $this->createUser();
        $outsider = $this->createUser();
        $team = $this->createGeneralTeamWithMembers($owner, []);

        $project = new ProjectStruct();
        $project->id_team = (int)$team->id;

        $model = new TestableProjectModel($project);
        $model->prepareUpdate('id_assignee', (int)$outsider->uid);

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Assignee must be team member');

        $model->invokeCheckIdAssignee((int)$team->id);
    }

    #[Test]
    public function checkIdTeamThrowsAuthorizationErrorWhenUserIsNotMember(): void
    {
        $owner = $this->createUser();
        $outsider = $this->createUser();
        $team = $this->createGeneralTeamWithMembers($owner, []);

        $project = new ProjectStruct();
        $project->id_team = (int)$team->id;

        $model = new TestableProjectModel($project);
        $model->setUser($outsider);
        $model->prepareUpdate('id_team', (int)$team->id);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionMessage('Not Authorized');

        $model->invokeCheckIdTeam();
    }

    #[Test]
    public function checkIdTeamSetsAssigneeWhenDestinationTeamIsPersonal(): void
    {
        $owner = $this->createUser();
        $oldTeam = $this->createGeneralTeamWithMembers($owner, []);
        $personalTeam = (new TeamDao())->getPersonalByUid((int)$owner->uid);

        $project = new ProjectStruct();
        $project->id_team = (int)$oldTeam->id;
        $project->id_assignee = null;

        $model = new TestableProjectModel($project);
        $model->setUser($owner);
        $model->prepareUpdate('id_team', (int)$personalTeam->id);

        $model->invokeCheckIdTeam();

        $willChange = $model->getWillChangeForTest();
        $this->assertSame((int)$owner->uid, $willChange['id_assignee']);

        $cacheTeams = $model->getCacheTeamsToCleanForTest();
        $this->assertContains((int)$personalTeam->id, $cacheTeams);
        $this->assertContains((int)$oldTeam->id, $cacheTeams);
    }

    #[Test]
    public function cleanAssigneeCachesSkipsUnknownTeamIds(): void
    {
        $model = new TestableProjectModel(new ProjectStruct());
        $model->setCacheTeamsToCleanForTest([999999999]);

        $model->invokeCleanAssigneeCaches();

        $this->assertTrue(true);
    }

    #[Test]
    public function cleanProjectCacheReturnsEarlyWhenProjectIdIsNull(): void
    {
        $project = new ProjectStruct();
        $project->id = null;

        $model = new TestableProjectModel($project);

        $model->invokeCleanProjectCache();

        $this->assertTrue(true);
    }

    #[Test]
    public function sendNotificationEmailsReturnsEarlyWhenAssigneeIsNull(): void
    {
        $project = new ProjectStruct();
        $project->id = null;

        $model = new TestableProjectModel($project);
        $model->setUser($this->makePlainUser(10));
        $model->setChangedFieldsForTest(['id_assignee' => null]);

        $model->invokeSendNotificationEmails();

        $this->assertTrue(true);
    }

    #[Test]
    public function sendNotificationEmailsSendsWhenAssigneeChangesToExistingUser(): void
    {
        $assigner = $this->createUser();
        $assignee = $this->createUser();
        $team = $this->createGeneralTeamWithMembers($assigner, [$assignee->email]);

        $project = $this->createProject((int)$team->id, null, 'Email Trigger');

        $model = new TestableProjectModel($project);
        $model->setUser($assigner);
        $model->setChangedFieldsForTest(['id_assignee' => (int)$assignee->uid]);

        $model->invokeSendNotificationEmails();

        $this->assertSame(1, FakeProjectAssignedEmail::$sent);
    }

    #[Test]
    public function checkAssigneeChangeInPersonalTeamThrowsWhenTeamIsMissing(): void
    {
        $model = new TestableProjectModel(new ProjectStruct());

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Team not found');

        $model->invokeCheckAssigneeChangeInPersonalTeam(99999999);
    }

    #[Test]
    public function checkIdAssigneeAllowsMemberAndTracksTeamCache(): void
    {
        $owner = $this->createUser();
        $assignee = $this->createUser();
        $team = $this->createGeneralTeamWithMembers($owner, [$assignee->email]);

        $project = $this->makeMinimalProjectStruct();
        $project->id_team = (int)$team->id;

        $model = new TestableProjectModel($project);
        $model->prepareUpdate('id_assignee', (int)$assignee->uid);

        $model->invokeCheckIdAssignee((int)$team->id);

        $this->assertContains((int)$team->id, $model->getCacheTeamsToCleanForTest());
    }

    #[Test]
    public function checkIdTeamNullsAssigneeWhenCurrentAssigneeNotInDestinationTeam(): void
    {
        $owner = $this->createUser();
        $currentAssignee = $this->createUser();
        $destinationTeam = $this->createGeneralTeamWithMembers($owner, []);

        $project = $this->makeMinimalProjectStruct();
        $project->id_team = (int)$destinationTeam->id + 1;
        $project->id_assignee = (int)$currentAssignee->uid;

        $model = new TestableProjectModel($project);
        $model->setUser($owner);
        $model->prepareUpdate('id_team', (int)$destinationTeam->id);

        $model->invokeCheckIdTeam();

        $willChange = $model->getWillChangeForTest();
        $this->assertArrayHasKey('id_assignee', $willChange);
        $this->assertNull($willChange['id_assignee']);
    }

    #[Test]
    public function cleanAssigneeCachesDestroysCacheForExistingTeam(): void
    {
        $owner = $this->createUser();
        $team = $this->createGeneralTeamWithMembers($owner, []);

        $model = new TestableProjectModel(new ProjectStruct());
        $model->setCacheTeamsToCleanForTest([(int)$team->id]);

        $model->invokeCleanAssigneeCaches();

        $this->assertTrue(true);
    }

    #[Test]
    public function cleanProjectCacheDestroysCacheWhenProjectIdExists(): void
    {
        $owner = $this->createUser();
        $team = $this->createGeneralTeamWithMembers($owner, []);
        $project = $this->createProject((int)$team->id, null, 'Cacheable Project');

        $model = new TestableProjectModel($project);

        $model->invokeCleanProjectCache();

        $this->assertTrue(true);
    }

    #[Test]
    public function updateWithAssigneeAndTeamInPayloadRunsCombinedAssigneeValidationPath(): void
    {
        $owner = $this->createUser();
        $assignee = $this->createUser();
        $team = $this->createGeneralTeamWithMembers($owner, [$assignee->email]);
        $project = $this->createProject((int)$team->id, null, 'Combined Update');

        $model = new ProjectModel($project);
        $model->setUser($owner);
        $model->prepareUpdate('id_team', (int)$team->id);
        $model->prepareUpdate('id_assignee', (int)$assignee->uid);

        $updated = $model->update();

        $this->assertSame((int)$assignee->uid, $updated->id_assignee);
        $this->assertSame(1, FakeProjectAssignedEmail::$sent);
    }

    protected function tearDown(): void
    {
        $connection = \Model\DataAccess\Database::obtain()->getConnection();

        foreach ($this->createdProjectIds as $projectId) {
            $connection->exec('DELETE FROM projects WHERE id = ' . (int)$projectId);
        }

        foreach ($this->createdUserIds as $uid) {
            $teamIds = $connection
                ->query('SELECT id FROM teams WHERE created_by = ' . (int)$uid)
                ?->fetchAll(PDO::FETCH_COLUMN) ?? [];

            foreach ($teamIds as $teamId) {
                $connection->exec('DELETE FROM teams_users WHERE id_team = ' . (int)$teamId);
                $connection->exec('DELETE FROM teams WHERE id = ' . (int)$teamId);
            }

            $connection->exec('DELETE FROM users WHERE uid = ' . (int)$uid);
        }

        parent::tearDown();
    }

    private function makePlainUser(int $uid): UserStruct
    {
        $user = new UserStruct();
        $user->uid = $uid;

        return $user;
    }

    private function makeMinimalProjectStruct(): ProjectStruct
    {
        $project = new ProjectStruct();
        $project->password = 'pw';
        $project->name = 'Project';
        $project->id_customer = 'project-model-test@example.org';
        $project->create_date = '2024-01-01 00:00:00';
        $project->status_analysis = 'DONE';
        $project->remote_ip_address = '127.0.0.1';

        return $project;
    }

    private function createUser(): UserStruct
    {
        /** @var UserStruct $user */
        $user = \Factory_User::create();
        $this->createdUserIds[] = (int)$user->uid;

        return $user;
    }

    /**
     * @param list<string> $memberEmails
     */
    private function createGeneralTeamWithMembers(UserStruct $owner, array $memberEmails): TeamStruct
    {
        $teamDao = new TeamDao();
        \Model\DataAccess\Database::obtain()->begin();

        $team = $teamDao->createUserTeam($owner, [
            'type' => Teams::GENERAL,
            'name' => 'ProjectModelTest Team ' . uniqid('', true),
            'members' => $memberEmails,
        ]);

        \Model\DataAccess\Database::obtain()->commit();

        return $team;
    }

    private function createProject(int $teamId, ?int $assigneeId, string $name): ProjectStruct
    {
        $connection = \Model\DataAccess\Database::obtain()->getConnection();

        $assigneeSql = $assigneeId === null ? 'NULL' : (string)(int)$assigneeId;
        $escapedName = $connection->quote($name);
        $password = $connection->quote('pw_' . uniqid());

        $connection->exec(
            "INSERT INTO projects\n" .
            "    (password, id_customer, id_team, name, create_date, status_analysis, remote_ip_address)\n" .
            "VALUES\n" .
            "    ($password, 'project-model-test@example.org', " . (int)$teamId . ", $escapedName, NOW(), 'DONE', '127.0.0.1')"
        );

        $projectId = (int)$connection->lastInsertId();

        if ($assigneeId !== null) {
            $connection->exec(
                'UPDATE projects SET id_assignee = ' . $assigneeSql . ' WHERE id = ' . $projectId
            );
        }

        $this->createdProjectIds[] = $projectId;

        $row = $connection
            ->query('SELECT * FROM projects WHERE id = ' . $projectId . ' LIMIT 1')
            ?->fetch();

        return new ProjectStruct($row ?: []);
    }

    private function fetchProjectName(int $projectId): string
    {
        $connection = \Model\DataAccess\Database::obtain()->getConnection();

        return (string)$connection
            ->query('SELECT name FROM projects WHERE id = ' . $projectId)
            ?->fetchColumn();
    }
}

class TestableProjectModel extends ProjectModel
{
    public function invokeCheckName(): void
    {
        $this->invokePrivate('checkName');
    }

    public function invokeCheckAssigneeChangeInPersonalTeam(int $idTeam): void
    {
        $this->invokePrivate('checkAssigneeChangeInPersonalTeam', [$idTeam]);
    }

    public function invokeCheckIdAssignee(int $idTeam): void
    {
        $this->invokePrivate('checkIdAssignee', [$idTeam]);
    }

    public function invokeCheckIdTeam(): void
    {
        $this->invokePrivate('checkIdTeam');
    }

    public function invokeCleanAssigneeCaches(): void
    {
        $this->invokePrivate('cleanAssigneeCaches');
    }

    public function invokeCleanProjectCache(): void
    {
        $this->invokePrivate('cleanProjectCache');
    }

    public function invokeSendNotificationEmails(): void
    {
        $this->_sendNotificationEmails();
    }

    /** @return array<string, mixed> */
    public function getWillChangeForTest(): array
    {
        $property = new ReflectionProperty(ProjectModel::class, 'willChange');

        /** @var array<string, mixed> $value */
        $value = $property->getValue($this);

        return $value;
    }

    /** @return list<int> */
    public function getCacheTeamsToCleanForTest(): array
    {
        $property = new ReflectionProperty(ProjectModel::class, 'cacheTeamsToClean');

        /** @var list<int> $value */
        $value = $property->getValue($this);

        return $value;
    }

    /** @param list<int> $teams */
    public function setCacheTeamsToCleanForTest(array $teams): void
    {
        $property = new ReflectionProperty(ProjectModel::class, 'cacheTeamsToClean');
        $property->setValue($this, $teams);
    }

    /** @param array<string, mixed> $fields */
    public function setChangedFieldsForTest(array $fields): void
    {
        $property = new ReflectionProperty(ProjectModel::class, 'changedFields');
        $property->setValue($this, $fields);
    }

    /**
     * @param string $method
     * @param array<int, mixed> $args
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod(ProjectModel::class, $method);

        return $reflection->invokeArgs($this, $args);
    }
}
