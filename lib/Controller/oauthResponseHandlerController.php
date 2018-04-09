<?php

header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

class oauthResponseHandlerController extends viewController{

    private $code ;
    private $error ;

    /**
     * @var Google_Service_Oauth2_Userinfoplus
     */
    private $remoteUser ;

	public function __construct(){
        parent::sessionStart();
		parent::__construct();
		parent::makeTemplate("oauth_response_handler.html");

		$filterArgs = array(
			'code'          => array( 'filter' => FILTER_SANITIZE_STRING),
			'error'         => array( 'filter' => FILTER_SANITIZE_STRING)
		);

		$__postInput = filter_input_array( INPUT_GET, $filterArgs );

		$this->code  = $__postInput[ 'code' ];
		$this->error = $__postInput[ 'error' ];
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
                $this->remoteUser->givenName,
                $this->remoteUser->familyName, $this->remoteUser->email
        ) ;

        $model->setProfilePicture( $this->remoteUser->picture );
        $model->setAccessToken( $this->client->getAccessToken() );

        $model->signIn() ;
    }

    protected function _initRemoteUser() {
        $this->client = OauthClient::getInstance()->getClient();
        $this->client->setAccessType( "offline" );

        $plus = new Google_Service_Oauth2($this->client);
        $this->client->authenticate($this->code);
        $this->remoteUser = $plus->userinfo->get();
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
