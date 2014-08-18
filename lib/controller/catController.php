<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
//include_once INIT::$UTILS_ROOT . "/filetype.class.php";
include_once INIT::$UTILS_ROOT . "/CatUtils.php";
include_once INIT::$UTILS_ROOT . "/langs/languages.class.php";
include_once INIT::$UTILS_ROOT . '/QA.php';

/**
 * Description of catController
 *
 * @author antonio
 */
class catController extends viewController {

	private $data = array();
	private $cid = "";
	private $jid = "";
	private $tid = "";
	private $password = "";
	private $source = "";
	private $pname = "";
	private $create_date = "";
	private $project_status = 'NEW';
	private $start_from = 0;
	private $page = 0;
	private $start_time = 0.00;
	private $downloadFileName;
	private $job_stats = array();
	private $source_rtl = false;
	private $target_rtl = false;
	private $job_owner = "";

	private $job_not_found = false;
	private $job_archived = false;
	private $job_cancelled = false;

    private $firstSegmentOfFiles = '[]';
    private $fileCounter = '[]';

    private $first_job_segment;
    private $last_job_segment;
    private $last_opened_segment;

	private $thisUrl;

	public function __construct() {
		$this->start_time = microtime(1) * 1000;

		parent::__construct(false);
		parent::makeTemplate("index.html");
		
		$filterArgs = array(
			'jid'           => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
			'password'      => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
			'start'         => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
			'page'          => array( 'filter' => FILTER_SANITIZE_NUMBER_INT )
		);
		$getInput = (object)filter_input_array( INPUT_GET, $filterArgs );

		$this->jid = $getInput->jid;
		$this->password = $getInput->password;
		$this->start_from = $getInput->start;
		$this->page = $getInput->page;

		if (isset($_GET['step'])) {
			$this->step = $_GET['step'];
		} else {
			$this->step = 1000;
		};

		if (is_null($this->page)) {
			$this->page = 1;
		}
		if (is_null($this->start_from)) {
			$this->start_from = ($this->page - 1) * $this->step;
		}

        if (isset($_GET['filter'])) {
			$this->filter_enabled = true;
		} else {
			$this->filter_enabled = false;
		};
        
		$this->downloadFileName = "";

		$this->thisUrl=$_SERVER['REQUEST_URI'];

	}

	private function parse_time_to_edit($ms) {
		if ($ms <= 0) {
			return array("00", "00", "00", "00");
		}

		$usec = $ms % 1000;
		$ms = floor($ms / 1000);

		$seconds = str_pad($ms % 60, 2, "0", STR_PAD_LEFT);
		$ms = floor($ms / 60);

		$minutes = str_pad($ms % 60, 2, "0", STR_PAD_LEFT);
		$ms = floor($ms / 60);

		$hours = str_pad($ms % 60, 2, "0", STR_PAD_LEFT);
		$ms = floor($ms / 60);

		return array($hours, $minutes, $seconds, $usec);
	}

