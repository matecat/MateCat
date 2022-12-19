<?php

class DqfTest {

    public static $dqf_username ;
    public static $dqf_password ;

    public function addDqfCredentials(Users_UserStruct $user ) {

        $dao = new \Users\MetadataDao() ;
        $dao->set( $user->uid, 'dqf_username', self::$dqf_username );
        $dao->set( $user->uid, 'dqf_password', self::$dqf_password );


    }





}