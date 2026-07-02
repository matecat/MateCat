<?php

namespace Model\ConnectedServices\Oauth\Github;

use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Token\AccessToken;
use Model\ConnectedServices\Oauth\AbstractProvider;
use Model\ConnectedServices\Oauth\ProviderUser;
use UnexpectedValueException;
use Utils\Registry\AppConfig;

class GithubProvider extends AbstractProvider
{

    const string PROVIDER_NAME = 'github';

    /**
     * @param string|null $redirectUrl
     *
     * @return Github
     */
    public function getClient(?string $redirectUrl = null): Github
    {
        return new Github([
            'clientId' => AppConfig::$GITHUB_OAUTH_CLIENT_ID,
            'clientSecret' => AppConfig::$GITHUB_OAUTH_CLIENT_SECRET,
            'redirectUri' => $redirectUrl ?? AppConfig::$GITHUB_OAUTH_REDIRECT_URL,
        ]);
    }

    /**
     * @param string $csrfTokenState
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getAuthorizationUrl(string $csrfTokenState): string
    {
        $options = [
            'state' => $csrfTokenState,
            'scope' => [
                'user',
                'user:email'
            ],
            'prompt' => 'select_account'
        ];
        $githubClient = $this->getClient($this->redirectUrl);

        return $githubClient->getAuthorizationUrl($options);
    }

    /**
     * @param string $code
     *
     * @return AccessToken
     * @throws IdentityProviderException
     * @throws GuzzleException
     * @throws UnexpectedValueException
     */
    public function getAccessTokenFromAuthCode(string $code): AccessToken
    {
        $githubClient = $this->getClient();

        /** @var AccessToken $token */
        $token = $githubClient->getAccessToken('authorization_code', [
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
     * @throws \TypeError
     */
    public function getResourceOwner(AccessToken $token): ProviderUser
    {
        $githubClient = $this->getClient($this->redirectUrl);

        $fetched = $githubClient->getResourceOwner($token);
        $fetched = $fetched->toArray();

        // GitHub only returns the full name
        $fullName = $fetched['name'] ?? throw new \TypeError('GitHub OAuth: name is required');
        $name = explode(" ", $fullName);

        $user = new ProviderUser();
        $user->email = $fetched['email'] ?? throw new \TypeError('GitHub OAuth: email is required');
        $user->name = $name[0];
        $user->lastName = $name[1] ?? null;
        $user->picture = $fetched['avatar_url'] ?? null;
        $user->authToken = (string) $token;
        $user->provider = self::PROVIDER_NAME;

        return $user;
    }
}
