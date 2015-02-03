<?php

//NO More needed
//include_once INIT::$UTILS_ROOT . "/API/Upload.php";
//include_once INIT::$UTILS_ROOT . "/Utils.php";

include_once INIT::$UTILS_ROOT."/engines/engine.class.php";

/**
 *
 * Create new Project on Matecat With HTTP POST ( multipart/form-data ) protocol
 *
 * POST Params:
 *
 * 'project_name'       => (string) The name of the project you want create
 * 'source_lang'        => (string) RFC 3066 language Code ( en-US )
 * 'target_lang'        => (string) RFC 3066 language(s) Code. Comma separated ( it-IT,fr-FR,es-ES )
 * 'tms_engine'         => (int)    Identifier for Memory Server ( ZERO means disabled, ONE means MyMemory )
 * 'mt_engine'          => (int)    Identifier for TM Server ( ZERO means disabled, ONE means MyMemory )
 * 'private_tm_key'     => (string) Private Key for MyMemory ( set to new to create a new one )
 *
 */
class NewController extends ajaxController {

	private $project_name;
	private $source_lang;
	private $target_lang;
	private $mt_engine;  //1 default MyMemory
	private $tms_engine;  //1 default MyMemory
	private $private_tm_key;

	private $private_tm_user = null;
	private $private_tm_pass = null;

	protected $api_output = array(
			'status' => 'FAIL',
			'message' => 'Untraceable error (sorry, not mapped)'
			);

	public function __construct() {

		//limit execution time to 300 seconds
		set_time_limit( 300 );

		parent::__construct();

		//force client to close connection, avoid UPLOAD_ERR_PARTIAL for keep-alive connections
		header("Connection: close");

		$filterArgs = array(
				'project_name'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
				'source_lang'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
				'target_lang'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
				'tms_engine'         => array( 'filter' => FILTER_VALIDATE_INT,    'flags' => FILTER_REQUIRE_SCALAR, 'options' => array( 'default' => 1, 'min_range' => 0 ) ),
				'mt_engine'          => array( 'filter' => FILTER_VALIDATE_INT,    'flags' => FILTER_REQUIRE_SCALAR, 'options' => array( 'default' => 1, 'min_range' => 0 ) ),
				'private_tm_key'     => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
				);

		$__postInput = filter_input_array( INPUT_POST, $filterArgs );

		if( !isset($__postInput[ 'tms_engine' ]) || is_null( $__postInput[ 'tms_engine' ] ) ) $__postInput[ 'tms_engine' ] = 1;
		if( !isset($__postInput[ 'mt_engine' ]) || is_null( $__postInput[ 'mt_engine' ] ) )  $__postInput[ 'mt_engine' ]  = 1;

		foreach( $__postInput as $key => $val ){
			$__postInput[$key] = urldecode( $val );
		}

		//NOTE: This is for debug purpose only,
		//NOTE: Global $_POST Overriding from CLI
		//$__postInput = filter_var_array( $_POST, $filterArgs );

		$this->project_name            = $__postInput[ 'project_name' ];
		$this->source_lang             = $__postInput[ 'source_lang' ];
		$this->target_lang             = $__postInput[ 'target_lang' ];
		$this->tms_engine              = $__postInput[ 'tms_engine' ]; // Default 1 MyMemory
		$this->mt_engine               = $__postInput[ 'mt_engine' ]; // Default 1 MyMemory
		$this->private_tm_key          = $__postInput[ 'private_tm_key' ];

		try {
			if ( $this->tms_engine != 0 ) {
				include_once INIT::$UTILS_ROOT . "/engines/tms.class.php";
				$test_valid_TMS = new TMS( $this->tms_engine );
			}
			if ( $this->mt_engine != 0 && $this->mt_engine != 1 ) {
				include_once INIT::$UTILS_ROOT . "/engines/mt.class.php";
				$test_valid_MT = new MT( $this->mt_engine );
			}
		} catch ( Exception $ex ) {
			$this->api_output[ 'message' ] = $ex->getMessage();
			Log::doLog( $ex->getMessage() );

			return -1;
		}

		//from api a key is sent and the value is 'new'
		if ( $this->private_tm_key == 'new' ) {

			try {

				$APIKeySrv = new TMSService();

				$newUser = $APIKeySrv->createMyMemoryKey();

				$this->private_tm_user = $newUser->id;
				$this->private_tm_pass = $newUser->pass;

				$this->private_tm_key = array(
						array(
								'key'  => $newUser->key,
								'name' => null,
								'r'    => true,
								'w'    => true
						)
				);

			} catch ( Exception $e ) {

				$this->api_output[ 'message' ] = 'Project Creation Failure';
				$this->api_output[ 'debug' ]   = array( "code" => $e->getCode(), "message" => $e->getMessage() );

				return -1;
			}

		} else {

			//if a string is sent, transform it into a valid array
			if ( !empty( $this->private_tm_key ) ) {
				$this->private_tm_key = array(
						array(
								'key'  => $this->private_tm_key,
								'name' => null,
								'r'    => true,
								'w'    => true
						)
				);
			} else {
				$this->private_tm_key = array();
			}

		}

		//This is only an element, this seems redundant,
		// but if we need more than a key in the next api version we can easily handle them here
		$this->private_tm_key = array_filter( $this->private_tm_key, array( "self", "sanitizeTmKeyArr" ) );

		if (empty($_FILES)) {
			$this->result['errors'][] = array("code" => -1, "message" => "Missing file. Not Sent.");
			return -1;
		}

	}

