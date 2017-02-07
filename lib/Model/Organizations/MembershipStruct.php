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
    public $email;
    public $first_name;
    public $last_name;

}