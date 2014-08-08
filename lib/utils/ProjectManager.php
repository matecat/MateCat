<?php
/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 22/10/13
 * Time: 17.25
 *
 */
include_once INIT::$UTILS_ROOT . "/xliff.parser.1.3.class.php";
include_once INIT::$UTILS_ROOT . "/DetectProprietaryXliff.php";

class ProjectManager {

	/**
	 * @var ArrayObject|RecursiveArrayObject
	 */
	protected $projectStructure;

	protected $mysql_link;

	protected $tmxServiceWrapper;

	protected $checkTMX;
	/*
	   flag used to indicate TMX check status: 
	   0-not to check, or check passed
	   1-still checking, but no useful TM for this project have been found, so far (no one matches this project langpair)
	 */

	protected $langService;

	public function __construct( ArrayObject $projectStructure = null ){

		if ( $projectStructure == null ) {
			$projectStructure = new RecursiveArrayObject(
				array(
					'id_project'         => null,
					'id_customer'        => null,
					'user_ip'            => null,
					'project_name'       => null,
					'result'             => null,
					'private_tm_key'     => 0,
					'private_tm_user'    => null,
					'private_tm_pass'    => null,
					'uploadToken'        => null,
					'array_files'        => array(), //list of file names
					'file_id_list'       => array(),
					'file_references'    => array(),
					'source_language'    => null,
					'target_language'    => null,
					'mt_engine'          => null,
					'tms_engine'         => null,
					'ppassword'          => null,
					'array_jobs'         => array( 'job_list' => array(), 'job_pass' => array(),'job_segments' => array() ),
					'job_segments'       => array(), //array of job_id => array( min_seg, max_seg )
					'segments'           => array(), //array of files_id => segmentsArray()
					'translations'       => array(), //one translation for every file because translations are files related
					'query_translations' => array(),
					'status'             => Constants_ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS,
					'job_to_split'       => null,
					'job_to_split_pass'  => null,
					'split_result'       => null,
					'job_to_merge'       => null,
					'lang_detect_files'  => array()
				) );
		}

		$this->projectStructure = $projectStructure;

		//get the TMX management component from the factory
		$this->tmxServiceWrapper=TMSServiceFactory::getTMXService($this->projectStructure['tms_engine']);

		$this->langService=Languages::getInstance();

		$this->checkTMX=0;

		$mysql_hostname = INIT::$DB_SERVER;   // Database Server machine
		$mysql_database = INIT::$DB_DATABASE;     // Database Name
		$mysql_username = INIT::$DB_USER;   // Database User
		$mysql_password = INIT::$DB_PASS;

		$this->mysql_link = mysql_connect($mysql_hostname, $mysql_username, $mysql_password);
		mysql_select_db($mysql_database, $this->mysql_link);

	}

	public function getProjectStructure(){
		return $this->projectStructure;
	}

	private function sortByStrLenAsc($a, $b){
		return strlen($a) >= strlen($b);
	}

