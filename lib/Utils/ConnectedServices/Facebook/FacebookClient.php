<?php

namespace ConnectedServices\Facebook;

use ConnectedServices\ConnectedServiceInterface;
use ConnectedServices\ConnectedServiceUserModel;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use OauthClient;
use Utils;

class FacebookClient implements ConnectedServiceInterface
{
    /**
     * @inheritDoc
     */
    public function getAuthorizationUrl()
    {
        $options = [
            'state' => Utils::randomString(20),
            'scope' => [
                'email',
            ]
        ];
        $facebookClient = FacebookClientFactory::create();

        return $facebookClient->getAuthorizationUrl($options);
    }

    /**
     * @param $code
     * @return AccessToken|mixed
     * @throws IdentityProviderException
     */
    public function getAuthToken($code)
    {
        $facebookClient = FacebookClientFactory::create();

        /** @var AccessToken $token */
        $token = $facebookClient->getAccessToken('authorization_code', [
            'code' => $code
        ]);

        return $token;
    }

    /**
     * @param $token
     * @return ConnectedServiceUserModel
     */
    public function getResourceOwner($token): ConnectedServiceUserModel
    {
        $facebookClient = FacebookClientFactory::create();
        $fetched = $facebookClient->getResourceOwner($token);

        $user = new ConnectedServiceUserModel();
        $user->email = $fetched->getEmail();
        $user->name = $fetched->getFirstName();
        $user->lastName = $fetched->getLastName();
        $user->picture = $fetched->getPictureUrl();
        $user->authToken = $token;
        $user->provider = OauthClient::FACEBOOK_PROVIDER;

        return $user;
    }
}