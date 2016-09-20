<?php
use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;

/**
 * Description of manageController
 *
 * @author andrea
 */
class manageController extends viewController {

	private $jid = "";
	private $pid = "";
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

        $this->page = $postInput[ 'page' ];

        if($this->page == null || empty($this->page)){
            $this->page = 1;
        }
		$this->lang_handler = Langs_Languages::getInstance();

		if ($postInput[ 'filter' ] !== null && $postInput[ 'filter' ]) {
			$this->filter_enabled = true;
		} else {
			$this->filter_enabled = false;
		};
	}

	public function doAction() {

		list( $uid, $email ) = $this->getLoginUserParams();
		$activity             = new ActivityLogStruct();
		$activity->action     = ActivityLogStruct::ACCESS_MANAGE_PAGE;
		$activity->ip         = Utils::getRealIpAddr();
		$activity->uid        = $uid;
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
