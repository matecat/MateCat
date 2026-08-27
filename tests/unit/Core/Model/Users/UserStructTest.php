<?php


namespace Matecat\Core\Model\Users;

use Matecat\TestHelpers\AbstractTest;
use Model\Users\AuthTokenScope;
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

        $user->initAuthToken(AuthTokenScope::PasswordReset);

        $this->assertNotNull($user->confirmation_token);
        $this->assertNotNull($user->confirmation_token_created_at);
        $this->assertSame(
            66,
            strlen($user->confirmation_token),
            'two-character marker plus a sha256 digest in hex'
        );
    }

    /**
     * The point of the whole scheme: a copy of the table is not a set of spendable links. What is
     * stored has to be the digest, and the secret that travels in the mail must not appear in it.
     */
    #[Test]
    public function initAuthTokenStoresADigestAndNeverTheSecret(): void
    {
        $user = new UserStruct();

        $user->initAuthToken(AuthTokenScope::PasswordReset);
        $raw = $user->authTokenForUrl();

        $this->assertSame(UserStruct::AUTH_TOKEN_RANDOM_LENGTH, strlen($raw));
        $this->assertStringNotContainsString($raw, (string)$user->confirmation_token);
        $this->assertSame(
            AuthTokenScope::PasswordReset->storedForm($raw),
            $user->confirmation_token
        );
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

    /**
     * The token in flight cannot be re-sent — only a digest of it is stored — so a repeated request
     * mints. What must survive is the deadline: naming an address repeatedly cannot push the expiry
     * of a live token forward.
     */
    #[Test]
    public function initAuthTokenIfStaleKeepsTheDeadlineOfATokenInsideItsWindow(): void
    {
        $user = new UserStruct();
        $user->initAuthToken(AuthTokenScope::PasswordReset);
        $token = $user->confirmation_token;
        $createdAt = $user->confirmation_token_created_at;

        $user->initAuthTokenIfStale(AuthTokenScope::PasswordReset);

        $this->assertNotSame($token, $user->confirmation_token);
        $this->assertSame(
            $createdAt,
            $user->confirmation_token_created_at,
            'a repeated request must not slide the expiry forward, or a token stays alive for ever'
        );
    }

    #[Test]
    public function initAuthTokenIfStaleMintsOnceTheWindowHasPassed(): void
    {
        $user = new UserStruct();
        $user->initAuthToken(AuthTokenScope::PasswordReset);
        $token = $user->confirmation_token;
        $user->confirmation_token_created_at = date(
            'Y-m-d H:i:s',
            time() - AuthTokenScope::PasswordReset->ttlSeconds() - 60
        );

        $user->initAuthTokenIfStale(AuthTokenScope::PasswordReset);

        $this->assertNotSame($token, $user->confirmation_token);
        $this->assertSame(66, strlen((string)$user->confirmation_token));
        $this->assertGreaterThan(
            time() - 60,
            strtotime((string)$user->confirmation_token_created_at),
            'an expired token gets a fresh deadline, otherwise the new link is born dead'
        );
    }

    #[Test]
    public function initAuthTokenIfStaleMintsWhenThereIsNoTokenYet(): void
    {
        $user = new UserStruct();

        $user->initAuthTokenIfStale(AuthTokenScope::PasswordReset);

        $this->assertNotEmpty($user->confirmation_token);
        $this->assertNotEmpty($user->authTokenForUrl());
    }

    /**
     * The whole point of the marker: a token minted for one flow must not be reusable as the other
     * flow's token. A pending confirmation is replaced rather than handed to the reset flow, because
     * one column holds one token.
     */
    #[Test]
    public function initAuthTokenIfStaleWillNotReuseTheOtherFlowsToken(): void
    {
        $user = new UserStruct();
        $user->initAuthToken(AuthTokenScope::SignupConfirmation);
        $confirmToken = $user->confirmation_token;

        $user->initAuthTokenIfStale(AuthTokenScope::PasswordReset);

        $this->assertNotSame($confirmToken, $user->confirmation_token);
        $this->assertStringStartsWith(AuthTokenScope::PasswordReset->marker(), (string)$user->confirmation_token);
    }

    /**
     * The marker is stored but never travels in the link, so each flow can prepend its own and have a
     * mismatch miss.
     */
    #[Test]
    public function authTokenForUrlReturnsTheSecretMintedThisRequest(): void
    {
        $user = new UserStruct();
        $user->initAuthToken(AuthTokenScope::PasswordReset);

        $raw = $user->authTokenForUrl();

        $this->assertSame(48, strlen($raw));
        $this->assertStringStartsNotWith(AuthTokenScope::PasswordReset->marker(), $raw);
        $this->assertSame($raw, $user->authTokenForUrl(), 'the secret is stable for the whole request');
    }

    /**
     * A struct hydrated from a row holds a digest and nothing else. There is no link to build from
     * it, and the digest itself must never be handed out as one — presenting it back would be a
     * replay of a value an attacker could have read straight out of the table.
     */
    #[Test]
    public function authTokenForUrlHasNothingToReturnForAStoredDigest(): void
    {
        $user = new UserStruct();
        $user->confirmation_token = AuthTokenScope::PasswordReset->storedForm('some-secret');

        $this->assertSame('', $user->authTokenForUrl());
    }

    /**
     * Tokens stored before hashing carry the secret in clear behind their marker, and links built
     * from them are still in flight, so those keep working until they expire.
     */
    #[Test]
    public function authTokenForUrlStillStripsTheMarkerOffAPreHashingToken(): void
    {
        $secret = str_repeat('a', UserStruct::AUTH_TOKEN_RANDOM_LENGTH);
        $user = new UserStruct();
        $user->confirmation_token = AuthTokenScope::PasswordReset->marker() . $secret;

        $this->assertSame($secret, $user->authTokenForUrl());
    }

    /**
     * Tokens issued before scoping carry no marker and may still be in flight, so they have to pass
     * through untouched.
     */
    #[Test]
    public function authTokenForUrlLeavesAnUnmarkedLegacyTokenAlone(): void
    {
        $user = new UserStruct();
        $user->confirmation_token = 'legacy-token-with-no-marker';

        $this->assertSame('legacy-token-with-no-marker', $user->authTokenForUrl());
    }

    /**
     * Each flow's lifetime must apply only to its own tokens, which is what the marker buys.
     */
    #[Test]
    public function eachScopeCarriesItsOwnLifetime(): void
    {
        $this->assertSame(1800, AuthTokenScope::PasswordReset->ttlSeconds());
        $this->assertSame(259200, AuthTokenScope::SignupConfirmation->ttlSeconds());
        $this->assertNotSame(
            AuthTokenScope::PasswordReset->marker(),
            AuthTokenScope::SignupConfirmation->marker()
        );
    }

    /**
     * A token with no timestamp cannot be shown to be fresh, so it must not be treated as such.
     */
    #[Test]
    public function initAuthTokenIfStaleMintsWhenTheTimestampIsMissing(): void
    {
        $user = new UserStruct();
        $user->confirmation_token = 'a-token-with-no-timestamp';
        $user->confirmation_token_created_at = null;

        $user->initAuthTokenIfStale(AuthTokenScope::PasswordReset);

        $this->assertNotSame('a-token-with-no-timestamp', $user->confirmation_token);
        $this->assertNotNull($user->confirmation_token_created_at);
    }

    #[Test]
    public function getDecryptedOauthAccessTokenReturnsNullWhenNoToken(): void
    {
        $user = new UserStruct();

        $this->assertNull($user->getDecryptedOauthAccessToken());
    }
}
