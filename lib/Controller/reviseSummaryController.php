<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use WordCount\WordCountStruct;

/**
 * Description of catController
 *
 * @Deprecated
 *
 * @author antonio
 */
class reviseSummaryController extends viewController {

	private $jid;
	private $project_status = "";

    private $data;
    private $job_stats;
    private $job_archived = false;
    private $job_owner_email;
    private $password;

    public function __construct() {
		parent::__construct();
		parent::makeTemplate("revise_summary.html");

        $filterArgs = array(
                'jid'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_GET, $filterArgs );

        $this->jid      = $__postInput[ "jid" ];
        $this->password = $__postInput[ "password" ];
        $this->thisUrl  = $_SERVER[ 'REQUEST_URI' ];

    }

	public function doAction() {

        //pay a little query to avoid to fetch 5000 rows
        $this->data = $jobStruct = Jobs_JobDao::getByIdAndPassword( $this->jid, $this->password );

        $wStruct = WordCountStruct::loadFromJob( $jobStruct );

        if( $jobStruct['status'] == Constants_JobStatus::STATUS_ARCHIVED || $jobStruct['status'] == Constants_JobStatus::STATUS_CANCELLED ){
            //this job has been archived
            $this->job_archived = true;
            $this->job_owner_email = $jobStruct['job_owner'];
        }

        $this->job_stats = CatUtils::getFastStatsForJob( $wStruct, false );
        $this->project_status = $this->data->getProject();

        $this->_saveActivity();
	}

    protected function _saveActivity(){

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->jid;
        $activity->id_project = $this->data[ 'id_project' ];
        $activity->action     = ActivityLogStruct::ACCESS_REVISE_SUMMARY_PAGE;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

    }
    
	public function setTemplateVars() {

        $this->template->job_archived = ( $this->job_archived ) ? 1 : '';
        $this->template->owner_email  = $this->job_owner_email;

        $this->template->jid          = $this->jid;
        $this->template->password     = $this->password;
        $this->template->pid          = $this->data['id_project'];

        $this->template->pname        = $this->project_status[ 'name' ];
        $this->template->source_code  = $this->data[ 'source' ];
        $this->template->target_code  = $this->data[ 'target' ];

        $this->template->creation_date = $this->data['create_date'];

        $this->template->word_count_type        = $this->data->getProject()->getWordCountType();
        $this->job_stats[ 'analysis_complete' ] = ( $this->project_status[ 'status_analysis' ] == Constants_ProjectStatus::STATUS_DONE ? true : false );
        $this->template->job_stats              = $this->job_stats;


        $this->template->build_number  = INIT::$BUILD_NUMBER;
        $this->template->extended_user = ($this->isLoggedIn() !== false ) ? trim( $this->user->fullName() ) : "";
        $this->template->logged_user   = ($this->isLoggedIn() !== false ) ? $this->user->shortName() : "";

        $projectMetaDataDao = new Projects_MetadataDao();
        $projectMetaData = null;

        if($this->getProject() !== null){
            $projectMetaData = $projectMetaDataDao->get($this->getProject()->id, Projects_MetadataDao::FEATURES_KEY);
        }

        $this->template->project_plugins = (!empty($projectMetaData)) ?  $this->featureSet->filter('appendInitialTemplateVars', explode(",", $projectMetaData->value)) : [];

        /*
         * Line Feed PlaceHolding System
         */
        $this->template->brPlaceholdEnabled = $placeHoldingEnabled = true;

        if ( $placeHoldingEnabled ) {

            $this->template->lfPlaceholder        = CatUtils::lfPlaceholder;
            $this->template->crPlaceholder        = CatUtils::crPlaceholder;
            $this->template->crlfPlaceholder      = CatUtils::crlfPlaceholder;
            $this->template->lfPlaceholderClass   = CatUtils::lfPlaceholderClass;
            $this->template->crPlaceholderClass   = CatUtils::crPlaceholderClass;
            $this->template->crlfPlaceholderClass = CatUtils::crlfPlaceholderClass;
            $this->template->lfPlaceholderRegex   = CatUtils::lfPlaceholderRegex;
            $this->template->crPlaceholderRegex   = CatUtils::crPlaceholderRegex;
            $this->template->crlfPlaceholderRegex = CatUtils::crlfPlaceholderRegex;

            $this->template->tabPlaceholder      = CatUtils::tabPlaceholder;
            $this->template->tabPlaceholderClass = CatUtils::tabPlaceholderClass;
            $this->template->tabPlaceholderRegex = CatUtils::tabPlaceholderRegex;

            $this->template->nbspPlaceholder      = CatUtils::nbspPlaceholder;
            $this->template->nbspPlaceholderClass = CatUtils::nbspPlaceholderClass;
            $this->template->nbspPlaceholderRegex = CatUtils::nbspPlaceholderRegex;
        }

        $lang_handler = Langs_Languages::getInstance();
        $this->template->source_rtl = ( $lang_handler->isRTL( $this->data[ 'source' ] ) ) ? true : false ;
        $this->template->target_rtl = ( $lang_handler->isRTL( $this->data[ 'target' ] ) ) ? true : false ;

        $this->template->searchable_statuses = $this->searchableStatuses();
        $this->template->first_job_segment   = $this->data->job_first_segment ;

    }

    /**
     * @return array
     */
    private function searchableStatuses() {
        $statuses = array_merge(
                Constants_TranslationStatus::$INITIAL_STATUSES,
                Constants_TranslationStatus::$TRANSLATION_STATUSES,
                Constants_TranslationStatus::$REVISION_STATUSES
        );

        return array_map( function ( $item ) {
            return (object)array( 'value' => $item, 'label' => $item );
        }, $statuses );
    }
}


