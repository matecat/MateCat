<?php

namespace Matecat\Core\Controllers\Authentication;

use Utils\Session\ArraySessionStore;
use Utils\Session\PhpSessionStore;
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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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

    // ─── Constructor (authenticate): session as profile cache ─────────────

    #[Test]
    public function constructorWithSessionDataSetsUserWhenTheCookieAgrees(): void
    {
        $user        = new UserStruct();
        $user->uid   = 99;
        $user->email = 'session@example.com';

        $session = new ArraySessionStore(['user' => $user]);

        // This test used to pass on session data alone. That was the defect: session state was an
        // authorization decision, so revoking the token ring did not log anyone out. The session
        // is now only a cache, and it takes a cookie the ring still accepts to reach it.
        $this->authCookieMock->method('getCredentials')
            ->willReturn(['user' => ['uid' => 99]]);

        $helper = $this->createHelper($session);

        $this->assertSame(99, $helper->getUser()->uid);
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
        $this->assertSame(1, $helper->regeneratedSessionIds);
    }

    #[Test]
    public function alreadyAuthenticatedSessionDoesNotRotateTheSessionId(): void
    {
        $user        = new UserStruct();
        $user->uid   = 7;
        $user->email = 'in@example.com';

        $session = new ArraySessionStore(['user' => $user]);

        // The cookie is mandatory now, even with a fully populated session: the token ring is the
        // only authority, so a request that cannot present a live cookie is not authenticated no
        // matter what the session holds.
        $this->authCookieMock->method('getCredentials')
            ->willReturn(['user' => ['uid' => 7]]);

        $helper = $this->createHelper($session);

        // isLogged() proves the session cache really was used — without it a silent failure into
        // the catch block would also report zero rotations and the test would prove nothing.
        $this->assertTrue($helper->isLogged());

        // This is the hot path: it runs on every authenticated request. Rotating here would churn
        // the id continuously and race parallel requests, and it buys nothing — the privilege
        // transition already happened.
        $this->assertSame(0, $helper->regeneratedSessionIds);
    }

    /**
     * Every /api/v2 and /api/v3 route is served by a plain KleinController, which is stateless and so
     * is handed a StatelessSessionStore — a store whose get() throws by design.
     *
     * On this path the session is only ever a cache for the user row, so "there is no session" is a
     * cache miss, not an error. Without the guard, cachedSessionUser() threw on the first line of the
     * cookie branch, authenticate()'s catch (Throwable) swallowed it, $this->user was never assigned,
     * and a perfectly valid login cookie produced 401 Invalid Login on every browser-issued v2/v3
     * call. Observed on dev against GET /api/v2/teams/:id/members.
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

    #[Test]
    public function aMatchingSessionIsUsedAsTheProfileCacheAndSkipsTheUserLookup(): void
    {
        $user        = new UserStruct();
        $user->uid   = 7;
        $user->email = 'in@example.com';

        $session = new ArraySessionStore(['user' => $user]);

        $this->authCookieMock->method('getCredentials')
            ->willReturn(['user' => ['uid' => 7]]);

        // The point of keeping the session: it spares the profile rebuild. If the lookup ran the
        // cache would be doing nothing for us.
        $this->userDaoMock->expects($this->never())->method('getByUid');

        $helper = $this->createHelper($session);

        $this->assertTrue($helper->isLogged());
        $this->assertSame(7, $helper->getUser()->uid);
    }

    #[Test]
    public function aSessionBelongingToAnotherUserIsNeverServedToTheCookieHolder(): void
    {
        $sessionUser        = new UserStruct();
        $sessionUser->uid   = 7;
        $sessionUser->email = 'in@example.com';

        $cookieUser        = new UserStruct();
        $cookieUser->uid   = 99;
        $cookieUser->email = 'other@example.com';

        $session = new ArraySessionStore(['user' => $sessionUser]);

        // A hazard this restructure introduces and has to close in the same change: the two
        // branches were mutually exclusive before, so a session holding one user could never be
        // reached by a cookie proving a different one. Serving the cached profile on uid mismatch
        // would hand user 7's teams and services to user 99.
        $this->authCookieMock->method('getCredentials')
            ->willReturn(['user' => ['uid' => 99]]);

        $this->userDaoMock->expects($this->once())
            ->method('getByUid')
            ->with(99)
            ->willReturn($cookieUser);

        $helper = $this->createHelper($session);

        $this->assertTrue($helper->isLogged());
        $this->assertSame(99, $helper->getUser()->uid);
        $this->assertSame(99, $session->get('user')->uid);
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
        $this->assertSame(0, $helper->regeneratedSessionIds);
    }

    #[Test]
    public function regenerateSessionIdIsANoOpWithoutAnActiveSession(): void
    {
        $session = new ArraySessionStore();
        $helper  = new AuthenticationHelper(
            $session, $this->userDaoMock, $this->apiKeyDaoMock, $this->authCookieMock
        );

        $method = new \ReflectionMethod(AuthenticationHelper::class, 'regenerateSessionId');
        $method->invoke($helper);

        // The real implementation guards on session_status() so it stays silent under PHPUnit,
        // where no session is running. Without that guard PHP emits a warning here.
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function regenerateSessionIdRotatesARealSessionAndDropsTheOldEntry(): void
    {
        // No other test can reach the real session_regenerate_id() call. By the time the suite is
        // running PHPUnit has written output, and PHP then refuses to start a session at all
        // ("Session cannot be started after headers have already been sent"), so the guard in
        // regenerateSessionId() always returns early. A separate process has produced no output
        // yet, which makes the real call reachable exactly here. use_cookies is off so nothing
        // attempts to emit a Set-Cookie header.
        session_start(['use_cookies' => false, 'cache_limiter' => '']);
        $this->assertSame(PHP_SESSION_ACTIVE, session_status(), 'precondition: a real session must be running');

        $_SESSION['probe'] = 'carried-over';
        $oldId             = session_id();

        $helper = new AuthenticationHelper(
            new PhpSessionStore(), $this->userDaoMock, $this->apiKeyDaoMock, $this->authCookieMock
        );

        $method = new \ReflectionMethod(AuthenticationHelper::class, 'regenerateSessionId');
        $method->invoke($helper);

        $newId = session_id();
        $this->assertNotSame($oldId, $newId, 'the session id must change once the session is authenticated');
        $this->assertSame('carried-over', $_SESSION['probe'], 'session data must survive the rotation');

        // The delete_old_session argument is load-bearing: without it the previous id remains
        // replayable and the rotation is cosmetic. Assert the old entry is really gone rather than
        // trusting the argument. Skipped rather than silently passing under another save handler,
        // where the on-disk layout below would not apply.
        if (ini_get('session.save_handler') !== 'files') {
            $this->markTestSkipped('old-entry removal is asserted against the files save handler only');
        }

        $savePath = session_save_path() ?: sys_get_temp_dir();
        session_write_close();

        $this->assertFileExists($savePath . '/sess_' . $newId, 'precondition: the new entry must be on disk');
        $this->assertFileDoesNotExist($savePath . '/sess_' . $oldId, 'the old session entry must be deleted');
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

    // ─── refreshSessionUser (static, not an authentication pass) ──────────
    //
    // Replaces refreshSession(), which unset the session keys so the *next* request would rebuild
    // them through a second full fromRequest() pass — ring check, getByUid, profile build and cookie
    // renewal — only to throw the result away. This re-reads the one key that is still cached in the
    // session, from the DAO cache, with the uid the session already proved.

    #[Test]
    public function refreshSessionUserRepopulatesTheUserFromTheDaoCache(): void
    {
        $stored        = new UserStruct();
        $stored->uid   = 99;
        $stored->email = 'renamed@example.com';

        $stale        = new UserStruct();
        $stale->uid   = 99;
        $stale->email = 'old@example.com';

        $this->userDaoMock->expects($this->once())
            ->method('setCacheTTL')
            ->with(60 * 60 * 24)
            ->willReturnSelf();

        $this->userDaoMock->expects($this->once())
            ->method('getByUid')
            ->with(99)
            ->willReturn($stored);

        $session = new ArraySessionStore(['uid' => 99, 'user' => $stale]);

        AuthenticationHelper::refreshSessionUser($session, $this->userDaoMock);

        $this->assertSame('renamed@example.com', $session->get('user')->email);
    }

    #[Test]
    public function refreshSessionUserDropsTheUserWhenTheUidNoLongerResolves(): void
    {
        $this->userDaoMock->method('getByUid')->willReturn(null);

        $session = new ArraySessionStore(['uid' => 99, 'user' => new UserStruct()]);

        AuthenticationHelper::refreshSessionUser($session, $this->userDaoMock);

        // A deleted user must not be left behind as a stale cached struct.
        $this->assertFalse($session->has('user'));
    }

    #[Test]
    public function refreshSessionUserIsANoOpWithoutAUidInSession(): void
    {
        // No uid means nothing was ever proved, so there is nothing to refresh and no query to run.
        $this->userDaoMock->expects($this->never())->method('getByUid');

        $session = new ArraySessionStore();

        AuthenticationHelper::refreshSessionUser($session, $this->userDaoMock);

        $this->assertSame([], $session->keys());
    }

    #[Test]
    public function refreshSessionUserTouchesNothingButTheUserKey(): void
    {
        $stored      = new UserStruct();
        $stored->uid = 99;
        $this->userDaoMock->method('getByUid')->willReturn($stored);

        // What the old refreshSession() + fromRequest() pair did that this must not: re-run
        // authentication, and with it re-stamp the session and renew the cookie. The cookie is not
        // even reachable from here — a static call with a UserDao cannot consult the ring — and the
        // rest of the session must come back byte-identical.
        $session = new ArraySessionStore(['uid' => 99, 'cid' => 'client-1', 'wanted_url' => '/manage']);

        AuthenticationHelper::refreshSessionUser($session, $this->userDaoMock);

        $this->assertSame(99, $session->get('uid'));
        $this->assertSame('client-1', $session->get('cid'));
        $this->assertSame('/manage', $session->get('wanted_url'));
        $this->assertSame($stored, $session->get('user'));
        $this->assertSame(['uid', 'cid', 'wanted_url', 'user'], $session->keys());
    }

    // ─── destroyAuthentication (instance method) ─────────────────────────

    #[Test]
    public function destroyAuthenticationClearsSessionVarsOnInstance(): void
    {
        $user    = new UserStruct();
        $session = new ArraySessionStore(['user' => $user]);
        $helper = $this->createHelper($session);

        try {
            $helper->destroyAuthentication();
        } catch (\Throwable) {
            // cookie store may throw in test environment without active session
        }

        $this->assertFalse($session->has('user'));
    }
}

class TestableAuthenticationHelper extends AuthenticationHelper
{
    public int $regeneratedSessionIds = 0;

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

    protected function regenerateSessionId(): void
    {
        $this->regeneratedSessionIds++;
    }
}
