<?php

abstract class viewController extends controller {

    /**
     * Template Engine Instance
     *
     * @var PHPTALWithAppend
     */
    protected $template = null;

    /**
     * @var Google_Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $authURL;

    protected $login_required = false;


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
            //take note of url we wanted to go after
            $_SESSION[ 'wanted_url' ] = $_SERVER[ 'REQUEST_URI' ];
            $mustRedirectToLogin      = true;
        }

        if ( $mustRedirectToLogin ) {
            FlashMessage::set( 'popup', 'login', FlashMessage::SERVICE );

            header( 'Location: ' . Routes::appRoot() );
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
        ob_start( "ob_gzhandler" ); // compress page before sending
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
     * @return string
     * @deprecated use getAuthUrl instead.
     */
    public function generateAuthURL() {
        return $this->getAuthUrl();
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

        $this->template->user_plugins =  $this->featureSet->filter('appendInitialTemplateVars', $this->featureSet->getCodes());

        $this->template->footer_js            = [];
        $this->template->config_js            = [];
        $this->template->css_resources        = [];
        $this->template->authURL              = $this->getAuthUrl();
        $this->template->gdriveAuthURL        = \ConnectedServices\GDrive::generateGDriveAuthUrl();
        $this->template->enableMultiDomainApi = INIT::$ENABLE_MULTI_DOMAIN_API;
        $this->template->ajaxDomainsNumber    = INIT::$AJAX_DOMAINS;


    }

    /**
     * setTemplateFinalVars
     *
     * Here you have the possiblity to set additional template variables that you always want available in the
     * template. This is the pleace where to set variables like user_id, email address and so on.
     */
    private function setTemplateFinalVars() {

        $MMTLicense = $this->userIsLogged ? $this->featureSet->filter( "MMTLicense", $this->user) : [];
        $isAnInternalUser  = $this->userIsLogged ? $this->featureSet->filter( "isAnInternalUser", $this->user->email) : false;

        $this->template->logged_user      = $this->user->shortName();
        $this->template->extended_user    = $this->user->fullName();
        $this->template->isAnInternalUser = $isAnInternalUser;
        $this->template->isMMTEnabled     = (isset($MMTLicense['enabled']) and $isAnInternalUser) ? $MMTLicense['enabled'] : false;
        $this->template->MMTId            = (isset($MMTLicense['id']) and $isAnInternalUser) ? $MMTLicense['id'] : null;
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
     */
    public function getAuthUrl() {
        if ( is_null( $this->authURL ) ) {
            $this->client  = OauthClient::getInstance()->getClient();
            $this->authURL = $this->client->createAuthUrl();
        }

        return $this->authURL;
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
            $this->template->isOpenAiEnabled     = !empty(INIT::$OPENAI_API_KEY);

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
     * @return array
     * @throws \API\V2\Exceptions\AuthenticationError
     * @throws \Exceptions\NotFoundException
     * @throws \Exceptions\ValidationError
     * @throws \TaskRunner\Exceptions\EndQueueException
     * @throws \TaskRunner\Exceptions\ReQueueException
     */
    protected function removeMMTFromEngines(array $engines = []) {

        $isAnInternalUser  = $this->userIsLogged ? $this->featureSet->filter( "isAnInternalUser", $this->user->email) : false;

        if($isAnInternalUser){
            $MMTLicense = $this->userIsLogged ? $this->featureSet->filter( "MMTLicense", $this->user) : [];

            if(!empty($MMTLicense) and isset($MMTLicense['id'])){
                foreach ($engines as $index => $engine){
                    if($engine->id === $MMTLicense['id']){
                        unset($engines[$index]);
                    }
                }
            }
        }

        return $engines;
    }

}
