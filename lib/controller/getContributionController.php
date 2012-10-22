<?php

//include INIT::$ROOT . "/lib/utils/mymemory_queries_temp.php";
include INIT::$UTILS_ROOT . "/engines/mt.class.php";
include INIT::$UTILS_ROOT . "/engines/tms.class.php";
include INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$MODEL_ROOT . "/queries.php";

class getContributionController extends ajaxcontroller {

    private $id_segment;
    private $id_job;
    private $num_results;
    private $text;
        
    private $source;
    private $target;
    private $id_mt_engine;
    private $id_tms;
    private $id_translator;

    public function __construct() {
        parent::__construct();

        $this->id_segment = $this->get_from_get_post('id_segment');
        $this->id_job = $this->get_from_get_post('id_job');
        $this->num_results = $this->get_from_get_post('num_results');
        $this->text = $this->get_from_get_post('text');
        $this->id_translator = $this->get_from_get_post('id_translator');

	if ($this->id_translator=='unknown_translator'){
		$this->id_translator="";	
	}
        
        
    }

    public function doAction() {
//         log::doLog('getContribution');
        if (empty($this->id_segment)) {
            $this->result['errors'][] = array("code" => -1, "message" => "missing id_segment");
        }

//log::doLog("text is $this->text");
        if (empty($this->text)) {
            $this->result['errors'][] = array("code" => -2, "message" => "missing text");
        }

        if (empty($this->num_results)) {
            $this->num_results = INIT::$DEFAULT_NUM_RESULTS_FROM_TM;
        }

        if (!empty($this->result['errors'])) {
            return -1;
        }
        
        //$st=getSourceTargetFromJob($this->id_job);
        $st = getJobData($this->id_job);
        
        $this->source=$st['source'];
        $this->target=$st['target'];
        $this->id_mt_engine = $st['id_mt_engine'];
        $this->id_tms = $st['id_tms'];

        $tms_match = array();
        if (!empty($this->id_tms)) {

            $mt_from_tms = 1;
            if (!empty($this->id_mt_engine)) {
                $mt_from_tms = 0;
            }
            $tms = new TMS($this->id_tms);
            $tms_match = $tms->get($this->text, $this->source, $this->target, "demo@matecat.com", $mt_from_tms, $this->id_translator);
        }
        // UNUSED
        $mt_res = array();
        $mt_match = "";
        if (!empty($this->id_mt_engine)) {

            $mt = new MT($this->id_mt_engine);
            $mt_result = $mt->get($this->text, $this->source, $this->target);
//	     log::doLog(__CLASS__." - mt engine result");
//	     log::doLog($mt_result);
//echo "MT";
//print_r($mt_result);

            if ($mt_result[0] < 0) {
                $mt_match = '';
        //$matches = $fake_matches;
            } else {
                $mt_match = $mt_result[1];
                $penalty = $mt->getPenalty();
                $mt_score = 100 - $penalty;
                $mt_score.="%";

                $mt_match_res = new TMS_GET_MATCHES($this->text, $mt_match, $mt_score, "MT-" . $mt->getName(), date("Y-m-d"));
                $mt_res = $mt_match_res->get_as_array();
            }
        }
        $matches=array();
		
        if (!empty($tms_match)) {
            $matches = $tms_match;
        }
// log::doLog ("mt matchpppppppppp");

        if (!empty($mt_match)) {
// log::doLog ("mt CE'");
            $matches[] = $mt_res;
            usort($matches, "compareScore");
        }
        $matches=array_slice ($matches,0,INIT::$DEFAULT_NUM_RESULTS_FROM_TM);
        $res = $this->setSuggestionReport($matches);
        if (is_array($res) and array_key_exists("error", $res)) {
            // log::doLog("store suggestion report error for segment $this->id_job-$this->id_segment");
        }
//echo "\n\n";
//print_r ($matches);
//echo "\n\n";
        foreach ($matches as &$match) {
            if (strpos($match['created_by'], 'MT') !== false) {
                $match['match'] = 'MT';
            }
            if ($match['created_by'] == 'MT!') {
                $match['created_by'] = 'MT'; //MyMemory returns MT!
            }
			$segSource = html_entity_decode($match['segment']);
			$match['segment'] = htmlspecialchars($segSource);
			$segTranslation = html_entity_decode($match['translation']);
			$match['translation'] = htmlspecialchars($segTranslation);			
        }
// log::doLog('MATCH: '.$matches[0]['match']);

        $this->result['data']['matches'] = $matches;
    }

	private function setSuggestionReport($matches) {
		if (count ($matches)>0) {
		        $suggestions_json_array = json_encode($matches);
	        	$match = $matches[0];
	        	$suggestion = $match['translation'];
	        	$suggestion_match = $match['match'];
	        	$suggestion_source = $match['created_by'];
	        	$ret = CatUtils::addTranslationSuggestion($this->id_segment, $this->id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source);
		        return $ret;
			}
		return 0;

    }

}

function compareScore($a, $b) {
    return floatval($a['match']) < floatval($b['match']);
}

?>
