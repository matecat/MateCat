<?php

class AuthCookie {

    //get user name in cookie, if present
    public static function getCredentials() {
        //log::doLog("credentials request");
        $val = false;
        if ( self::validate() ) {
            $data = self::getData();
            $val  = array('username' => $data[ 'username' ], 'uid' => $data['uid'] );
            //log::doLog("successfully pulled client name: $val");
        } else {

            //log::doLog("no valid cookie found");
        }

        return $val;
    }

    //set a cookie with a username
    public static function setCredentials( $username, $uid ) {
        $new_expire_date = time() + INIT::$AUTHCOOKIEDURATION;
        $new_cookie_data = array(
                'uid'         => $uid,
                'username'    => $username,
                'expire_date' => $new_expire_date,
                'hash'        => hash( 'sha256', INIT::$AUTHSECRET . $username . $uid . $new_expire_date )
        );
        $new_cookie_data = json_encode( $new_cookie_data );
        //log::doLog("inserting in ".INIT::$AUTHCOOKIENAME." following data: ".$new_cookie_data);
        $outcome = setcookie( INIT::$AUTHCOOKIENAME, $new_cookie_data, $new_expire_date, '/' );
        if ( !$outcome ) {
            //log::doLog("Failed setting cookie");
        }
    }

    public static function destroyAuthentication() {
        unset( $_COOKIE[ INIT::$AUTHCOOKIENAME ] );
        setcookie( INIT::$AUTHCOOKIENAME, '', 0, '/' );
        session_destroy();
    }

    //get data from cookie
    private static function getData() {
        if ( isset( $_COOKIE[ INIT::$AUTHCOOKIENAME ] ) and !empty( $_COOKIE[ INIT::$AUTHCOOKIENAME ] ) ) {
            $data = json_decode( $_COOKIE[ INIT::$AUTHCOOKIENAME ], true );
        } else {
            $data = false;
        }

        return $data;
    }

    //perform a validation against cookie, both sanity and expiration
    private static function validate() {
        //log::doLog("performing validation");
        $valid = false;
        //get cookie data, if available
        $cookie = self::getData();
        if ( is_array( $cookie ) ) {
            //cookie is an JSON string containing: username,expire date,hash(secret in config file+username+expire date)
            //expire date is timestamp in seconds
            //compute expected hash based on data in cookie
            $expected_hash = hash( 'sha256', INIT::$AUTHSECRET . $cookie[ 'username' ] . $cookie[ 'uid' ] . $cookie[ 'expire_date' ] );            
            //check if valid hash and expiration still in time
            if ( $cookie[ 'hash' ] == $expected_hash and time() < $cookie[ 'expire_date' ] and self::tryToRefreshToken( $cookie[ 'username' ] ) ) {
                //ok, refresh value
                //log::doLog("Validation succeed, refreshing cookie");
                self::setCredentials( $cookie[ 'username' ], $cookie[ 'uid' ] );
                //confirm login
                $valid = true;
            } else {
                //log::doLog("Failed validation");
                //cookie invalid, destroy session
                self::destroyAuthentication();
                $valid = false;
            }
        }

        return $valid;
    }
    
    public static function tryToRefreshToken($username){
        $oauthTokenEncryption = OauthTokenEncryption::getInstance();
        $valid = false;
        
        $userData = getUserData( $username );
        
        if ( is_array( $userData ) && array_key_exists( 'oauth_access_token', $userData ) ) {
            $accessToken = $userData[ 'oauth_access_token' ];

            if( $oauthTokenEncryption->isTokenEncrypted( $accessToken ) ) {
                $accessToken = $oauthTokenEncryption->decrypt( $accessToken );
            } else {
                $userDataEncryptToken = array(
                    'email'                 => $username,
                    'oauth_access_token'    => $oauthTokenEncryption->encrypt( $accessToken )
                );
                tryInsertUserFromOAuth( $userDataEncryptToken );
            }

            if ( $accessToken !== '' ) {
                $valid = self::validOrRefreshedToken( $username, $accessToken );
            }
        }
        
        return $valid;
    }
    
    private static function validOrRefreshedToken($username, $accessToken) {
        $client = OauthClient::getInstance()->getClient();
        $oauthTokenEncryption = OauthTokenEncryption::getInstance();

        $client->setAccessToken( $accessToken );
        
        if ( $client->isAccessTokenExpired() && $client->getRefreshToken() != null ) {
            $client->refreshToken( $client->getRefreshToken() );
            
            $newToken = $oauthTokenEncryption->encrypt( $client->getAccessToken() );
            
            $userData = array(
                'email'                 => $username,
                'oauth_access_token'    => $newToken
            );
            
            $result = tryInsertUserFromOAuth( $userData );

            if( false == $result ){
                return false;
            }
        }
        
        return true;
    }
}

?>
