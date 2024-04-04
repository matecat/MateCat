<?php

namespace ConnectedServices\LinkedIn;

use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Utils;

class LinkedInClient
{
    /**
     * @return string
     */
    public static function getAuthorizationUrl() {

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
    public static function getAuthToken($code){
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
    public static function getResourceOwner($token){

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

        return json_decode($response->getBody()->getContents());
    }
}

//config.linkedInAuthUrl
