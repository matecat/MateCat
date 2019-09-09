<?php

use Email\InvitedToTeamEmail;
use Email\MembershipCreatedEmail;
use Email\MembershipDeletedEmail;
use Teams\MembershipDao;
use Teams\MembershipStruct;
use Teams\PendingInvitations;
use Teams\TeamDao;
use Teams\TeamStruct;

class TeamModel {

    protected $member_emails = array();

    /**
     * @var \Teams\TeamStruct
     */
    protected $struct;

    /**
     * @var Users_UserStruct
     */
    protected $user;

    /**
     * @var \Teams\MembershipStruct[]
     */
    protected $new_memberships;

    /**
     * @var array
     */
    protected $uids_to_remove = array();

    /**
     * @var Users_UserStruct[]
     */
    protected $removed_users = array();

    protected $emails_to_invite;

    /**
     * @var \Teams\MembershipStruct[]
     */
    protected $all_memberships;

    public function __construct( TeamStruct $struct ) {
        $this->struct = $struct;
    }

    public function addMemberEmail( $email ) {
        $this->member_emails[] = $email;
    }

    public function setUser( Users_UserStruct $user ) {
        $this->user = $user;
    }

    public function addMemberEmails( $emails ) {
        foreach ( $emails as $email ) {
            $this->addMemberEmail( $email );
        }
    }

    public function removeMemberUids( $uids ) {
        $this->uids_to_remove = array_merge( $this->uids_to_remove, $uids );
    }

    /**
     * Updated member list.
     *
     * @return \Teams\MembershipStruct[] the full list of members after the update.
     */
    public function updateMembers() {
        $this->removed_users = array();

        Database::obtain()->begin();

        $membershipDao = new MembershipDao();

        if ( !empty( $this->member_emails ) ) {

            $this->_checkAddMembersToPersonalTeam();

            $this->new_memberships = $membershipDao->createList( [
                    'team'    => $this->struct,
                    'members' => $this->member_emails
            ] );
        }

        if ( !empty( $this->uids_to_remove ) ) {

            //check if this is the last user of the team
            $memberList = $membershipDao->getMemberListByTeamId( $this->struct->id );

            $projectDao = new Projects_ProjectDao();

            foreach ( $this->uids_to_remove as $uid ) {
                $user = $membershipDao->deleteUserFromTeam( $uid, $this->struct->id );

                //check if this is the last user of the team
                // if it is, move all projects of the team to the personal team and assign them to himself
                // moreover, delete the old team
                if( count( $memberList ) == 1 ){
                    $teamDao = new TeamDao();
                    $personalTeam = $teamDao->setCacheTTL( 60 * 60 * 24 )->getPersonalByUser( $user );
                    $projectDao->massiveSelfAssignment( $this->struct, $user, $personalTeam );
                    $teamDao->deleteTeam( $this->struct );
                } elseif ( $user ) {
                    $this->removed_users[] = $user;
                    $projectDao->unassignProjects( $this->struct, $user );
                }

            }

        }

        ( new MembershipDao )->destroyCacheForListByTeamId( $this->struct->id );

        $this->all_memberships = ( new MembershipDao )
                ->setCacheTTL( 3600 )
                ->getMemberListByTeamId( $this->struct->id );

        Database::obtain()->commit();

        $this->_sendEmailsToNewMemberships();
        $this->_sendEmailsToInvited();
        $this->_setPendingStatuses();
        $this->_sendEmailsForRemovedMemberships();

        return $this->all_memberships;
    }

    public function create() {
        if ( !$this->user ) {
            throw new Exception( 'User is not set' );
        }

        $this->struct->type = strtolower( $this->struct->type );

        $this->_checkType();
        $this->_checkPersonalUnique();

        $this->struct = $this->_createTeamWithMatecatUsers(); //update the struct of the team in the model

        $this->_sendEmailsToNewMemberships();
        $this->_sendEmailsToInvited();
        $this->_setPendingStatuses();

        return $this->struct;
    }

