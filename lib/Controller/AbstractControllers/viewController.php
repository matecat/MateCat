<?php

use ConnectedServices\Facebook\FacebookProvider;
use ConnectedServices\Github\GithubProvider;
use ConnectedServices\Google\GoogleProvider;
use ConnectedServices\LinkedIn\LinkedInProvider;
use ConnectedServices\Microsoft\MicrosoftProvider;
use ConnectedServices\OauthClient;
use ConnectedServices\ProviderInterface;
use GlobalMessages\GlobalMessagesPublisher;
use Klein\HttpStatus;

abstract class viewController extends controller {

    /**
     * Template Engine Instance
     *
     * @var PHPTAL|null
     */
    protected ?PHPTAL $template = null;

    /**
     * @var ProviderInterface
     */
    protected ProviderInterface $client;

    private ?Projects_ProjectStruct $project = null;

    /**
     * Class constructor
     *
     * @throws ReflectionException
     * @throws Exception
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

        // SESSION ENABLED
        $this->identifyUser();

        GlobalMessagesPublisher::emit(new AMQHandler());

        $this->featureSet = new FeatureSet();
    }

    /**
     * Perform Authentication Requests and set incoming url
     */
    protected function checkLoginRequiredAndRedirect() {

        //if no login set and login is required
        if ( !$this->isLoggedIn() ) {
            $_SESSION[ 'wanted_url' ] = ltrim( $_SERVER[ 'REQUEST_URI' ], '/' );
            header( "Location: " . INIT::$HTTPHOST . INIT::$BASEURL . "signin", false );
            exit;
        } elseif ( isset( $_SESSION[ 'wanted_url' ] ) ) {
            // handle redirect after login
            $this->redirectToWantedUrl();
        }

    }

    protected function redirectToWantedUrl() {
        header( "Location: " . INIT::$HTTPHOST . INIT::$BASEURL . $_SESSION[ 'wanted_url' ], false );
        unset( $_SESSION[ 'wanted_url' ] );
        exit;
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
            throw new Exception( 'Template is not defined' );
        }

        if ( $this->userIsLogged ) {
            $this->featureSet->loadFromUserEmail( $this->user->email );
        }

        $this->template->user_plugins = $this->featureSet->filter( 'appendInitialTemplateVars', $this->featureSet->getCodes() );

        $this->template->footer_js     = [];
        $this->template->config_js     = [];
        $this->template->css_resources = [];

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
    }

    /**
     * tell the children to set the template vars
     *
     * @return mixed
     */
    abstract function setTemplateVars();

    /**
     * @return bool
     */
    public static function isRevision(): bool {

        $controller = static::getInstance();

        if ( isset( $controller->id_job ) and isset( $controller->received_password ) ) {
            $jid        = $controller->jid;
            $password   = $controller->received_password;
            $isRevision = CatUtils::isRevisionFromIdJobAndPassword( $jid, $password );

            if ( !$isRevision ) {
                $isRevision = CatUtils::getIsRevisionFromRequestUri();
            }

            return $isRevision;
        }

        return CatUtils::getIsRevisionFromRequestUri();
    }

    protected function render404( $customTemplate = '404.html' ) {
        $this->renderCustomHTTP( $customTemplate, 404 );
    }

    /**
     * @throws Exception
     */
    protected function renderCustomHTTP( $customTemplate, $httpCode ) {
        $status = new HttpStatus( $httpCode );
        header( "HTTP/1.0 " . $status->getFormattedString() );
        $this->finalize();
        die();
    }

    /**
     * Create an instance of skeleton PHPTAL template
     *
     * @param string $skeleton_file
     */
    protected function makeTemplate( string $skeleton_file ) {
        try {

            $this->template = new PHPTALWithAppend( INIT::$TEMPLATE_ROOT . "/$skeleton_file" ); // create a new template object

            $this->template->basepath            = INIT::$BASEURL;
            $this->template->hostpath            = INIT::$HTTPHOST;
            $this->template->build_number        = INIT::$BUILD_NUMBER;
            $this->template->use_compiled_assets = INIT::$USE_COMPILED_ASSETS;
            $this->template->enabledBrowsers     = INIT::$ENABLED_BROWSERS;
            $this->template->maxFileSize         = INIT::$MAX_UPLOAD_FILE_SIZE;
            $this->template->maxTMXFileSize      = INIT::$MAX_UPLOAD_TMX_FILE_SIZE;
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

    protected function intOauthClients() {
        $this->template->googleAuthURL    = ( INIT::$GOOGLE_OAUTH_CLIENT_ID ) ? OauthClient::getInstance( GoogleProvider::PROVIDER_NAME )->getAuthorizationUrl( $_SESSION ) : "";
        $this->template->githubAuthUrl    = ( INIT::$GITHUB_OAUTH_CLIENT_ID ) ? OauthClient::getInstance( GithubProvider::PROVIDER_NAME )->getAuthorizationUrl( $_SESSION ) : "";
        $this->template->linkedInAuthUrl  = ( INIT::$LINKEDIN_OAUTH_CLIENT_ID ) ? OauthClient::getInstance( LinkedInProvider::PROVIDER_NAME )->getAuthorizationUrl( $_SESSION ) : "";
        $this->template->microsoftAuthUrl = ( INIT::$LINKEDIN_OAUTH_CLIENT_ID ) ? OauthClient::getInstance( MicrosoftProvider::PROVIDER_NAME )->getAuthorizationUrl( $_SESSION ) : "";
        $this->template->facebookAuthUrl  = ( INIT::$FACEBOOK_OAUTH_CLIENT_ID ) ? OauthClient::getInstance( FacebookProvider::PROVIDER_NAME )->getAuthorizationUrl( $_SESSION ) : "";

        $this->template->googleDriveEnabled = Bootstrap::isGDriveConfigured();
        $this->template->gdriveAuthURL      = ( $this->isLoggedIn() && Bootstrap::isGDriveConfigured() ) ? OauthClient::getInstance( GoogleProvider::PROVIDER_NAME, INIT::$HTTPHOST . "/gdrive/oauth/response" )->getAuthorizationUrl( $_SESSION, 'drive' ) : "";
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
