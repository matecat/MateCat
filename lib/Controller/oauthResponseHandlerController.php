<?php

use ConnectedServices\ConnectedServiceInterface;
use ConnectedServices\ConnectedServiceUserModel;

header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

class oauthResponseHandlerController extends BaseKleinViewController {

    /**
     * @var ConnectedServiceUserModel
     */
    private $remoteUser ;

    /**
     * @var ConnectedServiceInterface
     */
    private $client;

    public function response() {

        $params = filter_var_array( $this->request->params(), [
            'provider'      => [ 'filter' => FILTER_SANITIZE_STRING ],
			'code'          => [ 'filter' => FILTER_SANITIZE_STRING ],
			'error'         => [ 'filter' => FILTER_SANITIZE_STRING ]
        ] );

        if (isset($params['code']) && !empty($params['code'])) {
            $this->_processSuccessfulOAuth($params['code'], $params['provider']) ;

            $this->response->body( $this->view->execute() );
            $this->response->send();

        } elseif ( $params['error'] ) {
            $this->_respondWithError($params['error']);
        }
    }

    public function setTemplateVars()
    {
        if ( isset( $_SESSION['wanted_url'] ) ) {
            $this->view->wanted_url = $_SESSION['wanted_url'] ;
        }
    }

    protected function afterConstruct() {
        $this->setTemplateVars();
        $this->setView( \INIT::$TEMPLATE_ROOT . '/oauth_response_handler.html');
    }

    /**
     * Successful OAuth2 authentication handling
     * @param $code
     * @param null $provider
     */
    protected function _processSuccessfulOAuth($code, $provider = null) {

        // OAuth2 authentication
        $this->_initRemoteUser($code, $provider) ;

        $model = new OAuthSignInModel(
            $this->remoteUser->name,
            $this->remoteUser->lastName,
            $this->remoteUser->email
        ) ;

        $model->setProvider( $this->remoteUser->provider );
        $model->setProfilePicture( $this->remoteUser->picture );
        $model->setAccessToken( $this->remoteUser->authToken );

        $model->signIn() ;
    }

    /**
     * This method fetches the remote user
     * from the OAuth2 provider
     * @param $code
     * @param null $provider
     */
    protected function _initRemoteUser($code, $provider = null) {

        try {
            $this->client = OauthClient::getInstance($provider)->getClient();
            $token = $this->client->getAuthToken($code);
            $this->remoteUser = $this->client->getResourceOwner($token);
        } catch (Exception $exception){
            // in case of bad request, redirect to homepage
            header( "Location: " . INIT::$HTTPHOST . INIT::$BASEURL );
            die();
        }
    }

    protected function _respondWithError($error) {
        // TODO
    }

    protected function collectFlashMessages()
    {
        // prevent this controller to collect flash messages, leave
        // them to the next rendering.
    }
}
