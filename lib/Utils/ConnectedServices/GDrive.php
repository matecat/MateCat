<?php


namespace ConnectedServices;

use Exception;
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
     * @throws \Exception
     */
    public static function getsNewToken( $raw_token ) {
        $client = GoogleClientFactory::create();
        $client->setAccessToken( $raw_token );

        $json_token    = json_decode( $raw_token, true );
        $refresh_token = $json_token[ 'refresh_token' ];

        if ( $client->isAccessTokenExpired() ) {

            $grants = $client->refreshToken( $refresh_token );

            if ( isset( $grants[ 'error' ] ) ) {
                throw new Exception( $grants[ 'error_description' ] );
            }

            $access_token = $client->getAccessToken();

            //
            // 2019-01-02
            // -------------------------
            // Google Api V3 return $access_token as an array, so we need to encode it to JSON string
            //
            if ( is_array( $access_token ) ) {
                $access_token = json_encode( $access_token, true );
            }

            return $access_token;
        }

        return false;
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
