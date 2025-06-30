<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:45
 */

namespace Model\Teams;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use ReflectionException;
use Users_UserDao;
use Users_UserStruct;

class MembershipStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int $id = null;
    public int $id_team;
    public int $uid;
    public bool $is_admin;

    /**
     * @var Users_UserStruct
     */
    private Users_UserStruct $user;

    /**
     * @var TeamStruct
     */
    private TeamStruct $team;


    /**
     * @var
     */
    private array $user_metadata = [];

    /**
     * @var int
     */
    private int $projects = 0;

    public function setUser( Users_UserStruct $user ) {
        $this->user = $user;
    }

    public function setUserMetadata( array $user_metadata ) {
        if ( $user_metadata == null ) {
            $user_metadata = [];
        }
        $this->user_metadata = $user_metadata;
    }

    public function getUserMetadata() {
        return $this->user_metadata;
    }

    /**
     * @return Users_UserStruct|null
     * @throws ReflectionException
     */
    public function getUser() {
        if ( is_null( $this->user ) ) {
            $this->user = ( new Users_UserDao() )->setCacheTTL( 60 * 60 * 24 )->getByUid( $this->uid );
        }

        return $this->user;
    }

    /**
     * @return TeamStruct
     * @throws ReflectionException
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
    public function setAssignedProjects( int $projects ): MembershipStruct {
        $this->projects = $projects;

        return $this;
    }


}