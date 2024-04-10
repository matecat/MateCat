<?php

namespace ConnectedServices\Facebook;

use ConnectedServices\ConnectedServiceFactoryInterface;
use INIT;
use League\OAuth2\Client\Provider\Facebook;

class FacebookClientFactory implements ConnectedServiceFactoryInterface
{
    private static $instance;

    private function __construct(){}

    /**
     * @param null $redirectUrl
     * @return Facebook|mixed
     */
    public static function create($redirectUrl = null) {

        if ( !self::$instance) {
            self::$instance = new Facebook([
                'clientId'          => INIT::$FACEBOOK_OAUTH_CLIENT_ID,
                'clientSecret'      => INIT::$FACEBOOK_OAUTH_CLIENT_SECRET,
                'redirectUri'       => INIT::$FACEBOOK_OAUTH_REDIRECT_URL,
                'graphApiVersion'   => 'v2.10',
            ]);
        }

        return self::$instance;
    }
}
