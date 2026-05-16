<?php

namespace Model\Teams;

use DomainException;
use Exception;
use InvalidArgumentException;
use Model\DataAccess\Database;
use Model\Projects\ProjectDao;
use Model\Users\UserStruct;
use PDOException;
use ReflectionException;
use RuntimeException;
use TypeError;
use Utils\Constants\Teams;
use Utils\Email\InvitedToTeamEmail;
use Utils\Email\MembershipCreatedEmail;
use Utils\Email\MembershipDeletedEmail;
use Utils\Redis\RedisHandler;

class TeamModel
{

    /** @var list<string> */
    protected array $member_emails = [];

    /**
     * @var TeamStruct
     */
    protected TeamStruct $struct;

    /**
     * @var UserStruct
     */
    protected UserStruct $user;

    /**
     * @var MembershipStruct[]
     */
    protected array $new_memberships = [];

    /** @var list<int> */
    protected array $uids_to_remove = [];

    /**
     * @var UserStruct[]
     */
    protected array $removed_users = [];

    /**
     * @var MembershipStruct[]
     */
    protected array $all_memberships;

    public function __construct(TeamStruct $struct)
    {
        $this->struct = $struct;
    }

    public function addMemberEmail(string $email): void
    {
        $this->member_emails[] = $email;
    }

    public function setUser(UserStruct $user): void
    {
        $this->user = $user;
    }

    /**
     * @param list<string> $emails
     */
    public function addMemberEmails(array $emails): void
    {
        foreach ($emails as $email) {
            $this->addMemberEmail($email);
        }
    }

    /**
     * @param list<int> $uids
     */
    public function removeMemberUids(array $uids): void
    {
        $this->uids_to_remove = array_merge($this->uids_to_remove, $uids);
    }

