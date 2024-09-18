<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use ConnectedServices\Google\GoogleProvider;
use ConnectedServices\OauthClient;

class manageController extends viewController {

    public $notAllCancelled = 0;

    protected $_outsource_login_API = '//signin.translated.net/';

    protected bool $login_required = true;

    public function __construct() {
        parent::__construct();

        parent::makeTemplate( "manage.html" );

        $this->lang_handler = Langs_Languages::getInstance();

        $this->featureSet->loadFromUserEmail( $this->user->email );
    }

    public function doAction() {

        $this->featureSet->filter( 'beginDoAction', $this );

        $this->checkLoginRequiredAndRedirect();

        $activity             = new ActivityLogStruct();
        $activity->action     = ActivityLogStruct::ACCESS_MANAGE_PAGE;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

    }

    /**
     * @throws Exception
     */
    public function setTemplateVars() {

        $this->template->outsource_service_login = $this->_outsource_login_API;

        $this->template->googleAuthURL    = "";
        $this->template->githubAuthUrl    = "";
        $this->template->linkedInAuthUrl  = "";
        $this->template->microsoftAuthUrl = "";
        $this->template->facebookAuthUrl  = "";

        $this->template->googleDriveEnabled = Bootstrap::isGDriveConfigured();
        $this->template->gdriveAuthURL      = Bootstrap::isGDriveConfigured() ? OauthClient::getInstance( GoogleProvider::PROVIDER_NAME, INIT::$HTTPHOST . "/gdrive/oauth/response" )->getAuthorizationUrl( $_SESSION, 'drive' ) : "";

        $this->decorator = new ManageDecorator( $this, $this->template );
        $this->decorator->decorate();

        $this->featureSet->appendDecorators(
                'ManageDecorator',
                $this,
                $this->template
        );
    }

}
