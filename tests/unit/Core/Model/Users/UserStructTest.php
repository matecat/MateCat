<?php


namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use Utils\Tools\Utils;

class UserStructTest extends AbstractTest
{
    #[Test]
    public function isLoggedReturnsTrueWhenAllFieldsSet(): void
    {
        $user = new UserStruct();
        $user->uid = 1;
        $user->email = 'a@b.com';
        $user->first_name = 'A';
        $user->last_name = 'B';

        $this->assertTrue($user->isLogged());
        $this->assertFalse($user->isAnonymous());
    }

    #[Test]
    public function isLoggedReturnsFalseWhenMissingFields(): void
    {
        $user = new UserStruct();

        $this->assertFalse($user->isLogged());
        $this->assertTrue($user->isAnonymous());
    }

    /**
     * Regression guard. `isLogged()` used to demand a first and last name as well, which locked every
     * account carrying blank ones out of the application: the login call itself answered 200 and set
     * the cookie, then every authenticated request that followed was rejected as anonymous, so the
     * browser bounced straight back to the login form.
     *
     * A display name is profile data, not an authentication fact, so it must not participate here.
     * Re-adding either name to the condition reintroduces the lockout — and the two cases above cannot
     * detect that, since one populates every field and the other populates none, leaving both green
     * under either implementation. This is the case that fails.
     */
    #[Test]
    public function isLoggedIgnoresBlankNames(): void
    {
        $user = new UserStruct();
        $user->uid = 35026;
        $user->email = 'a@b.com';
        $user->first_name = '';
        $user->last_name = '';

        $this->assertTrue($user->isLogged());
        $this->assertFalse($user->isAnonymous());
    }

    /**
     * Identity is both halves: neither alone identifies an account.
     */
    #[Test]
    public function isLoggedRequiresBothUidAndEmail(): void
    {
        $uidOnly = new UserStruct();
        $uidOnly->uid = 1;

        $emailOnly = new UserStruct();
        $emailOnly->email = 'a@b.com';

        $this->assertFalse($uidOnly->isLogged());
        $this->assertFalse($emailOnly->isLogged());
    }

    #[Test]
    public function fullNameCombinesFirstAndLast(): void
    {
        $user = new UserStruct();
        $user->first_name = 'John';
        $user->last_name = 'Doe';

        $this->assertSame('John Doe', $user->fullName());
    }

    #[Test]
    public function shortNameReturnsInitials(): void
    {
        $user = new UserStruct();
        $user->first_name = 'John';
        $user->last_name = 'Doe';

        $this->assertSame('JD', $user->shortName());
    }

    #[Test]
    public function shortNameHandlesNullNames(): void
    {
        $user = new UserStruct();

        $this->assertSame('', $user->shortName());
    }

    #[Test]
    public function gettersReturnProperties(): void
    {
        $user = new UserStruct();
        $user->uid = 42;
        $user->email = 'test@example.com';
        $user->first_name = 'A';
        $user->last_name = 'B';

        $this->assertSame(42, $user->getUid());
        $this->assertSame('test@example.com', $user->getEmail());
        $this->assertSame('A', $user->getFirstName());
        $this->assertSame('B', $user->getLastName());
    }

    #[Test]
    public function getStructReturnsNewInstance(): void
    {
        $struct = UserStruct::getStruct();

        $this->assertInstanceOf(UserStruct::class, $struct);
        $this->assertNull($struct->uid);
    }

    #[Test]
    public function everSignedInReturnsFalseForFreshUser(): void
    {
        $user = new UserStruct();

        $this->assertFalse($user->everSignedIn());
    }

    #[Test]
    public function everSignedInReturnsTrueWithEmailConfirmed(): void
    {
        $user = new UserStruct();
        $user->email_confirmed_at = '2026-01-01 00:00:00';

        $this->assertTrue($user->everSignedIn());
    }

    #[Test]
    public function clearAuthTokenNullifiesFields(): void
    {
        $user = new UserStruct();
        $user->confirmation_token = 'abc';
        $user->confirmation_token_created_at = '2026-01-01';

        $user->clearAuthToken();

        $this->assertNull($user->confirmation_token);
        $this->assertNull($user->confirmation_token_created_at);
    }

    #[Test]
    public function initAuthTokenSetsFields(): void
    {
        $user = new UserStruct();

        $user->initAuthToken();

        $this->assertNotNull($user->confirmation_token);
        $this->assertNotNull($user->confirmation_token_created_at);
        $this->assertSame(50, strlen($user->confirmation_token));
    }

    /**
     * Provider-only accounts have no salt and no password. Attempting a password login against one is
     * an ordinary miss, so it answers false — the same answer a wrong password gets, and never an
     * exception.
     */
    #[Test]
    public function passwordMatchReturnsFalseWhenSaltNull(): void
    {
        $user = new UserStruct();

        $this->assertFalse($user->passwordMatch('test'));
    }

    #[Test]
    public function passwordMatchReturnsFalseWhenPassNull(): void
    {
        $user = new UserStruct();
        $user->salt = 'somesalt';

        $this->assertFalse($user->passwordMatch('test'));
    }

    /**
     * An empty salt is not the same thing as a missing one: those accounts do hold a password, hashed
     * against the empty value, and it has to keep verifying.
     */
    #[Test]
    public function passwordMatchStillVerifiesAgainstAnEmptySalt(): void
    {
        $user = new UserStruct();
        $user->salt = '';
        $user->pass = Utils::encryptPass('correct-horse', '');

        $this->assertTrue($user->passwordMatch('correct-horse'));
        $this->assertFalse($user->passwordMatch('wrong-horse'));
    }

    /**
     * The repair has to leave the account usable: same password, real salt, and it still verifies.
     */
    #[Test]
    public function rotateEmptySaltRehashesAgainstAFreshSalt(): void
    {
        $user = new UserStruct();
        $user->salt = '';
        $user->pass = Utils::encryptPass('correct-horse', '');
        $oldPass = $user->pass;

        $this->assertTrue($user->rotateEmptySalt('correct-horse'));
        $this->assertSame(32, strlen((string)$user->salt));
        $this->assertNotSame($oldPass, $user->pass);
        $this->assertTrue($user->passwordMatch('correct-horse'));
        $this->assertFalse($user->passwordMatch('wrong-horse'));
    }

    #[Test]
    public function rotateEmptySaltLeavesARealSaltAlone(): void
    {
        $user = new UserStruct();
        $user->salt = 'a-real-salt';
        $user->pass = Utils::encryptPass('correct-horse', 'a-real-salt');
        $oldPass = $user->pass;

        $this->assertFalse($user->rotateEmptySalt('correct-horse'));
        $this->assertSame('a-real-salt', $user->salt);
        $this->assertSame($oldPass, $user->pass);
    }

    /**
     * NULL is not an empty salt: it means the account has no password at all, so there is nothing to
     * re-hash and inventing one would fabricate a credential.
     */
    #[Test]
    public function rotateEmptySaltIgnoresAnAccountWithNoPassword(): void
    {
        $user = new UserStruct();

        $this->assertFalse($user->rotateEmptySalt('correct-horse'));
        $this->assertNull($user->salt);
        $this->assertNull($user->pass);
    }

    #[Test]
    public function getDecryptedOauthAccessTokenReturnsNullWhenNoToken(): void
    {
        $user = new UserStruct();

        $this->assertNull($user->getDecryptedOauthAccessToken());
    }
}
