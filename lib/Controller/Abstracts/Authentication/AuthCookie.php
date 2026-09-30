<?php

namespace Controller\Abstracts\Authentication;

use DomainException;
use Exception;
use Model\Users\UserStruct;
use ReflectionException;
use RuntimeException;
use TypeError;
use UnexpectedValueException;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\Tools\SimpleJWT;

/**
 * Reads, issues, renews and destroys the login cookie, keeping it in step with the user's token ring.
 *
 * The token ring is bound once, in the constructor, rather than passed to each call. That is what
 * makes it impossible to ask for credentials *without* checking revocation: the previous static API
 * took an optional handler, and passing null validated a cookie's signature while silently skipping
 * the ring.
 *
 * Note this class still reads and writes $_COOKIE directly. Being an instance makes it injectable
 * into its consumers; it does not make it free of superglobals. It no longer touches the PHP session
 * at all — that belongs to the session store.
 */
class AuthCookie
{

    /**
     * Renew the login cookie once it is older than this.
     *
     * Decouples remember-me from exposure: the cookie still lives
     * {@see AppConfig::$AUTHCOOKIEDURATION} so a user returning within that window stays signed in
     * forever, while a token that stops being renewed is retired two generations later — about two
     * of these intervals rather than a full cookie lifetime.
     *
     * Two limits on that, because renewal is driven by traffic and the ring cannot tell who is
     * holding a token:
     *
     *  - A user who stops visiting triggers no renewal, so nothing retires their token early and it
     *    survives to {@see AppConfig::$AUTHCOOKIEDURATION}. Unavoidable while remember-me requires
     *    the cookie to outlive that idle period.
     *  - A captured token that the attacker keeps *using* renews itself: their requests advance the
     *    chain and they are issued fresh cookies indefinitely, while the token the victim's browser
     *    still holds becomes the grandparent and is retired — so the mechanism can end up signing
     *    the victim out and leaving the attacker in. Shortening this interval does not help; closing
     *    it needs reuse detection, where presenting an already-retired token revokes the whole ring.
     *    Recorded as follow-up work, not implemented here.
     *
     * An upper bound, not the literal threshold — see {@see renewAfterSeconds()}.
     */
    private const int RENEW_AFTER_SECONDS = 86400;

    private readonly CookieManager $cookieManager;

    /**
     * Whether this process issued a login cookie while serving the current request.
     *
     * Static, and that is not laziness: the three login paths each build their own AuthCookie, and
     * AuthenticationHelper::fromRequest() builds another one to read the result, so an instance field
     * would be invisible to the reader. What has to be answered is a property of the *request* — "did
     * we mint the cookie we are now reading, or did the browser hand it to us?" — and under PHP-FPM a
     * static is exactly request-scoped.
     *
     * A test process serves many notional requests in one PHP lifetime, so tests reset it through
     * {@see forgetIssuedCredentials()}.
     */
    private static bool $issuedCredentialsThisRequest = false;

    /**
     * @param SessionTokenStoreHandler $tokenStore The user's token ring.
     * @param CookieManager|null $cookieManager Cookie writer; tests pass a spy to observe emissions.
     */
    public function __construct(
        private readonly SessionTokenStoreHandler $tokenStore,
        ?CookieManager $cookieManager = null,
    ) {
        $this->cookieManager = $cookieManager ?? new CookieManager();
    }

    /**
     * Retrieve the user data from the authentication cookie, if present, valid and still accepted.
     *
     * Both conditions are mandatory: the JWT must verify, and the token must still be in the ring.
     * A revoked user holds a perfectly valid cookie, so the ring check is what logs them out.
     *
     * @return ?array<string, mixed> The payload if the cookie is valid and active, null otherwise.
     * @throws ReflectionException Throws an exception if there is an issue with reflection during validation.
     * @throws Exception
     * @throws TypeError
     */
    public function getCredentials(): ?array
    {
        // Retrieve the payload data from the authentication cookie.
        $payload = $this->getData();

        // Return null if the payload is empty or does not contain a valid user ID.
        if (empty($payload) || empty($payload['user']['uid'])) {
            return null;
        }

        // Is this exact token still one the server accepts?
        if (!$this->tokenStore->isLoginCookieStillActive($payload['user']['uid'], $this->getCookieRawValue())) {
            return null;
        }

        // Return the valid payload.
        return $payload;
    }

