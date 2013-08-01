<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include INIT::$UTILS_ROOT . "/langs/languages.class.php";

class analyzeController extends viewcontroller {

	private $pid;
	private $password;
	private $pname = "";
	private $total_raw_word_count = 0;
	private $total_raw_word_count_print = "";
	private $fast_analysis_wc = 0;
	private $tm_analysis_wc = 0;
	private $standard_analysis_wc = 0;
	private $fast_analysis_wc_print = "";
	private $standard_analysis_wc_print = "";
	private $tm_analysis_wc_print = "";
	private $raw_wc_time = 0;
	private $fast_wc_time = 0;
	private $tm_wc_time = 0;
	private $standard_wc_time = 0;
	private $fast_wc_unit = "";
	private $tm_wc_unit = "";
	private $raw_wc_unit = "";
	private $standard_wc_unit = "";
	private $jobs;
	private $project_not_found = false;
	private $project_status = "";
	private $num_segments = 0;
	private $num_segments_analyzed = 0;

	public function __construct() {
		parent::__construct(false);
		parent::makeTemplate("analyze.html");

		$this->pid = $this->get_from_get_post("pid");
		$this->password = $this->get_from_get_post("password");
		$this->jobs = array();
	}

	public function doAction() {
		$project_data = getProjectData($this->pid, $this->password);

		$lang_handler = Languages::getInstance();

		if (empty($project_data)) {
			$this->project_not_found = true;
		}
		foreach ($project_data as &$pdata) {
                    
			$this->num_segments+=$pdata['total_segments'];
			if (empty($this->pname)) {
				$this->pname = $pdata['name'];
			}

			if (empty($this->project_status)) {
				$this->project_status = $pdata['status_analysis'];
			}

			if ($this->tm_analysis_wc == 0) {
				$this->tm_analysis_wc = $pdata['tm_analysis_wc'];
			}

			if ($this->standard_analysis_wc == 0) {
				$this->standard_analysis_wc = $pdata['standard_analysis_wc'];
			}

			if ($this->tm_analysis_wc == 0) {
				$this->tm_analysis_wc = $pdata['fast_analysis_wc'];
			}

			$this->tm_analysis_wc_print = number_format($this->tm_analysis_wc, 0, ".", ",");

			if ($this->fast_analysis_wc == 0) {
				$this->fast_analysis_wc = $pdata['fast_analysis_wc'];
				$this->fast_analysis_wc_print = number_format($this->fast_analysis_wc, 0, ".", ",");
			}

			// if zero then print empty instead of 0
			if ($this->standard_analysis_wc == 0) {
				$this->standard_analysis_wc_print = "";
			}

			if ($this->fast_analysis_wc == 0) {
				$this->fast_analysis_wc_print = "";
			}

			if ($this->tm_analysis_wc == 0) {
				$this->tm_analysis_wc_print = "";
			}


			$this->total_raw_word_count+=$pdata['file_raw_word_count'];
			$pdata['file_eq_word_count'] = number_format($pdata['file_eq_word_count'], 0, ".", ",");

			if (!array_key_exists("jid", $pdata)) {
				$this->jobs[$pdata['jid']] = array("password" => "", "files" => array());
			}

			$jid = $pdata['jid'];
        
			$source = $lang_handler->getLocalizedName( $pdata['source'] );
			$target = $lang_handler->getLocalizedName( $pdata['target'] );
		
			$source_short = $pdata['source'];
			$target_short = $pdata['target'];

			$password = $pdata['jpassword'];
			unset($pdata['name']);
			unset($pdata['source']);
			unset($pdata['target']);
			unset($pdata['jid']);
			unset($pdata['jpassword']);

			$this->jobs[$jid]['source'] = $source;
			$this->jobs[$jid]['target'] = $target;
			$this->jobs[$jid]['source_short'] = $source_short;
			$this->jobs[$jid]['target_short'] = $target_short;
			$this->jobs[$jid]['password'] = $password;

            if (!array_key_exists("total_raw_word_count", $this->jobs[$jid])){
                $this->jobs[$jid]['total_raw_word_count']=0;
            }

			//calculate total word counts per job (summing different files)
			$this->jobs[$jid]['total_raw_word_count']+=$pdata['file_raw_word_count'];
			//format the total (yeah, it's ugly doing it every cycle)
			$this->jobs[$jid]['total_raw_word_count_print']=number_format($this->jobs[$jid]['total_raw_word_count'],0,".",",");

			$pdata['file_raw_word_count'] = number_format($pdata['file_raw_word_count'],0,".",",");
			$this->jobs[$jid]['files'][] = $pdata;
		}

		$raw_wc_time = $this->total_raw_word_count / INIT::$ANALYSIS_WORDS_PER_DAYS;
		$tm_wc_time = $this->tm_analysis_wc / INIT::$ANALYSIS_WORDS_PER_DAYS;
		$fast_wc_time = $this->fast_analysis_wc / INIT::$ANALYSIS_WORDS_PER_DAYS;

		$raw_wc_unit = 'day';
		$tm_wc_unit = 'day';
		$fast_wc_unit = 'day';

		if ($raw_wc_time > 0 and $raw_wc_time < 1) {
			$raw_wc_time*=8; //convert to hours (1 work day = 8 hours)
			$raw_wc_unit = 'hour';
		}

		if ($raw_wc_time > 0 and $raw_wc_time < 1) {
			$raw_wc_time*=60; //convert to minutes
			$raw_wc_unit = 'minute';
		}

		if ($raw_wc_time > 1) {
			$raw_wc_unit.= 's';
		}


		if ($tm_wc_time > 0 and $tm_wc_time < 1) {
			$tm_wc_time*=8; //convert to hours (1 work day = 8 hours)
			$tm_wc_unit = 'hour';
		}

		if ($tm_wc_time > 0 and $tm_wc_time < 1) {
			$tm_wc_time*=60; //convert to minutes
			$tm_wc_unit = 'minute';
		}

		if ($tm_wc_time > 1) {
			$tm_wc_unit.= 's';
		}

		if ($fast_wc_time > 0 and $fast_wc_time < 1) {
			$fast_wc_time*=8; //convert to hours (1 work day = 8 hours)
			$fast_wc_unit = 'hour';
		}

		if ($fast_wc_time > 0 and $fast_wc_time < 1) {
			$fast_wc_time*=60; //convert to minutes
			$fast_wc_unit = 'minute';
		}

		if ($fast_wc_time > 1) {
			$fast_wc_unit.= 's';
		}

		$this->raw_wc_time = ceil($raw_wc_time);
		$this->fast_wc_time = ceil($fast_wc_time);
		$this->tm_wc_time = ceil($tm_wc_time);
		$this->raw_wc_unit = $raw_wc_unit;
		$this->tm_wc_unit = $tm_wc_unit;
		$this->fast_wc_unit = $fast_wc_unit;


		if ($this->raw_wc_time == 8 and $this->raw_wc_unit == "hours") {
			$this->raw_wc_time = 1;
			$this->raw_wc_unit = "day";
		}
		if ($this->raw_wc_time == 60 and $this->raw_wc_unit == "minutes") {
			$this->raw_wc_time = 1;
			$this->raw_wc_unit = "hour";
		}

		if ($this->fast_wc_time == 8 and $this->fast_wc_time == "hours") {
			$this->fast_wc_time = 1;
			$this->fast_wc_time = "day";
		}
		if ($this->tm_wc_time == 60 and $this->tm_wc_time == "minutes") {
			$this->tm_wc_time = 1;
			$this->tm_wc_time = "hour";
		}

		if ($this->total_raw_word_count == 0) {
			$this->total_raw_word_count_print = "";
		} else {
			$this->total_raw_word_count_print = number_format($this->total_raw_word_count, 0, ".", ",");
		}

	}

