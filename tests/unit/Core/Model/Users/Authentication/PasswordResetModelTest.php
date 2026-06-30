<?php


namespace Matecat\Core\Model\Users\Authentication;
use Controller\API\Commons\Exceptions\ValidationError;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\Authentication\PasswordResetModel;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Utils\Tools\Utils;

class PasswordResetModelTest extends AbstractTest
{
    private function makeUserWithToken(): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.com';
        $user->salt = 'test-salt';
        $user->pass = Utils::encryptPass('old-pass', 'test-salt');
        $user->confirmation_token = 'valid-token';
        $user->confirmation_token_created_at = date('Y-m-d H:i:s');

        return $user;
    }

    private function makeMockDao(?UserStruct $user = null): UserDao
    {
        $dao = $this->createStub(UserDao::class);
        $dao->method('getByConfirmationToken')->willReturn($user);
        $dao->method('updateStruct')->willReturn(1);
        $dao->method('destroyCacheByEmail')->willReturn(true);
        $dao->method('destroyCacheByUid')->willReturn(true);

        return $dao;
    }

    #[Test]
    public function constructorSetsTokenFromParam(): void
    {
        $session = [];
        $dao = $this->makeMockDao();
        $model = new PasswordResetModel($session, $dao, 'my-token');

        $ref = new ReflectionProperty($model, 'token');
        $this->assertSame('my-token', $ref->getValue($model));
    }

    #[Test]
    public function constructorFallsBackToSessionToken(): void
    {
        $session = ['password_reset_token' => 'session-token'];
        $dao = $this->makeMockDao();
        $model = new PasswordResetModel($session, $dao, null);

        $ref = new ReflectionProperty($model, 'token');
        $this->assertSame('session-token', $ref->getValue($model));
    }

    #[Test]
    public function validateUserThrowsWhenUserNotFound(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid authentication token');

        $session = [];
        $dao = $this->makeMockDao(null);
        $model = new PasswordResetModel($session, $dao, 'bad-token');
        $model->validateUser();
    }

    #[Test]
    public function validateUserThrowsWhenTokenExpired(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Auth token expired');

        $user = $this->makeUserWithToken();
        $user->confirmation_token_created_at = date('Y-m-d H:i:s', strtotime('2 hours ago'));

        $session = [];
        $dao = $this->makeMockDao($user);
        $model = new PasswordResetModel($session, $dao, 'valid-token');
        $model->validateUser();
    }

    #[Test]
    public function validateUserSucceedsWithValidToken(): void
    {
        $user = $this->makeUserWithToken();

        $session = [];
        $dao = $this->makeMockDao($user);
        $model = new PasswordResetModel($session, $dao, 'valid-token');
        $model->validateUser();

        $this->assertSame('valid-token', $session['password_reset_token']);
    }

    #[Test]
    public function resetPasswordChangesPassword(): void
    {
        $user = $this->makeUserWithToken();
        $oldPass = $user->pass;

        $session = [];
        $dao = $this->makeMockDao($user);
        $model = new PasswordResetModel($session, $dao, 'valid-token');
        $model->resetPassword('new-secure-pass!');

        $this->assertNotSame($oldPass, $user->pass);
        $this->assertNull($user->confirmation_token);
    }

    #[Test]
    public function resetPasswordThrowsWhenUserNotFound(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid authentication token');

        $session = [];
        $dao = $this->makeMockDao(null);
        $model = new PasswordResetModel($session, $dao, 'bad-token');
        $model->resetPassword('new-pass!');
    }

    #[Test]
    public function resetPasswordSetsEmailConfirmedWhenNull(): void
    {
        $user = $this->makeUserWithToken();
        $user->email_confirmed_at = null;

        $session = [];
        $dao = $this->makeMockDao($user);
        $model = new PasswordResetModel($session, $dao, 'valid-token');
        $model->resetPassword('new-pass!');

        $this->assertNotNull($user->email_confirmed_at);
    }

    #[Test]
    public function flushWantedUrlReturnsAndClearsSession(): void
    {
        $session = ['wanted_url' => 'https://example.com/target'];
        $dao = $this->makeMockDao();
        $model = new PasswordResetModel($session, $dao, 'token');

        $url = $model->flushWantedURL();

        $this->assertSame('https://example.com/target', $url);
        $this->assertArrayNotHasKey('wanted_url', $session);
    }

    #[Test]
    public function flushWantedUrlReturnsDefaultWhenNotSet(): void
    {
        $session = [];
        $dao = $this->makeMockDao();
        $model = new PasswordResetModel($session, $dao, 'token');

        $url = $model->flushWantedURL();

        $this->assertNotEmpty($url);
    }

    #[Test]
    public function getUserReturnsNull(): void
    {
        $session = [];
        $dao = $this->makeMockDao();
        $model = new PasswordResetModel($session, $dao, 'token');

        $this->assertNull($model->getUser());
    }
}
