<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:17
 */

namespace Organizations;

use Constants_Organizations;
use DataAccess_AbstractDaoSilentStruct ;
use DataAccess_IDaoStruct ;

class OrganizationStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id ;
    public $name;
    public $created_by;
    public $created_at ;
    public $type = Constants_Organizations::PERSONAL;

    protected $members ;

    /**
     * @param MembershipStruct[] $list
     */
    public function setMembers($list) {
        $this->members = $list ;
    }

    /**
     * @return null|MembershipStruct[]
     */
    public function getMembers() {
        return $this->members ;
    }

}