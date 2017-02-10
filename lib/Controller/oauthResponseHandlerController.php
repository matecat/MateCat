<?php
header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

class oauthResponseHandlerController extends viewController{

    private $code ;
    private $error ;

    /**
     * @var Users_UserStruct
     */
    private $user ;

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
    }

    protected function _processSuccessfulOAuth() {
        $this->_prepareUser();

        $userDao = new Users_UserDao() ;
        $existingUser = $userDao->getByEmail( $this->user->email ) ;

        if ( $existingUser ) {
            $this->_updateExistingUser($existingUser) ;

        } else {
            $this->_createNewUser();
            $this->_welcomeNewUser();
        }

        $this->_authenticateUser();

        $project = new \Users\RedeemableProject($this->user, $_SESSION)  ;
        $project->tryToRedeem()  ;
    }

	protected function _prepareUser() {
        $this->client = OauthClient::getInstance()->getClient();
        $this->client->setAccessType( "offline" );

        $plus = new Google_Service_Oauth2($this->client);
        $this->client->authenticate($this->code);
        $remoteUser = $plus->userinfo->get();

        $this->user = new Users_UserStruct() ;
        $this->user->email  = $remoteUser['email'] ;
        $this->user->first_name = $remoteUser['givenName'];
        $this->user->last_name = $remoteUser['familyName'];
        $this->user->oauth_access_token = OauthTokenEncryption::getInstance()->encrypt(
            $this->client->getAccessToken()
        );
    }

    protected function _respondWithError() {
        // TODO
    }

    protected function _createNewUser() {
        $this->user->create_date = Utils::mysqlTimestamp(time() ) ;
        $this->user->uid = Users_UserDao::insertStruct($this->user);

        $dao = new \Organizations\OrganizationDao();
        $dao->createPersonalOrganization($this->user);
    }

    protected function _updateExistingUser(Users_UserStruct $existing_user) {
        $this->user->uid = $existing_user->uid ;
        Users_UserDao::updateStruct( $this->user, array('fields' =>
            array('oauth_access_token')
        ) ) ;
    }

    protected function _authenticateUser() {
        AuthCookie::setCredentials($this->user->email, $this->user->uid );
        $_SESSION[ 'cid' ]  = $this->user->email ;
        $_SESSION[ 'uid' ]  = $this->user->uid ;
    }

    protected function _welcomeNewUser() {
        $email = new \Email\WelcomeEmail($this->user) ;
        $email->send() ;
        FlashMessage::set('popup', 'profile', FlashMessage::SERVICE);
    }


    protected function collectFlashMessages()
    {
        // prevent this controller to collect flash messages, leave
        // them to the next rendering.
    }
}
