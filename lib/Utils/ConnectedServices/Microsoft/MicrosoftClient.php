<?php

namespace ConnectedServices\Microsoft;

use ConnectedServices\ConnectedServiceInterface;
use ConnectedServices\ConnectedServiceUserModel;
use GuzzleHttp\Exception\GuzzleException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use OauthClient;

class MicrosoftClient implements ConnectedServiceInterface
{
    /**
     * @return string
     */
    public function getAuthorizationUrl()
    {

        $linkedInClient = MicrosoftClientFactory::create();

        return $linkedInClient->getAuthorizationUrl();
    }

    /**
     * @param $code
     * @return AccessToken
     * @throws IdentityProviderException
     */
    public function getAuthToken($code)
    {
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
    public function getResourceOwner($token): ConnectedServiceUserModel
    {
        $microsoftClient = MicrosoftClientFactory::create();
        $fetched = $microsoftClient->getResourceOwner($token);

        $user = new ConnectedServiceUserModel();
        $user->email = $fetched->getEmail();
        $user->name = $fetched->getFirstname();
        $user->lastName = $fetched->getLastname();
        $user->picture = $fetched->getUrls();
        $user->authToken = $token;
        $user->provider = OauthClient::MICROSOFT_PROVIDER;

        return $user;
    }
}
