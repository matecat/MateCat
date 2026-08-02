<?php

namespace Matecat\Core\Controllers\Authentication;

use Utils\Session\ArraySessionStore;
use Utils\Session\SessionStore;
use RuntimeException;
use Utils\Session\StatelessSessionStore;
use Utils\Session\StatelessSessionViolation;
use Controller\Abstracts\Authentication\AuthCookie;
use Controller\Abstracts\Authentication\AuthenticationHelper;
use Matecat\TestHelpers\AbstractTest;
use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Model\DataAccess\Database;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Behavioral-parity copy of {@see AuthenticationHelperTest}, exercising the
 * split/refactored implementation. The SAME observable behavior must hold.
 */
#[AllowMockObjectsWithoutExpectations]
#[CoversClass(AuthenticationHelper::class)]
class AuthenticationHelperRefactoredTest extends AbstractTest
{
    /** @var ApiKeyDao&MockObject */
    private ApiKeyDao&MockObject $apiKeyDaoMock;

    /** @var UserDao&MockObject */
    private UserDao&MockObject $userDaoMock;

    private AuthCookie&MockObject $authCookieMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiKeyDaoMock      = $this->createMock(ApiKeyDao::class);
        $this->userDaoMock        = $this->createMock(UserDao::class);
        $this->authCookieMock    = $this->createMock(AuthCookie::class);
    }

    private function createHelper(SessionStore $session, ?string $apiKey = null, ?string $apiSecret = null, bool $sessionActive = true): TestableAuthenticationHelper
    {
        return TestableAuthenticationHelper::create(
            $session,
            $this->userDaoMock,
            $this->apiKeyDaoMock,
            $this->authCookieMock,
            $apiKey,
            $apiSecret,
            $sessionActive
        );
    }

    // ─── Basic getters (no auth) ─────────────────────────────────────────

    #[Test]
    public function loggedIsFalseByDefault(): void
    {
        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session);

        $this->assertFalse($helper->isLogged());
    }

    #[Test]
    public function getUserReturnsUserStruct(): void
    {
        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session);

        $this->assertInstanceOf(UserStruct::class, $helper->getUser());
    }

    #[Test]
    public function getApiRecordReturnsNullByDefault(): void
    {
        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session);

        $this->assertNull($helper->getApiRecord());
    }

    // ─── validKeys ───────────────────────────────────────────────────────

    #[Test]
    public function validKeysReturnsFalseWhenBothNull(): void
    {
        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session);

        $this->assertFalse($helper->validKeys(null, null));
    }

    #[Test]
    public function validKeysReturnsFalseWhenKeyIsEmptyString(): void
    {
        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session);

        $this->assertFalse($helper->validKeys('', ''));
    }

    #[Test]
    public function validKeysSetsApiRecordWhenKeyFound(): void
    {
        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session);

        $apiRecord = new ApiKeyStruct(['api_key' => 'k1', 'api_secret' => 's1', 'uid' => 1, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']);
        $this->apiKeyDaoMock->method('findByKey')
            ->with('key123')
            ->willReturn($apiRecord);

        $result = $helper->validKeys('key123', 's1');

        $this->assertTrue($result);
        $this->assertSame($apiRecord, $helper->getApiRecord());
    }

    #[Test]
    public function validKeysReturnsFalseWhenSecretMismatch(): void
    {
        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session);

        $apiRecord = new ApiKeyStruct(['api_key' => 'k1', 'api_secret' => 'correct', 'uid' => 1, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']);
        $this->apiKeyDaoMock->method('findByKey')
            ->with('key123')
            ->willReturn($apiRecord);

        $result = $helper->validKeys('key123', 'wrong');

        $this->assertFalse($result);
        $this->assertSame($apiRecord, $helper->getApiRecord());
    }

    #[Test]
    public function validKeysReturnsFalseWhenKeyNotFound(): void
    {
        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session);

        $this->apiKeyDaoMock->method('findByKey')->willReturn(null);

        $this->assertFalse($helper->validKeys('unknown', 'secret'));
        $this->assertNull($helper->getApiRecord());
    }

    #[Test]
    public function validKeysUsesEmptyStringWhenApiKeyIsNull(): void
    {
        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session);

        $this->apiKeyDaoMock->expects($this->once())
            ->method('findByKey')
            ->with('')
            ->willReturn(null);

        $helper->validKeys(null, 'some_secret');
    }

    // ─── Constructor (authenticate): API key auth path ────────────────────

    #[Test]
    public function constructorWithValidApiKeySetsUserAndLogged(): void
    {
        $user            = new UserStruct();
        $user->uid       = 42;
        $user->email     = 'api@example.com';
        $user->first_name = 'Test';
        $user->last_name  = 'User';

        $this->userDaoMock->method('getByUid')->with(42)->willReturn($user);

        $apiRecord = new ApiKeyStruct(
            ['api_key' => 'k1', 'api_secret' => 's1', 'uid' => 42, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']
        );
        $this->apiKeyDaoMock->method('findByKey')->with('k1')->willReturn($apiRecord);

        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session, 'k1', 's1');

        $this->assertTrue($helper->isLogged());
        $this->assertSame(42, $helper->getUser()->uid);
        $this->assertSame('api@example.com', $helper->getUser()->email);
        $this->assertNotNull($helper->getApiRecord());
    }

    #[Test]
    public function constructorWithValidApiKeyButNullUserKeepsDefaultUser(): void
    {
        $this->userDaoMock->method('getByUid')->willReturn(null);

        $apiRecord = new ApiKeyStruct(
            ['api_key' => 'k1', 'api_secret' => 's1', 'uid' => 42, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']
        );
        $this->apiKeyDaoMock->method('findByKey')->with('k1')->willReturn($apiRecord);

        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session, 'k1', 's1');

        $this->assertFalse($helper->isLogged());
        $this->assertNull($helper->getUser()->uid);
    }

    #[Test]
    public function constructorWithInvalidSecretDoesNotSetUser(): void
    {
        $apiRecord = new ApiKeyStruct(
            ['api_key' => 'k1', 'api_secret' => 'real_secret', 'uid' => 42, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']
        );
        $this->apiKeyDaoMock->method('findByKey')->with('k1')->willReturn($apiRecord);

        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session, 'k1', 'wrong_secret');

        $this->assertFalse($helper->isLogged());
        $this->assertNull($helper->getUser()->uid);
    }

    // ─── Authenticating stores no credentials in the session ──────────────

    /**
     * The session used to hold the whole UserStruct, which declares `pass`, `salt` and
     * `confirmation_token` — so authenticating serialised the password hash, the salt and the
     * confirmation token into the session's Redis database, kept them for the session's idle lifetime
     * and did not purge them when the password changed.
     *
     * Asserting on the stored *values*, not just on the absence of the `user` key: a future change
     * that puts the struct back under another name, or that stores the hash as a scalar field, has to
     * fail this. `uid` is the one thing that may be there, and it is not a credential.
     */
    #[Test]
    public function authenticatingStoresTheUidAndNoCredentialMaterial(): void
    {
        $user                     = new UserStruct();
        $user->uid                = 99;
        $user->email              = 'session@example.com';
        $user->pass               = 'the-password-hash';
        $user->salt               = 'the-salt';
        $user->confirmation_token = 'the-confirmation-token';

        $this->userDaoMock->method('getByUid')->with(99)->willReturn($user);
        $this->authCookieMock->method('getCredentials')->willReturn(['user' => ['uid' => 99]]);

        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session);

        $this->assertSame(99, $helper->getUser()->uid);
        $this->assertSame(['uid'], $session->keys());

        $stored = json_encode($session->all());
        $this->assertIsString($stored);
        $this->assertStringNotContainsString('the-password-hash', $stored);
        $this->assertStringNotContainsString('the-salt', $stored);
        $this->assertStringNotContainsString('the-confirmation-token', $stored);
    }

    // ─── Constructor (authenticate): cookie auth path ─────────────────────

    #[Test]
    public function cookiePathLoadsUserFromDaoAndPopulatesSession(): void
    {
        $user        = new UserStruct();
        $user->uid   = 5;
        $user->email = 'cookie@example.com';

        $this->userDaoMock->method('getByUid')->with(5)->willReturn($user);
        $this->authCookieMock->method('getCredentials')->willReturn(['user' => ['uid' => 5]]);

        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session); // no api key, empty session → cookie branch

        // TestableAuthenticationHelperRefactored forces sessionIsActive() = true,
        // so setUserSession() populates the injected session array.
        $this->assertSame(5, $helper->getUser()->uid);
        $this->assertSame(5, $session->get('uid'));

        // The profile is no longer built on the authenticated path at all: it is what made a
        // session-cache miss pay ~100 queries plus a Redis connection per team on an ordinary
        // request. GET /api/app/user builds it now, and UserStateStore caches it by uid.
        $this->assertFalse($session->has('user_profile'));
    }

    // ─── Session fixation: the id is rotated on the anonymous → authenticated hop ──────────

    #[Test]
    public function cookiePathRotatesTheSessionIdExactlyOnce(): void
    {
        $user        = new UserStruct();
        $user->uid   = 5;
        $user->email = 'cookie@example.com';

        $this->userDaoMock->method('getByUid')->with(5)->willReturn($user);
        $this->authCookieMock->method('getCredentials')->willReturn(['user' => ['uid' => 5]]);

        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session);

        $this->assertTrue($helper->isLogged());

        // Every login path — password, signup confirmation, OAuth — establishes the cookie and
        // then rebuilds the helper, landing here. An id known before the login must not still be
        // valid after it.
        $this->assertSame(1, $session->regenerationCount());
    }

    #[Test]
    public function alreadyAuthenticatedSessionDoesNotRotateTheSessionId(): void
    {
        $user        = new UserStruct();
        $user->uid   = 7;
        $user->email = 'in@example.com';

        // A session that already records this uid is one that authenticated on an earlier request.
        // That used to be expressed by seeding the cached UserStruct, which is gone; the uid is what
        // marks the session authenticated now.
        $session = new ArraySessionStore(['uid' => 7]);

        // The cookie is mandatory now, even with a fully populated session: the token ring is the
        // only authority, so a request that cannot present a live cookie is not authenticated no
        // matter what the session holds.
        $this->userDaoMock->method('getByUid')->with(7)->willReturn($user);
        $this->authCookieMock->method('getCredentials')
            ->willReturn(['user' => ['uid' => 7]]);

        $helper = $this->createHelper($session);

        // isLogged() guards against a silent failure into the catch block, which would also report
        // zero rotations and make the assertion below prove nothing.
        $this->assertTrue($helper->isLogged());

        // This is the hot path: it runs on every authenticated request. Rotating here would churn
        // the id continuously and race parallel requests, and it buys nothing — the privilege
        // transition already happened.
        $this->assertSame(0, $session->regenerationCount());
    }

    /**
     * Every /api/v2 and /api/v3 route is served by a plain KleinController, which is stateless and so
     * is handed a StatelessSessionStore — a store whose get() throws by design.
     *
     * The cookie branch must therefore never read the session to resolve identity. It once did — it
     * consulted a session-cached user row first — so on this path the very first line of the branch
     * threw, authenticate()'s catch (Throwable) swallowed it, $this->user was never assigned, and a
     * perfectly valid login cookie produced 401 Invalid Login on every browser-issued v2/v3 call.
     * Observed on dev against GET /api/v2/teams/:id/members.
     *
     * The session write in setUserSession() is not a second version of that hazard: it is gated on
     * sessionIsActive(), and a stateless controller never starts a session, so it is not reached.
     *
     * Asserting the uid, not just isLogged(): the point is that the cookie's identity survives the
     * absent session, and an assertion on the flag alone would also pass if some later change resolved
     * a different user.
     */
    #[Test]
    public function aCookieStillAuthenticatesWhenTheControllerIsStatelessAndItsSessionRefusesReads(): void
    {
        $user        = new UserStruct();
        $user->uid   = 36;
        $user->email = 'stateless@example.com';

        $this->userDaoMock->method('getByUid')->with(36)->willReturn($user);
        $this->authCookieMock->method('getCredentials')->willReturn(['user' => ['uid' => 36]]);

        $helper = $this->createHelper(new StatelessSessionStore(), sessionActive: false);

        $this->assertTrue($helper->isLogged());
        $this->assertSame(36, (int)$helper->getUser()->uid);
    }

    /**
     * A store that refuses reads paired with a session that reports active is a wiring mistake, not a
     * runtime state, and StatelessSessionStore raises StatelessSessionViolation to say exactly which
     * operation and key were refused. It is deliberately an unchecked \Error so that it surfaces —
     * phpstan.neon says as much: "Enforcement works by surfacing."
     *
     * It did not surface. The blanket catch (Throwable) absorbed it and reported "not authenticated"
     * instead, which is how a programming mistake reached production disguised as 401 Invalid Login on
     * every cookie-authenticated /api/v2 and /api/v3 request. A bug must not be able to impersonate a
     * failed login.
     */
    #[Test]
    public function aSessionWiringBugSurfacesInsteadOfImpersonatingAFailedLogin(): void
    {
        $user        = new UserStruct();
        $user->uid   = 5;
        $user->email = 'cookie@example.com';

        $this->userDaoMock->method('getByUid')->willReturn($user);
        $this->authCookieMock->method('getCredentials')->willReturn(['user' => ['uid' => 5]]);

        $this->expectException(StatelessSessionViolation::class);

        // sessionActive defaults to true, which together with a refusing store is the wiring bug.
        $this->createHelper(new StatelessSessionStore());
    }

    /**
     * The other half of the split, and the reason it is a split rather than a bare re-throw.
     *
     * An \Exception here is a runtime condition, not a bug: the ring check reaches Redis, so an
     * unreachable Redis throws on every authenticated request. Degrading to logged-out is the right
     * answer for that — re-throwing would turn one unavailable dependency into a site-wide 500. This
     * pins the soft path so a later widening of the re-throw cannot quietly take it away.
     */
    #[Test]
    public function anInfrastructureFailureStillDegradesToLoggedOut(): void
    {
        $this->authCookieMock->method('getCredentials')
            ->willThrowException(new RuntimeException('redis unreachable'));

        $helper = $this->createHelper(new ArraySessionStore());

        $this->assertFalse($helper->isLogged());
    }

    // ─── Ring is the sole authority ───────────────────────────────────────────

    #[Test]
    public function aPopulatedSessionWithoutALiveCookieIsNotAuthenticated(): void
    {
        $user        = new UserStruct();
        $user->uid   = 7;
        $user->email = 'in@example.com';

        $session = new ArraySessionStore(['user' => $user]);

        // What a revoked user looks like on their next request: the ring no longer holds their
        // token, so getCredentials() yields nothing, while their PHP session is still sitting
        // there fully populated. Before this change that session alone authenticated them and
        // revocation did not take effect until the session died of idleness.
        $this->authCookieMock->method('getCredentials')->willReturn(null);

        // The session must not be consulted for identity at all, so no user is ever loaded.
        $this->userDaoMock->expects($this->never())->method('getByUid');

        $helper = $this->createHelper($session);

        $this->assertFalse($helper->isLogged());
        $this->assertNull($helper->getUser()->uid);
    }

    /**
     * The inverse of the assertion this used to make. A matching session used to short-circuit the
     * lookup, which is what made the session a cache of the users row — and therefore of the password
     * hash. The read now happens on every authenticated request, and that is load-bearing rather than
     * incidental: it is why a rename, a disabled account or a revoked user takes effect on the next
     * request instead of surviving as long as the session does.
     *
     * The cost is one Redis read, not a query: getByUid() goes through the DAO's own uid-keyed cache
     * with the TTL asserted here.
     */
    #[Test]
    public function theUserIsResolvedFromTheDaoOnEveryRequestEvenWithAnAuthenticatedSession(): void
    {
        $user        = new UserStruct();
        $user->uid   = 7;
        $user->email = 'in@example.com';

        $session = new ArraySessionStore(['uid' => 7]);

        $this->authCookieMock->method('getCredentials')
            ->willReturn(['user' => ['uid' => 7]]);

        $this->userDaoMock->expects($this->once())
            ->method('setCacheTTL')
            ->with(60 * 60 * 24)
            ->willReturnSelf();

        $this->userDaoMock->expects($this->once())
            ->method('getByUid')
            ->with(7)
            ->willReturn($user);

        $helper = $this->createHelper($session);

        $this->assertTrue($helper->isLogged());
        $this->assertSame(7, $helper->getUser()->uid);
    }

    /**
     * Identity comes from the cookie, never from the session. This used to be enforced by an explicit
     * uid-equality guard over the session's cached struct; with that struct deleted the property holds
     * by construction, and is pinned here so a future reintroduction of a session-sourced user has to
     * fail rather than quietly serve one user's row to another.
     */
    #[Test]
    public function aSessionBelongingToAnotherUserIsNeverServedToTheCookieHolder(): void
    {
        $cookieUser        = new UserStruct();
        $cookieUser->uid   = 99;
        $cookieUser->email = 'other@example.com';

        // A session left recording uid 7 while the cookie proves 99.
        $session = new ArraySessionStore(['uid' => 7]);

        $this->authCookieMock->method('getCredentials')
            ->willReturn(['user' => ['uid' => 99]]);

        $this->userDaoMock->expects($this->once())
            ->method('getByUid')
            ->with(99)
            ->willReturn($cookieUser);

        $helper = $this->createHelper($session);

        $this->assertTrue($helper->isLogged());
        $this->assertSame(99, $helper->getUser()->uid);

        // The session is re-stamped to the uid the cookie proved, and rotated on the way, because this
        // is a different user taking over the session rather than the same one continuing.
        $this->assertSame(99, $session->get('uid'));
        $this->assertSame(1, $session->regenerationCount());
    }

    #[Test]
    public function apiKeyAuthenticationDoesNotRotateTheSessionId(): void
    {
        $user        = new UserStruct();
        $user->uid   = 42;
        $user->email = 'api@example.com';
        $this->userDaoMock->method('getByUid')->with(42)->willReturn($user);

        $apiRecord = new ApiKeyStruct(
            ['api_key' => 'k1', 'api_secret' => 's1', 'uid' => 42, 'enabled' => true, 'create_date' => '2024-01-01', 'last_update' => '2024-01-01']
        );
        $this->apiKeyDaoMock->method('findByKey')->with('k1')->willReturn($apiRecord);

        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session, 'k1', 's1');

        // API-key callers carry no session at all, so there is nothing to rotate.
        $this->assertTrue($helper->isLogged());
        $this->assertSame(0, $session->regenerationCount());
    }

    #[Test]
    public function realSessionGuardIsEvaluatedOnCookiePath(): void
    {
        // Uses the REAL class (not the Testable subclass) so the actual
        // sessionIsActive() guard is exercised.
        $user      = new UserStruct();
        $user->uid = 8;
        $this->userDaoMock->method('getByUid')->willReturn($user);
        $this->authCookieMock->method('getCredentials')->willReturn(['user' => ['uid' => 8]]);

        $session = new ArraySessionStore();
        $helper  = new AuthenticationHelper(
            $session, $this->userDaoMock, $this->apiKeyDaoMock, $this->authCookieMock
        );
        $helper->authenticate(null, null);

        $this->assertSame(8, $helper->getUser()->uid);
    }

    #[Test]
    public function loggerFailureDuringExceptionHandlingIsSwallowed(): void
    {
        // Outer flow throws (findByKey), then the logger payload itself throws
        // (getCredentials) → the inner catch must swallow it; stays logged-out.
        $this->apiKeyDaoMock->method('findByKey')->willThrowException(new \RuntimeException('db down'));
        $this->authCookieMock->method('getCredentials')->willThrowException(new \RuntimeException('cookie boom'));

        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session, 'k1', 's1');

        $this->assertFalse($helper->isLogged());
    }

    // ─── Constructor (authenticate): exception handling ───────────────────

    #[Test]
    public function constructorCatchesExceptionAndSetsLoggedFalse(): void
    {
        $this->apiKeyDaoMock->method('findByKey')
            ->willThrowException(new \RuntimeException('DB down'));

        $session = new ArraySessionStore();
        $helper  = $this->createHelper($session, 'some_key', 'some_secret');

        $this->assertFalse($helper->isLogged());
        $this->assertNull($helper->getApiRecord());
    }

    // ─── fromRequest (composition root, real DB) ─────────────────────────

    #[Test]
    public function fromRequestBuildsLoggedOutHelperForEmptySession(): void
    {
        $session = new ArraySessionStore();
        $helper  = AuthenticationHelper::fromRequest($session, obtainTestDatabase());

        $this->assertFalse($helper->isLogged());
        $this->assertInstanceOf(UserStruct::class, $helper->getUser());
        $this->assertNull($helper->getApiRecord());
    }

    /**
     * A different user taking over a session that still names somebody else. Rotating the id alone
     * relabels it and keeps the contents — session_regenerate_id() preserves them by design — so the
     * arriving user used to inherit the previous one's cart, redeemable project and GDrive tokens.
     */
    #[Test]
    public function aSessionBelongingToAnotherUserIsClearedBeforeTheNewIdentityIsStamped(): void
    {
        $arriving        = new UserStruct();
        $arriving->uid   = 42;
        $arriving->email = 'arriving@example.com';

        $this->userDaoMock->method('getByUid')->with(42)->willReturn($arriving);
        $this->authCookieMock->method('getCredentials')->willReturn(['user' => ['uid' => 42]]);

        $session = new ArraySessionStore([
            'uid'            => 7,
            'redeem_project' => 'a-project-belonging-to-user-7',
            'cart'           => ['user-7-items'],
        ]);

        $helper = $this->createHelper($session);

        // isLogged() guards against a silent failure into the catch block, which would also leave the
        // session empty and make the assertions below prove nothing.
        $this->assertTrue($helper->isLogged());

        $this->assertSame(42, $session->get('uid'));
        $this->assertFalse($session->has('redeem_project'));
        $this->assertFalse($session->has('cart'));
    }

    /**
     * The case that must NOT clear, and the reason the rule is guarded on the session already naming
     * someone. SignupController::confirm() reads invited_to_team, redeem_project and wanted_url out of
     * the session *after* authentication runs, and the ordinary visitor was anonymous when they
     * started — clearing here would break team signup and project redeem.
     */
    #[Test]
    public function anAnonymousSessionKeepsWhatItCarriesWhenItBecomesAuthenticated(): void
    {
        $arriving        = new UserStruct();
        $arriving->uid   = 42;
        $arriving->email = 'arriving@example.com';

        $this->userDaoMock->method('getByUid')->with(42)->willReturn($arriving);
        $this->authCookieMock->method('getCredentials')->willReturn(['user' => ['uid' => 42]]);

        $session = new ArraySessionStore([
            'invited_to_team' => ['team_id' => 5, 'email' => 'invited@example.com'],
            'wanted_url'      => '/the-page-they-asked-for',
        ]);

        $helper = $this->createHelper($session);

        $this->assertTrue($helper->isLogged());
        $this->assertSame(42, $session->get('uid'));
        $this->assertSame(['team_id' => 5, 'email' => 'invited@example.com'], $session->get('invited_to_team'));
        $this->assertSame('/the-page-they-asked-for', $session->get('wanted_url'));

        // Still rotated: anonymous to authenticated is the fixation transition.
        $this->assertSame(1, $session->regenerationCount());
    }

    // ─── destroyAuthentication (instance method) ─────────────────────────

    #[Test]
    public function destroyAuthenticationClearsSessionVarsOnInstance(): void
    {
        // Not just `uid`: a signed-out browser must not keep the previous user's flash messages,
        // GDrive tokens or cart either, so logging out ends the whole session.
        $session = new ArraySessionStore(['uid' => 7, 'redeem_project' => 'abc', 'cart' => ['x']]);
        $helper  = $this->createHelper($session);

        $helper->destroyAuthentication();

        $this->assertFalse($session->has('uid'));
        $this->assertSame([], $session->all());
    }

    /**
     * The ordering guarantee, and the reason revocation runs first. It is the only step that can
     * fail, because it reaches Redis; clearing the session marker before it meant a failure left the
     * request looking signed out while the cookie and its ring token still authenticated the next
     * one. Now a failed logout leaves the user plainly still logged in, and it says so.
     */
    #[Test]
    public function aFailedRevocationLeavesTheSessionMarkerRatherThanHalfLoggingOut(): void
    {
        $session = new ArraySessionStore(['uid' => 7]);
        $helper  = $this->createHelper($session);

        // Armed after construction on purpose: createHelper() runs authenticate(), which must be
        // allowed to complete normally so this exercises the logout and nothing else.
        $this->authCookieMock->method('destroyAuthentication')
            ->willThrowException(new RuntimeException('token ring unreachable'));

        $this->expectException(RuntimeException::class);

        try {
            $helper->destroyAuthentication();
        } finally {
            $this->assertTrue($session->has('uid'));
        }
    }
}

class TestableAuthenticationHelper extends AuthenticationHelper
{
    /**
     * Defaults to true, which is what every stateful case wants. A stateless controller is the
     * opposite case and has to be able to say so: there, no PHP session is ever started, and the
     * store it holds refuses reads.
     */
    public bool $sessionActive = true;

    public static function create(
        SessionStore $session,
        UserDao $userDao,
        ApiKeyDao $apiKeyDao,
        AuthCookie $authCookie,
        ?string $api_key = null,
        ?string $api_secret = null,
        bool $sessionActive = true,
    ): self {
        $self = new self($session, $userDao, $apiKeyDao, $authCookie);
        // Before authenticate(), not after: the cookie branch consults the session cache, so the
        // flag has to be in place for the run under test rather than for a later assertion.
        $self->sessionActive = $sessionActive;
        $self->authenticate($api_key, $api_secret);

        return $self;
    }

    public function validKeys(?string $api_key = null, ?string $api_secret = null): bool
    {
        return parent::validKeys($api_key, $api_secret);
    }

    protected function sessionIsActive(): bool
    {
        return $this->sessionActive;
    }
}
