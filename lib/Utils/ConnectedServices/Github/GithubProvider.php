<?php

namespace ConnectedServices\Github;

use ConnectedServices\AbstractProvider;
use ConnectedServices\ConnectedServiceUserModel;
use INIT;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Token\AccessToken;

class GithubProvider extends AbstractProvider {

    const PROVIDER_NAME = 'github';

    /**
     * @param string|null $redirectUrl
     *
     * @return Github
     */
    public static function getClient( ?string $redirectUrl = null ): Github {
        return new Github( [
                'clientId'     => INIT::$GITHUB_OAUTH_CLIENT_ID,
                'clientSecret' => INIT::$GITHUB_OAUTH_CLIENT_SECRET,
                'redirectUri'  => $redirectUrl ?? INIT::$GITHUB_OAUTH_REDIRECT_URL,
        ] );
    }

    /**
     * @param string $csrfTokenState
     *
     * @return string
     */
    public function getAuthorizationUrl( string $csrfTokenState ): string {
        $options      = [
                'state' => $csrfTokenState,
                'scope' => [
                        'user',
                        'user:email'
                ]
        ];
        $githubClient = static::getClient( $this->redirectUrl );

        return $githubClient->getAuthorizationUrl( $options );
    }

    /**
     * @param $code
     *
     * @return AccessToken
     * @throws IdentityProviderException
     */
    public function getAccessTokenFromAuthCode( $code ): AccessToken {
        $githubClient = static::getClient();

        /** @var AccessToken $token */
        $token = $githubClient->getAccessToken( 'authorization_code', [
                'code' => $code
        ] );

        return $token;
    }

    /**
     * @param $token
     *
     * @return ConnectedServiceUserModel
     */
    public function getResourceOwner( $token ): ConnectedServiceUserModel {

        $githubClient = static::getClient( $this->redirectUrl );

        $fetched = $githubClient->getResourceOwner( $token );
        $fetched = $fetched->toArray();

        // GitHub only returns the full name
        $name = explode( " ", $fetched[ 'name' ] );

        $user            = new ConnectedServiceUserModel();
        $user->email     = $fetched[ 'email' ];
        $user->name      = $name[ 0 ];
        $user->lastName  = $name[ 1 ];
        $user->picture   = $fetched[ 'avatar_url' ];
        $user->authToken = $token;
        $user->provider  = self::PROVIDER_NAME;

        return $user;
    }
}