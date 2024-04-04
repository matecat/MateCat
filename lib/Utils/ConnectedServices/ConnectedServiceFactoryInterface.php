<?php

namespace ConnectedServices;

interface ConnectedServiceFactoryInterface
{
    /**
     * @return mixed
     */
    public static function create();
}