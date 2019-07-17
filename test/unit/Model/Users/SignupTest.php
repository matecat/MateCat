<?php

use Users\Signup;

class SignupTest extends PHPUnit_Framework_TestCase {

    public function setup() {
        Database::obtain()->getConnection()->exec( 'DELETE FROM users' );
    }

    public function testSignupWithValidParams() {

        $signup = new Signup( [
                'first_name'            => 'John',
                'last_name'             => 'Doe',
                'password'              => '1234abcd',
                'password_confirmation' => '1234abcd',
                'email'                 => 'foo@example.org'
        ] );

        $signup->process();

        $dao  = new Users_UserDao();
        $user = $dao->getByEmail( 'foo@example.org' );
        $this->assertNotEmpty( $user );

    }

}
