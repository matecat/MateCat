<?php

namespace ConnectedServices;

interface ConnectedServiceFactoryInterface
{
    /**
     * Create an OAuth2 client
     *
     * @param null $redirectUrl
     * @return mixed
     */
    public static function create($redirectUrl = null);
}