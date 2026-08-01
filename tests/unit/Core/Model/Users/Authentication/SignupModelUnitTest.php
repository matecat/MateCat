<?php


namespace Matecat\Core\Model\Users\Authentication;

use Utils\Session\ArraySessionStore;
use Controller\API\Commons\Exceptions\ValidationError;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Teams\TeamDao;
use Model\Users\Authentication\SignupModel;
use Model\Users\AuthTokenScope;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Utils\Email\SetPasswordRequestEmail;

#[Group('unit')]
class SignupModelUnitTest extends AbstractTest
{

    /** @var list<int> */
    private array $createdUids = [];

    protected function tearDown(): void
    {
        if ($this->createdUids !== []) {
            $statement = obtainTestDatabase()->getConnection()->prepare("DELETE FROM users WHERE uid = ?");

            foreach ($this->createdUids as $uid) {
                $statement->execute([$uid]);
            }

            $this->createdUids = [];
        }

        parent::tearDown();
    }

    #[Test]
    public function testConstructPopulatesParams()
    {
        $session = new ArraySessionStore();
        $params = ['email' => 'test@example.com', 'password' => 'secret'];
        $model = new SignupModel($params, $session, new UserDao(obtainTestDatabase()), new TeamDao(obtainTestDatabase()));

        $this->assertSame($params, $model->getParams());
    }

    #[Test]
    public function testConstructCreatesUserStruct()
    {
        $session = new ArraySessionStore();
        $model = new SignupModel(['email' => 'test@example.com'], $session, new UserDao(obtainTestDatabase()), new TeamDao(obtainTestDatabase()));

        $this->assertInstanceOf(UserStruct::class, $model->getUser());
    }

    #[Test]
    public function testConstructWithDiInitializesDaos()
    {
        $userDao = $this->createStub(UserDao::class);
        $teamDao = $this->createStub(TeamDao::class);
        $session = new ArraySessionStore();
        $model = new SignupModel(['email' => 'test@example.com'], $session, $userDao, $teamDao);

        $this->assertSame('test@example.com', $model->getUser()->email);
    }

    #[Test]
    public function testGetUserReturnsUserStructWithGivenData()
    {
        $session = new ArraySessionStore();
        $model = new SignupModel(['email' => 'test@example.com'], $session, new UserDao(obtainTestDatabase()), new TeamDao(obtainTestDatabase()));

        $this->assertSame('test@example.com', $model->getUser()->email);
    }

    #[Test]
    public function testGetErrorReturnsNullInitially()
    {
        $session = new ArraySessionStore();
        $model = new SignupModel([], $session, new UserDao(obtainTestDatabase()), new TeamDao(obtainTestDatabase()));

        $this->assertNull($model->getError());
    }

    #[Test]
    public function testFlushWantedUrlReturnsAppRootWhenNotSet()
    {
        $session = new ArraySessionStore();
        $model = new SignupModel([], $session, new UserDao(obtainTestDatabase()), new TeamDao(obtainTestDatabase()));

        $url = $model->flushWantedURL();

        $this->assertIsString($url);
        $this->assertNotEmpty($url);
    }

    #[Test]
    public function testFlushWantedUrlReturnsStoredUrlAndUnsetsIt()
    {
        $session = new ArraySessionStore();
        $model = new SignupModel(['wanted_url' => '/dashboard'], $session, new UserDao(obtainTestDatabase()), new TeamDao(obtainTestDatabase()));

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__saveWantedUrl');
        $method->invoke($model);

        $url = $model->flushWantedURL();

        $this->assertSame('/dashboard', $url);
    }