	public function createProject() {

		// project name sanitize
		$oldName = $this->projectStructure['project_name'];
		$this->projectStructure['project_name'] = $this->_sanitizeName( $this->projectStructure['project_name'] );
		if( $this->projectStructure['project_name'] == false ){
			$this->projectStructure['result'][ 'errors' ][ ] = array( "code" => -5, "message" => "Invalid Project Name " . $oldName . ": it should only contain numbers and letters!" );
			return false;
		}

		// create project
		$this->projectStructure['ppassword']   = $this->_generatePassword();
		$this->projectStructure['user_ip']     = Utils::getRealIpAddr();
		$this->projectStructure['id_customer'] = 'translated_user';

		$this->projectStructure['id_project'] = insertProject( $this->projectStructure );

		//create user (Massidda 2013-01-24)
		//this is done only if an API key is provided
		if ( !empty( $this->projectStructure['private_tm_key'] ) ) {

			$APIKeySrv = TMSServiceFactory::getAPIKeyService();

			try {

				if( !$APIKeySrv->checkCorrectKey( $this->projectStructure['private_tm_key'] ) ){
					throw new Exception( "Error: The TM private key provided is not valid.", -3 );
				}

			} catch ( Exception $e ){
				$this->projectStructure['result']['errors'][] = array( "code" => $e->getCode(), "message" => $e->getMessage() );
				return false;
			}

			//the base case is when the user clicks on "generate private TM" button:
			//a (user, pass, key) tuple is generated and can be inserted
			//if it comes with it's own key without querying the creation API, create a (key,key,key) user
			if ( empty( $this->projectStructure['private_tm_user'] ) ) {
				$this->projectStructure['private_tm_user'] = $this->projectStructure['private_tm_key'];
				$this->projectStructure['private_tm_pass'] = $this->projectStructure['private_tm_key'];
			}

			insertTranslator( $this->projectStructure );

		}


		//sort files in order to process TMX first
		$sortedFiles=array();
		foreach ( $this->projectStructure['array_files'] as $fileName ) {
			if('tmx'== pathinfo($fileName, PATHINFO_EXTENSION)){
				//found TMX, enable language checking routines
				$this->checkTMX=1;
				array_unshift($sortedFiles,$fileName);
			}else{
				array_push($sortedFiles,$fileName);
			}

		}
		$this->projectStructure['array_files']=$sortedFiles;
		unset($sortedFiles);


		$uploadDir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $this->projectStructure['uploadToken'];
		foreach ( $this->projectStructure['array_files'] as $fileName ) {

			//if TMX,
			if('tmx'== pathinfo($fileName, PATHINFO_EXTENSION)){
				//import the TMX, the check is deferred after this loop
				log::doLog("loading \"$fileName\"");
				$import_outcome=$this->tmxServiceWrapper->import("$uploadDir/$fileName",$this->projectStructure['private_tm_key']);
				if('400'==$import_outcome['responseStatus']){
					$this->projectStructure['result']['errors'][] = array( "code" => -15, "message" => "Cant't load TMX files right now, try later" );
					return false;
				}
				if('403'==$import_outcome["responseStatus"]){
					$this->projectStructure['result']['errors'][] = array( "code" => -15, "message" => "Invalid key provided (".$this->projectStructure['private_tm_key'].")");
					return false;
				}

				//in any case, skip the rest of the loop, go to the next file
				continue;
			}

			/**
			 * Conversion Enforce
			 *
			 * we have to know if a file can be found in _converted directory
			 *
			 * Check Extension no more sufficient, we want check content
			 * if this is an idiom xlf file type, conversion are enforced
			 * $enforcedConversion = true; //( if conversion is enabled )
			 */
			$isAConvertedFile = true;
			try {

				$fileType = DetectProprietaryXliff::getInfo( INIT::$UPLOAD_REPOSITORY. DIRECTORY_SEPARATOR . $this->projectStructure['uploadToken'].DIRECTORY_SEPARATOR . $fileName );

				if ( DetectProprietaryXliff::isXliffExtension() ) {

					if ( INIT::$CONVERSION_ENABLED ) {

						//conversion enforce
						if ( !INIT::$FORCE_XLIFF_CONVERSION ) {

							//ONLY IDIOM is forced to be converted
							//if file is not proprietary like idiom AND Enforce is disabled
							//we take it as is
							if( !$fileType[ 'proprietary' ] ) {
								$isAConvertedFile = false;
								//ok don't convert a standard sdlxliff
							}

						} else {

							//if conversion enforce is active
							//we force all xliff files but not files produced by SDL Studio because we can handle them
							if( $fileType['proprietary_short_name'] == 'trados' ) {
								$isAConvertedFile = false;
								//ok don't convert a standard sdlxliff

							}

						}

					} elseif ( $fileType[ 'proprietary' ] ) {

						/**
						 * Application misconfiguration.
						 * upload should not be happened, but if we are here, raise an error.
						 * @see upload.class.php
						 * */
						$this->projectStructure['result']['errors'][] = array("code" => -8, "message" => "Proprietary xlf format detected. Not able to import this XLIFF file. ($fileName)");
						setcookie("upload_session", "", time() - 10000);
						return -1;
						//stop execution

					} elseif ( !$fileType[ 'proprietary' ] ) {
						$isAConvertedFile = false;
						//ok don't convert a standard sdlxliff
					}

				}

			} catch ( Exception $e ) { Log::doLog( $e->getMessage() ); }


			$mimeType = pathinfo( $fileName , PATHINFO_EXTENSION );

			$original_content = "";

			/*
			   if it's not one of the listed formats (or it is, but you had to convert it anyway), 
			   and conversion is enabled in first place
			 */
			if ( $isAConvertedFile ) {

				//converted file is inside "_converted" directory
				$fileDir          = $uploadDir . '_converted';
				$original_content = file_get_contents( "$uploadDir/$fileName" );
				$sha1_original    = sha1( $original_content );
				$original_content = gzdeflate( $original_content, 5 );

				//file name is a xliff converted like: 'a_word_document.doc.sdlxliff'
				$real_fileName = $fileName . '.sdlxliff';

			} else {

				//filename is already an xliff and it is in a canonical normal directory
				$sha1_original = "";
				$fileDir = $uploadDir;
				$real_fileName = $fileName;
			}

			$filePathName = $fileDir . DIRECTORY_SEPARATOR . $real_fileName;

			if ( !file_exists( $filePathName ) ) {
				$this->projectStructure[ 'result' ][ 'errors' ][ ] = array( "code" => -6, "message" => "File not found on server after upload." );
			}

			$contents = file_get_contents($filePathName);

			try {

				$fid = insertFile( $this->projectStructure, $fileName, $mimeType, $contents, $sha1_original, $original_content );
				$this->projectStructure[ 'file_id_list' ]->append( $fid );

				$this->_extractSegments( $filePathName, $fid );

				//Log::doLog( $this->projectStructure['segments'] );

			} catch ( Exception $e ){

				if ( $e->getCode() == -1 ) {
					$this->projectStructure['result']['errors'][] = array("code" => -1, "message" => "No text to translate in the file $fileName.");
				} elseif( $e->getCode() == -2 ) {
					$this->projectStructure['result']['errors'][] = array("code" => -7, "message" => "Failed to store segments in database for $fileName");
				} elseif( $e->getCode() == -3 ) {
					$this->projectStructure['result']['errors'][] = array("code" => -7, "message" => "File $fileName not found. Failed to save XLIFF conversion on disk");
				} elseif( $e->getCode() == -4 ) {
					$this->projectStructure['result']['errors'][] = array("code" => -7, "message" => "Internal Error. Xliff Import: Error parsing. ( $fileName )");
				} elseif( $e->getCode() == -11 ) {
					$this->projectStructure['result']['errors'][] = array("code" => -7, "message" => "Failed to store reference files on disk. Permission denied");
				} elseif( $e->getCode() == -12 ) {
					$this->projectStructure['result']['errors'][] = array("code" => -7, "message" => "Failed to store reference files in database");
				} else {
					//mysql insert Blob Error
					$this->projectStructure['result']['errors'][] = array("code" => -7, "message" => "File is Too large. ( $fileName )");
				}

				Log::doLog( $e->getMessage() );

			}
			//exit;
		}

		//check if the files language equals the source language. If not, set an error message.
		$this->validateFilesLanguages();

		/****************/
		//loop again through files to check to check for TMX loading
		foreach ( $this->projectStructure['array_files'] as $fileName ) {

			//if TMX, 
			if('tmx'== pathinfo($fileName, PATHINFO_EXTENSION)){
				//is the TM loaded?
				$loaded=false;

				//wait until current TMX is loaded
				while(!$loaded){
					//now we repeatedly scan the list of loaded TMs
					//this counter is used to get the latest TM in case of duplicates 
					$tmx_max_id=0;

					//check if TM has been loaded
					$allMemories=$this->tmxServiceWrapper->getStatus($this->projectStructure['private_tm_key'],$fileName);

					if("200"!=$allMemories['responseStatus'] or 0==count($allMemories['responseData']['tm'])){
						//what the hell? No memories although I've just loaded some? Eject!
						$this->projectStructure['result']['errors'][] = array( "code" => -15, "message" => "Cant't load TMX files right now, try later" );
						return false;

					}

					//scan through memories 
					foreach ( $allMemories[ 'responseData' ][ 'tm' ] as $memory ) {
						//obtain max id
						$tmx_max_id = max( $tmx_max_id, $memory[ 'id' ] );

						//if maximum is current, pick it (it means that, among duplicates, it's the latest)
						if ( $tmx_max_id == $memory[ 'id' ] ) {
							$current_tm = $memory;
						}
					}

					switch($current_tm['status']){
						case "0":
							//wait for the daemon to process it 
							//THIS IS WRONG BY DESIGN, WE SHOULD NOT ACT AS AN ASYNCH DAEMON WHILE WE ARE IN A SYNCH APACHE PROCESS
							log::doLog("waiting for \"$fileName\" to be loaded into MyMemory");
							sleep(3);
							break;
						case "1":
							//loaded (or error, in any case go ahead)
							log::doLog("\"$fileName\" has been loaded into MyMemory");
							$loaded=true;
							break;
						default:
							$this->projectStructure['result']['errors'][] = array( "code" => -14, "message" => "Invalid TMX ($fileName)" );
							return false;
							break;
					}

				}

				//once the language is loaded, check if language is compliant (unless something useful has already been found)
				if(1==$this->checkTMX){
					//get localized target languages of TM (in case it's a multilingual TM)
					$tmTargets=explode(';',$current_tm['target_lang']);

					//indicates if something has been found for current memory
					$found=false;

					//compare localized target languages array (in case it's a multilingual project) to the TM supplied
					//if nothing matches, then the TM supplied can't have matches for this project
					foreach($this->projectStructure['target_language'] as $projectTarget){
						if(in_array($this->langService->getLocalizedName($projectTarget),$tmTargets)){
							$found=true;
							break;
						}
					}

					//if this TM matches the project lagpair and something has been found
					if($found and $current_tm['source_lang']==$this->langService->getLocalizedName($this->projectStructure['source_language'])){
						//the TMX is good to go
						$this->checkTMX=0;
					}
				}
			}
		}

		if(1==$this->checkTMX){
			//this means that noone of uploaded TMX were usable for this project. Warn the user.
			$this->projectStructure['result']['errors'][] = array( "code" => -16, "message" => "No usable segment found in TMXs for the language pairs of this project" );
			return false;
		}

		if( !empty( $this->projectStructure['result']['errors'] ) ){
			Log::doLog( "Project Creation Failed. Sent to Output all errors." );
			Log::doLog( $this->projectStructure['result']['errors'] );

			return false;
		}

		//Log::doLog( array_pop( array_chunk( $SegmentTranslations[$fid], 25, true ) ) );
		//create job

		if (isset($_SESSION['cid']) and !empty($_SESSION['cid'])) {
			$owner = $_SESSION['cid'];
		} else {
			$_SESSION['_anonym_pid'] = $this->projectStructure['id_project'];
			//default user
			$owner = '';
		}


		$isEmptyProject = false;
		//Throws exception
		try {
			$this->_createJobs( $this->projectStructure, $owner );

			//FIXME for project with pre translation this query is not enough,
			//we need compare the number of segments with translations, but take an eye to the opensource

			$query_visible_segments = "SELECT count(*) as cattool_segments
				FROM segments WHERE id_file IN ( %s ) and show_in_cattool = 1";

			$string_file_list = implode( "," , $this->projectStructure['file_id_list']->getArrayCopy() );
			$query_visible_segments = sprintf( $query_visible_segments, $string_file_list );

			$res = mysql_query( $query_visible_segments, $this->mysql_link );

			if ( !$res ) {
				Log::doLog("Segment Search: Failed Retrieve min_segment/max_segment for files ( $string_file_list ) - DB Error: " . mysql_error() . " - \n");
				throw new Exception( "Segment Search: Failed Retrieve min_segment/max_segment for job", -5);
			}

			$rows = mysql_fetch_assoc( $res );

			if ( $rows['cattool_segments'] == 0  ) {
				Log::doLog("Segment Search: No segments in this project - \n");
				$isEmptyProject = true;
			}

		} catch ( Exception $ex ){
			$this->projectStructure['result']['errors'][] = array( "code" => -9, "message" => "Fail to create Job. ( {$ex->getMessage()} )" );
			return false;
		}

		self::_deleteDir( $uploadDir );
		if ( is_dir( $uploadDir . '_converted' ) ) {
			self::_deleteDir( $uploadDir . '_converted' );
		}

		$this->projectStructure['status'] = ( INIT::$VOLUME_ANALYSIS_ENABLED ) ? Constants_ProjectStatus::STATUS_NEW : Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE;
		if( $isEmptyProject ){
			$this->projectStructure['status'] = Constants_ProjectStatus::STATUS_EMPTY;
		}

		changeProjectStatus( $this->projectStructure['id_project'], $this->projectStructure['status'] );
		$this->projectStructure['result'][ 'code' ]            = 1;
		$this->projectStructure['result'][ 'data' ]            = "OK";
		$this->projectStructure['result'][ 'ppassword' ]       = $this->projectStructure['ppassword'];
		$this->projectStructure['result'][ 'password' ]        = $this->projectStructure['array_jobs']['job_pass'];
		$this->projectStructure['result'][ 'id_job' ]          = $this->projectStructure['array_jobs']['job_list'];
		$this->projectStructure['result'][ 'job_segments' ]    = $this->projectStructure['array_jobs']['job_segments'];
		$this->projectStructure['result'][ 'id_project' ]      = $this->projectStructure['id_project'];
		$this->projectStructure['result'][ 'project_name' ]    = $this->projectStructure['project_name'];
		$this->projectStructure['result'][ 'source_language' ] = $this->projectStructure['source_language'];
		$this->projectStructure['result'][ 'target_language' ] = $this->projectStructure['target_language'];
		$this->projectStructure['result'][ 'status' ]          = $this->projectStructure['status'];
		$this->projectStructure['result'][ 'lang_detect']       = $this->projectStructure['lang_detect_files'];
//	var_dump($this->projectStructure);
//		exit;
	}

