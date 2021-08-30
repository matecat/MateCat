<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:45
 */

namespace Teams;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class MembershipStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id;
    public $id_team;
    public $uid;
    public $is_admin;

    /**
     * @var \Users_UserStruct
     */
    private $user;

    /**
     * @var TeamStruct
     */
    private $team;


    /**
     * @var
     */
    private $user_metadata = [];

    /**
     * @var int
     */
    private $projects = 0;

    public function setUser( \Users_UserStruct $user ) {
        $this->user = $user;
    }

    public function setUserMetadata( $user_metadata ) {
        if ( $user_metadata == null ) {
            $user_metadata = [];
        }
        $this->user_metadata = $user_metadata;
    }

    public function getUserMetadata() {
        return $this->user_metadata;
    }

    /**
     * @return \Users_UserStruct|null
     */
    public function getUser() {
        if ( is_null( $this->user ) ) {
            $this->user = ( new \Users_UserDao() )->setCacheTTL( 60 * 60 * 24 )->getByUid( $this->uid );
        }

        return $this->user;
    }

    /**
     * @return TeamStruct
     */
    public function getTeam() {
        if ( is_null( $this->team ) ) {
            $this->team = ( new TeamDao() )->setCacheTTL( 60 * 60 * 24 )->findById( $this->id_team );
        }

        return $this->team;
    }

    /**
     * @return int
     */
    public function getAssignedProjects() {
        return $this->projects;
    }

    /**
     * @param int $projects
     *
     * @return $this
     */
    public function setAssignedProjects( $projects ) {
        $this->projects = $projects;

        return $this;
    }


}