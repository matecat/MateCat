<?php

namespace Model\ConnectedServices\Oauth;

use Exception;
use Google_Client;
use League\OAuth2\Client\Token\AccessToken;

/**
 * Every OAuth2 provider MUST implement this interface
 *
 * Interface ConnectedServiceInterface
 * @package ConnectedServices
 */
interface ProviderInterface
{
    /**
     * Return the authorization URL to call
     * to get the Auth Token
     *
     * @param string $csrfTokenState
     *
     * @return string
     */
    public function getAuthorizationUrl(string $csrfTokenState): string;

    /**
     * Generate the Auth Token from the code
     * obtained from the authorization URL
     *
     * @param string $code
     *
     * @return AccessToken
     * @throws Exception
     */
    public function getAccessTokenFromAuthCode(string $code): AccessToken;

    /**
     * Return the user from the OAuth2 provider as
     * ProviderUser instance
     *
     * @param AccessToken $token
     *
     * @return ProviderUser
     */
    public function getResourceOwner(AccessToken $token): ProviderUser;

    /**
     * Get the low-level client (below the abstraction)
     *
     * @param string|null $redirectUrl
     *
     * @return AbstractProvider|Google_Client
     */
    public static function getClient(?string $redirectUrl = null): mixed;

}