	protected function _createJobs( ArrayObject $projectStructure, $owner ) {

		foreach ( $projectStructure['target_language'] as $target ) {

			$query_min_max = "SELECT MIN( id ) AS job_first_segment , MAX( id ) AS job_last_segment
				FROM segments WHERE id_file IN ( %s )";

			$string_file_list = implode( "," , $projectStructure['file_id_list']->getArrayCopy() );
			$last_segments_query = sprintf( $query_min_max, $string_file_list );
			$res = mysql_query( $last_segments_query, $this->mysql_link );

			if ( !$res || mysql_num_rows( $res ) == 0 ) {
				Log::doLog("Segment Search: Failed Retrieve min_segment/max_segment for files ( $string_file_list ) - DB Error: " . mysql_error() . " - \n");
				throw new Exception( "Segment import - DB Error: " . mysql_error(), -5);
			}

			//IT IS EVERY TIME ONLY A LINE!! don't worry about a cycle
			$job_segments = mysql_fetch_assoc( $res );

			$password = $this->_generatePassword();
			$jid = insertJob( $projectStructure, $password, $target, $job_segments, $owner );
			$projectStructure['array_jobs']['job_list']->append( $jid );
			$projectStructure['array_jobs']['job_pass']->append( $password );
			$projectStructure['array_jobs']['job_segments']->offsetSet( $jid . "-" . $password, $job_segments );

			foreach ( $projectStructure['file_id_list'] as $fid ) {

				try {
					//prepare pre-translated segments queries
					if( !empty( $projectStructure['translations'] ) ){
						$this->_insertPreTranslations( $jid );
					}
				} catch ( Exception $e ) {
					$msg = "\n\n Error, pre-translations lost, project should be re-created. \n\n " . var_export( $e->getMessage(), true );
					Utils::sendErrMailReport($msg);
				}

				insertFilesJob($jid, $fid);

			}

		}

	}

