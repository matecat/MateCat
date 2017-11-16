<?php
use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;

class manageController extends viewController {

	public $notAllCancelled = 0;

    protected $_outsource_login_API = '//signin.translated.net/';

    protected $login_required = true ;

	public function __construct() {
		parent::__construct( );

		parent::makeTemplate("manage.html");

		$this->lang_handler = Langs_Languages::getInstance();

        $this->featureSet->loadFromUserEmail( $this->logged_user->email ) ;
	}

	public function doAction() {

	    $this->featureSet->filter( 'beginDoAction', $this );

	    $this->checkLoginRequiredAndRedirect();

		$activity             = new ActivityLogStruct();
		$activity->action     = ActivityLogStruct::ACCESS_MANAGE_PAGE;
		$activity->ip         = Utils::getRealIpAddr();
		$activity->uid        = $this->logged_user->uid;
		$activity->event_date = date( 'Y-m-d H:i:s' );
		Activity::save( $activity );
		
	}

	public function setTemplateVars() {
		$this->template->logged_user = ($this->logged_user !== false ) ? $this->logged_user->shortName() : "";
		$this->template->build_number = INIT::$BUILD_NUMBER;
        $this->template->basepath = INIT::$BASEURL;
        $this->template->hostpath = INIT::$HTTPHOST;
        $this->template->v_analysis = var_export( INIT::$VOLUME_ANALYSIS_ENABLED, true );
		$this->template->enable_omegat = ( INIT::$ENABLE_OMEGAT_DOWNLOAD !== false );
        $this->template->globalMessage = Utils::getGlobalMessage() ;
        $this->template->outsource_service_login    = $this->_outsource_login_API ;
        $this->template->enable_outsource           = $this->featureSet->filter('filter_enable_outsource', INIT::$ENABLE_OUTSOURCE);
	}

}
