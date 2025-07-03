<?php

use Model\Database;
use Model\Exceptions\ValidationError;
use Model\Users\Authentication\SignupModel;
use Model\Users\UserDao;
use TestHelpers\AbstractTest;

class SignupTest extends AbstractTest {

    public function setUp(): void {
        Database::obtain()->getConnection()->exec( 'DELETE FROM users' );
    }

    /**
     * @throws ValidationError
     */
    public function testSignupWithValidParams() {

        $session = [];
        $signup  = new SignupModel( [
                'first_name'            => 'John',
                'last_name'             => 'Doe',
                'password'              => '1234abcdxxxxxx!',
                'password_confirmation' => '1234abcdxxxxxx!',
                'email'                 => 'foo@example.org',
                'wanted_url'            => 'https://fake.example.com'
        ], $session );

        $signup->processSignup();

        $dao  = new UserDao();
        $user = $dao->getByEmail( 'foo@example.org' );
        $this->assertNotEmpty( $user );
        $this->assertEquals( 'https://fake.example.com', $session[ 'wanted_url' ] );

    }

}
