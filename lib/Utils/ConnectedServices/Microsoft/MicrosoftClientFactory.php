<?php

namespace ConnectedServices\Microsoft;

use ConnectedServices\ConnectedServiceFactoryInterface;
use INIT;
use Stevenmaguire\OAuth2\Client\Provider\Microsoft;

class MicrosoftClientFactory implements ConnectedServiceFactoryInterface
{
    private static $instance;

    private function __construct(){}

    /**
     * @param null $redirectUrl
     * @return mixed|Microsoft
     */
    public static function create($redirectUrl = null) {

        if ( !self::$instance) {
            self::$instance = new Microsoft([
                'clientId'          => INIT::$MICROSOFT_OAUTH_CLIENT_ID,
                'clientSecret'      => INIT::$MICROSOFT_OAUTH_CLIENT_SECRET,
                'redirectUri'       => INIT::$MICROSOFT_OAUTH_REDIRECT_URL,
            ]);
        }

        return self::$instance;
    }
}
