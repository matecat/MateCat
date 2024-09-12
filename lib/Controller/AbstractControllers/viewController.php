<?php

use ConnectedServices\GoogleClientFactory;

use ConnectedServices\ConnectedServiceInterface;
use ConnectedServices\GDrive;

abstract class viewController extends controller {

    /**
     * Template Engine Instance
     *
     * @var PHPTALWithAppend
     */
    protected $template = null;

    /**
     * @var ConnectedServiceInterface
     */
    protected $client;

    /**
     * Google auth URL
     *
     * @var string
     */
    protected $googleAuthURL;

    /**
     * @var string
     */
    protected $githubAuthURL;

    /**
     * @var string
     */
    protected $linkedInAuthURL;

    /**
     * @var string
     */
    protected $microsoftAuthURL;

    /**
     * @var string
     */
    protected $facebookAuthURL;

    /**
     * @var bool
     */
    protected $login_required = true;

    private ?Projects_ProjectStruct $project        = null;

    /**
     * Class constructor
     *
     */
    public function __construct() {

        $this->startTimer();

        if ( !Bootstrap::areMandatoryKeysPresent() ) {
            $controllerInstance = new CustomPage();
            $controllerInstance->setTemplate( "badConfiguration.html" );
            $controllerInstance->setCode( 503 );
            $controllerInstance->doAction();
            die(); // do not complete klein response, set 404 header in render404 instead of 200
        }

        //SESSION ENABLED
        $this->readLoginInfo( false );

        $this->featureSet = new FeatureSet();

    }

    /**
     * Perform Authentication Requests and set incoming url
     */
    public function checkLoginRequiredAndRedirect() {

        if ( !$this->login_required ) {
            return true;
        }

        //prepare redirect flag
        $mustRedirectToLogin = false;

        //if no login set and login is required
        if ( !$this->isLoggedIn() ) {
            $_SESSION['wanted_url'] = ltrim($_SERVER[ 'REQUEST_URI' ], '/');
            $mustRedirectToLogin = true;
        }

        if ( $mustRedirectToLogin ) {
            header( "Location: " . INIT::$HTTPHOST . INIT::$BASEURL . "signin", false  );
            exit;
        }

        return true;
    }

    public function setLoginRequired( $value ) {
        $this->login_required = $value;
    }

    /**
     * Return the content in the right format, it tell to the child class to execute template vars inflating
     *
     * @return mixed|void
     * @throws Exception
     * @see controller::finalize
     *
     */
    public function finalize() {

        $this->setInitialTemplateVars();
        $this->setTemplateVars();
        $this->featureSet->run( 'appendDecorators', $this, $this->template );
        $this->setTemplateFinalVars();

        ob_get_contents();
        ob_get_clean();
        $this->nocache();

        header( 'Content-Type: text/html; charset=utf-8' );

        /**
         * Execute Template Rendering
         */
        echo $this->template->execute();

        $this->logPageCall();

        if ( isset( $ignore ) ) {
            throw $ignore;
        }

    }

    /**
     * setInitialTemplateVars
     *
     * Initialize template variables that must be initialized to avoid templte errors.
     * These variables are expected to be overridden.
     * @throws Exception
     */
    private function setInitialTemplateVars() {

        if ( is_null( $this->template ) ) {
            throw new Exception( 'Tamplate is not defined' );
        }

        if ( $this->userIsLogged ) {
            $this->featureSet->loadFromUserEmail( $this->user->email );
        }

        $this->template->user_plugins = $this->featureSet->filter( 'appendInitialTemplateVars', $this->featureSet->getCodes() );

        $this->template->footer_js            = [];
        $this->template->config_js            = [];
        $this->template->css_resources        = [];

        $this->template->enableMultiDomainApi = INIT::$ENABLE_MULTI_DOMAIN_API;
        $this->template->ajaxDomainsNumber    = INIT::$AJAX_DOMAINS;

    }

    /**
     * @param string $tokenName
     * @param string $callbackUrl
     *
     * @return string
     * @throws Exception
     */
    protected function setGoogleAuthUrl( string $tokenName, string $callbackUrl ): string {

        if( !isset( $_SESSION[ $tokenName . INIT::$XSRF_TOKEN ] ) ){
            $_SESSION[ $tokenName . INIT::$XSRF_TOKEN ] = Utils::uuid4();
        }

        $googleClientForDrive                      = GoogleClientFactory::getGoogleClient( $callbackUrl );
        $googleClientForDrive->setState( $_SESSION[ $tokenName . INIT::$XSRF_TOKEN ] ); // set a state to be checked in the return request from browser

        return $googleClientForDrive->createAuthUrl();

    }

