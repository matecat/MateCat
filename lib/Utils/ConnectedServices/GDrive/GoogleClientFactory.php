<?php

namespace ConnectedServices\GDrive;

use ConnectedServices\Factory\AbstractGoogleClientFactory;
use ConnectedServices\Factory\GoogleClientFactoryInterface;

class GoogleClientFactory implements GoogleClientFactoryInterface
{
    /**
     * @return \Google_Client
     * @throws \Exception
     */
    public static function create() {
        return AbstractGoogleClientFactory::create( \INIT::$HTTPHOST . "/gdrive/oauth/response" );
    }
}
