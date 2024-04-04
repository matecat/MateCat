<?php

namespace ConnectedServices\Microsoft;

use ConnectedServices\ConnectedServiceInterface;
use ConnectedServices\ConnectedServiceUserModel;
use ConnectedServices\Google\GoogleClientFactory;
use Google_Service_Oauth2;
use OauthClient;

class GoogleClient implements ConnectedServiceInterface
{
    /**
     * @return string
     * @throws \Exception
     */
    public function getAuthorizationUrl() {

        $googleClient = GoogleClientFactory::create();

        return $googleClient->createAuthUrl();
    }

    /**
     * @param $code
     * @return array
     * @throws \Exception
     */
    public function getAuthToken($code){

        $googleClient = GoogleClientFactory::create();

        return $googleClient->fetchAccessTokenWithAuthCode($code);
    }

    /**
     * @param $token
     * @return ConnectedServiceUserModel
     * @throws \Exception
     */
    public function getResourceOwner($token): ConnectedServiceUserModel
    {

        $googleClient = GoogleClientFactory::create();
        $googleClient->setAccessType( "offline" );
        $googleClient->setAccessToken($token);

        $plus = new Google_Service_Oauth2($googleClient);
        $fetched = $plus->userinfo->get();

        $user = new ConnectedServiceUserModel();
        $user->email = $fetched->getEmail();
        $user->name = $fetched->getName();
        $user->lastName = $fetched->getFamilyName();
        $user->picture = $fetched->getPicture();
        $user->authToken = $token;
        $user->provider = OauthClient::GOOGLE_PROVIDER;

        return $user;
    }
}