    /**
     * Updated member list.
     *
     * @return MembershipStruct[] the full list of members after the update.
     * @throws ReflectionException
     * @throws Exception
     * @throws PDOException
     * @throws TypeError
     */
    public function updateMembers(): array
    {
        $this->removed_users = [];

        $teamId = $this->getTeamId();

        Database::obtain()->begin();

        $membershipDao = new MembershipDao();

        if (!empty($this->member_emails)) {
            $this->_checkAddMembersToPersonalTeam();

            $this->new_memberships = $membershipDao->createList([
                'team' => $this->struct,
                'members' => $this->member_emails
            ]);
        }

        if (!empty($this->uids_to_remove)) {
            //check if this is the last user of the team
            $memberList = $membershipDao->getMemberListByTeamId($teamId);

            $projectDao = new ProjectDao();

            foreach ($this->uids_to_remove as $uid) {
                $user = $membershipDao->deleteUserFromTeam($uid, $teamId);

                //check if this is the last user of the team
                // if it is, move all projects of the team to the personal team and assign them to himself
                // moreover, delete the old team
                if (count($memberList) == 1) {
                    if ($user === null) {
                        continue;
                    }
                    $teamDao = new TeamDao();
                    $personalTeam = $teamDao->setCacheTTL(60 * 60 * 24)->getPersonalByUser($user);
                    $projectDao->massiveSelfAssignment($this->struct, $user, $personalTeam);
                    $teamDao->deleteTeam($this->struct);
                } elseif ($user !== null) {
                    $this->removed_users[] = $user;
                    $projectDao->unassignProjects($this->struct, $user);
                }
            }
        }

        (new MembershipDao)->destroyCacheForListByTeamId($teamId);

        $this->all_memberships = (new MembershipDao)
            ->setCacheTTL(3600)
            ->getMemberListByTeamId($teamId);

        Database::obtain()->commit();

        $this->_sendEmailsToNewMemberships();
        $this->_sendEmailsToInvited();
        $this->_setPendingStatuses();
        $this->_sendEmailsForRemovedMemberships();

        return $this->all_memberships;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws PDOException
     * @throws TypeError
     * @throws DomainException
     * @throws InvalidArgumentException
     */
    public function create(): TeamStruct
    {
        $this->struct->type = strtolower($this->struct->type);

        $this->_checkType();
        $this->_checkPersonalUnique();

        $this->struct = $this->_createTeamWithMatecatUsers();

        $this->_sendEmailsToNewMemberships();
        $this->_sendEmailsToInvited();
        $this->_setPendingStatuses();

        return $this->struct;
    }

    /**
     * @throws Exception
     * @throws RuntimeException
     */
    protected function _sendEmailsToInvited(): void
    {
        foreach ($this->_getInvitedEmails() as $email) {
            $emailMessage = new InvitedToTeamEmail($this->user, $email, $this->struct);
            $emailMessage->send();
        }
    }

    /**
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws Exception
     */
    protected function _setPendingStatuses(): void
    {
        $teamId = $this->getTeamId();
        $redis = (new RedisHandler())->getConnection();
        foreach ($this->_getInvitedEmails() as $email) {
            $pendingInvitation = new PendingInvitations($redis, [
                'team_id' => $teamId,
                'email' => $email
            ]);
            $pendingInvitation->set();
        }
    }

    /**
     * @return list<string>
     * @throws RuntimeException
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _getInvitedEmails(): array
    {
        $emails_of_existing_members = array_filter(
            array_map(
                fn(MembershipStruct $membership): ?string => $membership->getUser()->email,
                $this->all_memberships
            )
        );

        return array_values(array_diff($this->member_emails, $emails_of_existing_members));
    }

    /**
     * @return MembershipStruct[]
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws Exception
     */
    protected function _getNewMembershipEmailList(): array
    {
        $notify_list = [];
        foreach ($this->new_memberships as $membership) {
            if ($membership->getUser()->uid != $this->user->uid) {
                $notify_list[] = $membership;
            }
        }

        return $notify_list;
    }

    /**
     * @return UserStruct[]
     */
    protected function _getRemovedMembersEmailList(): array
    {
        $notify_list = [];

        foreach ($this->removed_users as $user) {
            if ($user->uid != $this->user->uid) {
                $notify_list[] = $user;
            }
        }

        return $notify_list;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function _checkType(): void
    {
        if (!Teams::isAllowedType($this->struct->type)) {
            throw new InvalidArgumentException("Invalid Team Type");
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws InvalidArgumentException
     */
    protected function _checkPersonalUnique(): void
    {
        if ($this->struct->type !== Teams::PERSONAL) {
            return;
        }

        $dao = new TeamDao();
        $dao->getPersonalByUid($this->struct->created_by);
        throw new InvalidArgumentException("User already has the personal team");
    }

    /**
     * @throws DomainException
     */
    protected function _checkAddMembersToPersonalTeam(): void
    {
        if ($this->struct->type == Teams::PERSONAL) {
            throw new DomainException("Can not invite members to a Personal team.");
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws PDOException
     * @throws TypeError
     * @throws DomainException
     */
    protected function _createTeamWithMatecatUsers(): TeamStruct
    {
        $this->_checkAddMembersToPersonalTeam();

        $dao = new TeamDao();

        Database::obtain()->begin();
        $team = $dao->createUserTeam($this->user, [
            'type' => $this->struct->type,
            'name' => $this->struct->name,
            'members' => $this->member_emails
        ]);

        $this->new_memberships = $this->all_memberships = $team->getMembers() ?? [];

        Database::obtain()->commit();

        return $team;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws RuntimeException
     */
    protected function _sendEmailsToNewMemberships(): void
    {
        foreach ($this->_getNewMembershipEmailList() as $membership) {
            $email = new MembershipCreatedEmail($this->user, $membership);
            $email->send();
        }
    }

    /**
     * @throws Exception
     */
    protected function _sendEmailsForRemovedMemberships(): void
    {
        foreach ($this->_getRemovedMembersEmailList() as $user) {
            $email = new MembershipDeletedEmail($this->user, $user, $this->struct);
            $email->send();
        }
    }

    /**
     * @return $this
     * @throws ReflectionException
     * @throws Exception
     */
    public function updateMembersProjectsCount(): TeamModel
    {
        $teamId = $this->getTeamId();
        $this->all_memberships = (new MembershipDao())->setCacheTTL(60 * 60 * 24)->getMemberListByTeamId($teamId);

        if (!empty($this->all_memberships)) {
            $membersWithProjects = (new TeamDao())->setCacheTTL(60 * 60)->getAssigneeWithProjectsByTeam($this->struct);

            $assigneeIds = [];
            foreach ($membersWithProjects as $assignee) {
                $assigneeIds[$assignee->uid] = $assignee->getAssignedProjects();
            }

            foreach ($this->all_memberships as $member) {
                if ($member->uid === null) {
                    continue;
                }
                if (array_key_exists($member->uid, $assigneeIds)) {
                    $member->setAssignedProjects($assigneeIds[$member->uid]);
                }
            }

            $this->struct->setMembers($this->all_memberships);
        }

        return $this;
    }

    /**
     * Returns the team ID, asserting it is non-null (team has been persisted).
     *
     * @throws RuntimeException if team has no ID (not persisted yet)
     */
    private function getTeamId(): int
    {
        return $this->struct->id ?? throw new RuntimeException('Team must be persisted before this operation (id is null)');
    }

}
