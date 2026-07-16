<?php

namespace Model\ConnectedServices\Oauth\Microsoft;

use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Model\ConnectedServices\Oauth\AbstractProvider;
use Model\ConnectedServices\Oauth\ProviderUser;
use TypeError;
use UnexpectedValueException;
use Unt\OAuth2\Client\Provider\MicrosoftProvider as MicProvider;
use Unt\OAuth2\Client\Provider\MicrosoftResourceOwner;
use Utils\Registry\AppConfig;

class MicrosoftProvider extends AbstractProvider
{

    const string PROVIDER_NAME = 'microsoft';

    /**
     * @param string|null $redirectUrl
     *
     * @return MicProvider
     * @throws InvalidArgumentException
     */
    public function getClient(?string $redirectUrl = null): MicProvider
    {
        return new MicProvider([
            'clientId' => AppConfig::$MICROSOFT_OAUTH_CLIENT_ID ?? throw new InvalidArgumentException('MICROSOFT_OAUTH_CLIENT_ID not configured'),
            'clientSecret' => AppConfig::$MICROSOFT_OAUTH_CLIENT_SECRET ?? throw new InvalidArgumentException('MICROSOFT_OAUTH_CLIENT_SECRET not configured'),
            'redirectUri' => $redirectUrl ?? AppConfig::$MICROSOFT_OAUTH_REDIRECT_URL ?? throw new InvalidArgumentException('MICROSOFT_OAUTH_REDIRECT_URL not configured'),
        ]);
    }

    /**
     * @param string $csrfTokenState *
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public function getAuthorizationUrl(string $csrfTokenState): string
    {
        $options = [
            'state' => $csrfTokenState,
            'scope' => array_merge(
                ['openid', 'profile', 'email'],
                ['User.Read']
            )
        ];

        $microsoftClient = $this->getClient($this->redirectUrl);

        return $microsoftClient->getAuthorizationUrl($options);
    }

    /**
     * @param string $code
     *
     * @return AccessToken
     * @throws IdentityProviderException
     * @throws GuzzleException
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function getAccessTokenFromAuthCode(string $code): AccessToken
    {
        $microsoftClient = $this->getClient($this->redirectUrl);

        /** @var AccessToken $token */
        $token = $microsoftClient->getAccessToken('authorization_code', [
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
     * @throws UnexpectedValueException
     * @throws TypeError
     * @throws InvalidArgumentException
     */
    public function getResourceOwner(AccessToken $token): ProviderUser
    {
        $microsoftClient = $this->getClient($this->redirectUrl);
        /** @var MicrosoftResourceOwner $fetched */
        $fetched = $microsoftClient->getResourceOwner($token);

        $user = new ProviderUser();
        $user->email = $fetched->getEmail() ?? throw new TypeError('Microsoft OAuth: email is required');
        $user->name = $fetched->getGivenName() ?? $fetched->getDisplayName() ?? throw new TypeError('Microsoft OAuth: name is required');
        $user->lastName = $fetched->getSurname();
        $user->picture = null; // profile picture is not publicly accessible
        $user->authToken = (string)$token;
        $user->provider = self::PROVIDER_NAME;

        return $user;
    }
}
