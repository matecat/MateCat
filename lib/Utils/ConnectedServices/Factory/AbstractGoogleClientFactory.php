<?php

namespace ConnectedServices\Factory;

abstract class AbstractGoogleClientFactory {

    /**
     * @var array
     */
    private static $OAUTH_SCOPES = [
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/drive.install',
            'profile'
    ];

    /**
     * @param string $redirectUri
     *
     * @return \Google_Client
     */
    public static function create( $redirectUri ) {

        // LOGGER

        $client = new \Google_Client();

        $client->setApplicationName( \INIT::$OAUTH_CLIENT_APP_NAME );
        $client->setClientId( \INIT::$OAUTH_CLIENT_ID );
        $client->setClientSecret( \INIT::$OAUTH_CLIENT_SECRET );
        $client->setRedirectUri( $redirectUri );
        $client->setScopes( static::$OAUTH_SCOPES );
        $client->setAccessType( "offline" );
        $client->setApprovalPrompt('force');
        $client->setIncludeGrantedScopes(true);
        $client->setPrompt( "consent" );

        return $client;
    }
}