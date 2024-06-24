<?php

use Users\SignupModel;

class SignupTest extends PHPUnit_Framework_TestCase {

    public function setup() {
        Database::obtain()->getConnection()->exec( 'DELETE FROM users' );
    }

    public function testSignupWithValidParams() {

        $signup = new SignupModel( [
                'first_name'            => 'John',
                'last_name'             => 'Doe',
                'password'              => '1234abcdxxxxxx!',
                'password_confirmation' => '1234abcdxxxxxx!',
                'email'                 => 'foo@example.org'
        ] );

        $signup->process();

        $dao  = new Users_UserDao();
        $user = $dao->getByEmail( 'foo@example.org' );
        $this->assertNotEmpty( $user );

    }

}
