<?php

namespace Utils\ConnectedServices\Facebook;

use Exception;
use INIT;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Token\AccessToken;
use Utils\ConnectedServices\AbstractProvider;
use Utils\ConnectedServices\ProviderUser;

class FacebookProvider extends AbstractProvider {

    const PROVIDER_NAME = 'facebook';

    /**
     * @param string|null $redirectUrl
     *
     * @return Facebook
     */
    public static function getClient( ?string $redirectUrl = null ): Facebook {
        return new Facebook( [
                'clientId'        => INIT::$FACEBOOK_OAUTH_CLIENT_ID,
                'clientSecret'    => INIT::$FACEBOOK_OAUTH_CLIENT_SECRET,
                'redirectUri'     => $redirectUrl ?? INIT::$FACEBOOK_OAUTH_REDIRECT_URL,
                'graphApiVersion' => 'v2.10',
        ] );
    }

    /**
     * @param string $csrfTokenState *
     *
     * @inheritDoc
     * @throws Exception
     */
    public function getAuthorizationUrl( string $csrfTokenState ): string {

        $options = [
                'state' => $csrfTokenState,
                'scope' => [
                        'email',
                ]
        ];

        $facebookClient = static::getClient( $this->redirectUrl );

        return $facebookClient->getAuthorizationUrl( $options );

    }

    /**
     * @param string $code
     *
     * @return AccessToken
     * @throws IdentityProviderException
     */
    public function getAccessTokenFromAuthCode( string $code ): AccessToken {
        $facebookClient = static::getClient( $this->redirectUrl );

        /** @var AccessToken $token */
        $token = $facebookClient->getAccessToken( 'authorization_code', [
                'code' => $code
        ] );

        return $token;
    }

    /**
     * @param AccessToken $token
     *
     * @return ProviderUser
     */
    public function getResourceOwner( AccessToken $token ): ProviderUser {

        $facebookClient = static::getClient( $this->redirectUrl );

        $fetched = $facebookClient->getResourceOwner( $token );

        $user            = new ProviderUser();
        $user->email     = $fetched->getEmail();
        $user->name      = $fetched->getFirstName();
        $user->lastName  = $fetched->getLastName();
        $user->picture   = $fetched->getPictureUrl();
        $user->authToken = $token;
        $user->provider  = self::PROVIDER_NAME;

        return $user;
    }
}