	private function validateFilesLanguages(){
		/**
		 * @var $filesSegments RecursiveArrayObject
		 */
		$filesSegments = $this->projectStructure['segments'];

		/**
		 * This is a map <file_name, check_result>, where check_result is one
		 * of these status strings:<br/>
		 * - ok         --> the language detected for this file is the same of source language<br/>
		 * - warning    --> the language detected for this file is different from the source language
		 *
		 * @var $filename2SourceLangCheck array
		 */
		$filename2SourceLangCheck = array();

		//istantiate MyMemory analyzer and detect languages for each file uploaded
		$mma = new MyMemoryAnalyzer();
		$res = $mma->detectLanguage($filesSegments, $this->projectStructure['lang_detect_files']);

		//for each language detected, check if it's not equal to the source language
		$langsDetected = $res['responseData']['translatedText'];
		if($res !== null &&
			is_array($langsDetected) &&
			count($langsDetected) == count($this->projectStructure['array_files'])) {

			$counter = 0;
			foreach($langsDetected as $fileLang){
				$currFileName = $this->projectStructure['array_files'][$counter];

				//get language code
				$sourceLang = array_shift( explode ( "-", $this->projectStructure['source_language']) );

				//get extended language name using google language code
				$languageExtendedName = langs_GoogleLanguageMapper::getLanguageCode( $fileLang );

				//get extended language name using standard language code
				$langClass = Languages::getInstance();
				$sourceLanguageExtendedName = strtolower( $langClass->getLocalizedName($sourceLang) );

				//Check job's detected language. In case of undefined language, mark it as valid
				if($fileLang !== 'und' &&
					$fileLang != $sourceLang &&
					$sourceLanguageExtendedName != $languageExtendedName){

					$filename2SourceLangCheck[$currFileName] = 'warning';

					$languageExtendedName= ucfirst($languageExtendedName);

					$this->projectStructure['result']['errors'][] = array(
						"code"      => -17,
						"message"   => "The source language you selected seems ".
                                        "to be different from the source language in \"$currFileName\". Please check."
					);
				}
				else{
					$filename2SourceLangCheck[$currFileName] = 'ok';
				}

				$counter++;
			}

			if(in_array("warning", array_values( $filename2SourceLangCheck ) ) ){
				$this->projectStructure['result'][ 'lang_detect' ] = $filename2SourceLangCheck;
			}
		}
		else{
			//There are errors while parsing JSON.
			//Noop
		}
	}

	/**
	 *
	 * Build a job split structure, minimum split value are 2 chunks
	 *
	 * @param ArrayObject $projectStructure
	 * @param int         $num_split
	 * @param array       $requestedWordsPerSplit Matecat Equivalent Words ( Only valid for Pro Version )
	 *
	 * @return RecursiveArrayObject
	 *
	 * @throws Exception
	 */
	public function getSplitData( ArrayObject $projectStructure, $num_split = 2, $requestedWordsPerSplit = array() ) {

		$num_split = (int)$num_split;

		if( $num_split < 2 ){
			throw new Exception( 'Minimum Chunk number for split is 2.', -2 );
		}

		if( !empty( $requestedWordsPerSplit ) && count($requestedWordsPerSplit) != $num_split ){
			throw new Exception( "Requested words per chunk and Number of chunks not consistent.", -3 );
		}

		if( !empty( $requestedWordsPerSplit ) && !INIT::$VOLUME_ANALYSIS_ENABLED ){
			throw new Exception( "Requested words per chunk available only for Matecat PRO version", -4 );
		}

		/**
		 * Select all rows raw_word_count and eq_word_count
		 * and their totals ( ROLLUP )
		 * reserve also two columns for job_first_segment and job_last_segment
		 *
		 * +----------------+-------------------+---------+-------------------+------------------+
		 * | raw_word_count | eq_word_count     | id      | job_first_segment | job_last_segment |
		 * +----------------+-------------------+---------+-------------------+------------------+
		 * |          26.00 |             22.10 | 2390662 |           2390418 |          2390665 |
		 * |          30.00 |             25.50 | 2390663 |           2390418 |          2390665 |
		 * |          48.00 |             40.80 | 2390664 |           2390418 |          2390665 |
		 * |          45.00 |             38.25 | 2390665 |           2390418 |          2390665 |
		 * |        3196.00 |           2697.25 |    NULL |           2390418 |          2390665 |  -- ROLLUP ROW
		 * +----------------+-------------------+---------+-------------------+------------------+
		 *
		 */
		$query = "SELECT
			SUM( raw_word_count ) AS raw_word_count,
			SUM(eq_word_count) AS eq_word_count,
			job_first_segment, job_last_segment, s.id
				FROM segments s
				LEFT  JOIN segment_translations st ON st.id_segment = s.id
				INNER JOIN jobs j ON j.id = st.id_job
				WHERE s.id BETWEEN j.job_first_segment AND j.job_last_segment
				AND j.id = %u
				AND j.password = '%s'
				GROUP BY s.id WITH ROLLUP";

		$query = sprintf( $query,
			$projectStructure[ 'job_to_split' ],
			$projectStructure[ 'job_to_split_pass' ]
		);

		$res   = mysql_query( $query, $this->mysql_link );

		//assignment in condition is often dangerous, deprecated
		while ( ( $rows[] = mysql_fetch_assoc( $res ) ) != false );
		array_pop( $rows ); //destroy last assignment row ( every time === false )

		if( empty( $rows ) ){
			throw new Exception( 'No segments found for job ' . $projectStructure[ 'job_to_split' ], -5 );
		}

		$row_totals     = array_pop( $rows ); //get the last row ( ROLLUP )
		unset($row_totals['id']);

		if( empty($row_totals['job_first_segment']) || empty($row_totals['job_last_segment']) ){
			throw new Exception('Wrong job id or password. Job segment range not found.', -6);
		}

		//if fast analysis with equivalent word count is present
		$count_type    = ( !empty( $row_totals[ 'eq_word_count' ] ) ? 'eq_word_count' : 'raw_word_count' );
		$total_words   = $row_totals[ $count_type ];

		if( empty( $requestedWordsPerSplit ) ){
			/*
			 * Simple Split with pretty equivalent number of words per chunk
			 */
			$words_per_job = array_fill( 0, $num_split, round( $total_words / $num_split, 0 ) );
		} else {
			/*
			 * User defined words per chunk, needs some checks and control structures
			 */
			$words_per_job = $requestedWordsPerSplit;
		}

		$counter = array();
		$chunk   = 0;

		$reverse_count = array( 'eq_word_count' => 0, 'raw_word_count' => 0 );

		foreach( $rows as $row ) {

			if( !array_key_exists( $chunk, $counter ) ){
				$counter[$chunk] = array(
					'eq_word_count'  => 0,
					'raw_word_count' => 0,
					'segment_start'  => $row['id'],
					'segment_end'    => 0,
				);
			}

			$counter[$chunk][ 'eq_word_count' ]  += $row[ 'eq_word_count' ];
			$counter[$chunk][ 'raw_word_count' ] += $row[ 'raw_word_count' ];
			$counter[$chunk][ 'segment_end' ]     = $row[ 'id' ];

			//check for wanted words per job
			//create a chunk when reach the requested number of words
			//and we are below the requested number of splits
			//so we add to the last chunk all rests
			if( $counter[$chunk][ $count_type ] >= $words_per_job[$chunk] && $chunk < $num_split -1 /* chunk is zero based */ ){
				$counter[$chunk][ 'eq_word_count' ]  = (int)$counter[$chunk][ 'eq_word_count' ];
				$counter[$chunk][ 'raw_word_count' ] = (int)$counter[$chunk][ 'raw_word_count' ];

				$reverse_count[ 'eq_word_count' ]   += (int)$counter[$chunk][ 'eq_word_count' ];
				$reverse_count[ 'raw_word_count' ]  += (int)$counter[$chunk][ 'raw_word_count' ];

				$chunk++;
			}

		}

		if( $total_words > $reverse_count[ $count_type ] ){
			$counter[$chunk][ 'eq_word_count' ]  = round( $row_totals[ 'eq_word_count' ] - $reverse_count[ 'eq_word_count' ] );
			$counter[$chunk][ 'raw_word_count' ] = round( $row_totals[ 'raw_word_count' ] - $reverse_count['raw_word_count'] );
		}

		if( count( $counter ) < 2 ){
			throw new Exception( 'The requested number of words for the first chunk is too large. I cannot create 2 chunks.', -7 );
		}

		$result = array_merge( $row_totals, array( 'chunks' => $counter ) );

		$projectStructure['split_result'] = new ArrayObject( $result );

		return $projectStructure['split_result'];

	}

