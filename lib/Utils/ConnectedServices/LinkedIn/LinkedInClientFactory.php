<?php

namespace ConnectedServices\LinkedIn;

use ConnectedServices\ConnectedServiceFactoryInterface;
use INIT;
use League\OAuth2\Client\Provider\LinkedIn;

class LinkedInClientFactory implements ConnectedServiceFactoryInterface
{
    private static $instance;

    private function __construct(){}

    /**
     * @param null $redirectUrl
     * @return LinkedIn|mixed
     */
    public static function create($redirectUrl = null) {

        if ( !self::$instance) {
            self::$instance = new LinkedIn([
                'clientId'          => INIT::$LINKEDIN_OAUTH_CLIENT_ID,
                'clientSecret'      => INIT::$LINKEDIN_OAUTH_CLIENT_SECRET,
                'redirectUri'       => INIT::$LINKEDIN_OAUTH_REDIRECT_URL,
            ]);
        }

        return self::$instance;
    }
}