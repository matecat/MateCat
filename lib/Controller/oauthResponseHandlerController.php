<?php

use ConnectedServices\ConnectedServiceUserModel;

header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

class oauthResponseHandlerController extends viewController{

    private $provider ;
    private $code ;
    private $error ;

    /**
     * @var ConnectedServiceUserModel
     */
    private $remoteUser ;

	public function __construct(){
        parent::sessionStart();
		parent::__construct();
		parent::makeTemplate("oauth_response_handler.html");

		$filterArgs = array(
			'provider'      => array( 'filter' => FILTER_SANITIZE_STRING),
			'code'          => array( 'filter' => FILTER_SANITIZE_STRING),
			'error'         => array( 'filter' => FILTER_SANITIZE_STRING)
		);

		$__postInput = filter_input_array( INPUT_GET, $filterArgs );

		$this->provider  = $__postInput[ 'provider' ];
		$this->code      = $__postInput[ 'code' ];
		$this->error     = $__postInput[ 'error' ];
	}

    public function doAction() {
        if (isset($this->code) && $this->code) {
            $this->_processSuccessfulOAuth() ;
        }
        elseif ( $this->error ) {
            $this->_respondWithError();
        }
    }

    public function setTemplateVars()
    {
        // TODO: Implement setTemplateVars() method.
        if ( isset( $_SESSION['wanted_url'] ) ) {
            $this->template->wanted_url = $_SESSION['wanted_url'] ;
        }
    }

    protected function _processSuccessfulOAuth() {
        $this->_initRemoteUser() ;

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

    protected function _initRemoteUser() {

        try {
            $this->client = OauthClient::getInstance($this->provider)->getClient();

            $token = $this->client->getAuthToken($this->code);
            $this->remoteUser = $this->client->getResourceOwner($token);
        } catch (Exception $exception){
            $this->error = $exception->getMessage();

            header( "Location: " . INIT::$HTTPHOST . INIT::$BASEURL );
            die();
        }
    }

    protected function _respondWithError() {
        // TODO
    }

    protected function collectFlashMessages()
    {
        // prevent this controller to collect flash messages, leave
        // them to the next rendering.
    }
}