	public function finalize() {
		$toJson = json_encode( $this->api_output );
		echo $toJson;
	}

	public function doAction() {

		$uploadFile = new Upload();

		try {
			$stdResult = $uploadFile->uploadFiles( $_FILES );
		} catch( Exception $e ){
			$stdResult = array();
			$this->result = array(
					'errors' => array(
						array( "code" => -1, "message" => $e->getMessage() )
						)
					);
			$this->api_output[ 'message' ] = $e->getMessage();
		}

		$arFiles = array();
		foreach( $stdResult as $input_name => $input_value ){
			$arFiles[] = $input_value->name;
		}

		//if fileupload was failed this index ( 0 = does not exists )
		$default_project_name = @$arFiles[0];
		if (count($arFiles) > 1) {
			$default_project_name = "MATECAT_PROJ-" . date("Ymdhi");
		}

		if ( empty( $this->project_name ) ) {
			$this->project_name = $default_project_name; //'NO_NAME'.$this->create_project_name();
		}

		if ( empty( $this->source_lang ) ) {
			$this->api_output[ 'message' ] = "Missing source language." ;
			$this->result[ 'errors' ][ ] = array( "code" => -3, "message" => "Missing source language." );
		}

		if ( empty( $this->target_lang ) ) {
			$this->api_output[ 'message' ] = "Missing target language.";
			$this->result[ 'errors' ][ ] = array( "code" => -4, "message" => "Missing target language." );
		}

		//ONE OR MORE ERRORS OCCURRED : EXITING
		//for now we sent to api output only the LAST error message, but we log all
		if ( !empty( $this->result[ 'errors' ] ) ) {
			$msg = "Error \n\n " . var_export( array_merge( $this->result, $_POST ), true );
			Log::doLog( $msg );
			Utils::sendErrMailReport( $msg );
			return -1; //exit code
		}

		/* Do conversions here */
		$converter = new ConvertFileWrapper( $stdResult );
		$converter->intDir = $uploadFile->getUploadPath();
		$converter->errDir = INIT::$CONVERSIONERRORS_REPOSITORY . DIRECTORY_SEPARATOR . $uploadFile->getDirUploadToken();
		$converter->source_lang = $this->source_lang;
		$converter->target_lang = $this->target_lang;
		$converter->doAction();

	    $status = $converter->checkResult();
		if( !empty( $status ) ){
			$this->api_output[ 'message' ] = 'Project Conversion Failure';
			$this->api_output[ 'debug' ]   = $status;
			$this->result[ 'errors' ]      = $status;
			Log::doLog( $status );
			return -1;
		}
		/* Do conversions here */

		$projectManager = new ProjectManager();
		$projectStructure = $projectManager->getProjectStructure();

		$projectStructure[ 'project_name' ]      = $this->project_name;
		$projectStructure[ 'result' ]            = $this->result;
		$projectStructure[ 'private_tm_key' ]    = $this->private_tm_key;
		$projectStructure[ 'private_tm_user' ]   = $this->private_tm_user;
		$projectStructure[ 'private_tm_pass' ]   = $this->private_tm_pass;
		$projectStructure[ 'uploadToken' ]       = $uploadFile->getDirUploadToken();
		$projectStructure[ 'array_files' ]       = $arFiles; //list of file name
		$projectStructure[ 'source_language' ]   = $this->source_lang;
		$projectStructure[ 'target_language' ]   = explode( ',', $this->target_lang );
		$projectStructure[ 'mt_engine' ]         = $this->mt_engine;
		$projectStructure[ 'tms_engine' ]        = $this->tms_engine;
		$projectStructure[ 'status' ]            = Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS;
		$projectStructure[ 'skip_lang_validation' ] = true;

		$projectManager = new ProjectManager( $projectStructure );
		$projectManager->createProject();

		$this->result = $projectStructure['result'];

		if( !empty( $projectStructure['result']['errors'] ) ){
			//errors already logged
			$this->api_output['message'] = 'Project Creation Failure';
            $this->api_output[ 'debug' ] = $projectStructure['result']['errors'];

		} else {

			//everything ok
			$this->api_output[ 'status' ]       = 'OK';
			$this->api_output[ 'message' ]      = 'Success';
			$this->api_output[ 'id_project' ]   = $projectStructure['result']['id_project'];
			$this->api_output[ 'project_pass' ]    = $projectStructure['result']['ppassword'];
		}

	}

	private static function sanitizeTmKeyArr( $elem ){

		$elem = TmKeyManagement_TmKeyManagement::sanitize( new TmKeyManagement_TmKeyStruct( $elem ) );
		return $elem->toArray();

	}

}
