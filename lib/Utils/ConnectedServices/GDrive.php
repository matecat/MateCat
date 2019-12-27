<?php


namespace ConnectedServices;

use INIT;
use ConnectedServices\GDrive\GoogleClientFactory;

class GDrive {

    /**
     * Generate OAuth URL with GDrive Scopes added
     */
    public static function generateGDriveAuthUrl() {
        $oauthClient = GoogleClientFactory::create();
        $authURL     = $oauthClient->createAuthUrl();

        return $authURL;
    }

    /**
     * This function returns a new token if the previous is expired.
     * If not expired false is returned.
     *
     * @param $raw_token
     *
     * @return mixed
     */
    public static function getsNewToken( $raw_token ) {
        $client = GoogleClientFactory::create();
        $client->setAccessToken( $raw_token );

        $json_token    = json_decode( $raw_token, true );
        $refresh_token = $json_token[ 'refresh_token' ];

        if ( $client->isAccessTokenExpired() ) {

            $client->refreshToken( $refresh_token );
            $access_token = $client->getAccessToken();

            // TODO: check if the slash in refresh token creates some issue with the refreshToken call
            // return self::accessTokenToJsonString( $access_token ) ;
            return $access_token;

        } else {
            return false;
        }
    }


    /**
     * Enforce token to be passed passed around as json_string, to favour encryption and storage.
     * Prevent slash escape, see: http://stackoverflow.com/a/14419483/1297909
     *
     * TODO: verify this is
     *
     * @param $token
     *
     * @return string
     */
    public static function accessTokenToJsonString( $token ) {
        if ( !is_array( $token ) ) {
            $token = json_decode( $token );
        }

        return json_encode( $token, JSON_UNESCAPED_SLASHES );
    }
}
