<?php

use API\Commons\BaseKleinViewController;
use ConnectedServices\ConnectedServiceUserModel;
use ConnectedServices\OauthClient;

class oauthResponseHandlerController extends BaseKleinViewController {

    /**
     * @var ConnectedServiceUserModel
     */
    private ConnectedServiceUserModel $remoteUser;

    /**
     * @throws ReflectionException
     */
    public function response() {

        $params = filter_var_array( $this->request->params(), [
                'provider' => [ 'filter' => FILTER_SANITIZE_STRING ],
                'state'    => [ 'filter' => FILTER_SANITIZE_STRING ],
                'code'     => [ 'filter' => FILTER_SANITIZE_STRING ],
                'error'    => [ 'filter' => FILTER_SANITIZE_STRING ]
        ] );

        if ( empty( $params[ 'state' ] ) || $_SESSION[ $params[ 'provider' ] . '-' . INIT::$XSRF_TOKEN ] !== $params[ 'state' ] ) {
            $this->close( 401 );
        }

        if ( !empty( $params[ 'code' ] ) ) {
            $this->_processSuccessfulOAuth( $params[ 'code' ], $params[ 'provider' ] );
        }

        $this->close( 200 );

    }

    public function setTemplateVars() {
        if ( isset( $_SESSION[ 'wanted_url' ] ) ) {
            $this->view->wanted_url = $_SESSION[ 'wanted_url' ]; //https://dev.matecat.com/translate/205-txt/en-GB-it-IT/25-8a4ee829fb52
        }
    }

    protected function afterConstruct() {
        $this->setTemplateVars();
        $this->setView( INIT::$TEMPLATE_ROOT . '/oauth_response_handler.html' );
    }

    /**
     * Successful OAuth2 authentication handling
     *
     * @param      $code
     * @param null $provider
     *
     * @throws ReflectionException
     */
    protected function _processSuccessfulOAuth( $code, $provider = null ) {

        // OAuth2 authentication
        $this->_initRemoteUser( $code, $provider );

        $model = new OAuthSignInModel(
                $this->remoteUser->name,
                $this->remoteUser->lastName,
                $this->remoteUser->email
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
            $this->close( $exception->getCode() >= 400 && $exception->getCode() < 500 ? $exception->getCode() : 400 );
        }
    }

    protected function close( int $code ) {
        $this->response->code( $code );
        $this->response->body( $this->view->execute() );
        $this->response->send();
        die();
    }

}