    /**
     * setTemplateFinalVars
     *
     * Here you have the possiblity to set additional template variables that you always want available in the
     * template. This is the pleace where to set variables like user_id, email address and so on.
     */
    private function setTemplateFinalVars() {

        $MMTLicense       = $this->userIsLogged ? $this->featureSet->filter( "MMTLicense", $this->user ) : [];
        $isAnInternalUser = $this->userIsLogged ? $this->featureSet->filter( "isAnInternalUser", $this->user->email ) : false;

        $this->template->logged_user      = $this->user->shortName();
        $this->template->extended_user    = $this->user->fullName();
        $this->template->isAnInternalUser = $isAnInternalUser;
        $this->template->isMMTEnabled     = ( isset( $MMTLicense[ 'enabled' ] ) and $isAnInternalUser ) ? $MMTLicense[ 'enabled' ] : false;
        $this->template->MMTId            = ( isset( $MMTLicense[ 'id' ] ) and $isAnInternalUser ) ? $MMTLicense[ 'id' ] : null;
        $this->template->isLoggedIn       = $this->userIsLogged;
        $this->template->userMail         = $this->user->email;
        $this->collectFlashMessages();

        $this->template->googleDriveEnabled = Bootstrap::areOauthKeysPresent() && Bootstrap::isGDriveConfigured();
    }

    /**
     * tell the children to set the template vars
     *
     * @return mixed
     */
    abstract function setTemplateVars();

