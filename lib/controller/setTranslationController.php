<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/CatUtils.php";
include_once INIT::$UTILS_ROOT . '/QA.php';
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';
include_once INIT::$UTILS_ROOT . '/Log.php';

class setTranslationController extends ajaxController {

	private $id_job;
	private $id_segment;
	private $id_translator;
	private $status;
	private $time_to_edit;
	private $translation;
	private $id_first_file;
	private $err;
	private $chosen_suggestion_index;

	public function __construct() {
        $this->disableSessions();
		parent::__construct();
        
        $this->id_job        = $this->get_from_get_post( 'id_job' );
        $this->id_segment    = $this->get_from_get_post( 'id_segment' );
        $this->id_translator = $this->get_from_get_post( 'id_translator' );
        $this->status        = strtoupper( $this->get_from_get_post( 'status' ) );
        $this->time_to_edit  = $this->get_from_get_post( 'time_to_edit' );
        $this->translation   = $this->get_from_get_post( 'translation' );
        $this->id_first_file = $this->get_from_get_post( 'id_first_file' );
        $this->err           = $this->get_from_get_post( 'errors' );
        $this->password      = $this->get_from_get_post( 'password' );

        //index of suggestions from which the translator drafted the contribution
        $this->chosen_suggestion_index = $this->get_from_get_post( 'chosen_suggestion_index' );


//        $filterArgs = array(
//            'translation'              => array( 'filter' => FILTER_UNSAFE_RAW ),
//        );
//
//        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );
//
//        Log::doLog( $_POST );
//        Log::doLog( $this->__postInput );

	}

	public function doAction() {

        switch( $this->status ) {
            case 'TRANSLATED':
            case 'APPROVED':
            case 'REJECTED':
            case 'DRAFT':
                break;
            default:
                //NO debug and NO-actions for un-mapped status
                $this->result['code'] = 1;
                $this->result['data'] = "OK";

                $msg = "Error Hack Status \n\n " . var_export( $_POST, true ) ;
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );

                return;
                break;
        }

		if (empty($this->id_segment)) {
			$this->result['error'][] = array("code" => -1, "message" => "missing id_segment");
		}

		if (empty($this->id_job)) {
			$this->result['error'][] = array("code" => -2, "message" => "missing id_job");
		} else {

            //get Job Infos, we need only a row of jobs ( split )
            $job_data = getJobData( (int)$this->id_job, $this->password );
            if ( empty( $job_data ) ) {
                $msg = "Error : empty job data \n\n " . var_export( $_POST, true ) . "\n";
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
            }

            $db = Database::obtain();
            $err   = $db->get_error();
            $errno = $err[ 'error_code' ];

            if ( $errno != 0 ) {
                $msg = "Error : empty job data \n\n " .  var_export($_POST ,true )."\n";
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
                $this->result['error'][] = array("code" => -101, "message" => "database error");
		return -1;
            }

            //add check for job status archived.
            if ( strtolower( $job_data[ 'status' ] ) == 'archived' ) {
                $this->result[ 'error' ][ ] = array( "code" => -3, "message" => "job archived" );
            }

            $pCheck = new AjaxPasswordCheck();
            //check for Password correctness
            if ( empty( $job_data ) || !$pCheck->grantJobAccessByJobData( $job_data, $this->password, $this->id_segment ) ) {
                $this->result[ 'error' ][ ] = array( "code" => -10, "message" => "wrong password" );
            }

        }

		if (empty($this->id_first_file)) {
			$this->result['error'][] = array("code" => -5, "message" => "missing id_first_file");
		}

		if (empty($this->time_to_edit)) {
			$this->time_to_edit = 0;
		}

		if ( is_null($this->translation) || $this->translation === '' ) {
            Log::doLog( "Empty Translation \n\n" . var_export( $_POST, true ) );
			return 0; // won's save empty translation but there is no need to return an error
		}

		//ONE OR MORE ERRORS OCCURRED : EXITING
		if ( !empty($this->result['error']) ) {
            $msg = "Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );
			return -1;
		}
		$this->translation = CatUtils::view2rawxliff($this->translation);

		//check tag mismatch
		//get original source segment, first
		$segment = getSegment($this->id_segment);

		//compare segment-translation and get results
        $check = new QA($segment['segment'], $this->translation);
        $check->performConsistencyCheck();

        if( $check->thereAreErrors() ){
            $err_json = $check->getErrorsJSON();
            $translation = $this->translation;
        } else {
            $err_json = '';
            $translation = $check->getTrgNormalized();


            /**
             * PATCH to discover troubles about string normalization
             * Some strange behaviours occurred about getTrgNormalized method
             * TODO remove after it has collected data??
             */
            $postCheck = new QA( $segment['segment'], $translation );
            $postCheck->performConsistencyCheck();
            if( $postCheck->thereAreErrors() ){

                $msg = "\n\n Error setTranslationController \n\n Consistency failure: \n\n Used original Translation. \n\n
                        - job id            : " . $this->id_job . "
                        - segment id        : " . $this->id_segment . "
                        - original source   : " . $segment['segment'] . "
                        - original target   : " . $this->translation . "
                        - normalized target : " . $translation;

                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );

                $translation = $this->translation;

            }

        }

        $msg = "\n\n setTranslationController \n Consistency Log:
