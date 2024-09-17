<?php

use ConnectedServices\GoogleClientFactory;
use Exceptions\AuthorizationError;

class oauthResponseHandlerController extends viewController {

    private $code;
    private $error;

    /**
     * @var Google_Service_Oauth2_Userinfo
     */
    private $remoteUser;
    /**
     * @var mixed
     */
    private string $state;

    public function __construct() {
        parent::sessionStart();
        parent::__construct();
        parent::makeTemplate( "oauth_response_handler.html" );

        $filterArgs = [
                'code'  => [ 'filter' => FILTER_SANITIZE_STRING ],
                'state' => [ 'filter' => FILTER_SANITIZE_STRING ],
                'error' => [ 'filter' => FILTER_SANITIZE_STRING ]
        ];

        $__postInput = filter_input_array( INPUT_GET, $filterArgs );

        $this->code  = $__postInput[ 'code' ];
        $this->state = $__postInput[ 'state' ];
        $this->error = $__postInput[ 'error' ];
    }

    /**
     * @throws AuthorizationError
     * @throws Exception
     */
    public function doAction() {

        if ( empty( $this->state ) || $_SESSION[ 'google-' . INIT::$XSRF_TOKEN ] !== $this->state ) {
            throw new AuthorizationError( "Forbidden" );
        }

        if ( isset( $this->code ) && $this->code ) {
            $this->_processSuccessfulOAuth();
        } elseif ( $this->error ) {
            throw new AuthorizationError( "Forbidden" );
        }
    }

    public function setTemplateVars() {
        if ( isset( $_SESSION[ 'wanted_url' ] ) ) {
            $this->template->wanted_url = $_SESSION[ 'wanted_url' ];
        }
    }

    /**
     * @throws Exception
     */
    protected function _processSuccessfulOAuth() {

        $this->client = GoogleClientFactory::getGoogleClient( INIT::$OAUTH_REDIRECT_URL );
        $this->_initRemoteUser();

        $model = new OAuthSignInModel(
                $this->remoteUser->givenName,
                $this->remoteUser->familyName, $this->remoteUser->email
        );

        $model->setProfilePicture( $this->remoteUser->picture );
        $model->setAccessToken( $this->client->getAccessToken() );

        $model->signIn();

    }

    /**
     * @throws Exception
     */
    protected function _initRemoteUser() {

        $this->client->setAccessType( "offline" );

        $plus = new Google_Service_Oauth2( $this->client );
        $this->client->fetchAccessTokenWithAuthCode( $this->code );
        /** @var Google_Service_Oauth2_Userinfo $remoteUser */
        $this->remoteUser = $plus->userinfo->get();
    }

    protected function collectFlashMessages() {
        // prevent this controller to collect flash messages, leave
        // them to the next rendering.
    }
}
