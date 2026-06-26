<?php


namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Exceptions\ValidationError;
use Model\Teams\TeamDao;
use Model\Users\Authentication\SignupModel;
use Model\Users\UserDao;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('PersistenceNeeded')]
class SignupTest extends AbstractTest
{

    public function setUp(): void
    {
        parent::setUp();
        obtainTestDatabase()->getConnection()->exec('DELETE FROM users');
    }

    /**
     * @throws ValidationError
     */
    #[Test]
    public function testSignupWithValidParams()
    {
        $session = [];
        $signup = new SignupModel([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => '1234abcdxxxxxx!',
            'password_confirmation' => '1234abcdxxxxxx!',
            'email' => 'foo@example.org',
            'wanted_url' => 'https://fake.example.com'
        ], $session, new UserDao(obtainTestDatabase()), new TeamDao(obtainTestDatabase()));

        $signup->processSignup();

        $dao = new UserDao(obtainTestDatabase());
        $user = $dao->getByEmail('foo@example.org');
        $this->assertNotEmpty($user);
        $this->assertEquals('https://fake.example.com', $session['wanted_url']);
    }

}
