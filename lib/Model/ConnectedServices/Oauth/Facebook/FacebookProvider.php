<?php

namespace Model\ConnectedServices\Oauth\Facebook;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Token\AccessToken;
use Model\ConnectedServices\Oauth\AbstractProvider;
use Model\ConnectedServices\Oauth\ProviderUser;
use Utils\Registry\AppConfig;

class FacebookProvider extends AbstractProvider
{

    const string PROVIDER_NAME = 'facebook';

    /**
     * @param string|null $redirectUrl
     *
     * @return Facebook
     * @throws InvalidArgumentException
     */
    public function getClient(?string $redirectUrl = null): Facebook
    {
        return new Facebook([
            'clientId' => AppConfig::$FACEBOOK_OAUTH_CLIENT_ID,
            'clientSecret' => AppConfig::$FACEBOOK_OAUTH_CLIENT_SECRET,
            'redirectUri' => $redirectUrl ?? AppConfig::$FACEBOOK_OAUTH_REDIRECT_URL,
            'graphApiVersion' => 'v2.10',
        ]);
    }

    /**
     * @param string $csrfTokenState *
     *
     * @inheritDoc
     * @throws Exception
     */
    public function getAuthorizationUrl(string $csrfTokenState): string
    {
        $options = [
            'state' => $csrfTokenState,
            'scope' => [
                'email',
            ]
        ];

        $facebookClient = $this->getClient($this->redirectUrl);

        return $facebookClient->getAuthorizationUrl($options);
    }

    /**
     * @param string $code
     *
     * @return AccessToken
     * @throws IdentityProviderException
     * @throws GuzzleException
     * @throws \UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function getAccessTokenFromAuthCode(string $code): AccessToken
    {
        $facebookClient = $this->getClient($this->redirectUrl);

        /** @var AccessToken $token */
        $token = $facebookClient->getAccessToken('authorization_code', [
            'code' => $code
        ]);

        return $token;
    }

    /**
     * @param AccessToken $token
     *
     * @return ProviderUser
     * @throws GuzzleException
     * @throws IdentityProviderException
     * @throws \TypeError
     * @throws InvalidArgumentException
     */
    public function getResourceOwner(AccessToken $token): ProviderUser
    {
        $facebookClient = $this->getClient($this->redirectUrl);

        $fetched = $facebookClient->getResourceOwner($token);

        $user = new ProviderUser();
        $user->email = $fetched->getEmail() ?? throw new \TypeError('Facebook OAuth: email is required');
        $user->name = $fetched->getFirstName() ?? throw new \TypeError('Facebook OAuth: name is required');
        $user->lastName = $fetched->getLastName();
        $user->picture = $fetched->getPictureUrl();
        $user->authToken = (string) $token;
        $user->provider = self::PROVIDER_NAME;

        return $user;
    }
}