    public function _sendEmailsToInvited() {
        foreach ( $this->getInvitedEmails() as $email ) {
            $email = new InvitedToTeamEmail( $this->user, $email, $this->struct );
            $email->send();
        }
    }

    public function _setPendingStatuses() {
        $redis = ( new \RedisHandler() )->getConnection();
        foreach ( $this->getInvitedEmails() as $email ) {
            $pendingInvitation = new PendingInvitations( $redis, [
                    'team_id' => $this->struct->id,
                    'email'   => $email
            ] );
            $pendingInvitation->set();
        }
    }

    public function getInvitedEmails() {

        $emails_of_existing_members = array_map( function ( MembershipStruct $membership ) {
            return $membership->getUser()->email;
        }, $this->all_memberships );

        return array_diff( $this->member_emails, $emails_of_existing_members );

    }

    /**
     * @return \Teams\MembershipStruct[]
     */
    public function getNewMembershipEmailList() {
        $notify_list = [];
        foreach ( $this->new_memberships as $membership ) {
            if ( $membership->getUser()->uid != $this->user->uid ) {
                $notify_list[] = $membership;
            }
        }

        return $notify_list;
    }

    public function getRemovedMembersEmailList() {
        $notify_list = [];

        foreach ( $this->removed_users as $user ) {
            if ( $user->uid != $this->user->uid ) {
                $notify_list[] = $user;
            }
        }

        return $notify_list;
    }


    protected function _checkType() {
        if ( !Constants_Teams::isAllowedType( $this->struct->type ) ) {
            throw new InvalidArgumentException( "Invalid Team Type" );
        }
    }

    protected function _checkPersonalUnique() {
        $dao = new TeamDao();
        if ( $this->struct->type == Constants_Teams::PERSONAL && $dao->getPersonalByUid( $this->struct->created_by ) ) {
            throw new InvalidArgumentException( "User already has the personal team" );
        }
    }

    protected function _checkAddMembersToPersonalTeam() {
        if ( $this->struct->type == Constants_Teams::PERSONAL ) {
            throw new DomainException( "Can not invite members to a Personal team." );
        }
    }

    protected function _createTeamWithMatecatUsers() {

        $this->_checkAddMembersToPersonalTeam();

        $dao = new TeamDao();

        Database::obtain()->begin();
        $team = $dao->createUserTeam( $this->user, [
                'type'    => $this->struct->type,
                'name'    => $this->struct->name,
                'members' => $this->member_emails
        ] );

        $this->new_memberships = $this->all_memberships = $team->getMembers(); //the new members are obviously all existent members

        Database::obtain()->commit();

        return $team;
    }

    protected function _sendEmailsToNewMemberships() {
        foreach ( $this->getNewMembershipEmailList() as $membership ) {
            $email = new MembershipCreatedEmail( $this->user, $membership );
            $email->send();
        }
    }

    protected function _sendEmailsForRemovedMemberships() {
        foreach ( $this->getRemovedMembersEmailList() as $user ) {
            $email = new MembershipDeletedEmail( $this->user, $user, $this->struct );
            $email->send();
        }
    }

    /**
     * @return $this
     */
    public function updateMembersProjectsCount(){

        $this->all_memberships = ( new MembershipDao() )->setCacheTTL( 60 * 60 * 24 )->getMemberListByTeamId( $this->struct->id );

        if( !empty( $this->all_memberships ) ){

            $membersWithProjects = ( new TeamDao() )->setCacheTTL( 60 * 60 )->getAssigneeWithProjectsByTeam( $this->struct );

            $assigneeIds = [];
            foreach( $membersWithProjects as $assignee ){
                $assigneeIds[ $assignee->uid ] = $assignee->getAssignedProjects();
            }

            foreach ( $this->all_memberships as $member ){
                $memberWithAssignment = array_key_exists( $member->uid, $assigneeIds );
                if( $memberWithAssignment !== false ){
                    $member->setAssignedProjects( $assigneeIds[ $member->uid ] );
                }
            }

            $this->struct->setMembers( $this->all_memberships );

        }

        return $this;

    }

}