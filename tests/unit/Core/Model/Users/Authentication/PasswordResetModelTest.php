<?php


namespace Matecat\Core\Model\Users\Authentication;

use Utils\Session\ArraySessionStore;
use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
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

    /**
     * A stub rather than a mock: these cases do not assert on revocation, and a mock with no
     * configured expectations is a PHPUnit notice. The revocation assertions live in their own
     * case below, which builds a mock explicitly.
     */
    private function makeTokenStore(): SessionTokenStoreHandler
    {
        return $this->createStub(SessionTokenStoreHandler::class);
    }

    private function makeMockDao(?UserStruct $user = null): UserDao
    {
        $dao = $this->createStub(UserDao::class);
        $dao->method('getByScopedConfirmationToken')->willReturn($user);
        $dao->method('updateStruct')->willReturn(1);
        $dao->method('destroyCache');

        return $dao;
    }

    #[Test]
    public function constructorSetsTokenFromParam(): void
    {
        $session = new ArraySessionStore();
        $dao = $this->makeMockDao();
        $model = new PasswordResetModel($session, $dao, $this->makeTokenStore(), 'my-token');

        $ref = new ReflectionProperty($model, 'token');
        $this->assertSame('my-token', $ref->getValue($model));
    }

    #[Test]
    public function constructorFallsBackToSessionToken(): void
    {
        $session = new ArraySessionStore(['password_reset_token' => 'session-token']);
        $dao = $this->makeMockDao();
        $model = new PasswordResetModel($session, $dao, $this->makeTokenStore(), null);

        $ref = new ReflectionProperty($model, 'token');
        $this->assertSame('session-token', $ref->getValue($model));
    }

    #[Test]
    public function validateUserThrowsWhenUserNotFound(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid authentication token');

        $session = new ArraySessionStore();
        $dao = $this->makeMockDao(null);
        $model = new PasswordResetModel($session, $dao, $this->makeTokenStore(), 'bad-token');
        $model->validateUser();
    }

    /**
     * Arriving with no token at all is a bad request, not a server fault. It used to raise a
     * RuntimeException, which Bootstrap::exceptionHandler() has no case for, so the caller was
     * answered 500 for something they were free to get wrong.
     */
    #[Test]
    public function anAbsentTokenIsARejectionRatherThanAFailure(): void
    {
        foreach (['validateUser', 'resetPassword'] as $method) {
            $model = new PasswordResetModel(
                new ArraySessionStore(),
                $this->makeMockDao(),
                $this->makeTokenStore(),
                null
            );

            try {
                $method === 'validateUser' ? $model->validateUser() : $model->resetPassword('new-pass!');
                $this->fail("$method accepted a request carrying no reset token");
            } catch (ValidationError $e) {
                $this->assertSame('Missing reset token', $e->getMessage());
            }
        }
    }

    #[Test]
    public function validateUserThrowsWhenTokenExpired(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Auth token expired');

        $user = $this->makeUserWithToken();
        $user->confirmation_token_created_at = date('Y-m-d H:i:s', strtotime('2 hours ago'));

        $session = new ArraySessionStore();
        $dao = $this->makeMockDao($user);
        $model = new PasswordResetModel($session, $dao, $this->makeTokenStore(), 'valid-token');
        $model->validateUser();
    }

    #[Test]
    public function validateUserSucceedsWithValidToken(): void
    {
        $user = $this->makeUserWithToken();

        $session = new ArraySessionStore();
        $dao = $this->makeMockDao($user);
        $model = new PasswordResetModel($session, $dao, $this->makeTokenStore(), 'valid-token');
        $model->validateUser();

        $this->assertSame('valid-token', $session->get('password_reset_token'));
    }

    #[Test]
    public function resetPasswordRevokesEveryLoginTokenForTheUser(): void
    {
        $user  = $this->makeUserWithToken();
        $store = $this->createMock(SessionTokenStoreHandler::class);

        // This flow previously revoked nothing at all: the user arrives with no authentication
        // cookie, so removeLoginCookieFromStore() was handed an empty value and returned early.
        // Anyone holding a stolen cookie kept working straight through the reset.
        $store->expects($this->once())
            ->method('revokeAllLoginTokens')
            ->with($user->uid);

        $session = new ArraySessionStore();
        (new PasswordResetModel($session, $this->makeMockDao($user), $store, 'valid-token'))
            ->resetPassword('new-secure-pass!');
    }

    #[Test]
    public function aRejectedResetRevokesNothing(): void
    {
        $store = $this->createMock(SessionTokenStoreHandler::class);
        $store->expects($this->never())->method('revokeAllLoginTokens');

        $session = new ArraySessionStore();
        $model   = new PasswordResetModel($session, $this->makeMockDao(), $store, 'wrong-token');

        // An unusable token must not be able to log the account's devices out.
        $this->expectException(ValidationError::class);
        $model->resetPassword('new-secure-pass!');
    }

    #[Test]
    public function resetPasswordChangesPassword(): void
    {
        $user = $this->makeUserWithToken();
        $oldPass = $user->pass;

        $session = new ArraySessionStore();
        $dao = $this->makeMockDao($user);
        $model = new PasswordResetModel($session, $dao, $this->makeTokenStore(), 'valid-token');
        $model->resetPassword('new-secure-pass!');

        $this->assertNotSame($oldPass, $user->pass);
        $this->assertNull($user->confirmation_token);
    }

    #[Test]
    public function resetPasswordThrowsWhenUserNotFound(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Invalid authentication token');

        $session = new ArraySessionStore();
        $dao = $this->makeMockDao(null);
        $model = new PasswordResetModel($session, $dao, $this->makeTokenStore(), 'bad-token');
        $model->resetPassword('new-pass!');
    }

    #[Test]
    public function resetPasswordSetsEmailConfirmedWhenNull(): void
    {
        $user = $this->makeUserWithToken();
        $user->email_confirmed_at = null;

        $session = new ArraySessionStore();
        $dao = $this->makeMockDao($user);
        $model = new PasswordResetModel($session, $dao, $this->makeTokenStore(), 'valid-token');
        $model->resetPassword('new-pass!');

        $this->assertNotNull($user->email_confirmed_at);
    }

    #[Test]
    public function flushWantedUrlReturnsAndClearsSession(): void
    {
        $session = new ArraySessionStore(['wanted_url' => 'https://example.com/target']);
        $dao = $this->makeMockDao();
        $model = new PasswordResetModel($session, $dao, $this->makeTokenStore(), 'token');

        $url = $model->flushWantedURL();

        $this->assertSame('https://example.com/target', $url);
        $this->assertFalse($session->has('wanted_url'));
    }

    #[Test]
    public function flushWantedUrlReturnsDefaultWhenNotSet(): void
    {
        $session = new ArraySessionStore();
        $dao = $this->makeMockDao();
        $model = new PasswordResetModel($session, $dao, $this->makeTokenStore(), 'token');

        $url = $model->flushWantedURL();

        $this->assertNotEmpty($url);
    }

    #[Test]
    public function getUserReturnsNull(): void
    {
        $session = new ArraySessionStore();
        $dao = $this->makeMockDao();
        $model = new PasswordResetModel($session, $dao, $this->makeTokenStore(), 'token');

        $this->assertNull($model->getUser());
    }

    #[Test]
    public function resetPasswordThrowsWhenTokenExpired(): void
    {
        $this->expectException(ValidationError::class);
        $this->expectExceptionMessage('Auth token expired');

        $user = $this->makeUserWithToken();
        $user->confirmation_token_created_at = date('Y-m-d H:i:s', strtotime('31 minutes ago'));
        $oldPass = $user->pass;

        // validateUser() guards the link click, but the form submission that follows reads the token
        // back out of the session and lands here. Without its own check, this method would accept a
        // token of any age as long as the session outlived the 30 minute window.
        $session = new ArraySessionStore(['password_reset_token' => 'valid-token']);
        $model = new PasswordResetModel($session, $this->makeMockDao($user), $this->makeTokenStore(), null);

        try {
            $model->resetPassword('new-pass');
        } finally {
            $this->assertSame($oldPass, $user->pass);
        }
    }

    /**
     * Accounts created through an external provider are inserted without a salt, and this used to be
     * the one flow that could give them a password — except it aborted on the missing salt, so the
     * owner had no way back in at all. Mint one instead.
     */
    #[Test]
    public function resetPasswordMintsASaltWhenTheAccountHasNone(): void
    {
        foreach ([null, ''] as $missingSalt) {
            $user = $this->makeUserWithToken();
            $user->salt = $missingSalt;
            $user->pass = null;

            $session = new ArraySessionStore();
            $model = new PasswordResetModel($session, $this->makeMockDao($user), $this->makeTokenStore(), 'valid-token');
            $model->resetPassword('new-pass');

            $this->assertSame(32, strlen((string)$user->salt));
            $this->assertTrue(
                Utils::verifyPass('new-pass', $user->salt, (string)$user->pass),
                'the new password must verify against the freshly minted salt'
            );
        }
    }

    /**
     * A salt that is already present is what the stored hash was built with, so it has to survive.
     */
    #[Test]
    public function resetPasswordKeepsAnExistingSalt(): void
    {
        $user = $this->makeUserWithToken();

        $session = new ArraySessionStore();
        $model = new PasswordResetModel($session, $this->makeMockDao($user), $this->makeTokenStore(), 'valid-token');
        $model->resetPassword('new-pass');

        $this->assertSame('test-salt', $user->salt);
        $this->assertTrue(Utils::verifyPass('new-pass', 'test-salt', (string)$user->pass));
    }
}
