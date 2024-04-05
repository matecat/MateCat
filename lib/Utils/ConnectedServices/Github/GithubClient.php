<?php

namespace ConnectedServices\Github;

use ConnectedServices\ConnectedServiceInterface;
use ConnectedServices\ConnectedServiceUserModel;

class GithubClient implements ConnectedServiceInterface
{
    public function getAuthorizationUrl()
    {
        // TODO: Implement getAuthorizationUrl() method.
    }

    public function getAuthToken($code)
    {
        // TODO: Implement getAuthToken() method.
    }

    public function getResourceOwner($token): ConnectedServiceUserModel
    {
        // TODO: Implement getResourceOwner() method.
    }
}
