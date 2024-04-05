<?php

namespace ConnectedServices;

/**
 * Every OAuth2 provider MUST implement this interface
 *
 * Interface ConnectedServiceInterface
 * @package ConnectedServices
 */
interface ConnectedServiceInterface
{
    /**
     * Return the authorization URL to call
     * to get the Auth Token
     *
     * @return string
     */
    public function getAuthorizationUrl();

    /**
     * Generate the Auth Token from the code
     * obtained from the authorization URL
     *
     * @param $code
     * @return mixed
     */
    public function getAuthToken($code);

    /**
     * Return the user from the OAuth2 provider as
     * ConnectedServiceUserModel instance
     *
     * @param $token
     * @return ConnectedServiceUserModel
     */
    public function getResourceOwner($token): ConnectedServiceUserModel;
}