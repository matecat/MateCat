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

	private $job_not_found = false;
	private $job_archived = false;
	private $job_cancelled = false;

    private $firstSegmentOfFiles = '[]';

    private $first_job_segment;
    private $last_opened_segment;

	private $thisUrl;

	public function __construct() {
		$this->start_time = microtime(1) * 1000;

		parent::__construct(false);
		parent::makeTemplate("index.html");
		$this->jid = $this->get_from_get_post("jid");
		$this->password = $this->get_from_get_post("password");
		$this->start_from = $this->get_from_get_post("start");
		$this->page = $this->get_from_get_post("page");

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

        if ($data[0]['status'] == 'cancelled') {
            $this->job_cancelled = true;
            //stop execution
            return;
        }

		foreach ($data as $i => $prj) {

            $this->project_status = $prj;

			if (empty($this->pname)) {
				$this->pname = $prj['pname'];
				$this->downloadFileName = $prj['pname'] . ".zip"; // will be overwritten below in case of one file job
			}

			if (empty($this->last_opened_segment)) {
				$this->last_opened_segment = $prj['last_opened_segment'];
			}

			if (empty($this->first_job_segment)) {
//				$this->first_job_segment = @$seg['id_segment_start'];
			}
            
			if (empty($this->cid)) {
				$this->cid = $prj['cid'];
			}

			if (empty($this->pid)) {
				$this->pid = $prj['pid'];
			}

			if (empty($this->tid)) {
				$this->tid = $prj['tid'];
			}

			if (empty($this->create_date)) {
				$this->create_date = $prj['create_date'];
			}

			if (empty($this->source_code)) {
				$this->source_code = $prj['source'];
			}

			if (empty($this->target_code)) {
				$this->target_code = $prj['target'];
			}

			if (empty($this->source)) {
				$s = explode("-", $prj['source']);
				$source = strtoupper($s[0]);
				$this->source = $source;
				$this->source_rtl= ($lang_handler->isRTL(strtolower($this->source)))? ' rtl-source' : '';
			}

			if (empty($this->target)) {
				$t = explode("-", $prj['target']);
				$target = strtoupper($t[0]);
				$this->target = $target;
				$this->target_rtl= ($lang_handler->isRTL(strtolower($this->target)))? ' rtl-target' : '';
			}
			//check if language belongs to supported right-to-left languages


			if ($prj['status'] == 'archived') {
				$this->job_archived = true;
			}

			$id_file = $prj['id_file'];


			if (!isset($this->data["$id_file"])) {
				$files_found[] = $prj['filename'];
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
            $wStruct->setNewWords( $prj['new_words'] );
            $wStruct->setDraftWords( $prj['draft_words'] );
            $wStruct->setTranslatedWords( $prj['translated_words'] );
            $wStruct->setApprovedWords( $prj['approved_words'] );
            $wStruct->setRejectedWords( $prj['rejected_words'] );

			unset($prj['id_file']);
			unset($prj['source']);
			unset($prj['target']);
			unset($prj['source_code']);
			unset($prj['target_code']);
			unset($prj['mime_type']);
			unset($prj['filename']);
			unset($prj['jid']);
			unset($prj['pid']);
			unset($prj['cid']);
			unset($prj['tid']);
			unset($prj['pname']);
			unset($prj['create_date']);
//			unset($seg['id_segment_end']);
//			unset($seg['id_segment_start']);
			unset($prj['last_opened_segment']);

            unset( $prj[ 'new_words' ] );
            unset( $prj[ 'draft_words' ] );
            unset( $prj[ 'translated_words' ] );
            unset( $prj[ 'approved_words' ] );
            unset( $prj[ 'rejected_words' ] );

            $this->job_stats = CatUtils::getFastStatsForJob( $wStruct );

            //BackWard Compatibility, for projects created with old versions
            if( $wStruct->getTotal() == 0 ){
                $wCounter = new WordCount_Counter();
                $wStruct = $wCounter->initializeJobWordCount( $this->jid, $this->password );
            }

//            Log::doLog( $this->job_stats );

            //$this->job_stats = CatUtils::getStatsForJob( $this->jid, null, $this->password );

        }

		if (empty($this->last_opened_segment)) {
			$this->last_opened_segment = getFirstSegmentId($this->jid, $this->password);
		}

		if (count($files_found) == 1) {
			$this->downloadFileName = $files_found[0];
		}

        /**
         * get first segment of every file
         */
         $this->firstSegmentOfFiles = json_encode( getFirstSegmentOfFilesInJob( $this->jid ) );

	}

	public function setTemplateVars() {

        if ( $this->job_not_found || $this->job_cancelled ) {
            $this->template->pid                 = null;
            $this->template->target              = null;
            $this->template->source_code         = null;
            $this->template->target_code         = null;
            $this->template->firstSegmentOfFiles = null;
        } else {
            $this->template->pid                 = $this->pid;
            $this->template->target              = $this->target;
            $this->template->source_code         = $this->source_code;
            $this->template->target_code         = $this->target_code;
            $this->template->firstSegmentOfFiles = $this->firstSegmentOfFiles;
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
        $this->template->last_opened_segment = $this->last_opened_segment;
        //$this->template->data                = $this->data;

        $this->job_stats['STATUS_BAR_NO_DISPLAY'] = ( $this->project_status['status_analysis'] == 'DONE' ? '' : 'display:none;' );
        $this->job_stats['ANALYSIS_COMPLETE']   = ( $this->project_status['status_analysis'] == 'DONE' ? true : false );

//        Log::doLog( $this->job_stats );

        $this->template->job_stats = $this->job_stats;

        $end_time                               = microtime( true ) * 1000;
        $load_time                              = $end_time - $this->start_time;
        $this->template->load_time              = $load_time;
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
		$this->template->filtered = $this->filter_enabled;
		$this->template->filtered_class = ($this->filter_enabled) ? ' open' : '';

        ( INIT::$VOLUME_ANALYSIS_ENABLED        ? $this->template->analysis_enabled = true : null );

        if( array_key_exists( $this->target_code, CatUtils::$cjk ) ){
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

        }

    }

}
