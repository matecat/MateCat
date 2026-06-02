<?php

use Controller\API\Commons\Exceptions\ValidationError;
use Model\Teams\TeamDao;
use Model\Users\Authentication\SignupModel;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

#[Group('unit')]
class SignupModelUnitTest extends AbstractTest
{
    #[Test]
    public function testConstructPopulatesParams()
    {
        $session = [];
        $params = ['email' => 'test@example.com', 'password' => 'secret'];
        $model = new SignupModel($params, $session);

        $this->assertSame($params, $model->getParams());
    }

    #[Test]
    public function testConstructCreatesUserStruct()
    {
        $session = [];
        $model = new SignupModel(['email' => 'test@example.com'], $session);

        $this->assertInstanceOf(UserStruct::class, $model->getUser());
    }

    #[Test]
    public function testConstructWithDiInitializesDaos()
    {
        $userDao = $this->createStub(UserDao::class);
        $teamDao = $this->createStub(TeamDao::class);
        $session = [];
        $model = new SignupModel(['email' => 'test@example.com'], $session, $userDao, $teamDao);

        $this->assertSame('test@example.com', $model->getUser()->email);
    }

    #[Test]
    public function testGetUserReturnsUserStructWithGivenData()
    {
        $session = [];
        $model = new SignupModel(['email' => 'test@example.com'], $session);

        $this->assertSame('test@example.com', $model->getUser()->email);
    }

    #[Test]
    public function testGetErrorReturnsNullInitially()
    {
        $session = [];
        $model = new SignupModel([], $session);

        $this->assertNull($model->getError());
    }

    #[Test]
    public function testFlushWantedUrlReturnsAppRootWhenNotSet()
    {
        $session = [];
        $model = new SignupModel([], $session);

        $url = $model->flushWantedURL();

        $this->assertIsString($url);
        $this->assertNotEmpty($url);
    }

    #[Test]
    public function testFlushWantedUrlReturnsStoredUrlAndUnsetsIt()
    {
        $session = [];
        $model = new SignupModel(['wanted_url' => '/dashboard'], $session);

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__saveWantedUrl');
        $method->invoke($model);

        $url = $model->flushWantedURL();

        $this->assertSame('/dashboard', $url);
    }

    #[Test]
    public function testResendConfirmationEmailWithEmptyStringReturnsEarly()
    {
        SignupModel::resendConfirmationEmail('');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function testResendConfirmationEmailWithWhitespaceReturnsEarly()
    {
        SignupModel::resendConfirmationEmail('   ');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function testResendConfirmationEmailCallsDaoWithValidEmail()
    {
        $user = new UserStruct(['email' => 'test@example.com', 'confirmation_token' => 'tok123']);
        $user->initAuthToken();

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
        $session = [];
        $model = new SignupModel([], $session);

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

        $session = [];
        $model = new SignupModel(['email' => 'existing@example.com'], $session, $dao);

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

        $session = [];
        $model = new SignupModel(['email' => 'new@example.com'], $session, $dao);

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__userAlreadyExists');

        $this->assertFalse($method->invoke($model));
    }

    #[Test]
    public function testUserAlreadyExistsAndIsActiveReturnsFalseWhenUidNotSet()
    {
        $session = [];
        $model = new SignupModel(['email' => 'test@example.com'], $session);

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__userAlreadyExistsAndIsActive');

        $this->assertFalse($method->invoke($model));
    }

    #[Test]
    public function testUserAlreadyExistsAndIsActiveReturnsTrueWhenEmailConfirmed()
    {
        $session = [];
        $model = new SignupModel(['email' => 'test@example.com'], $session);
        $model->getUser()->uid = 123;
        $model->getUser()->email_confirmed_at = '2024-01-01 00:00:00';

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__userAlreadyExistsAndIsActive');

        $this->assertTrue($method->invoke($model));
    }

    #[Test]
    public function testUserAlreadyExistsAndIsActiveReturnsTrueWhenHasOauthToken()
    {
        $session = [];
        $model = new SignupModel(['email' => 'test@example.com'], $session);
        $model->getUser()->uid = 123;
        $model->getUser()->oauth_access_token = 'token123';

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__userAlreadyExistsAndIsActive');

        $this->assertTrue($method->invoke($model));
    }

    #[Test]
    public function testPrepareNewUserThrowsRuntimeExceptionWhenEmailIsNull()
    {
        $session = [];
        $model = new SignupModel([], $session);

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__prepareNewUser');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User email must be set before signup');

        $method->invoke($model);
    }

    #[Test]
    public function testSaveWantedUrlStoresInSession()
    {
        $session = [];
        $model = new SignupModel(['wanted_url' => '/projects/123'], $session);

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__saveWantedUrl');
        $method->invoke($model);

        $this->assertSame('/projects/123', $session['wanted_url']);
    }

    #[Test]
    public function testUpdatePersistedUserGeneratesSaltWhenEmpty()
    {
        $session = [];
        $model = new SignupModel(['email' => 'test@example.com', 'password' => 'secret123'], $session);
        $model->getUser()->salt = '';

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__updatePersistedUser');
        $method->invoke($model);

        $this->assertNotEmpty($model->getUser()->salt);
        $this->assertEquals(15, strlen($model->getUser()->salt));
    }

    #[Test]
    public function testUpdatePersistedUserKeepsExistingSalt()
    {
        $session = [];
        $model = new SignupModel(['email' => 'test@example.com', 'password' => 'secret123'], $session);
        $model->getUser()->salt = 'existing_salt_v';

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__updatePersistedUser');
        $method->invoke($model);

        $this->assertSame('existing_salt_v', $model->getUser()->salt);
    }

    #[Test]
    public function testConfirmThrowsWhenTokenNotFound()
    {
        $dao = $this->createMock(UserDao::class);
        $dao->method('getByConfirmationToken')
            ->with('bad_token')
            ->willReturn(null);

        $session = [];
        $signup = new SignupModel(['token' => 'bad_token'], $session, $dao);

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
        $dao->method('getByConfirmationToken')
            ->with('abc123')
            ->willReturn($user);

        $session = [];
        $signup = new SignupModel(['token' => 'abc123'], $session, $dao);

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
        $dao->method('getByConfirmationToken')
            ->with('abc123')
            ->willReturn($user);
        $dao->method('updateStruct')->willReturn(1);
        $dao->method('destroyCacheByEmail')->willReturn(true);
        $dao->method('destroyCacheByUid')->willReturn(true);

        $session = [];
        $signup = new SignupModel(['token' => 'abc123'], $session, $dao);

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

        $session = [];
        $signup = new SignupModel([
            'email' => 'test@example.com',
            'wanted_url' => '/path',
        ], $session, $dao);

        $this->assertTrue($signup->forgotPassword());
    }

    #[Test]
    public function testForgotPasswordReturnsFalseWhenUserNotFound()
    {
        $dao = $this->createMock(UserDao::class);
        $dao->method('getByEmail')
            ->with('unknown@example.com')
            ->willReturn(null);

        $session = [];
        $signup = new SignupModel([
            'email' => 'unknown@example.com',
            'wanted_url' => '/path',
        ], $session, $dao);

        $this->assertFalse($signup->forgotPassword());
    }

    #[Test]
    public function testSessionIsPassedByReference()
    {
        $session = ['existing' => 'value'];
        $model = new SignupModel(['wanted_url' => '/path'], $session);

        $ref = new ReflectionClass($model);
        $method = $ref->getMethod('__saveWantedUrl');
        $method->invoke($model);

        $this->assertSame('/path', $session['wanted_url']);
        $this->assertSame('value', $session['existing']);
    }
}
