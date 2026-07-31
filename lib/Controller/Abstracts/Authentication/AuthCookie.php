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

    /**
     * Cookie writer seam. Null in production (a fresh CookieManager is used);
     * tests inject a spy via {@see self::setCookieManager()} to observe emissions.
     */
    private static ?CookieManager $cookieManager = null;

    /**
     * Overrides the cookie writer (test seam). Pass null to restore the default.
     */
    public static function setCookieManager(?CookieManager $cookieManager): void
    {
        self::$cookieManager = $cookieManager;
    }

    private static function cookieManager(): CookieManager
    {
        return self::$cookieManager ?? new CookieManager();
    }

    /**
     * Retrieve the user data from the authentication cookie, if present and valid.
     *
     * This method extracts the payload from the authentication cookie and verifies
     * its validity. If a `SessionTokenRingHandler` is provided, it also checks
     * whether the login cookie is still active in the session token ring.
     *
     * @param SessionTokenStoreHandler|null $sessionTokenStoreHandler Optional handler for managing session token rings.
     *
     * @return ?array<string, mixed> Returns the payload array if the cookie is valid and active, or null otherwise.
     * @throws ReflectionException Throws an exception if there is an issue with reflection during validation.
     * @throws Exception
     * @throws TypeError
     */
    public static function getCredentials(?SessionTokenStoreHandler $sessionTokenStoreHandler = null): ?array
    {
        // Retrieve the payload data from the authentication cookie.
        $payload = self::getData();

        // Return null if the payload is empty or does not contain a valid user ID.
        if (empty($payload) || empty($payload['user']['uid'])) {
            return null;
        }

        // If a session token ring handler is provided, check if the login cookie is still active.
        if ($sessionTokenStoreHandler !== null && !$sessionTokenStoreHandler->isLoginCookieStillActive($payload['user']['uid'], self::getCookieRawValue())) {
            return null;
        }

        // Return the valid payload.
        return $payload;
    }

    /**
     * Issues a login cookie for a user who has just authenticated.
     *
     * Mints a signed cookie, adds it to the user's token ring so it will pass
     * {@see isLoginCookieStillActive()}, and sends it to the browser. Called by the three login
     * paths only — password login, signup confirmation and OAuth.
     *
     * Keeping an existing cookie alive is {@see renewIfStale()}'s job, not this one. This method
     * used to carry a second "revamp" mode that re-issued a token once the old one had already
     * expired; the only caller was the session branch of AuthenticationHelper::authenticate(), and
     * once the token ring became the sole authority an expired cookie resolves to no user at all,
     * so re-issuing after expiry cannot work. Renewal now happens before expiry instead.
     *
     * @param UserStruct $user The user object containing user details.
     * @param SessionTokenStoreHandler $sessionTokenStoreHandler Handler for managing session token rings.
     *
     * @return void
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public static function setCredentials(UserStruct $user, SessionTokenStoreHandler $sessionTokenStoreHandler): void
    {
        $userId = $user->uid ?? throw new RuntimeException('Cannot set credentials for a user without a UID');

        // Generate a new signed authentication cookie and its expiration date.
        [$new_cookie_data, $new_expire_date] = static::generateSignedAuthCookie($user);

        // Activate the token in the user token store, then hand the cookie to the browser.
        $sessionTokenStoreHandler->setCookieLoginTokenActive($userId, $new_cookie_data);
        self::setCookie($new_cookie_data, $new_expire_date);
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
    public static function renewIfStale(UserStruct $user, SessionTokenStoreHandler $sessionTokenStoreHandler): void
    {
        $currentCookie = self::getCookieRawValue();

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

        [$new_cookie_data, $new_expire_date] = static::generateSignedAuthCookie($user, md5($currentCookie));

        // Mint and publish before retiring anything, so a concurrent request always finds at least
        // one live token in the ring. Two renewals landing in the same second converge on one
        // field rather than multiplying: SimpleJWT stamps iat from time(), never sets jti and adds
        // no randomness, so the payload is byte-identical and the HSET is idempotent.
        $sessionTokenStoreHandler->setCookieLoginTokenActive($userId, $new_cookie_data);
        self::setCookie($new_cookie_data, $new_expire_date);

        // Later reads in this same request must see the token that was just issued, or they would
        // validate a value the browser is no longer going to send.
        $_COOKIE[AppConfig::$AUTHCOOKIENAME] = $new_cookie_data;

        // Retire the grandparent, never the parent. At this instant the browser still holds the
        // parent, so in-flight requests are carrying it and retiring it would log them out
        // mid-page-load. The grandparent is a full renewal interval old and no request lives that
        // long, which makes this race-free with no grace period to tune — that is the entire
        // reason for carrying an extra generation.
        if (is_string($grandparentFieldName)) {
            $sessionTokenStoreHandler->retireLoginToken($userId, $grandparentFieldName);
        }

        // Collect fields left behind by devices that stopped renewing. Runs here rather than on the
        // hot path: this is once per renewal interval per device, not once per request.
        $sessionTokenStoreHandler->pruneExpiredLoginTokens($userId, static function (string $token): bool {
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
    private static function setCookie(string $data, int $expireDate): void
    {
        self::cookieManager()->set(AppConfig::$AUTHCOOKIENAME, $data, $expireDate, true, true, 'Lax');
    }

    /**
     * @return array{string, int}
     *
     * @throws TypeError
     * @throws UnexpectedValueException
     */
    protected static function generateSignedAuthCookie(UserStruct $user, ?string $previousFieldName = null): array
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
     * Destroy authentication by removing the authentication cookie and invalidating the session.
     *
     * @param SessionTokenStoreHandler|null $sessionTokenStoreHandler Optional handler for managing session token stores.
     *
     * @return void
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public static function destroyAuthentication(?SessionTokenStoreHandler $sessionTokenStoreHandler = null): void
    {
        if (!empty($sessionTokenStoreHandler)) {
            // Retrieve the payload data from the authentication cookie.
            $payload = self::getData();
            $userId = (int)($payload['user']['uid'] ?? 0);

            // Remove the login cookie from the session token store if a valid payload exists.
            $sessionTokenStoreHandler->removeLoginCookieFromStore($userId, $_COOKIE[AppConfig::$AUTHCOOKIENAME] ?? '');

            // Retire the superseded token as well. Renewal deliberately leaves the parent live for
            // in-flight requests, so without this a logout would leave a token that still passes
            // isLoginCookieStillActive() for up to a full renewal interval — the browser no longer
            // has it, but anyone who captured it would.
            $previousFieldName = $payload['prev'] ?? null;

            if ($userId !== 0 && is_string($previousFieldName)) {
                $sessionTokenStoreHandler->retireLoginToken($userId, $previousFieldName);
            }
        }

        // Unset the authentication cookie from the global $_COOKIE array.
        unset($_COOKIE[AppConfig::$AUTHCOOKIENAME]);

        // Set an expired cookie in the browser to effectively remove it.
        self::cookieManager()->delete(AppConfig::$AUTHCOOKIENAME);

        // Destroy the current session if active.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Get data from auth cookie.
     *
     * @return ?array<string, mixed>
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    private static function getData(): ?array
    {
        if (isset($_COOKIE[AppConfig::$AUTHCOOKIENAME]) and !empty($_COOKIE[AppConfig::$AUTHCOOKIENAME])) {
            try {
                return SimpleJWT::getValidatedInstanceFromString(
                    $_COOKIE[AppConfig::$AUTHCOOKIENAME],
                    AppConfig::$AUTHSECRET
                )->getPayload();
            } catch (DomainException|UnexpectedValueException $e) {
                LoggerFactory::getLogger('login_exceptions')->debug($e->getMessage() . " " . $_COOKIE[AppConfig::$AUTHCOOKIENAME]);
                self::destroyAuthentication();
            }
        }

        return null;
    }

    private static function getCookieRawValue(): string
    {
        return $_COOKIE[AppConfig::$AUTHCOOKIENAME] ?? '';
    }

}

