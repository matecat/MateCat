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
     * @return Microsoft
     */
    public static function create() {

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

//config.microsoftAuthUrl