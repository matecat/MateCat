<?php

namespace ConnectedServices\LinkedIn;

use INIT;
use League\OAuth2\Client\Provider\LinkedIn;

class LinkedInClientFactory
{
    /**
     * @return LinkedIn
     */
    public static function create() {
        return new LinkedIn([
            'clientId'          => INIT::$LINKEDIN_OAUTH_CLIENT_ID,
            'clientSecret'      => INIT::$LINKEDIN_OAUTH_CLIENT_SECRET,
            'redirectUri'       => INIT::$LINKEDIN_OAUTH_REDIRECT_URL,
        ]);
    }
}

//config.linkedInAuthUrl