    /**
     * Issues a login cookie for a user who has just authenticated.
     *
     * Mints a signed cookie, adds it to the user's token ring so it will pass
     * {@see SessionTokenStoreHandler::isLoginCookieStillActive()}, and sends it to the browser.
     * Called by the three login paths only — password login, signup confirmation and OAuth.
     *
     * Keeping an existing cookie alive is {@see renewIfStale()}'s job, not this one.
     *
     * @param UserStruct $user The user object containing user details.
     *
     * @return void
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function setCredentials(UserStruct $user): void
    {
        $userId = $user->uid ?? throw new RuntimeException('Cannot set credentials for a user without a UID');

        // Generate a new signed authentication cookie and its expiration date, naming any live
        // cookie this login replaces as its predecessor.
        [$new_cookie_data, $new_expire_date] = $this->generateSignedAuthCookie($user, $this->supersededFieldName($userId));

        // Activate the token in the user token store, then hand the cookie to the browser.
        $this->tokenStore->setCookieLoginTokenActive($userId, $new_cookie_data);
        $this->setCookie($new_cookie_data, $new_expire_date);

        // Recorded so a later reader in this same request can tell an identity we just established
        // from one the browser presented. {@see AuthenticationHelper::setUserSession()} is the reader.
        self::$issuedCredentialsThisRequest = true;
    }

    /**
     * Did this process issue a login cookie while serving this request?
     *
     * A login mints the cookie here, server-side. A cookie that merely arrived cannot set this. That
     * difference is the only thing separating a legitimate account switch from an identity planted in
     * somebody's browser, because on the wire the two are identical: a session naming one user and a
     * valid cookie naming another, with no logout in between.
     */
    public function issuedCredentialsThisRequest(): bool
    {
        return self::$issuedCredentialsThisRequest;
    }

    /**
     * Reset the request-scoped marker. For tests, which serve many requests in one PHP lifetime.
     */
    public static function forgetIssuedCredentials(): void
    {
        self::$issuedCredentialsThisRequest = false;
    }

    /**
     * Drop the browser cookie without touching the token ring.
     *
     * For a caller that has decided the presented cookie must stop being used *here* while leaving it
     * valid elsewhere. Deliberately not {@see destroyAuthentication()}: retiring the token would sign
     * that account out on every device, so any false positive in the caller's judgement would become a
     * global logout for a real user instead of one re-login in one browser.
     */
    public function dropCookie(): void
    {
        $this->clearCookie();
    }

    /**
     * The ring field name of a live cookie this login is about to replace, or null when there is
     * nothing to inherit.
     *
     * A login landing on a browser that already holds a cookie used to abandon that token: the
     * browser leaves with the replacement, so no later request can ever carry the old chain, and
     * nothing renews it — the field simply sat in the ring until its own expiry, a full
     * {@see AppConfig::$AUTHCOOKIEDURATION} during which a captured copy still authenticated.
     * Naming it as the new cookie's predecessor hands it to the ordinary grandparent rule in
     * {@see renewIfStale()}, so the first renewal retires it: about one renewal interval instead of
     * a whole cookie lifetime. It is not retired here for the same reason renewal never retires the
     * parent — requests already in flight from other tabs are still carrying it.
     *
     * Restricted to the same uid on purpose. A field name only means anything inside
     * `active_user_login_tokens:<uid>`, and `prev` is retired from the ring of whoever the *new*
     * cookie belongs to, so carrying another account's field name across a login switch would ask
     * one user's ring to retire a token it does not own and drop a reverse key belonging to that
     * other account's live chain. A switch therefore leaves the previous occupant's token to expire
     * on its own, exactly as before this change.
     *
     * The ring is deliberately not consulted: a token that was already revoked is not made any more
     * alive by being named as a predecessor, so the round trip would buy nothing on the login path.
     *
     * @throws TypeError
     */
    private function supersededFieldName(int $userId): ?string
    {
        $currentCookie = $this->getCookieRawValue();

        if ($currentCookie === '') {
            return null;
        }

        try {
            $payload = $this->readPayload();
        } catch (DomainException|UnexpectedValueException) {
            // Unreadable, tampered with or already expired. Nothing worth naming, and nothing that
            // could still authenticate anyone either.
            return null;
        }

        if ((int)($payload['user']['uid'] ?? 0) !== $userId) {
            return null;
        }

        return md5($currentCookie);
    }

