<?php

namespace Controller\Abstracts\Authentication;

use DomainException;
use Exception;
use Model\Users\UserStruct;
use ReflectionException;
use UnexpectedValueException;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\Tools\SimpleJWT;

class AuthCookie
{

    /**
     * Retrieve the user data from the authentication cookie, if present and valid.
     *
     * This method extracts the payload from the authentication cookie and verifies
     * its validity. If a `SessionTokenRingHandler` is provided, it also checks
     * whether the login cookie is still active in the session token ring.
     *
     * @param SessionTokenStoreHandler|null $sessionTokenStoreHandler Optional handler for managing session token rings.
     *
     * @return ?array Returns the payload array if the cookie is valid and active, or null otherwise.
     * @throws ReflectionException Throws an exception if there is an issue with reflection during validation.
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
     * Set a cookie with a username and manage its lifecycle.
     *
     * This method generates a signed authentication cookie for the given user
     * and sets it in the browser. It also interacts with the session token ring
     * to ensure the token's validity and manage its lifecycle.
     *
     * @param UserStruct $user The user object containing user details.
     * @param SessionTokenStoreHandler $sessionTokenStoreHandler Handler for managing session token rings.
     * @param bool|null $isALoginCookieRevamp Indicates if this is a login cookie revamp.
     *
     * @return void
     * @throws ReflectionException
     */
    public static function setCredentials(UserStruct $user, SessionTokenStoreHandler $sessionTokenStoreHandler, ?bool $isALoginCookieRevamp = false): void
    {
        // Generate a new signed authentication cookie and its expiration date.
        [$new_cookie_data, $new_expire_date] = static::generateSignedAuthCookie($user);

        if ($isALoginCookieRevamp) {
            // If this is a login cookie revamp, this ensures that the session is valid and still active.
            // This prevents the cookie from expiring while the session is still valid.

            // Retrieve the current credentials from the JWT token to check validity.
            $payload = self::getCredentials();

            // If the payload is invalid (expired), generate a new token and set the new cookie.
            if (empty($payload) || empty($payload['user']['uid'])) {
                // Activate the new token in the user token store (e.g., Redis).
                $sessionTokenStoreHandler->setCookieLoginTokenActive($user->uid, $new_cookie_data);
                // Remove the previous token from the token store if applicable.
                $sessionTokenStoreHandler->removeLoginCookieFromStore($user->uid, $_COOKIE[AppConfig::$AUTHCOOKIENAME] ?? '');
                // Set the new cookie in the browser.
                self::setCookie($new_cookie_data, $new_expire_date);
            }
        } else {
            // For a new login, activate the token in the user token store.
            $sessionTokenStoreHandler->setCookieLoginTokenActive($user->uid, $new_cookie_data);
            // Set the new cookie in the browser.
            self::setCookie($new_cookie_data, $new_expire_date);
        }
    }

    /**
     * Helper to set a cookie.
     *
     * @param string $data
     * @param int $expireDate
     */
    private static function setCookie(string $data, int $expireDate): void
    {
        CookieManager::setCookie(AppConfig::$AUTHCOOKIENAME, $data, [
            'expires' => $expireDate,
            'path' => '/',
            'domain' => AppConfig::$COOKIE_DOMAIN,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * @param UserStruct $user
     *
     * @return array
     */
    protected static function generateSignedAuthCookie(UserStruct $user): array
    {
        $JWT = new SimpleJWT(
            [
                'user' => [
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'has_password' => !is_null($user->pass),
                    'last_name' => $user->last_name,
                    'uid' => (int)$user->uid,
                ],
            ],
            AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
            AppConfig::$AUTHSECRET,
            AppConfig::$AUTHCOOKIEDURATION
        );

        return [$JWT->jsonSerialize(), $JWT->getExpireDate()];
    }

    /**
     * Destroy authentication by removing the authentication cookie and invalidating the session.
     *
     * This method performs the following steps:
     * - If a `SessionTokenRingHandler` is provided, it retrieves the payload from the authentication cookie
     *   and removes the login token from the session token store.
     * - Unsets the authentication cookie from the global `$_COOKIE` array.
     * - Sets an expired cookie in the browser to effectively remove it.
     * - Destroys the current session to invalidate the user's authentication.
     *
     * @param SessionTokenStoreHandler|null $sessionTokenStoreHandler Optional handler for managing session token stores.
     *
     * @return void
     * @throws ReflectionException Throws an exception if there is an issue during the removal process.
     */
    public static function destroyAuthentication(?SessionTokenStoreHandler $sessionTokenStoreHandler = null): void
    {
        if (!empty($sessionTokenStoreHandler)) {
            // Retrieve the payload data from the authentication cookie.
            $payload = self::getData();

            LoggerFactory::getLogger('login_exceptions')->alert( 'Session Destroy Phase 2 Remove Cookie From Redis', $payload );

            // Remove the login cookie from the session token store if a valid payload exists.
            $sessionTokenStoreHandler->removeLoginCookieFromStore($payload['user']['uid'] ?? 0, $_COOKIE[AppConfig::$AUTHCOOKIENAME] ?? '');
        }

        LoggerFactory::getLogger('login_exceptions')->alert( 'Session Destroy Phase 3 Unset Cookie', [ $_COOKIE[AppConfig::$AUTHCOOKIENAME] ] );

        // Unset the authentication cookie from the global $_COOKIE array.
        unset($_COOKIE[AppConfig::$AUTHCOOKIENAME]);

        // Set an expired cookie in the browser to effectively remove it.
        CookieManager::setCookie(
            AppConfig::$AUTHCOOKIENAME,
            '',
            [
                'expires' => 0,
                'path' => '/',
                'domain' => AppConfig::$COOKIE_DOMAIN
            ]
        );

        // Destroy the current session.
        session_destroy();

        LoggerFactory::getLogger('login_exceptions')->alert( 'Session Destroy Phase 4 Session Destroyed', $_COOKIE );
    }

    /**
     * Get data from auth cookie
     *
     * Example:
     *
     * {
     *  "metadata": {
     *    "gplus_picture": "https://lh3.googleusercontent.com/a/xxxxxxxxxx"
     *  },
     *  "user": {
     *    "email": "domenico@translated.net",
     *    "first_name": "Domenico",
     *    "has_password": true,
     *    "last_name": "Lupinetti",
     *    "uid": 166
     *  }
     * }
     *
     * @return ?array
     * @throws ReflectionException
     * @throws Exception
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

