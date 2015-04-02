<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/04/15
 * Time: 12.54
 */

class User_UserStruct extends DataAccess_AbstractDaoObjectStruct implements DataAccess_IDaoStruct {

    public $uid;
    public $email;
    public $create_date;
    public $first_name;
    public $last_name;

    public static function getStruct() {
        return new User_UserStruct();
    }
} 