    /**
     * Re-issues the login cookie once it is older than {@see self::RENEW_AFTER_SECONDS}.
     *
     * Replaces the old post-expiry "revamp": now that the token ring is the sole authority, an
     * expired JWT yields no uid at all, so renewal has to happen *before* expiry or every user
     * would be signed out hard at {@see AppConfig::$AUTHCOOKIEDURATION}.
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function renewIfStale(UserStruct $user): void
    {
        $currentCookie = $this->getCookieRawValue();

        if ($currentCookie === '') {
            return;
        }

        try {
            $jwt = SimpleJWT::getValidatedInstanceFromString($currentCookie, AppConfig::$AUTHSECRET);
        } catch (DomainException|UnexpectedValueException) {
            // An unreadable cookie is not this method's problem: getCredentials() has already
            // refused it, so the request is anonymous and there is nothing to renew.
            return;
        }

        // Read the age from iat, which a parsed instance keeps as issued. Renewal is decided by how
        // long ago the cookie was minted, so iat is the claim to read here rather than the expiry.
        $issuedAt = $jwt['iat'] ?? null;

        if (!is_int($issuedAt) || (time() - $issuedAt) <= $this->renewAfterSeconds()) {
            return;
        }

        $userId = $user->uid ?? throw new RuntimeException('Cannot renew credentials for a user without a UID');

        $grandparentFieldName = $jwt->getPayload()['prev'] ?? null;

        [$new_cookie_data, $new_expire_date] = $this->generateSignedAuthCookie($user, md5($currentCookie));

        // Mint and publish before retiring anything, so a concurrent request always finds at least
        // one live token in the ring. Two renewals landing in the same second converge on one
        // field rather than multiplying: SimpleJWT stamps iat from time(), never sets jti and adds
        // no randomness, so the payload is byte-identical and the HSET is idempotent.
        $this->tokenStore->setCookieLoginTokenActive($userId, $new_cookie_data);

        // Then confirm the token being renewed is *still* in the ring, and undo the mint if it is not.
        //
        // This closes a resurrection race. Revocation — a password change, a reset, a logout
        // elsewhere — deletes the user's whole map. The ring check that authorised this request ran
        // much earlier, at getCredentials(), so a revocation landing anywhere between then and the
        // write above used to be silently undone: the HSET recreates the map with a freshly minted,
        // perfectly valid token, and the user stays signed in on a credential issued *after* they
        // were revoked. That is the one failure that makes "DEL the map is complete revocation"
        // untrue.
        //
        // Re-reading after the write rather than before is what makes this cover every ordering.
        // Revocation before the mint, or between the mint and this check, both leave the parent
        // absent and the mint is withdrawn; revocation after this check deletes the map including
        // what was just written. The residual window is between the two statements below rather than
        // the length of a request, and the surviving hole is a process death inside it.
        //
        // Not atomic, deliberately. A Lua script would be, but it would have to reproduce the exact
        // stored encoding that DaoCacheTrait writes, and getting that wrong logs out every live
        // session on deploy. That belongs with removing the trait from this handler, not here.
        if (!$this->tokenStore->isLoginCookieStillActive($userId, $currentCookie)) {
            $this->tokenStore->retireLoginToken($userId, md5($new_cookie_data));

            return;
        }

        $this->setCookie($new_cookie_data, $new_expire_date);

        // Retire the grandparent, never the parent. At this instant the browser still holds the
        // parent, so in-flight requests are carrying it and retiring it would log them out
        // mid-page-load. The grandparent is a full renewal interval old and no request lives that
        // long, which makes this race-free with no grace period to tune — that is the entire
        // reason for carrying an extra generation.
        if (is_string($grandparentFieldName)) {
            $this->tokenStore->retireLoginToken($userId, $grandparentFieldName);
        }

        // Collect fields left behind by devices that stopped renewing. Runs here rather than on the
        // hot path: this is once per renewal interval per device, not once per request.
        $this->tokenStore->pruneExpiredLoginTokens($userId, static function (string $token): bool {
            try {
                SimpleJWT::getValidatedInstanceFromString($token, AppConfig::$AUTHSECRET);
            } catch (UnexpectedValueException $e) {
                // Code 2 is the expiry signal (SimpleJWT:262-269). Any other code means the token
                // could not be judged rather than judged dead, so it stays.
                return $e->getCode() === 2;
            } catch (DomainException) {
                // A bad signature is not an expiry. Keeping it is the safe direction, and it
                // cannot authenticate anyone either way.
                return false;
            }

            return false;
        });
    }

    /**
     * How old a cookie must be before it is re-issued.
     *
     * {@see self::RENEW_AFTER_SECONDS} on its own would be wrong for any cookie lifetime shorter
     * than a day: renewal only ever happens to a token that still validates, so a threshold at or
     * past the expiry means renewal never fires and every user is hard-logged-out once per
     * lifetime — remember-me silently stops working. Halving the lifetime keeps a full renewal
     * interval of headroom before expiry.
     *
     * At the shipped {@see AppConfig::$AUTHCOOKIEDURATION} of 7 days this returns
     * RENEW_AFTER_SECONDS unchanged; the bound only takes over below a 2-day lifetime.
     */
    private function renewAfterSeconds(): int
    {
        return min(self::RENEW_AFTER_SECONDS, intdiv(AppConfig::$AUTHCOOKIEDURATION, 2));
    }