	public function doAction() {
		$files_found = array();
		$lang_handler = Languages::getInstance();

		$data = getSegmentsInfo($this->jid, $this->password);
		if (empty($data) or $data < 0) {
			$this->job_not_found = true;
            //stop execution
            return;
		}

		//retrieve job owner. It will be useful also if the job is archived or cancelled
		$this->job_owner = ($data[0]['job_owner'] != "") ? $data[0]['job_owner'] : "support@matecat.com" ;

		if ($data[0]['status'] == 'cancelled') {
            $this->job_cancelled = true;
            //stop execution
            return;
        }

		if ($data[0]['status'] == 'archived') {
			$this->job_archived = true;
//			$this->setTemplateVars();
			//stop execution
			return;
		}

		$jobIsArchivable = count( Utils::getArchivableJobs($this->jid) ) > 0;

		if( $jobIsArchivable && !$this->job_cancelled ) {
			//TODO: change this workaround

			$res = "job";
			$new_status = 'archived';

			updateJobsStatus( $res, $this->jid, $new_status, null, null, $this->password );
			$this->job_archived = true;
		}

		foreach ($data as $i => $job) {

            $this->project_status = $job; // get one row values for the project are the same for every row

			if (empty($this->pname)) {
				$this->pname = $job['pname'];
				$this->downloadFileName = $job['pname'] . ".zip"; // will be overwritten below in case of one file job
			}

			if (empty($this->last_opened_segment)) {
				$this->last_opened_segment = $job['last_opened_segment'];
			}

			if (empty($this->cid)) {
				$this->cid = $job['cid'];
			}

			if (empty($this->pid)) {
				$this->pid = $job['pid'];
			}

			if (empty($this->tid)) {
				$this->tid = $job['tid'];
			}

			if (empty($this->create_date)) {
				$this->create_date = $job['create_date'];
			}

			if (empty($this->source_code)) {
				$this->source_code = $job['source'];
			}

			if (empty($this->target_code)) {
				$this->target_code = $job['target'];
			}

			if (empty($this->source)) {
				$s = explode("-", $job['source']);
				$source = strtoupper($s[0]);
				$this->source = $source;
				$this->source_rtl= ($lang_handler->isRTL(strtolower($this->source)))? ' rtl-source' : '';
			}

			if (empty($this->target)) {
				$t = explode("-", $job['target']);
				$target = strtoupper($t[0]);
				$this->target = $target;
				$this->target_rtl= ($lang_handler->isRTL(strtolower($this->target)))? ' rtl-target' : '';
			}
			//check if language belongs to supported right-to-left languages


			if ($job['status'] == 'archived') {
				$this->job_archived = true;
				$this->job_owner = $data[0]['job_owner'];
			}

			$id_file = $job['id_file'];


			if (!isset($this->data["$id_file"])) {
				$files_found[] = $job['filename'];
//				$file_stats = CatUtils::getStatsForFile($id_file);
//
//				$this->data["$id_file"]['jid'] = $seg['jid'];
//				$this->data["$id_file"]["filename"] = $seg['filename'];
//				$this->data["$id_file"]["mime_type"] = $seg['mime_type'];
////				$this->data["$id_file"]['id_segment_start'] = @$seg['id_segment_start'];
////				$this->data["$id_file"]['id_segment_end'] = @$seg['id_segment_end'];
//				$this->data["$id_file"]['source'] = $lang_handler->getLocalizedName($seg['source'],'en');
//				$this->data["$id_file"]['target'] = $lang_handler->getLocalizedName($seg['target'],'en');
//				$this->data["$id_file"]['source_code'] = $seg['source'];
//				$this->data["$id_file"]['target_code'] = $seg['target'];
//				$this->data["$id_file"]['last_opened_segment'] = $seg['last_opened_segment'];
//				$this->data["$id_file"]['file_stats'] = $file_stats;
			}
			//$this->filetype_handler = new filetype($seg['mime_type']);

            $wStruct = new WordCount_Struct();

            $wStruct->setIdJob( $this->jid );
            $wStruct->setJobPassword( $this->password );
            $wStruct->setNewWords( $job['new_words'] );
            $wStruct->setDraftWords( $job['draft_words'] );
            $wStruct->setTranslatedWords( $job['translated_words'] );
            $wStruct->setApprovedWords( $job['approved_words'] );
            $wStruct->setRejectedWords( $job['rejected_words'] );

			unset($job['id_file']);
			unset($job['source']);
			unset($job['target']);
			unset($job['source_code']);
			unset($job['target_code']);
			unset($job['mime_type']);
			unset($job['filename']);
			unset($job['jid']);
			unset($job['pid']);
			unset($job['cid']);
			unset($job['tid']);
			unset($job['pname']);
			unset($job['create_date']);
			unset($job['owner']);
//			unset($seg['id_segment_end']);
//			unset($seg['id_segment_start']);
			unset($job['last_opened_segment']);

            unset( $job[ 'new_words' ] );
            unset( $job[ 'draft_words' ] );
            unset( $job[ 'translated_words' ] );
            unset( $job[ 'approved_words' ] );
            unset( $job[ 'rejected_words' ] );

            //For projects created with No tm analysis enabled
            if( $wStruct->getTotal() == 0 && ( $job['status_analysis'] == Constants_ProjectStatus::STATUS_DONE ||  $job['status_analysis'] == Constants_ProjectStatus::STATUS_NOT_TO_ANALYZE ) ){
                $wCounter = new WordCount_Counter();
                $wStruct = $wCounter->initializeJobWordCount( $this->jid, $this->password );
                Log::doLog( "BackWard compatibility set Counter." );
            }

            $this->job_stats = CatUtils::getFastStatsForJob( $wStruct );

//            Log::doLog( $this->job_stats );

            //$this->job_stats = CatUtils::getStatsForJob( $this->jid, null, $this->password );

        }

        //TODO check and improve, this is not needed
		if (empty($this->last_opened_segment)) {
			$this->last_opened_segment = getFirstSegmentId($this->jid, $this->password);
		}

        $this->first_job_segment =$this->project_status['job_first_segment'];
        $this->last_job_segment =$this->project_status['job_last_segment'];

		if (count($files_found) == 1) {
			$this->downloadFileName = $files_found[0];
		}

        /**
         * get first segment of every file
         */
        $fileInfo = getFirstSegmentOfFilesInJob( $this->jid );
        $TotalPayable = array();
        foreach( $fileInfo as $file ){
            $TotalPayable[ $file['id_file'] ]['TOTAL_FORMATTED'] = $file['TOTAL_FORMATTED'];
        }
        $this->firstSegmentOfFiles = json_encode( $fileInfo );
        $this->fileCounter         = json_encode( $TotalPayable );

    }

