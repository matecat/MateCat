<?php

namespace Model\ConnectedServices\Oauth\LinkedIn;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\LinkedIn;
use League\OAuth2\Client\Token\AccessToken;
use Model\ConnectedServices\Oauth\AbstractProvider;
use Model\ConnectedServices\Oauth\ProviderUser;
use Utils\Registry\AppConfig;

class LinkedInProvider extends AbstractProvider
{

    const string PROVIDER_NAME = 'linkedin';

    /**
     * @param string|null $redirectUrl
     *
     * @return LinkedIn
     */
    public static function getClient(?string $redirectUrl = null): LinkedIn
    {
        return new LinkedIn([
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
     */
    public function getAuthorizationUrl(string $csrfTokenState): string
    {
        $options = [
            'state' => $csrfTokenState,
            'scope' => [
                'email',
                'profile',
                'openid',
            ],
            'prompt' => 'select_account'
        ];
        $linkedInClient = static::getClient();

        return $linkedInClient->getAuthorizationUrl($options);
    }

    /**
     * @param string $code
     *
     * @return AccessToken
     * @throws GuzzleException
     * @throws IdentityProviderException
     */
    public function getAccessTokenFromAuthCode(string $code): AccessToken
    {
        $linkedInClient = static::getClient($this->redirectUrl);

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
     */
    public function getResourceOwner(AccessToken $token): ProviderUser
    {
        $linkedInClient = static::getClient($this->redirectUrl);
        $response = $linkedInClient->getHttpClient()->request(
            'GET',
            'https://api.linkedin.com/v2/userinfo',
            [
                'headers' =>
                    [
                        'Authorization' => "Bearer $token"
                    ]
            ]
        );

        $fetched = json_decode($response->getBody()->getContents());

        $user = new ProviderUser();
        $user->email = $fetched->email;
        $user->name = $fetched->given_name;
        $user->lastName = $fetched->family_name;
        $user->picture = $fetched->picture;
        $user->authToken = $token;
        $user->provider = self::PROVIDER_NAME;

        return $user;
    }
}

//config.linkedInAuthUrl
