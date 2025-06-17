<?php

namespace AbstractControllers;

use API\Commons\ViewValidators\MandatoryKeysValidator;
use Bootstrap;
use ConnectedServices\Facebook\FacebookProvider;
use ConnectedServices\Github\GithubProvider;
use ConnectedServices\Google\GoogleProvider;
use ConnectedServices\LinkedIn\LinkedInProvider;
use ConnectedServices\Microsoft\MicrosoftProvider;
use ConnectedServices\OauthClient;
use Exception;
use FlashMessage;
use INIT;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Klein\ServiceProvider;
use PHPTAL;
use PHPTalBoolean;
use PHPTalMap;
use PHPTALWithAppend;
use Utils;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/10/16
 * Time: 10:24
 */
abstract class BaseKleinViewController extends AbstractStatefulKleinController implements IController {

    /**
     * @var PHPTALWithAppend
     */
    protected PHPTAL $view;

    /**
     * @var integer
     */
    protected int $httpCode;

    /**
     * @param Request              $request
     * @param Response             $response
     * @param ServiceProvider|null $service
     * @param App|null             $app
     *
     * @throws Exception
     */
    public function __construct( Request $request, Response $response, ?ServiceProvider $service = null, ?App $app = null ) {
        parent::__construct( $request, $response, $service, $app );
        $this->timingLogFileName = 'view_controller_calls_time.log';
        $this->appendValidator( new MandatoryKeysValidator( $this ) );
    }

    /**
     * @param string $template_name
     * @param array  $params
     * @param int    $code
     *
     * @return void
     * @throws Exception
     */
    public function setView( string $template_name, array $params = [], int $code = 200 ) {

        $this->view     = new PHPTALWithAppend( INIT::$TEMPLATE_ROOT . "/$template_name" );
        $this->httpCode = $code;

        $this->view->{'basepath'}             = INIT::$BASEURL;
        $this->view->{'hostpath'}             = INIT::$HTTPHOST;
        $this->view->{'build_number'}         = INIT::$BUILD_NUMBER;
        $this->view->{'support_mail'}         = INIT::$SUPPORT_MAIL;
        $this->view->{'enableMultiDomainApi'} = new PHPTalBoolean( INIT::$ENABLE_MULTI_DOMAIN_API );
        $this->view->{'ajaxDomainsNumber'}    = INIT::$AJAX_DOMAINS;
        $this->view->{'maxFileSize'}          = INIT::$MAX_UPLOAD_FILE_SIZE;
        $this->view->{'maxTMXFileSize'}       = INIT::$MAX_UPLOAD_TMX_FILE_SIZE;
        $this->view->{'flashMessages'}        = FlashMessage::flush();

        if ( $this->isLoggedIn() ) {
            $this->featureSet->loadFromUserEmail( $this->user->email );
        }

        $this->view->{'user_plugins'}     = new PHPTalMap( $this->getUser()->getOwnerFeatures() );
        $this->view->{'isLoggedIn'}       = new PHPTalBoolean( $this->isLoggedIn() );
        $this->view->{'userMail'}         = $this->getUser()->email;
        $this->view->{'isAnInternalUser'} = new PHPTalBoolean( $this->featureSet->filter( "isAnInternalUser", $this->getUser()->email ) );

        $this->view->{'footer_js'}     = [];
        $this->view->{'config_js'}     = [];
        $this->view->{'css_resources'} = [];

        // init oauth clients
        $this->view->{'googleAuthURL'}    = ( INIT::$GOOGLE_OAUTH_CLIENT_ID ) ? OauthClient::getInstance( GoogleProvider::PROVIDER_NAME )->getAuthorizationUrl( $_SESSION ) : "";
        $this->view->{'githubAuthUrl'}    = ( INIT::$GITHUB_OAUTH_CLIENT_ID ) ? OauthClient::getInstance( GithubProvider::PROVIDER_NAME )->getAuthorizationUrl( $_SESSION ) : "";
        $this->view->{'linkedInAuthUrl'}  = ( INIT::$LINKEDIN_OAUTH_CLIENT_ID ) ? OauthClient::getInstance( LinkedInProvider::PROVIDER_NAME )->getAuthorizationUrl( $_SESSION ) : "";
        $this->view->{'microsoftAuthUrl'} = ( INIT::$LINKEDIN_OAUTH_CLIENT_ID ) ? OauthClient::getInstance( MicrosoftProvider::PROVIDER_NAME )->getAuthorizationUrl( $_SESSION ) : "";
        $this->view->{'facebookAuthUrl'}  = ( INIT::$FACEBOOK_OAUTH_CLIENT_ID ) ? OauthClient::getInstance( FacebookProvider::PROVIDER_NAME )->getAuthorizationUrl( $_SESSION ) : "";

        $this->view->{'googleDriveEnabled'} = new PHPTalBoolean( Bootstrap::isGDriveConfigured() );
        $this->view->{'gdriveAuthURL'}      = ( $this->isLoggedIn() && Bootstrap::isGDriveConfigured() ) ? OauthClient::getInstance( GoogleProvider::PROVIDER_NAME, INIT::$HTTPHOST . "/gdrive/oauth/response" )->getAuthorizationUrl( $_SESSION, 'drive' ) : "";

        /**
         * This is a unique ID generated at runtime.
         * It is injected into the nonce attribute of `< script >` tags to allow browsers to safely execute the contained CSS and JavaScript.
         */
        $this->view->{'x_nonce_unique_id'}          = Utils::uuid4();
        $this->view->{'x_self_ajax_location_hosts'} = INIT::$ENABLE_MULTI_DOMAIN_API ? " *.ajax." . parse_url( INIT::$HTTPHOST )[ 'host' ] : null;

        $this->addParamsToView( $params );

        $this->view->setOutputMode( PHPTAL::HTML5 );

    }

    /**
     * @throws Exception
     */
    public function addParamsToView( array $params ) {

        if ( !isset( $this->view ) ) {
            throw new Exception( 'View not set. Method `setView` must be called before `addParams`' );
        }

        foreach ( $params as $key => $value ) {
            $this->view->{$key} = $value;
        }

    }

    /**
     * @param $httpCode integer
     */
    public function setCode( int $httpCode ) {
        $this->httpCode = $httpCode;
    }

    /**
     * @param int|null $code
     *
     * @return void
     */
    public function render( ?int $code = null ) {
        $this->response->noCache();
        $this->response->code( $code ?? $this->httpCode );
        $this->response->body( $this->view->execute() );
        $this->response->send();
        die();
    }

    public function redirectToWantedUrl() {
        header( "Location: " . INIT::$HTTPHOST . INIT::$BASEURL . $_SESSION[ 'wanted_url' ], false );
        unset( $_SESSION[ 'wanted_url' ] );
        exit;
    }

}