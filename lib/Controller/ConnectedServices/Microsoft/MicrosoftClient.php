<?php

namespace ConnectedServices\Microsoft;

use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Utils;

class MicrosoftClient
{
    /**
     * @return string
     */
    public static function getAuthorizationUrl() {

        $linkedInClient = MicrosoftClientFactory::create();

        return $linkedInClient->getAuthorizationUrl();
    }

    /**
     * @param $code
     * @return AccessToken
     * @throws IdentityProviderException
     */
    public static function getAuthToken($code){
        $microsoftClient = MicrosoftClientFactory::create();

        /** @var AccessToken $token */
        $token = $microsoftClient->getAccessToken('authorization_code', [
            'code' => $code
        ]);

        return $token;
    }

    /**
     * @param $token
     * @return mixed
     * @throws GuzzleException
     */
    public static function getResourceOwner($token){

        $microsoftClient = MicrosoftClientFactory::create();

        return $microsoftClient->getResourceOwner($token);
    }
}
