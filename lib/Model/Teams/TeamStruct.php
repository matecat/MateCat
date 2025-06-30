<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:17
 */

namespace Model\Teams;

use Constants_Teams;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class TeamStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int   $id   = null;
    public string $name;
    public int    $created_by;
    public string $created_at;
    public string $type = Constants_Teams::PERSONAL;

    /**
     * @var MembershipStruct[]
     */
    protected array $members;

    /**
     * @param MembershipStruct[] $list
     *
     * @return $this
     */
    public function setMembers( array $list ): TeamStruct {
        $this->members = $list;

        return $this;
    }

    /**
     * @return null|MembershipStruct[]
     */
    public function getMembers(): ?array {
        return $this->members;
    }

    /**
     * @param $uid
     *
     * @return bool
     */
    public function hasUser( $uid ) {
        foreach ( $this->getMembers() as $member ) {
            if ( $member->uid === $uid ) {
                return true;
            }
        }

        return false;
    }

}