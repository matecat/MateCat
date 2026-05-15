<?php

namespace Model\Projects;

use Controller\API\Commons\Exceptions\AuthorizationError;
use Exception;
use Model\Exceptions\ValidationError;
use Model\Teams\MembershipDao;
use Model\Teams\MembershipStruct;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PDOException;
use ReflectionException;
use Utils\Constants\Teams;
use Utils\Email\ProjectAssignedEmail;

/**
 * Class ProjectModel
 * @package Model\Projects
 *
 */
class ProjectModel
{

    /**
     * @var ProjectStruct
     */
    protected ProjectStruct $project_struct;

    /** @var array<string, mixed> */
    protected array $willChange = [];
    /** @var array<string, mixed> */
    protected array $changedFields = [];

    /** @var list<int> */
    protected array $cacheTeamsToClean = [];

    /**
     * @var UserStruct
     */
    protected UserStruct $user;

    public function __construct(ProjectStruct $project)
    {
        $this->project_struct = $project;
    }

    public function prepareUpdate(string $field, mixed $value): void
    {
        $this->willChange[$field] = $value;
    }

    public function setUser(UserStruct $user): void
    {
        $this->user = $user;
    }

    /**
     * @return ProjectStruct
     * @throws AuthorizationError
     * @throws ValidationError
     * @throws Exception
     */
    public function update(): ProjectStruct
    {
        $this->changedFields = [];

        $newStruct = new ProjectStruct($this->project_struct->toArray());

        if (isset($this->willChange['name'])) {
            $this->checkName();
        }

        if (
            isset($this->willChange['id_assignee']) &&
            isset($this->willChange['id_team'])
        ) {
            $this->checkIdAssignee($this->willChange['id_team']);
        } elseif (isset($this->willChange['id_assignee'])) {
            $id_team = $this->project_struct->id_team ?? throw new ValidationError('Project must have a team');
            $this->checkIdAssignee($id_team);
        }

        if (isset($this->willChange['id_team'])) {
            $this->checkIdTeam();
            $this->cleanProjectCache();
        }

        foreach ($this->willChange as $field => $value) {
            $newStruct->$field = $value;
        }

        $result = (new ProjectDao())->updateStruct($newStruct, [
            'fields' => array_keys($this->willChange)
        ]);

        if ($result) {
            $this->changedFields = $this->willChange;
            $this->willChange = [];

            $this->_sendNotificationEmails();
        }

        $this->project_struct = $newStruct;

        $this->cleanAssigneeCaches();

        return $this->project_struct;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _sendNotificationEmails(): void
    {
        if (
            ($this->changedFields['id_assignee'] ?? false) &&
            !is_null($this->changedFields['id_assignee']) &&
            $this->user->uid != $this->changedFields['id_assignee']
        ) {
            $assignee = (new UserDao)->getByUid($this->changedFields['id_assignee']);
            if ($assignee === null) {
                return;
            }
            $email = new ProjectAssignedEmail($this->user, $this->project_struct, $assignee);
            $email->send();
        }
    }

    /**
     * @throws ValidationError
     */
    private function checkName(): void
    {
        if (empty($this->willChange['name'])) {
            throw new ValidationError('Project name cannot be empty');
        }
    }

    /**
     * @throws ReflectionException
     * @throws ValidationError
     * @throws Exception
     */
    private function checkAssigneeChangeInPersonalTeam(int $id_team): void
    {
        $teamDao = new TeamDao();
        $team = $teamDao->setCacheTTL(60 * 60 * 24)->fetchById($id_team, TeamStruct::class);
        if ($team === null) {
            throw new ValidationError('Team not found');
        }
        if ($team->type == Teams::PERSONAL) {
            throw new ValidationError('Can\'t change the Assignee of a personal project.');
        }
    }

    /**
     * @throws ReflectionException
     * @throws ValidationError
     * @throws Exception
     */
    private function checkIdAssignee(int $id_team): void
    {
        $membershipDao = new MembershipDao();
        $members = $membershipDao->setCacheTTL(60)->getMemberListByTeamId($id_team);
        $id_assignee = $this->willChange['id_assignee'];
        $found = array_filter($members, function (MembershipStruct $member) use ($id_assignee) {
            return ($id_assignee == $member->uid);
        });

        if (empty($found)) {
            throw new ValidationError('Assignee must be team member');
        }

        $this->checkAssigneeChangeInPersonalTeam($id_team);

        $this->cacheTeamsToClean[] = $id_team;
    }

    /**
     * @throws AuthorizationError
     * @throws ReflectionException
     * @throws Exception
     */
    private function checkIdTeam(): void
    {
        $memberShip = new MembershipDao();

        //choose this method (and use array_filter) instead of findTeamByIdAndUser because the results of this one are cached
        $memberList = $memberShip->setCacheTTL(60)->getMemberListByTeamId($this->willChange['id_team']);

        $found = array_filter($memberList, function ($values) {
            return $values->uid == $this->user->uid;
        });

        if (empty($found)) {
            throw new AuthorizationError("Not Authorized", 403);
        }

        $uid = $this->user->uid ?? throw new AuthorizationError("User UID must not be null", 403);
        $team = (new TeamDao())->setCacheTTL(60 * 60)->getPersonalByUid($uid);

        // check if the destination team is personal, in such a case, set the assignee to the user UID
        if ($team->id == $this->willChange['id_team'] && $team->type == Teams::PERSONAL) {
            $this->willChange['id_assignee'] = $this->user->uid;
            $this->cacheTeamsToClean[] = (int) $this->willChange['id_team'];
            if ($this->project_struct->id_team !== null) {
                $this->cacheTeamsToClean[] = $this->project_struct->id_team;
            }
        }

        // If the project has an assignee and the destination team isn't personal,
        // we have to check if the assignee_id exists in the other team. If not, reset the assignee
        elseif ($this->project_struct->id_assignee) {
            $found = array_filter($memberList, function ($values) {
                return $this->project_struct->id_assignee == $values->uid;
            });

            if (empty($found)) {
                $this->willChange['id_assignee'] = null; //unset the assignee
            } else {
                //clean the cache for the new team member list of assigned projects
                $this->cacheTeamsToClean[] = (int) $this->willChange['id_team'];
            }

            //clean the cache for the old team member list of assigned projects
            if ($this->project_struct->id_team !== null) {
                $this->cacheTeamsToClean[] = $this->project_struct->id_team;
            }
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws PDOException
     */
    private function cleanAssigneeCaches(): void
    {
        $teamDao = new TeamDao();
        $this->cacheTeamsToClean = array_values(array_unique($this->cacheTeamsToClean));
        foreach ($this->cacheTeamsToClean as $team_id) {
            $teamInCacheToClean = $teamDao->setCacheTTL(60 * 60 * 24)->fetchById($team_id, TeamStruct::class);
            if ($teamInCacheToClean === null) {
                continue;
            }
            $teamDao->destroyCacheAssignee($teamInCacheToClean);
        }
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    private function cleanProjectCache(): void
    {
        $id = $this->project_struct->id;
        if ($id === null) {
            return;
        }
        $projectDao = new ProjectDao();
        $projectDao->destroyFetchByIdCache($id, ProjectStruct::class);
    }

}