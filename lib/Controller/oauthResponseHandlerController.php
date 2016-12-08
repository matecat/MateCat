<?php
header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

class oauthResponseHandlerController extends viewController{

	private $redirectUrl;
	private $userData=array();

	private $user_logged;

	private $oauthTokenEncryption;

	public function __construct(){

        //SESSION ENABLED
        parent::sessionStart();
		parent::__construct();
		parent::makeTemplate("oauth_response_handler.html");

		$this->user_logged = true;

		$this->client = OauthClient::getInstance()->getClient();
        $this->client->setAccessType( "offline" );

		$oauthTokenEncryption = OauthTokenEncryption::getInstance();

		$plus = new Google_Service_Oauth2($this->client);

		$filterArgs = array(
			'code'          => array( 'filter' => FILTER_SANITIZE_STRING),
			'error'         => array( 'filter' => FILTER_SANITIZE_STRING)
		);

		$__postInput = filter_input_array( INPUT_GET, $filterArgs );

		$code         = $__postInput[ 'code' ];
		$error        = $__postInput[ 'error' ];

		if (isset($code) && $code) {
			$this->client->authenticate($code);

			$user = $plus->userinfo->get();

			//get response from third-party
			$this->userData['email']              = $user['email'];
			$this->userData['first_name']         = $user['givenName'];
			$this->userData['last_name']          = $user['familyName'];
			$this->userData['oauth_access_token'] = $oauthTokenEncryption->encrypt(
				$this->client->getAccessToken()
			);
		}
		else if (isset($error)){
			$this->user_logged = false;
		}
	}

	public function doAction(){

        // TODO: this should be refactored. tryInsertUserFromOAuth has no longer reason to exist
        // we have now way to check if the result is a new insert or an update.

		if ( $this->user_logged && !empty($this->userData) ) {
			//user has been validated, data was by Google
			//check if user exists in db; if not, create

            $userDao = new Users_UserDao() ;
            $existingUser = $userDao->getByEmail( $this->userData['email'] ) ;

            if ( $existingUser && !is_null($existingUser->email_confirmed_at) ) {
                $newUser = FALSE ;
            } else {
                $newUser = TRUE ;
            }

            \Log::doLog( $this->userData );

			$result = tryInsertUserFromOAuth($this->userData);

			if(false==$result){
				die("error in insert");
			}

			AuthCookie::setCredentials($this->userData['email'], $result['uid']);

            $_SESSION[ 'cid' ]  = $this->userData['email'];
            $_SESSION[ 'uid' ]  = $result[ 'uid' ];

            $user = $userDao->getByUid( $result['uid'] ) ;

            $project = new \Users\RedeemableProject($user, $_SESSION)  ;
            $project->tryToRedeem()  ;

            if ( $newUser ) {
                FlashMessage::set('popup', 'profile', FlashMessage::SERVICE);
            }
		}
	}

	public function setTemplateVars()
    {
        // TODO: Implement setTemplateVars() method.
    }

    protected function collectFlashMessages()
    {
        // prevent this controller to collect flash messages, leave
        // them to the next rendering.
    }
}
