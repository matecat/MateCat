<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:17
 */

namespace Teams;

use Constants_Teams;
use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class TeamStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id ;
    public $name;
    public $created_by;
    public $created_at ;
    public $type = Constants_Teams::PERSONAL;

    /**
     * @var MembershipStruct[]
     */
    protected $members ;

    /**
     * @param MembershipStruct[] $list
     *
     * @return $this
     */
    public function setMembers($list) {
        $this->members = $list ;
        return $this;
    }

    /**
     * @return null|MembershipStruct[]
     */
    public function getMembers() {
        return $this->members ;
    }

    /**
     * @param $uid
     * @return bool
     */
    public function hasUser($uid) {
        foreach ($this->getMembers() as $member){
            if($member->uid === $uid){
                return true;
            }
        }

        return false;
    }

}