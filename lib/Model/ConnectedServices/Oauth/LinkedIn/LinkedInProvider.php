<?php

namespace Model\ConnectedServices\Oauth\LinkedIn;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Model\ConnectedServices\Oauth\AbstractProvider;
use Model\ConnectedServices\Oauth\ProviderUser;
use RuntimeException;
use TypeError;
use UnexpectedValueException;
use Utils\Registry\AppConfig;

class LinkedInProvider extends AbstractProvider
{

    const string PROVIDER_NAME = 'linkedin';

    /**
     * @param string|null $redirectUrl
     *
     * @return LinkedinFinal
     */
    public function getClient(?string $redirectUrl = null): LinkedinFinal
    {
        return new LinkedinFinal([
            'clientId' => AppConfig::$LINKEDIN_OAUTH_CLIENT_ID,
            'clientSecret' => AppConfig::$LINKEDIN_OAUTH_CLIENT_SECRET,
            'redirectUri' => $redirectUrl ?? AppConfig::$LINKEDIN_OAUTH_REDIRECT_URL,
        ]);
    }


    /**
     * @param string $csrfTokenState *
     *
     * @return string
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getAuthorizationUrl(string $csrfTokenState): string
    {
        $options = [
            'state' => $csrfTokenState,
            'scope' => [
                'email',
                'profile',
                'openid'
            ],
            'prompt' => 'select_account'
        ];
        $linkedInClient = $this->getClient();

        return $linkedInClient->getAuthorizationUrl($options);
    }

    /**
     * @param string $code
     *
     * @return AccessToken
     * @throws GuzzleException
     * @throws IdentityProviderException
     * @throws UnexpectedValueException
     */
    public function getAccessTokenFromAuthCode(string $code): AccessToken
    {
        $linkedInClient = $this->getClient($this->redirectUrl);

        /** @var AccessToken $token */
        $token = $linkedInClient->getAccessToken('authorization_code', [
            'code' => $code
        ]);

        return $token;
    }

    /**
     * @param AccessToken $token
     *
     * @return ProviderUser
     * @throws GuzzleException
     * @throws TypeError
     * @throws RuntimeException
     * @throws IdentityProviderException
     */
    public function getResourceOwner(AccessToken $token): ProviderUser
    {
        $linkedInClient = $this->getClient($this->redirectUrl);
        $response = $linkedInClient->getResourceOwner($token);

        $user = new ProviderUser();
        $user->email = $response->getEmail() ?? throw new TypeError('LinkedIn OAuth: email is required');
        $user->name = (string)($response->getAttribute('given_name') ?? throw new TypeError('LinkedIn OAuth: name is required'));
        $user->lastName = $response->getAttribute('family_name') ?: null;
        $user->picture = $response->getAttribute('picture') ?: null;
        $user->authToken = (string)$token;
        $user->provider = self::PROVIDER_NAME;

        return $user;
    }
}