	/**
	 * Do the split based on previous getSplitData analysis
	 * It clone the original job in the right number of chunks and fill these rows with:
	 * first/last segments of every chunk, last opened segment as first segment of new job
	 * and the timestamp of creation
	 *
	 * @param ArrayObject $projectStructure
	 *
	 * @throws Exception
	 */
	protected function _splitJob( ArrayObject $projectStructure ){

		$query_job = "SELECT * FROM jobs WHERE id = %u AND password = '%s'";
		$query_job = sprintf( $query_job, $projectStructure[ 'job_to_split' ], $projectStructure[ 'job_to_split_pass' ] );
		//$projectStructure[ 'job_to_split' ]

		$jobInfo = mysql_query( $query_job, $this->mysql_link );
		$jobInfo = mysql_fetch_assoc( $jobInfo );

		$data = array();
		$jobs = array();

		foreach( $projectStructure['split_result']['chunks'] as $chunk => $contents ){

			//            Log::doLog( $projectStructure['split_result']['chunks'] );

			//IF THIS IS NOT the original job, DELETE relevant fields
			if( $contents['segment_start'] != $projectStructure['split_result']['job_first_segment'] ){
				//next insert
				$jobInfo['password'] =  $this->_generatePassword();
				$jobInfo['create_date']  = date('Y-m-d H:i:s');
			}

			$jobInfo['last_opened_segment'] = $contents['segment_start'];
			$jobInfo['job_first_segment'] = $contents['segment_start'];
			$jobInfo['job_last_segment']  = $contents['segment_end'];

			$query = "INSERT INTO jobs ( " . implode( ", ", array_keys( $jobInfo ) ) . " )
				VALUES ( '" . implode( "', '", array_values( $jobInfo ) ) . "' )
				ON DUPLICATE KEY UPDATE
				last_opened_segment = {$jobInfo['last_opened_segment']},
                job_first_segment = '{$jobInfo['job_first_segment']}',
                job_last_segment = '{$jobInfo['job_last_segment']}'";


			//add here job id to list
			$projectStructure['array_jobs']['job_list']->append( $projectStructure[ 'job_to_split' ] );
			//add here passwords to list
			$projectStructure['array_jobs']['job_pass']->append( $jobInfo['password'] );

			$projectStructure['array_jobs']['job_segments']->offsetSet( $projectStructure[ 'job_to_split' ] . "-" . $jobInfo['password'], new ArrayObject( array( $contents['segment_start'], $contents['segment_end'] ) ) );

			$data[] = $query;
			$jobs[] = $jobInfo;
		}

		foreach( $data as $position => $query ){
			$res = mysql_query( $query, $this->mysql_link );

			$wCountManager = new WordCount_Counter();
			$wCountManager->initializeJobWordCount( $jobs[$position]['id'], $jobs[$position]['password'] );

			if( $res !== true ){
				$msg = "Failed to split job into " . count( $projectStructure['split_result']['chunks'] ) . " chunks\n";
				$msg .= "Tried to perform SQL: \n" . print_r(  $data ,true ) . " \n\n";
				$msg .= "Failed Statement is: \n" . print_r( $query, true ) . "\n";
				Utils::sendErrMailReport( $msg );
				throw new Exception( 'Failed to insert job chunk, project damaged.', -8 );
			}
		}

	}

	/**
	 * Apply new structure of job
	 *
	 * @param ArrayObject $projectStructure
	 */
	public function applySplit( ArrayObject $projectStructure ){
		$this->_splitJob( $projectStructure );
		Shop_Cart::getInstance( 'outsource_to_external_cache' )->emptyCart();
	}

	public function mergeALL( ArrayObject $projectStructure, $renewPassword = false ){

		$query_job = "SELECT *
			FROM jobs
			WHERE id = %u
			ORDER BY job_first_segment";

		$query_job = sprintf( $query_job, $projectStructure[ 'job_to_merge' ] );
		//$projectStructure[ 'job_to_split' ]

		$jobInfo = mysql_query( $query_job, $this->mysql_link );

		//assignment in condition is often dangerous, deprecated
		while ( ( $rows[] = mysql_fetch_assoc( $jobInfo ) ) != false );
		array_pop( $rows ); //destroy last assignment row ( every time === false )

		//get the min and
		$first_job = reset( $rows );
		$job_first_segment = $first_job['job_first_segment'];

		//the max segment from job list
		$last_job = end( $rows );
		$job_last_segment = $last_job['job_last_segment'];

		//change values of first job
		$first_job['job_first_segment'] = $job_first_segment; // redundant
		$first_job['job_last_segment']  = $job_last_segment;

		$oldPassword = $first_job['password'];
		if ( $renewPassword ){
			$first_job['password'] = self::_generatePassword();
		}

		$_data = array();
		foreach( $first_job as $field => $value ){
			$_data[] = "`$field`='$value'";
		}

		//----------------------------------------------------

		$queries = array();

		$queries[] = "UPDATE jobs SET " . implode( ", \n", $_data ) .
			" WHERE id = {$first_job['id']} AND password = '{$oldPassword}'"; //ose old password

		//delete all old jobs
		$queries[] = "DELETE FROM jobs WHERE id = {$first_job['id']} AND password != '{$first_job['password']}' "; //use new password


		foreach( $queries as $query ){
			$res = mysql_query( $query, $this->mysql_link );
			if( $res !== true ){
				$msg = "Failed to merge job  " . $rows[0]['id'] . " from " . count($rows) .  " chunks\n";
				$msg .= "Tried to perform SQL: \n" . print_r(  $queries ,true ) . " \n\n";
				$msg .= "Failed Statement is: \n" . print_r( $query, true ) . "\n";
				$msg .= "Original Status for rebuild job and project was: \n" . print_r( $rows, true ) . "\n";
				Utils::sendErrMailReport( $msg );
				throw new Exception( 'Failed to merge jobs, project damaged. Contact Matecat Support to rebuild project.', -8 );
			}
		}

		$wCountManager = new WordCount_Counter();
		$wCountManager->initializeJobWordCount( $first_job['id'], $first_job['password'] );

		Shop_Cart::getInstance( 'outsource_to_external_cache' )->emptyCart();

	}

