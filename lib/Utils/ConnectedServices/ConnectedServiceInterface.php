<?php

namespace ConnectedServices;

interface ConnectedServiceInterface
{
    public function getAuthorizationUrl();

    public function getAuthToken($code);

    public function getResourceOwner($token): ConnectedServiceUserModel;
}