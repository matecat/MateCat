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
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;

class MembershipStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int $id = null;
    public int $id_team;
    public ?int $uid = null; // this shouldn't be null, but it is in the database (old records)
    public ?bool $is_admin = null; // this shouldn't be null, but this struct is used also for partial records

    /**
     * @var UserStruct|null
     */
    private ?UserStruct $user = null;

    /**
     * @var TeamStruct|null
     */
    private ?TeamStruct $team = null;


    /**
     * @var array
     */
    private array $user_metadata = [];

    /**
     * @var int
     */
    private int $projects = 0;

    public function setUser( UserStruct $user ) {
        $this->user = $user;
    }

    public function setUserMetadata( array $user_metadata ) {
        if ( $user_metadata == null ) {
            $user_metadata = [];
        }
        $this->user_metadata = $user_metadata;
    }

    public function getUserMetadata(): array {
        return $this->user_metadata;
    }

    /**
     * @return UserStruct|null
     * @throws ReflectionException
     */
    public function getUser(): UserStruct {
        if ( is_null( $this->user ) ) {
            $this->user = ( new UserDao() )->setCacheTTL( 60 * 60 * 24 )->getByUid( $this->uid );
        }

        return $this->user;
    }

    /**
     * @return TeamStruct
     * @throws ReflectionException
     */
    public function getTeam(): TeamStruct {
        if ( is_null( $this->team ) ) {
            $this->team = ( new TeamDao() )->setCacheTTL( 60 * 60 * 24 )->findById( $this->id_team );
        }

        return $this->team;
    }

    /**
     * @return int
     */
    public function getAssignedProjects(): int {
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