	protected function _extractSegments( $files_path_name, $fid ){

		$info = pathinfo( $files_path_name );

		//create Structure fro multiple files
		$this->projectStructure['segments']->offsetSet( $fid, new ArrayObject( array() ) );

		// Checking Extentions
		if (($info['extension'] == 'xliff') || ($info['extension'] == 'sdlxliff') || ($info['extension'] == 'xlf')) {
			$content = file_get_contents( $files_path_name );
		} else {
			throw new Exception( "Failed to find Xliff - no segments found", -3 );
		}

		$xliff_obj = new Xliff_Parser();
		$xliff = $xliff_obj->Xliff2Array($content);

		// Checking that parsing went well
		if ( isset( $xliff[ 'parser-errors' ] ) or !isset( $xliff[ 'files' ] ) ) {
			Log::doLog( "Xliff Import: Error parsing. " . join( "\n", $xliff[ 'parser-errors' ] ) );
			throw new Exception( "Xliff Import: Error parsing. Check Log file.", -4 );
		}

		//needed to check if a file has only one segment
		//for correctness: we could have more tag files in the xliff
		$fileCounter_Show_In_Cattool = 0;

		// Creating the Query
		foreach ($xliff['files'] as $xliff_file) {

			if (!array_key_exists('trans-units', $xliff_file)) {
				continue;
			}

			//extract internal reference base64 files and store their index in $this->projectStructure
			$this->_extractFileReferences( $fid, $xliff_file );

			foreach ($xliff_file['trans-units'] as $xliff_trans_unit) {

				//initialize flag
				$show_in_cattool = 1;

				if ( !isset( $xliff_trans_unit[ 'attr' ][ 'translate' ] ) ) {
					$xliff_trans_unit[ 'attr' ][ 'translate' ] = 'yes';
				}

				if ( $xliff_trans_unit[ 'attr' ][ 'translate' ] == "no" ) {
					//No segments to translate
					//don't increment global counter '$fileCounter_Show_In_Cattool'
					$show_in_cattool = 0;
				} else {

					// If the XLIFF is already segmented (has <seg-source>)
					if (isset($xliff_trans_unit['seg-source'])) {

						foreach ($xliff_trans_unit['seg-source'] as $position => $seg_source) {

							$tempSeg = strip_tags($seg_source['raw-content']);
							$tempSeg = trim($tempSeg);

							//init tags
							$seg_source['mrk-ext-prec-tags'] = '';
							$seg_source['mrk-ext-succ-tags'] = '';

							if ( is_null($tempSeg) || $tempSeg === '' ) {
								$show_in_cattool = 0;
							} else {
								$extract_external = $this->_strip_external($seg_source['raw-content']);
								$seg_source['mrk-ext-prec-tags'] = $extract_external['prec'];
								$seg_source['mrk-ext-succ-tags'] = $extract_external['succ'];
								$seg_source['raw-content'] = $extract_external['seg'];

								if( isset( $xliff_trans_unit['seg-target'][$position]['raw-content'] ) ){
									$target_extract_external = $this->_strip_external( $xliff_trans_unit['seg-target'][$position]['raw-content'] );

									//we don't want THE CONTENT OF TARGET TAG IF PRESENT and EQUAL TO SOURCE???
									//AND IF IT IS ONLY A CHAR? like "*" ?
									//we can't distinguish if it is translated or not
									//this means that we lose the tags id inside the target if different from source
									$src = strip_tags( html_entity_decode( $extract_external['seg'], ENT_QUOTES, 'UTF-8' ) );
									$trg = strip_tags( html_entity_decode( $target_extract_external['seg'], ENT_QUOTES, 'UTF-8' ) );

									if( $src != $trg && !is_numeric($src) ){ //treat 0,1,2.. as translated content!

										$target = CatUtils::placeholdnbsp($target_extract_external['seg']);
										$target = mysql_real_escape_string($target);

										//add an empty string to avoid casting to int: 0001 -> 1
										//useful for idiom internal xliff id
										$this->projectStructure['translations']->offsetSet( "" . $xliff_trans_unit[ 'attr' ][ 'id' ] , new ArrayObject( array( 2 => $target ) ) );

										//seg-source and target translation can have different mrk id
										//override the seg-source surrounding mrk-id with them of target
										$seg_source['mrk-ext-prec-tags'] = $target_extract_external['prec'];
										$seg_source['mrk-ext-succ-tags'] = $target_extract_external['succ'];

									}

								}

							}

							//Log::doLog( $xliff_trans_unit ); die();

							$seg_source[ 'raw-content' ] = CatUtils::placeholdnbsp( $seg_source[ 'raw-content' ] );

							$mid                   = mysql_real_escape_string( $seg_source[ 'mid' ] );
							$ext_tags              = mysql_real_escape_string( $seg_source[ 'ext-prec-tags' ] );
							$source                = mysql_real_escape_string( $seg_source[ 'raw-content' ] );
							$source_hash           = mysql_real_escape_string( md5( $seg_source[ 'raw-content' ] ) );
							$ext_succ_tags         = mysql_real_escape_string( $seg_source[ 'ext-succ-tags' ] );
							$num_words             = CatUtils::segment_raw_wordcount( $seg_source[ 'raw-content' ], $xliff_file['attr']['source-language'] );
							$trans_unit_id         = mysql_real_escape_string( $xliff_trans_unit[ 'attr' ][ 'id' ] );
							$mrk_ext_prec_tags     = mysql_real_escape_string( $seg_source[ 'mrk-ext-prec-tags' ] );
							$mrk_ext_succ_tags     = mysql_real_escape_string( $seg_source[ 'mrk-ext-succ-tags' ] );

							if( $this->projectStructure['file_references']->offsetExists( $fid ) ){
								$file_reference = (int) $this->projectStructure['file_references'][$fid];
							} else $file_reference = 'NULL';

							$this->projectStructure['segments'][$fid]->append( "('$trans_unit_id',$fid,$file_reference,'$source','$source_hash',$num_words,'$mid','$ext_tags','$ext_succ_tags',$show_in_cattool,'$mrk_ext_prec_tags','$mrk_ext_succ_tags')" );

						}

					} else {

						$tempSeg = strip_tags( $xliff_trans_unit['source']['raw-content'] );
						$tempSeg = trim($tempSeg);
						$tempSeg = CatUtils::placeholdnbsp( $tempSeg );
						$prec_tags = NULL;
						$succ_tags = NULL;
						if ( empty( $tempSeg ) || $tempSeg == NBSPPLACEHOLDER ) { //@see CatUtils.php, ( DEFINE NBSPPLACEHOLDER ) don't show <x id=\"nbsp\"/>
							$show_in_cattool = 0;
						} else {
							$extract_external                              = $this->_strip_external( $xliff_trans_unit[ 'source' ][ 'raw-content' ] );
							$prec_tags= empty( $extract_external[ 'prec' ] ) ? null : $extract_external[ 'prec' ];
							$succ_tags= empty( $extract_external[ 'succ' ] ) ? null : $extract_external[ 'succ' ];
							$xliff_trans_unit[ 'source' ][ 'raw-content' ] = $extract_external[ 'seg' ];

							if ( isset( $xliff_trans_unit[ 'target' ][ 'raw-content' ] ) ) {

								$target_extract_external = $this->_strip_external( $xliff_trans_unit[ 'target' ][ 'raw-content' ] );

								if ( $xliff_trans_unit[ 'source' ][ 'raw-content' ] != $target_extract_external[ 'seg' ] ) {

									$target = CatUtils::placeholdnbsp( $target_extract_external[ 'seg' ] );
									$target = mysql_real_escape_string( $target );

									//add an empty string to avoid casting to int: 0001 -> 1
									//useful for idiom internal xliff id
									$this->projectStructure['translations']->offsetSet( "" . $xliff_trans_unit[ 'attr' ][ 'id' ], new ArrayObject( array( 2 => $target ) ) );

								}

							}
						}

						$source = CatUtils::placeholdnbsp( $xliff_trans_unit['source']['raw-content'] );

						//we do the word count after the place-holding with <x id="nbsp"/>
						//so &nbsp; are now not recognized as word and not counted as payable
						$num_words = CatUtils::segment_raw_wordcount($source, $xliff_file['attr']['source-language'] );

						//applying escaping after raw count
						$source      = mysql_real_escape_string( $source );
						$source_hash = mysql_real_escape_string( md5( $source ) );

						$trans_unit_id = mysql_real_escape_string($xliff_trans_unit['attr']['id']);

						if (!is_null($prec_tags)) {
							$prec_tags = mysql_real_escape_string($prec_tags);
						}
						if (!is_null($succ_tags)) {
							$succ_tags = mysql_real_escape_string($succ_tags);
						}

						if( $this->projectStructure['file_references']->offsetExists( $fid ) ){
							$file_reference = (int) $this->projectStructure['file_references'][$fid];
						} else $file_reference = 'NULL';

						$this->projectStructure['segments'][$fid]->append( "('$trans_unit_id',$fid, $file_reference,'$source','$source_hash',$num_words,NULL,'$prec_tags','$succ_tags',$show_in_cattool,NULL,NULL)" );

					}
				}

				//increment the counter for not empty segments
				$fileCounter_Show_In_Cattool += $show_in_cattool;

			}
		}

		// *NOTE*: PHP>=5.3 throws UnexpectedValueException, but PHP 5.2 throws ErrorException
		//use generic
		if ( empty( $this->projectStructure[ 'segments' ][ $fid ] ) || $fileCounter_Show_In_Cattool == 0 ) {
			Log::doLog( "Segment import - no segments found\n" );
			throw new Exception( "Segment import - no segments found", -1 );
		}

		$baseQuery = "INSERT INTO segments ( internal_id, id_file, id_file_part, segment, segment_hash, raw_word_count, xliff_mrk_id, xliff_ext_prec_tags, xliff_ext_succ_tags, show_in_cattool,xliff_mrk_ext_prec_tags,xliff_mrk_ext_succ_tags) values ";

		Log::doLog( "Segments: Total Rows to insert: " . count( $this->projectStructure['segments'][$fid] ) );
		//split the query in to chunks if there are too much segments
		$this->projectStructure['segments'][$fid]->exchangeArray( array_chunk( $this->projectStructure['segments'][$fid]->getArrayCopy(), 1000 ) );

		Log::doLog( "Segments: Total Queries to execute: " . count( $this->projectStructure['segments'][$fid] ) );


		foreach( $this->projectStructure['segments'][$fid] as $i => $chunk ){

			$res = mysql_query( $baseQuery . join(",\n", $chunk ) , $this->mysql_link);
			Log::doLog( "Segments: Executed Query " . ( $i+1 ) );
			if (!$res) {
				Log::doLog("Segment import - DB Error: " . mysql_error() . " - \n");
				throw new Exception( "Segment import - DB Error: " . mysql_error() . " - $chunk", -2 );
			}

		}

		//Log::doLog( $this->projectStructure );

		if( !empty( $this->projectStructure['translations'] ) ){

			$last_segments_query = "SELECT id, internal_id, segment_hash from segments WHERE id_file = %u";
			$last_segments_query = sprintf( $last_segments_query, $fid );

			$last_segments = mysql_query( $last_segments_query, $this->mysql_link );

			//assignment in condition is often dangerous, deprecated
			while ( ( $row = mysql_fetch_assoc( $last_segments ) ) != false ) {

				if( $this->projectStructure['translations']->offsetExists( "" . $row['internal_id'] ) ) {
					$this->projectStructure['translations'][ "" . $row['internal_id'] ]->offsetSet( 0, $row['id'] );
					$this->projectStructure['translations'][ "" . $row['internal_id'] ]->offsetSet( 1, $row['internal_id'] );
					//WARNING offset 2 are the target translations
					$this->projectStructure['translations'][ "" . $row['internal_id'] ]->offsetSet( 3, $row['segment_hash'] );
				}

			}

		}

	}

