<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$UTILS_ROOT . '/QA.php';
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';
include_once INIT::$UTILS_ROOT . '/log.class.php';

class setTranslationController extends ajaxcontroller {

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
            $job_data = getJobData( (int) $this->id_job, $this->password );

            //add check for job status archived.
            if( strtolower( $job_data['status'] ) == 'archived' ){
                $this->result['error'][] = array("code" => -3, "message" => "job archived");
            }

            $pCheck = new AjaxPasswordCheck();
            //check for Password correctness
            if( !$pCheck->grantJobAccessByJobData( $job_data, $this->password, $this->id_segment ) ){
                $this->result['error'][] = array("code" => -10, "message" => "wrong password");
            }

        }

		if (empty($this->id_first_file)) {
			$this->result['error'][] = array("code" => -5, "message" => "missing id_first_file");
		}

		if (empty($this->time_to_edit)) {
			$this->time_to_edit = 0;
		}

		if (empty($this->status)) {
			$this->status = 'DRAFT';
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

        Log::doLog( $msg . "\n" );


        $res = CatUtils::addSegmentTranslation($this->id_segment, $this->id_job, $this->status, $this->time_to_edit, $translation, $err_json,$this->chosen_suggestion_index, $check->thereAreErrors() );

        if (!empty($res['error'])) {
			$this->result['error'] = $res['error'];

            $msg = "\n\n Error addSegmentTranslation \n\n Database Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

			return -1;
		}

		$job_stats = CatUtils::getStatsForJob($this->id_job, null, $this->password);
		$file_stats = CatUtils::getStatsForFile($this->id_first_file);

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