	public function setTemplateVars() {

        if ( $this->job_not_found || $this->job_cancelled ) {
            $this->template->pid                 = null;
            $this->template->target              = null;
            $this->template->source_code         = null;
            $this->template->target_code         = null;
            $this->template->firstSegmentOfFiles = 0;
            $this->template->fileCounter         = 0;
	        $this->template->owner_email         = $this->job_owner;
        } else {
            $this->template->pid                 = $this->pid;
            $this->template->target              = $this->target;
            $this->template->source_code         = $this->source_code;
            $this->template->target_code         = $this->target_code;
            $this->template->firstSegmentOfFiles = $this->firstSegmentOfFiles;
            $this->template->fileCounter         = $this->fileCounter;
        }

        $this->template->jid         = $this->jid;
        $this->template->password    = $this->password;
        $this->template->cid         = $this->cid;
        $this->template->create_date = $this->create_date;
        $this->template->pname       = $this->pname;
        $this->template->tid         = $this->tid;
        $this->template->source      = $this->source;
        $this->template->source_rtl  = $this->source_rtl;
        $this->template->target_rtl  = $this->target_rtl;

        $this->template->first_job_segment   = $this->first_job_segment;
        $this->template->last_job_segment    = $this->last_job_segment;
        $this->template->last_opened_segment = $this->last_opened_segment;
		$this->template->owner_email         = $this->job_owner;
        //$this->template->data                = $this->data;

        $this->job_stats['STATUS_BAR_NO_DISPLAY'] = ( $this->project_status['status_analysis'] == Constants_ProjectStatus::STATUS_DONE ? '' : 'display:none;' );
        $this->job_stats['ANALYSIS_COMPLETE']   = ( $this->project_status['status_analysis'] == Constants_ProjectStatus::STATUS_DONE ? true : false );

//        Log::doLog( $this->job_stats );

        $this->template->job_stats              = $this->job_stats;

        $end_time                               = microtime( true ) * 1000;
        $load_time                              = $end_time - $this->start_time;
        $this->template->load_time              = $load_time;
        $this->template->tms_enabled            = var_export( (bool)$this->project_status[ 'id_tms' ], true );
        $this->template->mt_enabled             = var_export( (bool)$this->project_status[ 'id_mt_engine' ], true );
        $this->template->time_to_edit_enabled   = INIT::$TIME_TO_EDIT_ENABLED;
        $this->template->build_number           = INIT::$BUILD_NUMBER;
        $this->template->downloadFileName       = $this->downloadFileName;
        $this->template->job_not_found          = $this->job_not_found;
        $this->template->job_archived           = ( $this->job_archived ) ? ' archived' : '';
        $this->template->job_cancelled          = $this->job_cancelled;
        $this->template->logged_user            = trim( $this->logged_user[ 'first_name' ] . " " . $this->logged_user[ 'last_name' ] );
        $this->template->incomingUrl            = '/login?incomingUrl=' . $this->thisUrl;
        $this->template->warningPollingInterval = 1000 * ( INIT::$WARNING_POLLING_INTERVAL );
        $this->template->segmentQACheckInterval = 1000 * ( INIT::$SEGMENT_QA_CHECK_INTERVAL );
        $this->template->filtered               = $this->filter_enabled;
        $this->template->filtered_class         = ( $this->filter_enabled ) ? ' open' : '';

		( INIT::$VOLUME_ANALYSIS_ENABLED        ? $this->template->analysis_enabled = true : null );

		//check if it is a composite language, for cjk check that accepts only ISO 639 code
        if ( strpos( $this->target_code, '-' ) !== false ) {
            //pick only first part
            $tmp_lang               = explode( '-', $this->target_code );
            $target_code_no_country = $tmp_lang[ 0 ];
            unset( $tmp_lang );
        } else {
            //not a RFC code, it's fine
            $target_code_no_country = $this->target_code;
        }

        //check if cjk
        if ( array_key_exists( $target_code_no_country, CatUtils::$cjk ) ) {
            $this->template->taglockEnabled = 0;
        }

        /*
         * Line Feed PlaceHolding System
         */
		$this->template->brPlaceholdEnabled = $placeHoldingEnabled = true;

		if( $placeHoldingEnabled ){

			$this->template->lfPlaceholder        = CatUtils::lfPlaceholder;
			$this->template->crPlaceholder        = CatUtils::crPlaceholder;
			$this->template->crlfPlaceholder      = CatUtils::crlfPlaceholder;
			$this->template->lfPlaceholderClass   = CatUtils::lfPlaceholderClass;
			$this->template->crPlaceholderClass   = CatUtils::crPlaceholderClass;
			$this->template->crlfPlaceholderClass = CatUtils::crlfPlaceholderClass;
			$this->template->lfPlaceholderRegex   = CatUtils::lfPlaceholderRegex;
			$this->template->crPlaceholderRegex   = CatUtils::crPlaceholderRegex;
			$this->template->crlfPlaceholderRegex = CatUtils::crlfPlaceholderRegex;

            $this->template->tabPlaceholder       = CatUtils::tabPlaceholder;
            $this->template->tabPlaceholderClass  = CatUtils::tabPlaceholderClass;
            $this->template->tabPlaceholderRegex  = CatUtils::tabPlaceholderRegex;

            $this->template->nbspPlaceholder       = CatUtils::nbspPlaceholder;
            $this->template->nbspPlaceholderClass  = CatUtils::nbspPlaceholderClass;
            $this->template->nbspPlaceholderRegex  = CatUtils::nbspPlaceholderRegex;

		}

	}

}