	protected function _insertPreTranslations( $jid ){

		//    Log::doLog( array_shift( array_chunk( $SegmentTranslations, 5, true ) ) );

		foreach ( $this->projectStructure['translations'] as $internal_id => $struct ){

			if( empty($struct) ) {
				//            Log::doLog( $internal_id . " : " . var_export( $struct, true ) );
				continue;
			}

			//id_segment, id_job, segment_hash, status, translation, translation_date, tm_analysis_status, locked
			$this->projectStructure['query_translations']->append( "( '{$struct[0]}', $jid, '{$struct[3]}', 'TRANSLATED', '{$struct[2]}', NOW(), 'DONE', 1, 'ICE' )" );

		}

		// Executing the Query
		if( !empty( $this->projectStructure['query_translations'] ) ){

			$baseQuery = "INSERT INTO segment_translations (id_segment, id_job, segment_hash, status, translation, translation_date, tm_analysis_status, locked, match_type )
				values ";

			Log::doLog( "Pre-Translations: Total Rows to insert: " . count( $this->projectStructure['query_translations'] ) );
			//split the query in to chunks if there are too much segments
			$this->projectStructure['query_translations']->exchangeArray( array_chunk( $this->projectStructure['query_translations']->getArrayCopy(), 1000 ) );

			Log::doLog( "Pre-Translations: Total Queries to execute: " . count( $this->projectStructure['query_translations'] ) );

//			Log::doLog( print_r( $this->projectStructure['translations'],true ) );

			foreach( $this->projectStructure['query_translations'] as $i => $chunk ){

				$res = mysql_query( $baseQuery . join(",\n", $chunk ) , $this->mysql_link);
				Log::doLog( "Pre-Translations: Executed Query " . ( $i+1 ) );
				if (!$res) {
					Log::doLog("Segment import - DB Error: " . mysql_error() . " - \n");
					throw new Exception( "Translations Segment import - DB Error: " . mysql_error() . " - $chunk", -2 );
				}

			}

		}

		//clean translations and queries
		$this->projectStructure['query_translations']->exchangeArray( array() );
		$this->projectStructure['translations']->exchangeArray( array() );

	}

