<?php

namespace ConnectedServices\Github;

use ConnectedServices\ConnectedServiceInterface;
use ConnectedServices\ConnectedServiceUserModel;
use Exception;
use League\OAuth2\Client\Token\AccessToken;
use OauthClient;
use Utils;

class GithubClient implements ConnectedServiceInterface
{
    public function getAuthorizationUrl()
    {
        $options = [
            'state' => Utils::randomString(20),
            'scope' => [
                'user',
                'user:email'
            ]
        ];
        $githubClient = GithubClientFactory::create();

        return $githubClient->getAuthorizationUrl($options);
    }

    /**
     * @param $code
     * @return mixed|string
     * @throws Exception
     */
    public function getAuthToken($code)
    {
        $githubClient = GithubClientFactory::create();

        /** @var AccessToken $token */
        $token = $githubClient->getAccessToken('authorization_code', [
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
        $githubClient = GithubClientFactory::create();
        $fetched = $githubClient->getResourceOwner($token);
        $fetched = $fetched->toArray();

        // github only returns the full name
        $name = explode(" ", $fetched['name']);

        $user = new ConnectedServiceUserModel();
        $user->email = $fetched['email'];
        $user->name = $name[0];
        $user->lastName = $name[1];
        $user->picture = $fetched['avatar_url'];
        $user->authToken = $token;
        $user->provider = OauthClient::GITHUB_PROVIDER;

        return $user;
    }
}
