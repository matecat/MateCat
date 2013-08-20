<?php

include_once INIT::$UTILS_ROOT . "/engines/tms.class.php";
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class setContributionController extends ajaxcontroller {

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

        //NOTE: This is only for debug purpose only,
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
            // critical. Quit.
            return -1;
        }

        //get Job Infos
        $job_data = getJobData( (int) $this->id_job );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if( !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ){
            $this->result['error'][] = array("code" => -10, "message" => "wrong password");
        }

		if (!empty($this->result['error'])) {
			return -1;
		}

        $id_tms = $job_data[ 'id_tms' ];

		if ($id_tms != 0) {

            //if translator_username is empty no key is added to MyMemory api SET query string, so, anonymous by default

            $tms = new TMS( $id_tms );
            $result = $tms->set( $this->source, $this->target, $this->source_lang, $this->target_lang, "demo@matecat.com", $this->translator_username );

            if( !$result ){
                $this->result['error'][] = array("code" => -5, "message" => "Connection Error.");
                $this->result['code'] = -1;
                $this->result['data'] = "KO";
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