- job id            : " . $this->id_job . "
- segment id        : " . $this->id_segment . "
- firstCheckErrors  : " . var_export( $check->getErrors(), true ) . "
- postCheckErrors   : " . ( isset($postCheck) ? var_export( $postCheck->getErrors(), true ) : 'null' );

        //Log::doLog( $msg . "\n" );


        /*
         * begin stats counter
         */
        $old_translation = getCurrentTranslation( $this->id_job, $this->id_segment );

        $old_wStruct = new WordCount_Struct();
        $old_wStruct->setIdJob( $this->id_job );
        $old_wStruct->setJobPassword( $this->password );
        $old_wStruct->setNewWords( $job_data['new_words'] );
        $old_wStruct->setDraftWords( $job_data['draft_words'] );
        $old_wStruct->setTranslatedWords( $job_data['translated_words'] );
        $old_wStruct->setApprovedWords( $job_data['approved_words'] );
        $old_wStruct->setRejectedWords( $job_data['rejected_words'] );

        $old_wStruct->setIdSegment( $this->id_segment );

        //redundant, this is made into WordCount_Counter::updateDB
        $old_wStruct->setOldStatus( $old_translation['status'] );
        $old_wStruct->setNewStatus( $this->status );

        //redundant because the update is made only where status = old status
        if( $this->status != $old_translation['status'] ){

            //cambiato status, sposta i conteggi
            $old_count = ( !empty( $old_translation['eq_word_count'] ) ? $old_translation['eq_word_count'] : $segment['raw_word_count'] );

            $counter = new WordCount_Counter( $old_wStruct );
            $counter->setOldStatus( $old_translation['status'] );
            $counter->setNewStatus( $this->status );
            $newValues = $counter->getUpdatedValues( $old_count );

            /**
             * WARNING: THIS CHANGE THE STATUS OF SEGMENT_TRANSLATIONS ALSO
             *
             * Needed because of duplicated setTranslationsController calls for the same segment
             *   ( The second call fails for status in where condition )
             *
             */
            $newTotals = $counter->updateDB( $newValues );

        } else {
            $newTotals = $old_wStruct;
        }
        $_Translation                            = array();
        $_Translation[ 'id_segment' ]            = $this->id_segment;
        $_Translation[ 'id_job' ]                = $this->id_job;
        $_Translation[ 'status' ]                = $this->status;
        $_Translation[ 'time_to_edit' ]          = $this->time_to_edit;
        $_Translation[ 'translation' ]           = preg_replace( '/[ \t\n\r\0\x0A\xA0]+$/u', '', $translation );
        $_Translation[ 'serialized_errors_list' ] = $err_json;
        $_Translation[ 'suggestion_position' ]   = $this->chosen_suggestion_index;
        $_Translation[ 'warning' ]               = $check->thereAreErrors();
        $_Translation[ 'translation_date' ]      = date( "Y-m-d H:i:s" );

        /**
         * when the status of the translation changes, the auto propagation flag
         * must be removed
         */
        if( $_Translation[ 'translation' ] != $old_translation['translation'] || $this->status == 'TRANSLATED' || $this->status == 'APPROVED' ){
            $_Translation[ 'autopropagated_from' ] = 'NULL';
        }

        $res = CatUtils::addSegmentTranslation( $_Translation );

        if (!empty($res['error'])) {
            $this->result['error'] = $res['error'];

            $msg = "\n\n Error addSegmentTranslation \n\n Database Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            return -1;
        }

        //propagate translations
        $TPropagation                             = array();
        $TPropagation[ 'id_job' ]                 = $this->id_job;
        $TPropagation[ 'status' ]                 = 'DRAFT';
        $TPropagation[ 'translation' ]            = $translation;
        $TPropagation[ 'autopropagated_from' ]    = $this->id_segment;
        $TPropagation[ 'serialized_errors_list' ] = $err_json;
        $TPropagation[ 'warning' ]                = $check->thereAreErrors();
        $TPropagation[ 'translation_date' ]       = date( "Y-m-d H:i:s" );
        $TPropagation[ 'segment_hash' ]           = $old_translation[ 'segment_hash' ];

        if( $this->status == 'TRANSLATED' ){

            try {
                propagateTranslation( $TPropagation, $job_data );
            } catch ( Exception $e ){
                $msg = $e->getMessage() . "\n\n" . $e->getTraceAsString();
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
            }

        }

//		$job_stats = CatUtils::getStatsForJob($this->id_job, null, $this->password);
		$job_stats = CatUtils::getFastStatsForJob( $newTotals );
        $project = getProject( $job_data['id_project'] );
        $project = array_pop( $project );
        $job_stats['ANALYSIS_COMPLETE'] = ( $project['status_analysis'] == 'DONE' ? true : false );

		//$file_stats = CatUtils::getStatsForFile($this->id_first_file); //Removed .. HEAVY query, client don't need these info at moment

        $file_stats = array();

		$is_completed = ($job_stats['TRANSLATED_PERC'] == '100') ? 1 : 0;

		$update_completed = setJobCompleteness($this->id_job, $is_completed);

        if ( $update_completed < 0 ) {
            $msg = "\n\n Error setJobCompleteness \n\n " . var_export( $_POST, true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );
        }

		$this->result['stats'] = $job_stats;
		$this->result['file_stats'] = $file_stats;
		$this->result['code'] = 1;
		$this->result['data'] = "OK";

                /* FIXME: added for code compatibility with front-end. Remove. */
                $_warn = $check->getWarnings();
                $warning = $_warn[0];
                /* */

		$this->result['warning']['cod']=$warning->outcome;
		if($warning->outcome>0){
			$this->result['warning']['id']=$this->id_segment;
		} else {
			$this->result['warning']['id']=0;
		}

	}

}
