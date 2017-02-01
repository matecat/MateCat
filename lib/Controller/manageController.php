<?php
use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;

/**
 * Description of manageController
 *
 * @author andrea
 */
class manageController extends viewController {

	private $page = 1;
	public $notAllCancelled = 0;

	public function __construct() {
		$isAuthRequired = true;
		parent::__construct( $isAuthRequired );

		parent::makeTemplate("manage.html");

        $filterArgs = array(
            'page'      =>  array('filter'  =>  array(FILTER_SANITIZE_NUMBER_INT)),
            'filter'    =>  array('filter'  =>  array(FILTER_VALIDATE_BOOLEAN), 'options' => array(FILTER_NULL_ON_FAILURE))
        );

        $postInput = filter_input_array(INPUT_GET, $filterArgs);

        if( !empty( $postInput[ 'page' ] ) ){
            $this->page = $postInput[ 'page' ];
        }

		$this->lang_handler = Langs_Languages::getInstance();

		if ($postInput[ 'filter' ] !== null && $postInput[ 'filter' ]) {
			$this->filter_enabled = true;
		} else {
			$this->filter_enabled = false;
		};
	}

	public function doAction() {

		$activity             = new ActivityLogStruct();
		$activity->action     = ActivityLogStruct::ACCESS_MANAGE_PAGE;
		$activity->ip         = Utils::getRealIpAddr();
		$activity->uid        = $this->logged_user->uid;
		$activity->event_date = date( 'Y-m-d H:i:s' );
		Activity::save( $activity );
		
	}

	public function setTemplateVars() {

		$this->template->prev_page = ($this->page - 1);
		$this->template->next_page = ($this->page + 1);
		$this->template->languages = $this->lang_handler->getEnabledLanguages('en');
		$this->template->filtered = $this->filter_enabled;
		$this->template->filtered_class = ($this->filter_enabled) ? ' open' : '';
		$this->template->logged_user = ($this->logged_user !== false ) ? $this->logged_user->shortName() : "";
		$this->template->build_number = INIT::$BUILD_NUMBER;
        $this->template->basepath = INIT::$BASEURL;
        $this->template->hostpath = INIT::$HTTPHOST;
        $this->template->v_analysis = var_export( INIT::$VOLUME_ANALYSIS_ENABLED, true );
		$this->template->enable_omegat = ( INIT::$ENABLE_OMEGAT_DOWNLOAD !== false );

	}

}
