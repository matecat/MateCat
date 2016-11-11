<?php


namespace ConnectedServices ;

use Google_Client ;

use INIT ;

class GDrive {

    private static $OAUTH_SCOPES = array(
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/drive.install',
            'profile'
        );

    /**
     * Generate OAuth URL with GDrive Scopes added
     */
    public static function generateGDriveAuthUrl() {
        $oauthClient  = static::getClient();
        $authURL = $oauthClient->createAuthUrl();

        return $authURL;
    }

    public static function getClient() {
        $client = new Google_Client();

        $client->setApplicationName(INIT::$OAUTH_CLIENT_APP_NAME);
        $client->setClientId(INIT::$OAUTH_CLIENT_ID);

        $client->setClientSecret(INIT::$OAUTH_CLIENT_SECRET);

        $client->setRedirectUri(
            INIT::$HTTPHOST . "/gdrive/oauth/response"
        );

        $client->setScopes(static::$OAUTH_SCOPES);
        $client->setAccessType("offline");
        $client->setPrompt("consent");

        return $client ;
    }

    /**
     * This function returns a new token if the previous is expired.
     * If not expired false is returned.
     *
     * @param $raw_token
     * @return mixed
     */
    public static function getsNewToken( $raw_token ) {
        $client = self::getClient() ;
        $client->setAccessToken( $raw_token ) ;

        if ( $client->isAccessTokenExpired() ) {
            $client->refreshToken( $raw_token );

            return $client->getAccessToken();
        }
        else {
            return false;
        }
    }


}
