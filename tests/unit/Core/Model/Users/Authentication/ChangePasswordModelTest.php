<?php


namespace Matecat\Core\Model\Users\Authentication;
use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
use Controller\API\Commons\Exceptions\ValidationError;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\Authentication\ChangePasswordModel;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
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

    /**
     * A stub rather than a mock: these cases do not assert on revocation, and a mock with no
     * configured expectations is a PHPUnit notice. The revocation assertions live in their own
     * case below, which builds a mock explicitly.
     */
    private function makeTokenStore(): SessionTokenStoreHandler
    {
        return $this->createStub(SessionTokenStoreHandler::class);
    }

    private function makeMockDao(): UserDao
    {
        $dao = $this->createStub(UserDao::class);
        $dao->method('updateStruct')->willReturn(1);
        $dao->method('destroyCache');

        return $dao;
    }

    #[Test]
    public function changePasswordRevokesEveryLoginTokenForTheUser(): void
    {
        $user  = $this->makeUser();
        $store = $this->createMock(SessionTokenStoreHandler::class);

        // Other devices are still holding cookies minted under the old password, so the change has
        // to retire all of them — not merely the one presented by the device making the change,
        // which is all broadcastLogout() removes.
        $store->expects($this->once())
            ->method('revokeAllLoginTokens')
            ->with($user->uid);

        (new ChangePasswordModel($user, $this->makeMockDao(), $store))
            ->changePassword('old-pass', 'new-secure-pass!');
    }

    #[Test]
    public function aRejectedPasswordChangeRevokesNothing(): void
    {
        $store = $this->createMock(SessionTokenStoreHandler::class);
        $store->expects($this->never())->method('revokeAllLoginTokens');

        $this->expectException(ValidationError::class);

        // Revocation has to sit behind the old-password check. A wrong guess must not be able to
        // log every one of the account's devices out.
        (new ChangePasswordModel($this->makeUser(), $this->makeMockDao(), $store))
            ->changePassword('wrong-pass', 'new-secure-pass!');
    }

    #[Test]
    public function changePasswordSucceeds(): void
    {
        $user = $this->makeUser('old-pass');
        $dao = $this->makeMockDao();

        $model = new ChangePasswordModel($user, $dao, $this->makeTokenStore());
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

        $model = new ChangePasswordModel($user, $dao, $this->makeTokenStore());
        $model->changePassword('wrong-pass', 'new-pass-123!');
    }

    #[Test]
    public function changePasswordFailsWhenSameAsOld(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('New password cannot be the same as your old password');

        $user = $this->makeUser('old-pass');
        $dao = $this->makeMockDao();

        $model = new ChangePasswordModel($user, $dao, $this->makeTokenStore());
        $model->changePassword('old-pass', 'old-pass');
    }

    #[Test]
    public function changePasswordSetsEmailConfirmedWhenNull(): void
    {
        $user = $this->makeUser('old-pass');
        $user->email_confirmed_at = null;
        $dao = $this->makeMockDao();

        $model = new ChangePasswordModel($user, $dao, $this->makeTokenStore());
        $model->changePassword('old-pass', 'new-pass-123!');

        $this->assertNotNull($user->email_confirmed_at);
    }

    /**
     * A provider-only account has no password to change. That is a rejected attempt, not a broken row,
     * so it is answered the same way a wrong old password is.
     */
    #[Test]
    public function changePasswordRejectsAnAccountWithNoPassword(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid password');

        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'a@b.com';
        $dao = $this->makeMockDao();

        $model = new ChangePasswordModel($user, $dao, $this->makeTokenStore());
        $model->changePassword('old', 'new');
    }

    /**
     * An empty salt leaves the global pepper as the only per-account variation, so it must not survive
     * a password rewrite. Verification still has to run against the empty value the stored hash was
     * built with.
     */
    #[Test]
    public function changePasswordRotatesAnEmptySalt(): void
    {
        $user = $this->makeUser();
        $user->salt = '';
        $user->pass = Utils::encryptPass('old-pass', '');

        $model = new ChangePasswordModel($user, $this->makeMockDao(), $this->makeTokenStore());
        $model->changePassword('old-pass', 'new-pass');

        $this->assertSame(32, strlen((string)$user->salt));
        $this->assertTrue(Utils::verifyPass('new-pass', $user->salt, (string)$user->pass));
    }

    #[Test]
    public function changePasswordKeepsANonEmptySalt(): void
    {
        $user = $this->makeUser();

        $model = new ChangePasswordModel($user, $this->makeMockDao(), $this->makeTokenStore());
        $model->changePassword('old-pass', 'new-pass');

        $this->assertSame('test-salt', $user->salt);
        $this->assertTrue(Utils::verifyPass('new-pass', 'test-salt', (string)$user->pass));
    }

    /**
     * The rewritten salt is worthless if it is not persisted alongside the password.
     */
    #[Test]
    public function changePasswordPersistsTheSaltColumn(): void
    {
        $user = $this->makeUser();
        $user->salt = '';
        $user->pass = Utils::encryptPass('old-pass', '');

        $captured = [];
        $dao = $this->createMock(UserDao::class);
        $dao->method('destroyCache');
        $dao->expects($this->once())
            ->method('updateStruct')
            ->willReturnCallback(function ($struct, $options) use (&$captured) {
                $captured = $options['fields'];

                return 1;
            });

        (new ChangePasswordModel($user, $dao, $this->makeTokenStore()))->changePassword('old-pass', 'new-pass');

        $this->assertContains('salt', $captured);
        $this->assertContains('pass', $captured);
    }

    #[Test]
    public function changePasswordInvalidatesAnOutstandingResetToken(): void
    {
        $user = $this->makeUser('old-pass');
        $user->confirmation_token = 'a-reset-link-already-in-the-mailbox';
        $user->confirmation_token_created_at = '2026-01-01 00:00:00';

        $captured = [];
        $dao = $this->createStub(UserDao::class);
        $dao->method('updateStruct')->willReturnCallback(
            function (UserStruct $struct, array $fieldsToUpdate) use (&$captured): int {
                $captured = $fieldsToUpdate;

                return 1;
            }
        );
        $dao->method('destroyCache');

        (new ChangePasswordModel($user, $dao, $this->makeTokenStore()))->changePassword('old-pass', 'new-pass');

        // Changing the password has to retire any reset link already issued for the account. If the
        // token survives, whoever holds that link can set a third password for the rest of the
        // token's lifetime and silently undo the change the user just made.
        self::assertNull($user->confirmation_token);
        self::assertNull($user->confirmation_token_created_at);

        // Nulling the struct is not enough on its own: the fields have to be in the update list, or
        // the row keeps the old token and the change never reaches the database.
        self::assertContains('confirmation_token', $captured['fields']);
        self::assertContains('confirmation_token_created_at', $captured['fields']);
    }
}