    public function setTemplateVars() {

        $this->template->jobs                       = $this->jobs;
        $this->template->fast_analysis_wc           = $this->fast_analysis_wc;
        $this->template->fast_analysis_wc_print     = $this->fast_analysis_wc_print;
        $this->template->tm_analysis_wc             = $this->tm_analysis_wc;
        $this->template->tm_analysis_wc_print       = $this->tm_analysis_wc_print;
        $this->template->standard_analysis_wc       = $this->standard_analysis_wc;
        $this->template->standard_analysis_wc_print = $this->standard_analysis_wc_print;
        $this->template->total_raw_word_count       = $this->total_raw_word_count;
        $this->template->total_raw_word_count_print = $this->total_raw_word_count_print;
        $this->template->pname                      = $this->pname;
        $this->template->pid                        = $this->pid;
        $this->template->project_not_found          = $this->project_not_found;
        $this->template->fast_wc_time               = $this->fast_wc_time;
        $this->template->tm_wc_time                 = $this->tm_wc_time;
        $this->template->tm_wc_unit                 = $this->tm_wc_unit;
        $this->template->fast_wc_unit               = $this->fast_wc_unit;
        $this->template->standard_wc_unit           = $this->standard_wc_unit;
        $this->template->raw_wc_time                = $this->raw_wc_time;
        $this->template->standard_wc_time           = $this->standard_wc_time;
        $this->template->raw_wc_unit                = $this->raw_wc_unit;
        $this->template->project_status             = $this->project_status;
        $this->template->num_segments               = $this->num_segments;
        $this->template->num_segments_analyzed      = $this->num_segments_analyzed;
        $this->template->logged_user                = trim( $this->logged_user[ 'first_name' ] . " " . $this->logged_user[ 'last_name' ] );
        $this->template->build_number               = INIT::$BUILD_NUMBER;

        $this->template->isLoggedIn                 = $this->isLoggedIn();

        if( isset($_SESSION['_anonym_pid']) && ! empty($_SESSION['_anonym_pid'])  ){
            $_SESSION['incomingUrl'] = INIT::$HTTPHOST . $_SERVER['REQUEST_URI'];
            $this->template->showModalBoxLogin      = true;
        } else {
            $this->template->showModalBoxLogin      = false;
        }

        //print_r ($this->template); exit;

    }

}

?>
