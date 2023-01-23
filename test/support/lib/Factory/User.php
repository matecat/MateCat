<?php

use Teams\TeamDao;

class Factory_User extends Factory_Base {

    public static $email_counter = 0 ;

    static function create( $values=array() ) {
        $email_counter = self::$email_counter += 1 ;

        $values = array_merge(array(
            'email' => "test-email-{$email_counter}@example.org",
            'salt' => '1234abcd',
            'pass' => '1234abcd',
            'first_name' => 'John',
            'last_name' => 'Connor',
            'api_key' => '1234abcd'
        ), $values);

        $dao = new Users_UserDao( Database::obtain() );
        $userStruct = new Users_UserStruct( $values );
        $user = $dao->createUser( $userStruct );

        $orgDao = new TeamDao() ;
        $orgDao->createUserTeam( $user, array(
            'type' => Constants_Teams::PERSONAL,
            'name' => 'personal'
        ));

        return $user ;
    }

}
