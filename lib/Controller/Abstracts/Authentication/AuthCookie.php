<?php

namespace Controller\Abstracts\Authentication;

use DomainException;
use Model\Users\UserStruct;
use Utils\Logger\Log;
use Utils\Registry\AppConfig;
use Utils\Tools\SimpleJWT;

class AuthCookie {

    /**
     * Get user in cookie, if present
     *
     * @return ?array
     */
    public static function getCredentials(): ?array {

        $payload = self::getData();

        if ( empty( $payload ) || empty( $payload[ 'user' ][ 'uid' ] ) ) {
            return null;
        }

        return $payload;
    }

    /**
     * Set a cookie with a username
     *
     * @param UserStruct $user
     *
     */
    public static function setCredentials( UserStruct $user ) {

        [ $new_cookie_data, $new_expire_date ] = static::generateSignedAuthCookie( $user );
        CookieManager::setCookie( AppConfig::$AUTHCOOKIENAME, $new_cookie_data,
                [
                        'expires'  => $new_expire_date,
                        'path'     => '/',
                        'domain'   => AppConfig::$COOKIE_DOMAIN,
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'Lax',
                ]
        );
    }

    /**
     * @param UserStruct $user
     *
     * @return array
     */
    protected static function generateSignedAuthCookie( UserStruct $user ): array {

        $JWT = new SimpleJWT( [
                'user' => [
                        'email'        => $user->email,
                        'first_name'   => $user->first_name,
                        'has_password' => !is_null( $user->pass ),
                        'last_name'    => $user->last_name,
                        'uid'          => (int)$user->uid,
                ],
        ] );

        $JWT->setTimeToLive( AppConfig::$AUTHCOOKIEDURATION );

        return [ $JWT->jsonSerialize(), $JWT->getExpireDate() ];
    }

    /**
     * Destroy authentication
     */
    public static function destroyAuthentication() {

        unset( $_COOKIE[ AppConfig::$AUTHCOOKIENAME ] );
        CookieManager::setCookie( AppConfig::$AUTHCOOKIENAME, '',
                [
                        'expires' => 0,
                        'path'    => '/',
                        'domain'  => AppConfig::$COOKIE_DOMAIN
                ]
        );
        session_destroy();
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
     */
    private static function getData(): ?array {

        if ( isset( $_COOKIE[ AppConfig::$AUTHCOOKIENAME ] ) and !empty( $_COOKIE[ AppConfig::$AUTHCOOKIENAME ] ) ) {

            try {
                return SimpleJWT::getValidPayload( $_COOKIE[ AppConfig::$AUTHCOOKIENAME ] );
            } catch ( DomainException $e ) {
                Log::doJsonLog( $e->getMessage() . " " . $_COOKIE[ AppConfig::$AUTHCOOKIENAME ] );
                self::destroyAuthentication();
            }

        }

        return null;
    }

}