	protected function _generatePassword( $length = 12 ){
		return CatUtils::generate_password( $length );
	}

	protected function _strip_external( $a ) {
		$a               = str_replace( "\n", " NL ", $a );
		$pattern_x_start = '/^(\s*<x .*?\/>)(.*)/mis';
		$pattern_x_end   = '/(.*)(<x .*?\/>\s*)$/mis';

		//TODO:
		//What happens here? this regexp fails for
		//<g id="pt1497"><g id="pt1498"><x id="nbsp"/></g></g>
		//And this
		/* $pattern_g       = '/^(\s*<g [^>]*?>)(.*?)(<\/g>\s*)$/mis'; */
		//break document consistency in project Manager
		//where is the bug? there or in extract segments?

		$pattern_g       = '/^(\s*<g [^>]*?>)([^<]*?)(<\/g>\s*)$/mis';
		$found           = false;
		$prec            = "";
		$succ            = "";

		$c = 0;

		do {
			$c += 1;
			$found = false;

			do {
				$r = preg_match_all( $pattern_x_start, $a, $res );
				if ( isset( $res[ 1 ][ 0 ] ) ) {
					$prec .= $res[ 1 ][ 0 ];
					$a     = $res[ 2 ][ 0 ];
					$found = true;
				}
			} while ( isset( $res[ 1 ][ 0 ] ) );

			do {
				$r = preg_match_all( $pattern_x_end, $a, $res );
				if ( isset( $res[ 2 ][ 0 ] ) ) {
					$succ  = $res[ 2 ][ 0 ] . $succ;
					$a     = $res[ 1 ][ 0 ];
					$found = true;
				}
			} while ( isset( $res[ 2 ][ 0 ] ) );

			do {
				$r = preg_match_all( $pattern_g, $a, $res );
				if ( isset( $res[ 1 ][ 0 ] ) ) {
					$prec .= $res[ 1 ][ 0 ];
					$succ  = $res[ 3 ][ 0 ] . $succ;
					$a     = $res[ 2 ][ 0 ];
					$found = true;
				}
			} while ( isset( $res[ 1 ][ 0 ] ) );

		} while ( $found );
		$prec = str_replace( " NL ", "\n", $prec );
		$succ = str_replace( " NL ", "\n", $succ );
		$a    = str_replace( " NL ", "\n", $a );
		$r    = array( 'prec' => $prec, 'seg' => $a, 'succ' => $succ );

		return $r;
	}

	protected static function _deleteDir( $dirPath ) {
		return true;
		$iterator = new DirectoryIterator( $dirPath );

		foreach ( $iterator as $fileInfo ) {
			if ( $fileInfo->isDot() ) continue;
			if ( $fileInfo->isDir() ) {
				self::_deleteDir( $fileInfo->getPathname() );
			} else {
				unlink( $fileInfo->getPathname() );
			}
		}
		rmdir( $iterator->getPath() );

	}

	public static function getExtensionFromMimeType( $mime_type ){

		$reference = include('mime2extension.inc.php');
		if( array_key_exists( $mime_type, $reference ) ){
			if ( array_key_exists( 'default', $reference[$mime_type] ) ) return $reference[$mime_type]['default'];
			return $reference[$mime_type][ array_rand( $reference[$mime_type] ) ]; // rand :D
		}
		return null;

	}

	protected function _sanitizeName( $nameString ){

		$nameString = preg_replace( '/[^\p{L}0-9a-zA-Z_\.\-]/u', "_", $nameString );
		$nameString = preg_replace( '/[_]{2,}/', "_", $nameString );
		$nameString = str_replace( '_.', ".", $nameString );

		// project name validation
		$pattern = '/^[\p{L}\ 0-9a-zA-Z_\.\-]+$/u';

		if ( !preg_match( $pattern, $nameString, $rr ) ) {
			return false;
		}

		return $nameString;

	}

	/**
	 * Extract internal reference base64 files
	 * and store their index in $this->projectStructure
	 *
	 * @param $project_file_id
	 * @param $xliff_file_array
	 *
	 * @return null|int $file_reference_id
	 *
	 * @throws Exception
	 */
	protected function _extractFileReferences( $project_file_id, $xliff_file_array ){

		$fName = $this->_sanitizeName( $xliff_file_array['attr']['original'] );

		if( $fName != false ){
			$fName = mysql_real_escape_string( $fName, $this->mysql_link );
		} else {
			$fName = '';
		}

		$serialized_reference_meta     = array();
		$serialized_reference_binaries = array();

		/* Fix: PHP Warning:  Invalid argument supplied for foreach() */
		if( !isset( $xliff_file_array['reference'] ) ) return null;

		foreach( $xliff_file_array['reference'] as $pos => $ref ){

			$found_ref = true;

			$_ext = self::getExtensionFromMimeType( $ref['form-type'] );
			if( $_ext !== null ){

				//insert in database if exists extension
				//and add the id_file_part to the segments insert statement

				$refName = $this->projectStructure['id_project'] . "-" . $pos . "-" . $fName . "." . $_ext;

				$serialized_reference_meta[$pos]['filename']  = $refName;
				$serialized_reference_meta[$pos]['mime_type'] = mysql_real_escape_string( $ref['form-type'], $this->mysql_link );
				$serialized_reference_binaries[$pos]['base64']    = $ref['base64'];

				if( !is_dir( INIT::$REFERENCE_REPOSITORY ) ) mkdir( INIT::$REFERENCE_REPOSITORY, 0755 );

				$wBytes = file_put_contents( INIT::$REFERENCE_REPOSITORY . "/$refName", base64_decode( $ref['base64'] ) );

				if( !$wBytes ){
					throw new Exception ( "Failed to import references. $wBytes Bytes written.", -11 );
				}

			}

		}

		if( isset( $found_ref ) && !empty($serialized_reference_meta) ){

			$serialized_reference_meta     = serialize( $serialized_reference_meta );
			$serialized_reference_binaries = serialize( $serialized_reference_binaries );
			$queries = "INSERT INTO file_references ( id_project, id_file, part_filename, serialized_reference_meta, serialized_reference_binaries ) VALUES ( " . $this->projectStructure['id_project'] . ", $project_file_id, '$fName', '$serialized_reference_meta', '$serialized_reference_binaries' )";
			mysql_query( $queries, $this->mysql_link );

			$affected          = mysql_affected_rows( $this->mysql_link );
			$last_id           = "SELECT LAST_INSERT_ID() as fpID";
			$link_identifier   = mysql_query( $last_id, $this->mysql_link );
			$result            = mysql_fetch_assoc( $link_identifier );

			//last Insert id
			$file_reference_id = $result[ 'fpID' ];
			$this->projectStructure[ 'file_references' ]->offsetSet( $project_file_id, $file_reference_id );

			if( !$affected || !$file_reference_id ){
				throw new Exception ( "Failed to import references.", -12 );
			}

			return $file_reference_id;

		}


	}

}
