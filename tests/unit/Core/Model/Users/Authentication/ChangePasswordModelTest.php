<?php


namespace Matecat\Core\Model\Users\Authentication;
use Controller\API\Commons\Exceptions\ValidationError;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\Authentication\ChangePasswordModel;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Utils\Tools\Utils;

class ChangePasswordModelTest extends AbstractTest
{
    private function makeUser(string $password = 'old-pass'): UserStruct
    {
        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'test@example.com';
        $user->salt = 'test-salt';
        $user->pass = Utils::encryptPass($password, 'test-salt');
        $user->email_confirmed_at = '2026-01-01 00:00:00';

        return $user;
    }

    private function makeMockDao(): UserDao
    {
        $dao = $this->createStub(UserDao::class);
        $dao->method('updateStruct')->willReturn(1);
        $dao->method('destroyCacheByEmail')->willReturn(true);
        $dao->method('destroyCacheByUid')->willReturn(true);

        return $dao;
    }

    #[Test]
    public function changePasswordSucceeds(): void
    {
        $user = $this->makeUser('old-pass');
        $dao = $this->makeMockDao();

        $model = new ChangePasswordModel($user, $dao);
        $model->changePassword('old-pass', 'new-pass-123!');

        $this->assertTrue(Utils::verifyPass('new-pass-123!', $user->salt, $user->pass));
    }

    #[Test]
    public function changePasswordFailsWithWrongOldPassword(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid password');

        $user = $this->makeUser('old-pass');
        $dao = $this->makeMockDao();

        $model = new ChangePasswordModel($user, $dao);
        $model->changePassword('wrong-pass', 'new-pass-123!');
    }

    #[Test]
    public function changePasswordFailsWhenSameAsOld(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('New password cannot be the same as your old password');

        $user = $this->makeUser('old-pass');
        $dao = $this->makeMockDao();

        $model = new ChangePasswordModel($user, $dao);
        $model->changePassword('old-pass', 'old-pass');
    }

    #[Test]
    public function changePasswordSetsEmailConfirmedWhenNull(): void
    {
        $user = $this->makeUser('old-pass');
        $user->email_confirmed_at = null;
        $dao = $this->makeMockDao();

        $model = new ChangePasswordModel($user, $dao);
        $model->changePassword('old-pass', 'new-pass-123!');

        $this->assertNotNull($user->email_confirmed_at);
    }

    #[Test]
    public function changePasswordThrowsWhenSaltNull(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User salt must be set');

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'a@b.com';
        $dao = $this->makeMockDao();

        $model = new ChangePasswordModel($user, $dao);
        $model->changePassword('old', 'new');
    }
}
