<?php

namespace ConnectedServices\Github;

use ConnectedServices\ConnectedServiceFactoryInterface;
use INIT;
use League\OAuth2\Client\Provider\Github;

class GithubClientFactory implements ConnectedServiceFactoryInterface
{
    private static $instance;

    private function __construct(){}

    /**
     * @param null $redirectUrl
     * @return Github|mixed
     */
    public static function create($redirectUrl = null) {

        if ( !self::$instance) {
            self::$instance = new Github([
                'clientId'          => INIT::$GITHUB_OAUTH_CLIENT_ID,
                'clientSecret'      => INIT::$GITHUB_OAUTH_CLIENT_SECRET,
                'redirectUri'       => INIT::$GITHUB_OAUTH_REDIRECT_URL,
            ]);
        }

        return self::$instance;
    }
}