    /**
     * Helper to set a cookie.
     *
     * @param string $data
     * @param int $expireDate
     */
    /**
     * Issue the auth cookie, and keep $_COOKIE in step with it.
     *
     * The second half is not incidental. `setcookie()` only queues a Set-Cookie header, so $_COOKIE
     * still holds whatever arrived with the request; anything reading the cookie later in this same
     * request would resolve the *previous* occupant of the browser rather than the identity just
     * issued. That is a live hazard, because LoginController::login() runs
     * AuthenticationHelper::fromRequest() immediately after issuing the cookie: on an account switch
     * with no logout in between, it authenticated the old user, stamped their uid into the session,
     * and let renewIfStale() re-issue their cookie — two Set-Cookie headers for one name, last one
     * wins, so the browser stayed in the old account.
     *
     * It lives here rather than at the two call sites because both need it and one of them silently
     * did not have it. A future third caller cannot forget what the emitter itself does. The delete
     * path is already symmetric: {@see CookieManager::delete()} unsets $_COOKIE for the same reason.
     */
    private function setCookie(string $data, int $expireDate): void
    {
        $this->cookieManager->set(AppConfig::$AUTHCOOKIENAME, $data, $expireDate, true, true, 'Lax');

        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $data;
    }

    /**
     * @return array{string, int}
     *
     * @throws TypeError
     * @throws UnexpectedValueException
     */
    protected function generateSignedAuthCookie(UserStruct $user, ?string $previousFieldName = null): array
    {
        $claims = [
            'user' => [
                'email' => $user->email,
                'first_name' => $user->first_name,
                'has_password' => !is_null($user->pass),
                'last_name' => $user->last_name,
                'uid' => (int)$user->uid,
            ],
        ];

        if ($previousFieldName !== null && $previousFieldName !== '') {
            // Names the token this one supersedes, reusing the ring's own field name (an md5 of the
            // superseded cookie value), so there is nothing new to hash or store anywhere. It is
            // not a secret: an md5 is not reversible and it reveals only that a predecessor
            // existed.
            $claims['prev'] = $previousFieldName;
        }

        $JWT = new SimpleJWT(
            $claims,
            AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
            AppConfig::$AUTHSECRET,
            AppConfig::$AUTHCOOKIEDURATION
        );

        return [$JWT->jsonSerialize(), $JWT->getExpireDate()];
    }

