<?php

die();

include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class setContributionMTController extends ajaxController {

	private $segment;
    private $password;
	private $translation;
	private $source_lang;
	private $target_lang;
	private $id_job;
	private $suggestion_json_array;
	private $chosen_suggestion_index;
	private $time_to_edit;
	private $mt;

	public function __construct() {

		parent::__construct();

        $filterArgs = array(
                'source'                  => array( 'filter' => FILTER_UNSAFE_RAW ),
                'target'                  => array( 'filter' => FILTER_UNSAFE_RAW ),
                'source_lang'             => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
                'target_lang'             => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
                'id_segment'              => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'id_job'                  => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'chosen_suggestion_index' => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password'                => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
                'time_to_edit'            => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
        );

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$this->__postInput = filter_var_array( $_POST, $filterArgs );

        $this->segment                 = $this->__postInput[ 'source' ];
        $this->translation             = $this->__postInput[ 'target' ];
        $this->source_lang             = $this->__postInput[ 'source_lang' ];
        $this->target_lang             = $this->__postInput[ 'target_lang' ];
        $this->id_segment              = (int)$this->__postInput[ 'id_segment' ];
        $this->id_job                  = (int)$this->__postInput[ 'id_job' ];
        $this->chosen_suggestion_index = $this->__postInput[ 'chosen_suggestion_index' ];
        $this->password                = $this->__postInput[ 'password' ];
        $this->time_to_edit            = $this->__postInput[ 'time_to_edit' ];

    }

	public function doAction() {
		if (empty($this->segment)) {
			$this->result['errors'][] = array("code" => -1, "message" => "missing source segment");
		}

		if (empty($this->translation)) {
			$this->result['errors'][] = array("code" => -2, "message" => "missing target translation");
		}


		if (empty($this->source_lang)) {
			$this->result['errors'][] = array("code" => -3, "message" => "missing source lang");
		}

		if (empty($this->target_lang)) {
			$this->result['errors'][] = array("code" => -4, "message" => "missing target lang");
		}

		if (empty($this->time_to_edit)) {
			$this->result['errors'][] = array("code" => -5, "message" => "missing time to edit");
		}

		if (empty($this->id_segment)) {
			$this->result['errors'][] = array("code" => -6, "message" => "missing segment id");
		}

        //get Job Infos, we need only a row of jobs ( split )
        $job_data = getJobData( (int) $this->id_job, $this->password );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if( empty( $job_data ) || !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ){
            $this->result['errors'][] = array( "code" => -10, "message" => "wrong password" );

            $msg = "\n\n Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            return;
        }

		//mt engine to contribute to
		if ( $job_data['id_mt_engine'] <= 1 ){
			return false;
		}

        $this->mt = Engine::getInstance( $job_data['id_mt_engine'] );

		//array of storicised suggestions for current segment
        $this->suggestion_json_array = json_decode( getArrayOfSuggestionsJSON( $this->id_segment ), true );

        //extra parameters
        $extra = json_encode(
                    array(
                        'id_segment'              => $this->id_segment,
                        'suggestion_json_array'   => $this->suggestion_json_array,
                        'chosen_suggestion_index' => $this->chosen_suggestion_index,
                        'time_to_edit'            => $this->time_to_edit
                    )
        );
        //send stuff

        $config                  = $this->mt->getConfigStruct();
        $config[ 'segment' ]     = CatUtils::view2rawxliff( $this->segment );
        $config[ 'translation' ] = CatUtils::view2rawxliff( $this->translation );
        $config[ 'source' ]      = $this->source_lang;
        $config[ 'target' ]      = $this->target_lang;
        $config[ 'email' ]       = INIT::$MYMEMORY_API_KEY;
        $config[ 'segid' ]       = $this->id_segment;
        $config[ 'extra' ]       = $extra;
        $config[ 'id_user' ]     = array("TESTKEY");


        $outcome = $this->mt->set( $config );

        if ( $outcome->error->code < 0 ) {
            $this->result[ 'errors' ] = $outcome->error->get_as_array();
        }

	}

}