    #[Test]
    public function testResendConfirmationEmailWithEmptyStringReturnsEarly()
    {
        SignupModel::resendConfirmationEmail('', new UserDao(obtainTestDatabase()));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function testResendConfirmationEmailWithWhitespaceReturnsEarly()
    {
        SignupModel::resendConfirmationEmail('   ', new UserDao(obtainTestDatabase()));

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function testResendConfirmationEmailCallsDaoWithValidEmail()
    {
        $user = new UserStruct(['email' => 'test@example.com', 'confirmation_token' => 'tok123']);
        $user->initAuthToken(AuthTokenScope::PasswordReset);

        $dao = $this->createMock(UserDao::class);
        $dao->expects($this->once())
            ->method('getByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        SignupModel::resendConfirmationEmail('test@example.com', $dao);
    }

    #[Test]
    public function testResendConfirmationEmailReturnsEarlyWhenUserNotFound()
    {
        $dao = $this->createMock(UserDao::class);
        $dao->expects($this->once())
            ->method('getByEmail')
            ->with('valid@example.com')
            ->willReturn(null);

        SignupModel::resendConfirmationEmail('valid@example.com', $dao);
    }

    #[Test]
    public function testUserAlreadyExistsReturnsFalseWhenEmailIsNull()
    {
        $session = new ArraySessionStore();
        $model = new SignupModel([], $session, new UserDao(obtainTestDatabase()), new TeamDao(obtainTestDatabase()));

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__userAlreadyExists');

        $this->assertFalse($method->invoke($model));
    }

    #[Test]
    public function testUserAlreadyExistsReturnsTrueWhenUserFound()
    {
        $existingUser = new UserStruct(['uid' => 99, 'email' => 'existing@example.com']);
        $existingUser->uid = 99;

        $dao = $this->createMock(UserDao::class);
        $dao->method('getByEmail')
            ->with('existing@example.com')
            ->willReturn($existingUser);

        $session = new ArraySessionStore();
        $model = new SignupModel(['email' => 'existing@example.com'], $session, $dao, new TeamDao(obtainTestDatabase()));

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__userAlreadyExists');

        $this->assertTrue($method->invoke($model));
        $this->assertSame(99, $model->getUser()->uid);
    }

    #[Test]
    public function testUserAlreadyExistsReturnsFalseWhenUserNotFound()
    {
        $dao = $this->createMock(UserDao::class);
        $dao->method('getByEmail')
            ->with('new@example.com')
            ->willReturn(null);

        $session = new ArraySessionStore();
        $model = new SignupModel(['email' => 'new@example.com'], $session, $dao, new TeamDao(obtainTestDatabase()));

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__userAlreadyExists');

        $this->assertFalse($method->invoke($model));
    }

    #[Test]
    public function testPrepareNewUserThrowsRuntimeExceptionWhenEmailIsNull()
    {
        $session = new ArraySessionStore();
        $model = new SignupModel([], $session, new UserDao(obtainTestDatabase()), new TeamDao(obtainTestDatabase()));

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__prepareNewUser');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User email must be set before signup');

        $method->invoke($model);
    }

    #[Test]
    public function testSaveWantedUrlStoresInSession()
    {
        $session = new ArraySessionStore();
        $model = new SignupModel(['wanted_url' => '/projects/123'], $session, new UserDao(obtainTestDatabase()), new TeamDao(obtainTestDatabase()));

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__saveWantedUrl');
        $method->invoke($model);

        $this->assertSame('/projects/123', $session->get('wanted_url'));
    }

    /**
     * A signup request is unauthenticated, so submitting one for an address that already holds an
     * account must leave that row's credentials exactly as they were. Accounts created through an
     * external provider are the case to watch, since they carry no `email_confirmed_at`.
     */
    #[Test]
    #[Group('PersistenceNeeded')]
    public function testSignupOnAnExistingOauthAccountLeavesCredentialsUntouched()
    {
        $email = 'oauth-signup-guard-' . bin2hex(random_bytes(4)) . '@example.org';
        $uid = $this->insertOauthOnlyUser($email);

        $session = new ArraySessionStore();
        $model = new SignupModelWithoutMailer(
            ['email' => $email, 'password' => 'Attacker!Pass1', 'wanted_url' => '/'],
            $session,
            new UserDao(obtainTestDatabase()),
            new TeamDao(obtainTestDatabase())
        );
        $model->processSignup();

        $row = $this->fetchCredentialColumns($uid);

        $this->assertNull($row['salt'], 'signup must not write a salt onto an existing account');
        $this->assertNull($row['pass'], 'signup must not write a password onto an existing account');
    }

    /**
     * The address is taken, so the only safe move is to hand the mailbox a token and let whoever
     * receives it choose the password on the reset form.
     */
    #[Test]
    #[Group('PersistenceNeeded')]
    public function testSignupOnAnExistingOauthAccountIssuesASetPasswordToken()
    {
        $email = 'oauth-signup-token-' . bin2hex(random_bytes(4)) . '@example.org';
        $uid = $this->insertOauthOnlyUser($email);

        $session = new ArraySessionStore();
        $model = new SignupModelWithoutMailer(
            ['email' => $email, 'password' => 'Attacker!Pass1', 'wanted_url' => '/'],
            $session,
            new UserDao(obtainTestDatabase()),
            new TeamDao(obtainTestDatabase())
        );
        $model->processSignup();

        $row = $this->fetchCredentialColumns($uid);

        $this->assertNotEmpty($row['confirmation_token']);
        $this->assertNotEmpty($row['confirmation_token_created_at']);
        $this->assertSame(1, $model->setPasswordMailsSent, 'the mailbox owner must be told a password was requested');
    }

    /**
     * Repeated signups against a taken address must not retire the link already sitting in that
     * mailbox: minting a fresh token every time would let anyone break a reset the owner is part-way
     * through, just by naming their address.
     */
    #[Test]
    #[Group('PersistenceNeeded')]
    public function testSignupOnAnExistingAccountKeepsATokenThatIsStillFresh()
    {
        $email = 'oauth-signup-churn-' . bin2hex(random_bytes(4)) . '@example.org';
        $uid = $this->insertOauthOnlyUser($email);

        // a link the owner is already holding
        $dao = new UserDao(obtainTestDatabase());
        $existing = $dao->getByUid($uid);
        $existing->initAuthToken(AuthTokenScope::PasswordReset);
        $dao->updateStruct($existing, ['fields' => ['confirmation_token', 'confirmation_token_created_at']]);
        $issuedToken = $existing->confirmation_token;

        $session = new ArraySessionStore();
        $model = new SignupModelWithoutMailer(
            ['email' => $email, 'password' => 'Whatever!Pass1', 'wanted_url' => '/'],
            $session,
            new UserDao(obtainTestDatabase()),
            new TeamDao(obtainTestDatabase())
        );
        $model->processSignup();

        $row = $this->fetchCredentialColumns($uid);

        $this->assertSame($issuedToken, $row['confirmation_token'], 'the in-flight link must still work');
        $this->assertSame(1, $model->setPasswordMailsSent, 'and the same link is sent again');
    }

    /**
     * Replica of OAuthSignInModel::_createNewUser(): first name, last name and email only, so salt
     * and pass are left NULL by the insert.
     */
    private function insertOauthOnlyUser(string $email): int
    {
        $user = new UserStruct(['first_name' => 'Oauth', 'last_name' => 'User', 'email' => $email]);
        $user->oauth_access_token = 'TEST_ENCRYPTED_TOKEN';
        $user->create_date = \Utils\Tools\Utils::mysqlTimestamp(time());

        $uid = (new UserDao(obtainTestDatabase()))->insertStruct($user);
        $this->assertNotFalse($uid);

        $this->createdUids[] = (int)$uid;

        return (int)$uid;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchCredentialColumns(int $uid): array
    {
        $dao = new UserDao(obtainTestDatabase());
        $dao->destroyCacheByUid($uid);

        $statement = obtainTestDatabase()->getConnection()->prepare(
            "SELECT salt, pass, confirmation_token, confirmation_token_created_at FROM users WHERE uid = ?"
        );
        $statement->execute([$uid]);

        return $statement->fetch(\PDO::FETCH_ASSOC);
    }

    #[Test]
    public function testConfirmThrowsWhenTokenNotFound()
    {
        $dao = $this->createMock(UserDao::class);
        $dao->method('getByScopedConfirmationToken')
            ->with('bad_token')
            ->willReturn(null);

        $session = new ArraySessionStore();
        $signup = new SignupModel(['token' => 'bad_token'], $session, $dao, new TeamDao(obtainTestDatabase()));

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Confirmation token not found');

        $signup->confirm();
    }

    #[Test]
    public function testConfirmThrowsWhenConfirmationTokenCreatedAtIsNull()
    {
        $user = new UserStruct([
            'uid' => 1,
            'email' => 'test@example.com',
            'confirmation_token' => 'abc123',
            'confirmation_token_created_at' => null,
        ]);

        $dao = $this->createMock(UserDao::class);
        $dao->method('getByScopedConfirmationToken')
            ->with('abc123')
            ->willReturn($user);

        $session = new ArraySessionStore();
        $signup = new SignupModel(['token' => 'abc123'], $session, $dao, new TeamDao(obtainTestDatabase()));

        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Confirmation token is invalid');

        $signup->confirm();
    }

    #[Test]
    public function testConfirmAcceptsValidToken()
    {
        $user = new UserStruct([
            'uid' => 1,
            'email' => 'test@example.com',
            'confirmation_token' => 'abc123',
            'confirmation_token_created_at' => date('Y-m-d H:i:s'),
            'email_confirmed_at' => null,
        ]);
        $user->uid = 1;

        $dao = $this->createMock(UserDao::class);
        $dao->method('getByScopedConfirmationToken')
            ->with('abc123')
            ->willReturn($user);
        $dao->method('updateStruct')->willReturn(1);
        $dao->method('destroyCacheByEmail')->willReturn(true);
        $dao->method('destroyCacheByUid')->willReturn(true);

        $session = new ArraySessionStore();
        $signup = new SignupModel(['token' => 'abc123'], $session, $dao, new TeamDao(obtainTestDatabase()));

        $result = $signup->confirm();

        $this->assertInstanceOf(UserStruct::class, $result);
        $this->assertNotEmpty($result->email_confirmed_at);
    }

    #[Test]
    public function testForgotPasswordReturnsTrueWhenUserFound()
    {
        $user = new UserStruct(['email' => 'test@example.com', 'uid' => 1]);
        $user->uid = 1;

        $dao = $this->createMock(UserDao::class);
        $dao->method('getByEmail')
            ->with('test@example.com')
            ->willReturn($user);
        $dao->method('updateStruct')->willReturn(1);

        $session = new ArraySessionStore();
        $signup = new SignupModel([
            'email' => 'test@example.com',
            'wanted_url' => '/path',
        ], $session, $dao, new TeamDao(obtainTestDatabase()));

        $this->assertTrue($signup->forgotPassword());
    }

    #[Test]
    public function testForgotPasswordReturnsFalseWhenUserNotFound()
    {
        $dao = $this->createMock(UserDao::class);
        $dao->method('getByEmail')
            ->with('unknown@example.com')
            ->willReturn(null);

        $session = new ArraySessionStore();
        $signup = new SignupModel([
            'email' => 'unknown@example.com',
            'wanted_url' => '/path',
        ], $session, $dao, new TeamDao(obtainTestDatabase()));

        $this->assertFalse($signup->forgotPassword());
    }

    #[Test]
    public function testSessionIsPassedByReference()
    {
        $session = new ArraySessionStore(['existing' => 'value']);
        $model = new SignupModel(['wanted_url' => '/path'], $session, new UserDao(obtainTestDatabase()), new TeamDao(obtainTestDatabase()));

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__saveWantedUrl');
        $method->invoke($model);

        $this->assertSame('/path', $session->get('wanted_url'));
        $this->assertSame('value', $session->get('existing'));
    }
}

/**
 * Counts set-password mails instead of handing them to the real mailer, so the persistence
 * assertions can run without SMTP.
 */
class SignupModelWithoutMailer extends SignupModel
{

    public int $setPasswordMailsSent = 0;

    protected function createSetPasswordRequestEmail(): SetPasswordRequestEmail
    {
        $this->setPasswordMailsSent++;

        $email = $this->createStubEmail();

        return $email;
    }

    private function createStubEmail(): SetPasswordRequestEmail
    {
        return new class ($this->getUser()) extends SetPasswordRequestEmail {
            public function send(): void
            {
            }
        };
    }

}
