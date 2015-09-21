<?php

class Factory_User extends Factory_Base {

    static function create( $values ) {

        $values = array_merge(array(
            'email' => 'foo@example.org',
            'salt' => '1234abcd',
            'pass' => '1234abcd',
            'first_name' => 'John',
            'last_name' => 'Connor',
            'api_key' => '1234abcd'
        ), $values);

        $dao = new Users_UserDao( Database::obtain() );
        $userStruct = new Users_UserStruct( $values );

        return $dao->createUser( $userStruct );

    }

}