    /**
     * Destroy authentication: retire the token pair from the ring, drop the cookie, end the session.
     *
     * @return void
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function destroyAuthentication(): void
    {
        // Read the payload without clearing: this method clears at the end, and going through
        // getData() would both clear twice and, because the handler is bound rather than passed,
        // re-enter this method.
        try {
            $payload = $this->readPayload();
        } catch (DomainException|UnexpectedValueException) {
            // Nothing identifiable to retire from the ring. The cookie and session still go.
            $payload = null;
        }

        $userId = (int)($payload['user']['uid'] ?? 0);

        // Remove the login cookie from the session token store if a valid payload exists.
        $this->tokenStore->removeLoginCookieFromStore($userId, $_COOKIE[AppConfig::$AUTHCOOKIENAME] ?? '');

        // Retire the superseded token as well. Renewal deliberately leaves the parent live for
        // in-flight requests, so without this a logout would leave a token that still passes
        // isLoginCookieStillActive() for up to a full renewal interval — the browser no longer
        // has it, but anyone who captured it would.
        $previousFieldName = $payload['prev'] ?? null;

        if ($userId !== 0 && is_string($previousFieldName)) {
            $this->tokenStore->retireLoginToken($userId, $previousFieldName);
        }

        $this->clearCookie();
    }

    /**
     * Drops the browser cookie, touching neither the ring nor the session.
     *
     * Separate from {@see destroyAuthentication()} to break a recursion that the constructor-bound
     * handler would otherwise create: getData() calls this when a cookie fails to parse, and
     * destroyAuthentication() calls getData(). While the handler was an optional per-call argument,
     * getData() passed none and the ring branch was skipped; now that the handler is always present
     * that same path would re-enter destroyAuthentication() forever.
     *
     * Keeping the ring untouched here also preserves the previous behaviour exactly: an unparseable
     * cookie has never had its ring entry cleaned up, and this refactor does not change that.
     *
     * Ending the PHP session used to happen here too, and no longer does — that belongs to whoever
     * owns the session, which is {@see AuthenticationHelper::destroyAuthentication()} through its
     * injected store. The consequence worth naming: a cookie that fails to parse now drops the
     * cookie and leaves the session alone. The request is anonymous either way, because identity is
     * decided by the ring and an unparseable cookie yields no uid, so nothing is authorised that was
     * not authorised before; what changes is that unrelated session data survives a corrupt or
     * secret-rotated cookie instead of being wiped by a side effect of reading it.
     */
    private function clearCookie(): void
    {
        // Unset the authentication cookie from the global $_COOKIE array.
        unset($_COOKIE[AppConfig::$AUTHCOOKIENAME]);

        // Set an expired cookie in the browser to effectively remove it.
        $this->cookieManager->delete(AppConfig::$AUTHCOOKIENAME);
    }

    /**
     * Get data from auth cookie, discarding the cookie if it cannot be read.
     *
     * @return ?array<string, mixed>
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    private function getData(): ?array
    {
        try {
            return $this->readPayload();
        } catch (DomainException|UnexpectedValueException $e) {
            LoggerFactory::getLogger('login_exceptions')->debug($e->getMessage() . " " . $this->getCookieRawValue());
            $this->clearCookie();

            return null;
        }
    }

    /**
     * Parses the cookie and leaves it exactly as it found it.
     *
     * Split from {@see getData()} so a caller that is going to clear the cookie anyway does not
     * clear it twice — {@see destroyAuthentication()} needs the payload to know which tokens to
     * retire, but owns the clearing itself.
     *
     * @return ?array<string, mixed>
     * @throws DomainException If the signature does not verify.
     * @throws UnexpectedValueException If the token is malformed or expired.
     * @throws TypeError
     */
    private function readPayload(): ?array
    {
        $raw = $this->getCookieRawValue();

        if ($raw === '') {
            return null;
        }

        return SimpleJWT::getValidatedInstanceFromString($raw, AppConfig::$AUTHSECRET)->getPayload();
    }

    private function getCookieRawValue(): string
    {
        return $_COOKIE[AppConfig::$AUTHCOOKIENAME] ?? '';
    }

}