    /**
     * @return string
     * @throws Exception
     */
    public function getGoogleAuthUrl() {
        if ( is_null( $this->googleAuthURL ) ) {
            $this->client  = OauthClient::getInstance()->getClient();
            $this->googleAuthURL = $this->client->getAuthorizationUrl();
        }

        return $this->googleAuthURL;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getGithubAuthUrl(){
        if ( is_null( $this->githubAuthURL )
            and (
                !empty(INIT::$GITHUB_OAUTH_CLIENT_SECRET) and
                !empty(INIT::$GITHUB_OAUTH_CLIENT_ID) and
                !empty(INIT::$GITHUB_OAUTH_REDIRECT_URL)
            )
        ) {
            $this->client  = OauthClient::getInstance(OauthClient::GITHUB_PROVIDER)->getClient();
            $this->githubAuthURL = $this->client->getAuthorizationUrl();
        }

        return $this->githubAuthURL;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getLinkedInAuthUrl(){
        if ( is_null( $this->linkedInAuthURL )
            and (
                !empty(INIT::$LINKEDIN_OAUTH_CLIENT_SECRET) and
                !empty(INIT::$LINKEDIN_OAUTH_CLIENT_ID) and
                !empty(INIT::$LINKEDIN_OAUTH_REDIRECT_URL)
            )
        ) {
            $this->client  = OauthClient::getInstance(OauthClient::LINKEDIN_PROVIDER)->getClient();
            $this->linkedInAuthURL = $this->client->getAuthorizationUrl();
        }

        return $this->linkedInAuthURL;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getMicrosoftAuthUrl(){
        if (
            is_null( $this->microsoftAuthURL )
            and (
                !empty(INIT::$MICROSOFT_OAUTH_CLIENT_SECRET) and
                !empty(INIT::$MICROSOFT_OAUTH_CLIENT_ID) and
                !empty(INIT::$MICROSOFT_OAUTH_REDIRECT_URL)
            )
        ) {
            $this->client  = OauthClient::getInstance(OauthClient::MICROSOFT_PROVIDER)->getClient();
            $this->microsoftAuthURL = $this->client->getAuthorizationUrl();
        }

        return $this->microsoftAuthURL;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function getFacebookAuthUrl(){
        if (
            is_null( $this->facebookAuthURL )
            and (
                !empty(INIT::$FACEBOOK_OAUTH_CLIENT_SECRET) and
                !empty(INIT::$FACEBOOK_OAUTH_CLIENT_ID) and
                !empty(INIT::$FACEBOOK_OAUTH_REDIRECT_URL)
            )
        ) {
            $this->client  = OauthClient::getInstance(OauthClient::FACEBOOK_PROVIDER)->getClient();
            $this->facebookAuthURL = $this->client->getAuthorizationUrl();
        }

        return $this->facebookAuthURL;
    }

    /**
     * @return bool
     */
    public static function isRevision() {

        $controller = static::getInstance();

        if ( isset( $controller->id_job ) and isset( $controller->received_password ) ) {
            $jid        = $controller->jid;
            $password   = $controller->received_password;
            $isRevision = CatUtils::getIsRevisionFromIdJobAndPassword( $jid, $password );

            if ( null === $isRevision ) {
                $isRevision = CatUtils::getIsRevisionFromRequestUri();
            }

            return $isRevision;
        }

        return CatUtils::getIsRevisionFromRequestUri();
    }

    protected function render404( $customTemplate = '404.html' ) {
        $this->renderCustomHTTP( $customTemplate, 404 );
    }

    protected function renderCustomHTTP( $customTemplate, $httpCode ) {
        $status = new \Klein\HttpStatus( $httpCode );
        header( "HTTP/1.0 " . $status->getFormattedString() );
        $this->makeTemplate( $customTemplate );
        $this->finalize();
        die();
    }

    /**
     * Create an instance of skeleton PHPTAL template
     *
     * @param PHPTAL|string $skeleton_file
     */
    protected function makeTemplate( $skeleton_file ) {
        try {

            $this->template = $skeleton_file;
            if ( !$this->template instanceof PHPTAL ) {
                $this->template = new PHPTALWithAppend( INIT::$TEMPLATE_ROOT . "/$skeleton_file" ); // create a new template object
            }

            $this->template->basepath            = INIT::$BASEURL;
            $this->template->hostpath            = INIT::$HTTPHOST;
            $this->template->build_number        = INIT::$BUILD_NUMBER;
            $this->template->use_compiled_assets = INIT::$USE_COMPILED_ASSETS;
            $this->template->enabledBrowsers     = INIT::$ENABLED_BROWSERS;
            $this->template->maxFileSize         = INIT::$MAX_UPLOAD_FILE_SIZE;
            $this->template->maxTMXFileSize      = INIT::$MAX_UPLOAD_TMX_FILE_SIZE;
            $this->template->dqf_enabled         = false;
            $this->template->isOpenAiEnabled     = !empty( INIT::$OPENAI_API_KEY );

            /**
             * This is a unique ID generated at runtime.
             * It is injected into the nonce attribute of `< script >` tags to allow browsers to safely execute the contained CSS and JavaScript.
             */
            $this->template->x_nonce_unique_id          = Utils::uuid4();
            $this->template->x_self_ajax_location_hosts = INIT::$ENABLE_MULTI_DOMAIN_API ? " *.ajax." . parse_url( INIT::$HTTPHOST )[ 'host' ] : null;

            ( INIT::$VOLUME_ANALYSIS_ENABLED ? $this->template->analysis_enabled = true : null );
            $this->template->setOutputMode( PHPTAL::HTML5 );
        } catch ( Exception $e ) {
            echo "<pre>";
            print_r( $e );
            echo "\n\n\n";
            print_r( $this->template );
            echo "</pre>";
            exit;
        }
    }

    protected function collectFlashMessages() {
        $messages                      = FlashMessage::flush();
        $this->template->flashMessages = $messages;
    }

    /**
     * @return Projects_ProjectStruct
     */
    public function getProject() {
        return $this->project;
    }


    /**
     * @param \Projects_ProjectStruct $project
     *
     * @return $this
     */
    public function setProject( $project ) {
        $this->project = $project;

        return $this;
    }

    /**
     * Remove MMT from mt_engines if is an internal user
     *
     * @param array $engines
     *
     * @return array
     * @throws \API\Commons\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    protected function removeMMTFromEngines( array $engines = [] ) {

        $isAnInternalUser = $this->userIsLogged ? $this->featureSet->filter( "isAnInternalUser", $this->user->email ) : false;

        if ( $isAnInternalUser ) {
            $MMTLicense = $this->userIsLogged ? $this->featureSet->filter( "MMTLicense", $this->user ) : [];

            if ( !empty( $MMTLicense ) and isset( $MMTLicense[ 'id' ] ) ) {
                foreach ( $engines as $index => $engine ) {
                    if ( $engine->id === $MMTLicense[ 'id' ] ) {
                        unset( $engines[ $index ] );
                    }
                }
            }
        }

        return $engines;
    }

}
