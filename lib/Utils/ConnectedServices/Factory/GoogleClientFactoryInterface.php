<?php

namespace ConnectedServices\Factory;

interface GoogleClientFactoryInterface {

    /**
     * @return \Google_Client
     */
    public static function create();
}