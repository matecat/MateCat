<?php

namespace ConnectedServices;

interface ConnectedServiceFactoryInterface
{
    /**
     * @param null $redirectUrl
     * @return mixed
     */
    public static function create($redirectUrl = null);
}