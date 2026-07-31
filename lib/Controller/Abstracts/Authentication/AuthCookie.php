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
 * Note this class still reads and writes $_COOKIE directly and calls session_destroy(). Being an
 * instance makes it injectable into its consumers; it does not make it free of superglobals.
 */
class AuthCookie
{

    /**
     * Renew the login cookie once it is older than this.
     *
     * Decouples remember-me from exposure: the cookie still lives
     * {@see AppConfig::$AUTHCOOKIEDURATION} so a user returning within that window stays signed in
     * forever, while a captured token stops working after about two of these intervals, because
     * the renewal two generations later retires it.
     */
    private const int RENEW_AFTER_SECONDS = 86400;

    private readonly CookieManager $cookieManager;

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

        // Generate a new signed authentication cookie and its expiration date.
        [$new_cookie_data, $new_expire_date] = $this->generateSignedAuthCookie($user);

        // Activate the token in the user token store, then hand the cookie to the browser.
        $this->tokenStore->setCookieLoginTokenActive($userId, $new_cookie_data);
        $this->setCookie($new_cookie_data, $new_expire_date);
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

        // Read the age from iat, which a parsed instance keeps as issued. Deliberately NOT
        // getExpireDate(): for a parsed instance :172-174 loads the absolute exp into timeToLive,
        // so it returns iat + exp. Pre-existing bug, avoided rather than relied on.
        $issuedAt = $jwt['iat'] ?? null;

        if (!is_int($issuedAt) || (time() - $issuedAt) <= self::RENEW_AFTER_SECONDS) {
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
        $this->setCookie($new_cookie_data, $new_expire_date);

        // Later reads in this same request must see the token that was just issued, or they would
        // validate a value the browser is no longer going to send.
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $new_cookie_data;

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
     * Helper to set a cookie.
     *
     * @param string $data
     * @param int $expireDate
     */
    private function setCookie(string $data, int $expireDate): void
    {
        $this->cookieManager->set(AppConfig::$AUTHCOOKIENAME, $data, $expireDate, true, true, 'Lax');
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

        $this->clearCookieAndSession();
    }

    /**
     * Drops the browser cookie and the PHP session, touching the ring not at all.
     *
     * Separate from {@see destroyAuthentication()} to break a recursion that the constructor-bound
     * handler would otherwise create: getData() calls this when a cookie fails to parse, and
     * destroyAuthentication() calls getData(). While the handler was an optional per-call argument,
     * getData() passed none and the ring branch was skipped; now that the handler is always present
     * that same path would re-enter destroyAuthentication() forever.
     *
     * Keeping the ring untouched here also preserves the previous behaviour exactly: an unparseable
     * cookie has never had its ring entry cleaned up, and this refactor does not change that.
     */
    private function clearCookieAndSession(): void
    {
        // Unset the authentication cookie from the global $_COOKIE array.
        unset($_COOKIE[AppConfig::$AUTHCOOKIENAME]);

        // Set an expired cookie in the browser to effectively remove it.
        $this->cookieManager->delete(AppConfig::$AUTHCOOKIENAME);

        // Destroy the current session if active.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
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
            $this->clearCookieAndSession();

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
