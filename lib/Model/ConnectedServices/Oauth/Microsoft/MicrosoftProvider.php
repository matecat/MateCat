<?php

namespace Model\ConnectedServices\Oauth\Microsoft;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Token\AccessToken;
use Model\ConnectedServices\Oauth\AbstractProvider;
use Model\ConnectedServices\Oauth\ProviderUser;
use Stevenmaguire\OAuth2\Client\Provider\Microsoft;
use Utils\Registry\AppConfig;

class MicrosoftProvider extends AbstractProvider
{

    const string PROVIDER_NAME = 'microsoft';

    private const string AUTHORIZE_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    private const string TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    private const string RESOURCE_OWNER_URL = 'https://graph.microsoft.com/v1.0/me';
    private const string SCOPES = 'openid email profile User.Read';

    /**
     * @param string|null $redirectUrl
     *
     * @return Microsoft
     */
    public static function getClient(?string $redirectUrl = null): Microsoft
    {
        return new Microsoft([
            'clientId' => AppConfig::$MICROSOFT_OAUTH_CLIENT_ID,
            'clientSecret' => AppConfig::$MICROSOFT_OAUTH_CLIENT_SECRET,
            'redirectUri' => $redirectUrl ?? AppConfig::$MICROSOFT_OAUTH_REDIRECT_URL,
            'urlAuthorize' => self::AUTHORIZE_URL,
            'urlAccessToken' => self::TOKEN_URL,
            'urlResourceOwnerDetails' => self::RESOURCE_OWNER_URL,
        ]);
    }

    /**
     * @param string $csrfTokenState *
     *
     * @return string
     */
    public function getAuthorizationUrl(string $csrfTokenState): string
    {
        $params = [
            'client_id' => AppConfig::$MICROSOFT_OAUTH_CLIENT_ID,
            'redirect_uri' => $this->redirectUrl ?? AppConfig::$MICROSOFT_OAUTH_REDIRECT_URL,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $csrfTokenState,
            'prompt' => 'select_account',
        ];

        return self::AUTHORIZE_URL . '?' . http_build_query($params);
    }

    /**
     * @param string $code
     *
     * @return AccessToken
     * @throws GuzzleException
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function getAccessTokenFromAuthCode(string $code): AccessToken
    {
        $httpClient = new Client();

        $response = $httpClient->post(self::TOKEN_URL, [
            'form_params' => [
                'client_id' => AppConfig::$MICROSOFT_OAUTH_CLIENT_ID,
                'client_secret' => AppConfig::$MICROSOFT_OAUTH_CLIENT_SECRET,
                'code' => $code,
                'redirect_uri' => $this->redirectUrl ?? AppConfig::$MICROSOFT_OAUTH_REDIRECT_URL,
                'grant_type' => 'authorization_code',
                'scope' => self::SCOPES,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return new AccessToken($data);
    }

    /**
     * @param AccessToken $token
     *
     * @return ProviderUser
     * @throws GuzzleException
     * @throws \RuntimeException
     * @throws \TypeError
     */
    public function getResourceOwner(AccessToken $token): ProviderUser
    {
        $httpClient = new Client();

        $response = $httpClient->get(self::RESOURCE_OWNER_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token->getToken(),
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        $user = new ProviderUser();
        $user->email = $data['mail'] ?? $data['userPrincipalName'] ?? null;
        $user->name = $data['givenName'] ?? null;
        $user->lastName = $data['surname'] ?? null;
        $user->picture = null;
        $user->authToken = $token;
        $user->provider = self::PROVIDER_NAME;

        return $user;
    }
}
