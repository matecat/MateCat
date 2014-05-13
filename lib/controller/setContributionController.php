<?php
/**
 * Class engine already included in tms.class.php
 * BUT Not remove include_once INIT::$UTILS_ROOT . "/engines/engine.class.php";
 * Some PHP Version ( Ex: Debian 5.2.6-1+lenny13 does not work )
 */
include_once INIT::$UTILS_ROOT . "/engines/engine.class.php";
include_once INIT::$UTILS_ROOT . "/engines/tms.class.php";
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class setContributionController extends ajaxController {

	private $id_customer;
	private $translator_username;
	private $key = "";
	private $private_customer;
	private $private_translator;
	private $source;
	private $target;
	private $source_lang;
	private $target_lang;
	private $id_job;

    private $password;

    private $__postInput;

	public function __construct() {

        $this->disableSessions();
		parent::__construct();


        $filterArgs = array(
            'id_job'              => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'id_translator'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
            'id_customer'         => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
            'private_customer'    => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'private_translator'  => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'            => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'source'              => array( 'filter' => FILTER_UNSAFE_RAW ),
            'target'              => array( 'filter' => FILTER_UNSAFE_RAW ),
            'source_lang'         => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'target_lang'         => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$this->__postInput = filter_var_array( $_POST, $filterArgs );

        $this->id_job              = (int)$this->__postInput[ 'id_job' ];
        $this->translator_username = $this->__postInput[ 'id_translator' ];
        $this->id_customer         = $this->__postInput[ 'id_customer' ];
        $this->private_customer    = (int)$this->__postInput[ 'private_customer' ];
        $this->private_translator  = (int)$this->__postInput[ 'private_translator' ];
        $this->password            = $this->__postInput[ 'password' ];
        $this->source              = $this->__postInput[ 'source' ];
        $this->target              = $this->__postInput[ 'target' ];
        $this->source_lang         = $this->__postInput[ 'source_lang' ];
        $this->target_lang         = $this->__postInput[ 'target_lang' ];


		if (empty($this->id_customer)) {
			$this->id_customer = "Anonymous";
		}

	}

	public function doAction() {

		if (is_null($this->source) || $this->source === '') {
			$this->result['error'][] = array("code" => -1, "message" => "missing source segment");
		}

		if ( is_null($this->target) || $this->target === '' ) {
			$this->result['error'][] = array("code" => -2, "message" => "missing target segment");
		}


		if (empty($this->source_lang)) {
			$this->result['error'][] = array("code" => -3, "message" => "missing source lang");
		}

		if (empty($this->target_lang)) {
			$this->result['error'][] = array("code" => -2, "message" => "missing target lang");
		}

        if( empty($this->id_job) ){
            $this->result['error'][] = array("code" => -4, "message" => "id_job not valid");

            $msg = "\n\n Critical. Quit. \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            // critical. Quit.
            return -1;
        }

        //get Job Infos, we need only a row of jobs ( split )
        $job_data = getJobData( (int) $this->id_job, $this->password );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if( empty( $job_data ) ){
            $this->result['error'][] = array("code" => -101, "message" => "error fetching job data");
        }

        if(empty($this->result['error']) && !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ){
            $this->result['error'][] = array("code" => -10, "message" => "wrong password");
        }

        if (!empty($this->result['error'])) {
            $msg = "\n\n Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );
	        return -1;
	    }


        $config = TMS::getConfigStruct();

        $config[ 'segment' ]       = CatUtils::view2rawxliff( $this->source );
        $config[ 'translation' ]   = CatUtils::view2rawxliff( $this->target );
        $config[ 'source_lang' ]   = $this->source_lang;
        $config[ 'target_lang' ]   = $this->target_lang;
        $config[ 'email' ]         = "demo@matecat.com";
        $config[ 'id_user' ]       = $this->translator_username;

        $id_tms = $job_data[ 'id_tms' ];

		if ($id_tms != 0) {

            //if translator_username is empty no key is added to MyMemory API SET query string, so, anonymous by default

            $tms = new TMS( $id_tms );
            $result = $tms->set( $config );

            if( !$result ){
                $this->result['error'][] = array("code" => -5, "message" => "Connection Error.");
                $this->result['code'] = -1;
                $this->result['data'] = "KO";

                Log::doLog( "Set Contribution Failed." );
                Log::doLog( var_export( $_POST, true ) );

                return -1;
            }

			$this->result['code'] = 1;
			$this->result['data'] = "OK";

		} else {
			$this->result['code'] = 1;
			$this->result['data'] = "NOCONTRIB_OK";
		}

	}

}


