<?php

namespace Model\Teams;

use DomainException;
use Exception;
use InvalidArgumentException;
use Model\DataAccess\Database;
use Model\Projects\ProjectDao;
use Model\Users\UserStruct;
use ReflectionException;
use Utils\Constants\Teams;
use Utils\Email\InvitedToTeamEmail;
use Utils\Email\MembershipCreatedEmail;
use Utils\Email\MembershipDeletedEmail;
use Utils\Redis\RedisHandler;

class TeamModel
{

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

    /**
     * @var array
     */
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

    public function addMemberEmails(array $emails): void
    {
        foreach ($emails as $email) {
            $this->addMemberEmail($email);
        }
    }

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
     */
    public function updateMembers(): array
    {
        $this->removed_users = [];

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
            $memberList = $membershipDao->getMemberListByTeamId($this->struct->id);

            $projectDao = new ProjectDao();

            foreach ($this->uids_to_remove as $uid) {
                $user = $membershipDao->deleteUserFromTeam($uid, $this->struct->id);

                //check if this is the last user of the team
                // if it is, move all projects of the team to the personal team and assign them to himself
                // moreover, delete the old team
                if (count($memberList) == 1) {
                    $teamDao = new TeamDao();
                    $personalTeam = $teamDao->setCacheTTL(60 * 60 * 24)->getPersonalByUser($user);
                    $projectDao->massiveSelfAssignment($this->struct, $user, $personalTeam);
                    $teamDao->deleteTeam($this->struct);
                } elseif ($user) {
                    $this->removed_users[] = $user;
                    $projectDao->unassignProjects($this->struct, $user);
                }
            }
        }

        (new MembershipDao)->destroyCacheForListByTeamId($this->struct->id);

        $this->all_memberships = (new MembershipDao)
            ->setCacheTTL(3600)
            ->getMemberListByTeamId($this->struct->id);

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
     */
    public function create(): TeamStruct
    {
        $this->struct->type = strtolower($this->struct->type);

        $this->_checkType();
        $this->_checkPersonalUnique();

        $this->struct = $this->_createTeamWithMatecatUsers(); //update the struct of the team in the model

        $this->_sendEmailsToNewMemberships();
        $this->_sendEmailsToInvited();
        $this->_setPendingStatuses();

        return $this->struct;
    }

    /**
     * @throws Exception
     */
    protected function _sendEmailsToInvited(): void
    {
        foreach ($this->_getInvitedEmails() as $email) {
            $email = new InvitedToTeamEmail($this->user, $email, $this->struct);
            $email->send();
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function _setPendingStatuses(): void
    {
        $redis = (new RedisHandler())->getConnection();
        foreach ($this->_getInvitedEmails() as $email) {
            $pendingInvitation = new PendingInvitations($redis, [
                'team_id' => $this->struct->id,
                'email' => $email
            ]);
            $pendingInvitation->set();
        }
    }

    protected function _getInvitedEmails(): array
    {
        $emails_of_existing_members = array_map(
        /**
         * @throws ReflectionException
         */ function (MembershipStruct $membership) {
            return $membership->getUser()->email;
        },
            $this->all_memberships
        );

        return array_diff($this->member_emails, $emails_of_existing_members);
    }

    /**
     * @return MembershipStruct[]
     * @throws ReflectionException
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


    protected function _checkType(): void
    {
        if (!Teams::isAllowedType($this->struct->type)) {
            throw new InvalidArgumentException("Invalid Team Type");
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function _checkPersonalUnique(): void
    {
        $dao = new TeamDao();
        if ($this->struct->type == Teams::PERSONAL && $dao->getPersonalByUid($this->struct->created_by)) {
            throw new InvalidArgumentException("User already has the personal team");
        }
    }

    protected function _checkAddMembersToPersonalTeam(): void
    {
        if ($this->struct->type == Teams::PERSONAL) {
            throw new DomainException("Can not invite members to a Personal team.");
        }
    }

    /**
     * @throws ReflectionException
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

        $this->new_memberships = $this->all_memberships = $team->getMembers(); //the new members are all existent members

        Database::obtain()->commit();

        return $team;
    }

    /**
     * @throws ReflectionException
     * @throws Exception
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
     */
    public function updateMembersProjectsCount(): TeamModel
    {
        $this->all_memberships = (new MembershipDao())->setCacheTTL(60 * 60 * 24)->getMemberListByTeamId($this->struct->id);

        if (!empty($this->all_memberships)) {
            $membersWithProjects = (new TeamDao())->setCacheTTL(60 * 60)->getAssigneeWithProjectsByTeam($this->struct);

            $assigneeIds = [];
            foreach ($membersWithProjects as $assignee) {
                $assigneeIds[$assignee->uid] = $assignee->getAssignedProjects();
            }

            foreach ($this->all_memberships as $member) {
                $memberWithAssignment = array_key_exists($member->uid, $assigneeIds);
                if ($memberWithAssignment !== false) {
                    $member->setAssignedProjects($assigneeIds[$member->uid]);
                }
            }

            $this->struct->setMembers($this->all_memberships);
        }

        return $this;
    }

}