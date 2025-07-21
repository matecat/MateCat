<?php

namespace Model\ConnectedServices\Oauth\Microsoft;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Model\ConnectedServices\Oauth\AbstractProvider;
use Model\ConnectedServices\Oauth\ProviderUser;
use Stevenmaguire\OAuth2\Client\Provider\Microsoft;
use Utils\Registry\AppConfig;

class MicrosoftProvider extends AbstractProvider {

    const PROVIDER_NAME = 'microsoft';

    /**
     * @param string|null $redirectUrl
     *
     * @return Microsoft
     */
    public static function getClient( ?string $redirectUrl = null ): Microsoft {
        return new Microsoft( [
                'clientId'     => AppConfig::$MICROSOFT_OAUTH_CLIENT_ID,
                'clientSecret' => AppConfig::$MICROSOFT_OAUTH_CLIENT_SECRET,
                'redirectUri'  => $redirectUrl ?? AppConfig::$MICROSOFT_OAUTH_REDIRECT_URL,
        ] );
    }

    /**
     * @param string $csrfTokenState *
     *
     * @return string
     */
    public function getAuthorizationUrl( string $csrfTokenState ): string {

        $options = [
                'state'  => $csrfTokenState,
                'prompt' => 'select_account'
        ];

        $microsoftClient = static::getClient( $this->redirectUrl );

        return $microsoftClient->getAuthorizationUrl( $options );
    }

    /**
     * @param string $code
     *
     * @return AccessToken
     * @throws IdentityProviderException
     */
    public function getAccessTokenFromAuthCode( string $code ): AccessToken {
        $microsoftClient = static::getClient( $this->redirectUrl );

        /** @var AccessToken $token */
        $token = $microsoftClient->getAccessToken( 'authorization_code', [
                'code' => $code
        ] );

        return $token;
    }

    /**
     * @param AccessToken $token
     *
     * @return mixed
     */
    public function getResourceOwner( AccessToken $token ): ProviderUser {
        $microsoftClient = static::getClient( $this->redirectUrl );
        $fetched         = $microsoftClient->getResourceOwner( $token );

        $user            = new ProviderUser();
        $user->email     = $fetched->getEmail();
        $user->name      = $fetched->getFirstname();
        $user->lastName  = $fetched->getLastname();
        $user->picture   = null; // profile picture is not publicly accessible
        $user->authToken = $token;
        $user->provider  = self::PROVIDER_NAME;

        return $user;
    }
}
