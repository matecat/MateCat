<?php

namespace ConnectedServices\LinkedIn;

use ConnectedServices\ConnectedServiceInterface;
use ConnectedServices\ConnectedServiceUserModel;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use OauthClient;
use Utils;

class LinkedInClient implements ConnectedServiceInterface
{
    /**
     * @return string
     */
    public function getAuthorizationUrl()
    {
        $options = [
            'state' => Utils::randomString(20),
            'scope' => [
                'email',
                'profile',
                'openid',
            ]
        ];
        $linkedInClient = LinkedInClientFactory::create();

        return $linkedInClient->getAuthorizationUrl($options);
    }

    /**
     * @param $code
     * @return string
     * @throws IdentityProviderException
     */
    public function getAuthToken($code)
    {
        $linkedInClient = LinkedInClientFactory::create();

        /** @var AccessToken $token */
        $token = $linkedInClient->getAccessToken('authorization_code', [
            'code' => $code
        ]);

        return $token->getToken();
    }

    /**
     * @param $token
     * @return mixed
     * @throws GuzzleException
     */
    public function getResourceOwner($token): ConnectedServiceUserModel
    {
        $linkedInClient = LinkedInClientFactory::create();
        $response = $linkedInClient->getHttpClient()->request(
            'GET',
            'https://api.linkedin.com/v2/userinfo',
            ['headers' =>
                [
                    'Authorization' => "Bearer {$token}"
                ]
            ]
        );

        $fetched = json_decode($response->getBody()->getContents());

        $user = new ConnectedServiceUserModel();
        $user->email = $fetched->email;
        $user->name = $fetched->given_name;
        $user->lastName = $fetched->family_name;
        $user->picture = $fetched->picture;
        $user->authToken = $token;
        $user->provider = OauthClient::LINKEDIN_PROVIDER;

        return $user;
    }
}

//config.linkedInAuthUrl
