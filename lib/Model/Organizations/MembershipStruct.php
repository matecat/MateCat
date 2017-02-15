<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 20/12/2016
 * Time: 10:45
 */

namespace Organizations;

use DataAccess_AbstractDaoSilentStruct ;
use DataAccess_IDaoStruct ;

class MembershipStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id ;
    public $id_organization;
    public $uid ;
    public $is_admin ;

    /**
     * @var \Users_UserStruct
     */
    private $user ;

    public function setUser( \Users_UserStruct $user ) {
        $this->user = $user ;
    }

    /**
     * @return \Users_UserStruct|null
     */
    public function getUser( ) {
        return $this->user ;
    }

}