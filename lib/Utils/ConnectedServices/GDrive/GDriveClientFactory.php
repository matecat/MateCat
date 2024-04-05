<?php

namespace ConnectedServices\GDrive;

use ConnectedServices\ConnectedServiceFactoryInterface;
use ConnectedServices\Google\GoogleClientFactory;

class GDriveClientFactory implements ConnectedServiceFactoryInterface
{
    /**
     * @param null $redirectUrl
     * @return \Google_Client|mixed
     * @throws \Exception
     */
    public static function create($redirectUrl = null)
    {
        return GoogleClientFactory::create($redirectUrl);
    }
}

