<?php
use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;

/**
 * Description of catController
 *
 * @author antonio
 */
class reviseSummaryController extends viewController {

	private $jid = "";
	private $project_status = "";
	private $thisUrl;

    private $data;
    private $job_stats;
    private $job_archived = false;
    private $job_owner_email;
    private $qa_data;
    private $qa_overall_text;
    private $qa_overall_avg;
    private $qa_equivalent_class;
    private $totalJobWords;
    private $password;
    private $reviseClass;

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
        $this->data = $jobData = getJobData( $this->jid, $this->password );

        $wStruct = new WordCount_Struct();
        $wStruct->setIdJob( $this->jid );
        $wStruct->setJobPassword( $this->password );
        $wStruct->setNewWords( $jobData[ 'new_words' ] );
        $wStruct->setDraftWords( $jobData[ 'draft_words' ] );
        $wStruct->setTranslatedWords( $jobData[ 'translated_words' ] );
        $wStruct->setApprovedWords( $jobData[ 'approved_words' ] );
        $wStruct->setRejectedWords( $jobData[ 'rejected_words' ] );

        if( $jobData['status'] == Constants_JobStatus::STATUS_ARCHIVED || $jobData['status'] == Constants_JobStatus::STATUS_CANCELLED ){
            //this job has been archived
            $this->job_archived = true;
            $this->job_owner_email = $jobData['job_owner'];
        }

		$this->job_stats = CatUtils::getFastStatsForJob($wStruct);

        $proj = getProject( $jobData['id_project'] );
        $this->project_status = $proj[0];

        /**
         * Retrieve information about job errors
         * ( Note: these information are fed by the revision process )
         * @see setRevisionController
         */



        $this->reviseClass = new Constants_Revise();

        $jobQA = new Revise_JobQA(
                $this->jid,
                $this->password,
                $wStruct->getTotal(),
                $this->reviseClass
        );

        $this->featureSet->loadFromUserEmail( $this->user->getEmail() );

        list( $jobQA, $this->reviseClass ) = $this->featureSet->filter( "overrideReviseJobQA", [ $jobQA, $this->reviseClass ], $this->jid,
                $this->password,
                $wStruct->getTotal() );


        $jobQA->retrieveJobErrorTotals();
        $jobVote                   = $jobQA->evalJobVote();
        $this->totalJobWords       = $wStruct->getTotal();
        $this->qa_data             = $jobQA->getQaData();
        $this->qa_overall_text     = $jobVote[ 'minText' ];
        $this->qa_overall_avg      = $jobVote[ 'avg' ];
        $this->qa_equivalent_class = $jobVote[ 'equivalent_class' ];

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

        $this->template->job_archived = ( $this->job_archived ) ? INIT::JOB_ARCHIVABILITY_THRESHOLD : '';
        $this->template->owner_email  = $this->job_owner_email;

        $this->template->jid          = $this->jid;
        $this->template->password     = $this->password;
        $this->template->pid          = $this->data['id_project'];

        $this->template->pname        = $this->project_status[ 'name' ];
        $this->template->source_code  = $this->data[ 'source' ];
        $this->template->target_code  = $this->data[ 'target' ];

        $this->template->creation_date = $this->data['create_date'];

        $this->job_stats['STATUS_BAR_NO_DISPLAY'] = ( $this->project_status['status_analysis'] == Constants_ProjectStatus::STATUS_DONE ? '' : 'display:none;' );
        $this->job_stats['ANALYSIS_COMPLETE']     = ( $this->project_status['status_analysis'] == Constants_ProjectStatus::STATUS_DONE ? true : false );
        $this->template->job_stats                = $this->job_stats;


        $this->template->build_number  = INIT::$BUILD_NUMBER;
        $this->template->extended_user = ($this->isLoggedIn() !== false ) ? trim( $this->user->fullName() ) : "";
        $this->template->logged_user   = ($this->isLoggedIn() !== false ) ? $this->user->shortName() : "";

        //set the labels
        $error_info = array(
                constant( get_class( $this->reviseClass ) . "::ERR_TYPING" )      => 'Tag issues (mismatches, whitespaces)',
                constant( get_class( $this->reviseClass ) . "::ERR_TRANSLATION" ) => 'Translation errors (mistranslation, additions/omissions)',
                constant( get_class( $this->reviseClass ) . "::ERR_TERMINOLOGY" ) => 'Terminology and translation consistency',
                constant( get_class( $this->reviseClass ) . "::ERR_LANGUAGE" )    => 'Language quality (grammar, punctuation, spelling)',
                constant( get_class( $this->reviseClass ) . "::ERR_STYLE" )       => 'Style (readability, consistent style and tone)',
        );

        $error_max_thresholds = array(
                constant( get_class( $this->reviseClass ) . "::ERR_TYPING" )      => constant( get_class( $this->reviseClass ) . "::MAX_TYPING" ),
                constant( get_class( $this->reviseClass ) . "::ERR_TRANSLATION" ) => constant( get_class( $this->reviseClass ) . "::MAX_TRANSLATION" ),
                constant( get_class( $this->reviseClass ) . "::ERR_TERMINOLOGY" ) => constant( get_class( $this->reviseClass ) . "::MAX_TERMINOLOGY" ),
                constant( get_class( $this->reviseClass ) . "::ERR_LANGUAGE" )    => constant( get_class( $this->reviseClass ) . "::MAX_QUALITY" ),
                constant( get_class( $this->reviseClass ) . "::ERR_STYLE" )       => constant( get_class( $this->reviseClass ) . "::MAX_STYLE" )
        );

        $this->template->reviseClass = $this->reviseClass;



        foreach( $this->qa_data as $k => $value ){
            $this->qa_data[ $k ][ 'text_content' ] = $error_info[ $value[ 'type' ] ];
        }

//        $nrFormatter = new NumberFormatter("en-US", NumberFormatter::DECIMAL);

        //now set the field values of the qa
        $this->template->totalJobWords         = $this->totalJobWords;
        $this->template->qa_data               = $this->qa_data;
        $this->template->qa_overall            = $this->qa_overall_text;
        $this->template->qa_overall_avg        = $this->qa_overall_avg;
        $this->template->qa_equivalent_class   = $this->qa_equivalent_class;
        $this->template->overall_quality_class = ucfirst( strtolower( str_replace( ' ', '', $this->qa_overall_text ) ) );
        $this->template->error_max_thresholds = $error_max_thresholds;
//        $this->template->word_interval = $nrFormatter->format( constant( get_class( $this->reviseClass ) . "::WORD_INTERVAL" ) );
	}
}


