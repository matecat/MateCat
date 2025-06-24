<?php

namespace Controller\Abstracts\Authentication;

use CookieManager;
use DomainException;
use INIT;
use Log;
use SimpleJWT;
use Users_UserStruct;

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
     * @param Users_UserStruct $user
     *
     */
    public static function setCredentials( Users_UserStruct $user ) {

        [ $new_cookie_data, $new_expire_date ] = static::generateSignedAuthCookie( $user );
        CookieManager::setCookie( INIT::$AUTHCOOKIENAME, $new_cookie_data,
                [
                        'expires'  => $new_expire_date,
                        'path'     => '/',
                        'domain'   => INIT::$COOKIE_DOMAIN,
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'Lax',
                ]
        );
    }

    /**
     * @param Users_UserStruct $user
     *
     * @return array
     */
    protected static function generateSignedAuthCookie( Users_UserStruct $user ): array {

        $JWT = new SimpleJWT( [
                'user' => [
                        'email'        => $user->email,
                        'first_name'   => $user->first_name,
                        'has_password' => !is_null( $user->pass ),
                        'last_name'    => $user->last_name,
                        'uid'          => (int)$user->uid,
                ],
        ] );

        $JWT->setTimeToLive( INIT::$AUTHCOOKIEDURATION );

        return [ $JWT->jsonSerialize(), $JWT->getExpireDate() ];
    }

    /**
     * Destroy authentication
     */
    public static function destroyAuthentication() {

        unset( $_COOKIE[ INIT::$AUTHCOOKIENAME ] );
        CookieManager::setCookie( INIT::$AUTHCOOKIENAME, '',
                [
                        'expires' => 0,
                        'path'    => '/',
                        'domain'  => INIT::$COOKIE_DOMAIN
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

        if ( isset( $_COOKIE[ INIT::$AUTHCOOKIENAME ] ) and !empty( $_COOKIE[ INIT::$AUTHCOOKIENAME ] ) ) {

            try {
                return SimpleJWT::getValidPayload( $_COOKIE[ INIT::$AUTHCOOKIENAME ] );
            } catch ( DomainException $e ) {
                Log::doJsonLog( $e->getMessage() . " " . $_COOKIE[ INIT::$AUTHCOOKIENAME ] );
                self::destroyAuthentication();
            }

        }

        return null;
    }

}

