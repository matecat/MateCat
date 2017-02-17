<?php

class AuthCookie {

    //get user name in cookie, if present
    public static function getCredentials() {

        $payload = self::getData();

        if ( $payload ) {
            self::setCredentials( $payload[ 'username' ], $payload[ 'uid' ] );
        }

        return $payload;

    }

    //set a cookie with a username
    public static function setCredentials( $username, $uid ) {
        list( $new_cookie_data, $new_expire_date ) = static::generateSignedAuthCookie( $username, $uid );
        setcookie( INIT::$AUTHCOOKIENAME, $new_cookie_data, $new_expire_date, '/' );
    }

    public static function generateSignedAuthCookie( $username, $uid ) {

        $JWT = new SimpleJWT( [
                'uid'      => $uid,
                'username' => $username,
        ] );

        $JWT->setTimeToLive( INIT::$AUTHCOOKIEDURATION );

        return array( $JWT->jsonSerialize(), $JWT->getExpireDate() );
    }

    public static function destroyAuthentication() {
        unset( $_COOKIE[ INIT::$AUTHCOOKIENAME ] );
        setcookie( INIT::$AUTHCOOKIENAME, '', 0, '/' );
        session_destroy();
    }

    /**
     * get data from cookie
     *
     * @return mixed
     */
    private static function getData() {
        if ( isset( $_COOKIE[ INIT::$AUTHCOOKIENAME ] ) and !empty( $_COOKIE[ INIT::$AUTHCOOKIENAME ] ) ) {

            try {
                return SimpleJWT::getValidPayload( $_COOKIE[ INIT::$AUTHCOOKIENAME ] );
            } catch ( DomainException $e ) {
                Log::doLog( $e->getMessage() . " " . $_COOKIE[ INIT::$AUTHCOOKIENAME ] );
                self::destroyAuthentication();
            }
        }
    }

}

