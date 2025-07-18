<?php

namespace Controller\Views;

use Controller\Abstracts\BaseKleinViewController;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Exception;
use Model\ConnectedServices\Oauth\OauthClient;
use Model\ConnectedServices\Oauth\ProviderUser;
use Model\Users\Authentication\OAuthSignInModel;
use ReflectionException;
use Utils\Registry\AppConfig;

class OauthResponseHandlerController extends BaseKleinViewController {

    /**
     * @var ProviderUser
     */
    private ProviderUser $remoteUser;

    /**
     * @throws ReflectionException
     * @throws EnvironmentIsBrokenException
     */
    public function response() {

        $params = filter_var_array( $this->request->params(), [
                'provider' => [ 'filter' => FILTER_SANITIZE_STRING ],
                'state'    => [ 'filter' => FILTER_SANITIZE_STRING ],
                'code'     => [ 'filter' => FILTER_SANITIZE_STRING ],
                'error'    => [ 'filter' => FILTER_SANITIZE_STRING ]
        ] );

        if ( empty( $params[ 'state' ] ) || $_SESSION[ $params[ 'provider' ] . '-' . AppConfig::$XSRF_TOKEN ] !== $params[ 'state' ] ) {
            $this->render( 401 );
        }

        if ( !empty( $params[ 'code' ] ) ) {
            $this->_processSuccessfulOAuth( $params[ 'code' ], $params[ 'provider' ] );
        }

        $this->render( 200 );

    }

    /**
     * @return void
     * @throws Exception
     */
    protected function afterConstruct() {
        $this->setView( 'oauth_response_handler.html', [ 'wanted_url' => $_SESSION[ 'wanted_url' ] ?? null ] ); //https://dev.matecat.com/translate/205-txt/en-GB-it-IT/25-8a4ee829fb52
    }

    /**
     * Successful OAuth2 authentication handling
     *
     * @param      $code
     * @param null $provider
     *
     * @throws ReflectionException
     * @throws EnvironmentIsBrokenException
     */
    protected function _processSuccessfulOAuth( $code, $provider = null ) {

        // OAuth2 authentication
        $this->_initRemoteUser( $code, $provider );

        $model = new OAuthSignInModel(
                $this->remoteUser->email,
                $this->remoteUser->name,
                $this->remoteUser->lastName
        );

        $model->setProvider( $this->remoteUser->provider );
        $model->setProfilePicture( $this->remoteUser->picture );
        $model->setAccessToken( $this->remoteUser->authToken );

        $model->signIn();
    }

    /**
     * This method fetches the remote user
     * from the OAuth2 provider
     *
     * @param      $code
     * @param null $provider
     */
    protected function _initRemoteUser( $code, $provider = null ) {

        try {
            $client           = OauthClient::getInstance( $provider )->getProvider();
            $token            = $client->getAccessTokenFromAuthCode( $code );
            $this->remoteUser = $client->getResourceOwner( $token );
        } catch ( Exception $exception ) {
            $this->render( $exception->getCode() >= 400 && $exception->getCode() < 500 ? $exception->getCode() : 400 );
        }
    }

}
