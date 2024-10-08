<?php

namespace ConnectedServices;

use Exception;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Every OAuth2 provider MUST implement this interface
 *
 * Interface ConnectedServiceInterface
 * @package ConnectedServices
 */
interface ProviderInterface {
    /**
     * Return the authorization URL to call
     * to get the Auth Token
     *
     * @param string $csrfTokenState
     *
     * @return string
     */
    public function getAuthorizationUrl( string $csrfTokenState ): string;

    /**
     * Generate the Auth Token from the code
     * obtained from the authorization URL
     *
     * @param string $code
     *
     * @return AccessToken
     * @throws Exception
     */
    public function getAccessTokenFromAuthCode( string $code ): AccessToken;

    /**
     * Return the user from the OAuth2 provider as
     * ConnectedServiceUserModel instance
     *
     * @param AccessToken $token
     *
     * @return ConnectedServiceUserModel
     */
    public function getResourceOwner( AccessToken $token ): ConnectedServiceUserModel;

    /**
     * Get the low level client (below the abstraction)
     * @return mixed
     */
    public static function getClient( ?string $redirectUrl = null );

}