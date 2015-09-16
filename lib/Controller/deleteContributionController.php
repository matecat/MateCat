<?php

class deleteContributionController extends ajaxController {

    private $seg;
    private $tra;
    private $source_lang;
    private $target_lang;
    private $id_translator;
    private $password;
    private $tm_keys;

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
                'source_lang'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'target_lang'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'seg'            => array( 'filter' => FILTER_UNSAFE_RAW ),
                'tra'            => array( 'filter' => FILTER_UNSAFE_RAW ),
                'id_translator'  => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'password'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
                'id_job'         => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->source_lang   = $__postInput[ 'source_lang' ];
        $this->target_lang   = $__postInput[ 'target_lang' ];
        $this->source        = trim( $__postInput[ 'seg' ] );
        $this->target        = trim( $__postInput[ 'tra' ] );
        $this->id_translator = trim( $__postInput[ 'id_translator' ] ); //no more used
        $this->password      = trim( $__postInput[ 'password' ] );
        $this->id_job        = $__postInput[ 'id_job' ];

    }

    public function doAction() {


        if ( empty( $this->source_lang ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -1, "message" => "missing source_lang" );
        }

        if ( empty( $this->target_lang ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -2, "message" => "missing target_lang" );
        }

        if ( empty( $this->source ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -3, "message" => "missing source" );
        }

        if ( empty( $this->target ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -4, "message" => "missing target" );
        }

        //get Job Infos
        $job_data = getJobData( (int) $this->id_job, $this->password );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if( empty( $job_data ) || !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ){
            $this->result[ 'errors' ][ ] = array( "code" => -10, "message" => "wrong password" );
            return;
        }

        $this->tm_keys      = $job_data[ 'tm_keys' ];
        $this->checkLogin();

        $tms = Engine::getInstance( $job_data['id_tms'] );
        $config = $tms->getConfigStruct();
//        $config = TMS::getConfigStruct();

        $config[ 'segment' ]       = CatUtils::view2rawxliff( $this->source );
        $config[ 'translation' ]   = CatUtils::view2rawxliff( $this->target );
        $config[ 'source' ]        = $this->source_lang;
        $config[ 'target' ]        = $this->target_lang;
        $config[ 'email' ]         = INIT::$MYMEMORY_API_KEY;
        $config[ 'id_user' ]       = array();

        //get job's TM keys
        try{

            $tm_keys = $this->tm_keys;

            if ( self::isRevision() ) {
                $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
            } elseif( $this->userMail == $job_data['owner'] ){
                $tm_keys = TmKeyManagement_TmKeyManagement::getOwnerKeys( array($tm_keys), 'r', 'tm' );
                $tm_keys = json_encode( $tm_keys );
            }

            //get TM keys with read grants
            $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $tm_keys, 'r', 'tm', $this->uid, $this->userRole );

            if ( is_array( $tm_keys ) && !empty( $tm_keys ) ) {
                foreach ( $tm_keys as $tm_key ) {
                    $config[ 'id_user' ][ ] = $tm_key->key;
                }
            }

        }
        catch(Exception $e){
            $this->result[ 'errors' ][ ] = array( "code" => -11, "message" => "Cannot retrieve TM keys info." );
            return;
        }

        //prepare the errors report
        $set_code = array();

        /**
         * @var $tm_key TmKeyManagement_TmKeyStruct
		 */

		//if there's no key
		if(empty($tm_keys)){
			//try deleting anyway, it may be a public segment and it may work
			$TMS_RESULT = $tms->delete( $config );
			$set_code[ ] = $TMS_RESULT;
		}else{
			//loop over the list of keys
			foreach ( $tm_keys as $tm_key ) {
				//issue a separate call for each key
				$config[ 'id_user' ] = $tm_key->key;
				$TMS_RESULT = $tms->delete( $config );
				$set_code[ ] = $TMS_RESULT;
			}
		}

		$set_successful = true;
		if( array_search( false, $set_code, true ) ){
			//There's an errors
			$set_successful = false;
		}

		$this->result[ 'data' ] = ( $set_successful ? "OK" : null );
		$this->result[ 'code' ] = $set_successful;

	}


}

?>
