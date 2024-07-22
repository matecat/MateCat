<?php

use Teams\TeamDao;

class Factory_User extends Factory_Base {

    static function create( $values = [] ) {

        $userStruct = static::getNewUser( $values );

        $dao  = new Users_UserDao( Database::obtain() );
        $user = $dao->createUser( $userStruct );

        $orgDao = new TeamDao();
        $orgDao->createUserTeam( $user, [
                'type' => Constants_Teams::PERSONAL,
                'name' => 'personal'
        ] );

        return $user;
    }

    public static function getNewUser( $values = [] ) {

        $values = array_merge( [
                'email'      => "test-email-" . uniqid( '', true ) . "@example.org",
                'salt'       => '1234abcd',
                'pass'       => '1234abcd',
                'first_name' => 'John',
                'last_name'  => 'Connor',
                'api_key'    => '1234abcd'
        ], $values );

        return new Users_UserStruct( $values );

    }

}
