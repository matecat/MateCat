<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/04/15
 * Time: 12.54
 */
class Users_UserStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $uid;
    public $email;
    public $create_date;
    public $first_name;
    public $last_name;
    public $salt;
    public $api_key;
    public $pass;
    public $oauth_access_token ; 

    public static function getStruct() {
        return new Users_UserStruct();
    }

    public function fullName() {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function shortName() {
        return trim( mb_substr( $this->first_name, 0, 1 ) . "" . mb_substr( $this->last_name, 0, 1 ) );
    }

    public function getEmail() {
        return $this->email ;
    }

    /**
     * @return mixed
     */
    public function getUid() {
        return $this->uid;
    }

    /**
     * @return mixed
     */
    public function getFirstName() {
        return $this->first_name;
    }

    /**
     * @return mixed
     */
    public function getLastName() {
        return $this->last_name;
    }



}
