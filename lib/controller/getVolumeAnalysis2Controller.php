<?php

include_once INIT::$MODEL_ROOT . "/queries.php";

include_once INIT::$UTILS_ROOT . "/cat.class.php";

class getVolumeAnalysis2Controller extends ajaxcontroller {

	private $id_project;

	public function __construct() {
		parent::__construct();
		$this->id_project = $this->get_from_get_post('id_project');

	}

	public function doAction() {
		if (empty($this->id_project)) {
			$this->result['errors'] = array(-1, "No id project provided");
			return -1;
		}

		$res = getProjectStatsVolumeAnalysis($this->id_project);
		if (empty($res)) {
			$this->result['errors'][] = array(-2, "No more elements found for project $this->id_project");
			return -2;
		}

		$return_data = array();
		$total_init = array("MT" => array(0,"0"), "NEW" => array(0,"0"), "TM_100" => array(0,"0"), "TM_75_99" => array(0,"0"), "internal_matches" => array(0,"0"), "ICE" => array(0,"0"), "repetitions" => array(0,"0"));

		foreach ($res as $r) {
			$jid = $r['jid'];
			$words = $r['eq_word_count'];

			if (!array_key_exists($jid, $return_data)) {
				$return_data[$jid] = array();
				$return_data[$jid]['totals'] = $total_init;
				$return_data[$jid]['file_details'] = array();
			}

			if (!array_key_exists($r['id_file'], $return_data[$jid]['file_details'])) {
				$return_data[$jid]['file_details']["1703"] = $total_init;
				$return_data[$jid]['file_details']["1704"] = $total_init;
			}

			if ($r['suggestion_source'] == "MT") {
				$w=$return_data[$jid]['file_details']["1703"]["MT"][0] + $words;
				$words_print = number_format($w, 0, ".", ",");
				$return_data[$jid]['file_details']["1703"]["MT"]=array($w, $words_print);

				$w=$return_data[$jid]['file_details']["1704"]["MT"][0] + $words;
				$words_print = number_format($w, 0, ".", ",");
				$return_data[$jid]['file_details']["1704"]["MT"]=array($w, $words_print);

				$w=$return_data[$jid]['totals']["MT"][0] + $words;
				$words_print = number_format($w, 0, ".", ",");
				$return_data[$jid]['totals']["MT"]=array($w, $words_print);
				continue;
			}

			if ($r['suggestion_match'] == 100) {
				$w=$return_data[$jid]['file_details']["1703"]["TM_100"][0] + $words;
				$words_print = number_format($w, 0, ".", ",");
				$return_data[$jid]['file_details']["1703"]["TM_100"]=array($w, $words_print);

				$w=$return_data[$jid]['file_details']["1704"]["TM_100"][0] + $words;
				$words_print = number_format($w, 0, ".", ",");
				$return_data[$jid]['file_details']["1704"]["TM_100"]=array($w, $words_print);

				$w=$return_data[$jid]['totals']["TM_100"][0] + $words;
				$words_print = number_format($w, 0, ".", ",");
				$return_data[$jid]['totals']["TM_100"]=array($w, $words_print);

				continue;
			}

			if ($r['suggestion_match'] >= 75 and $r['suggestion_match'] <= 99) {
				//$words_print = number_format($w, 0, ".", ",");
				//$return_data[$jid]['file_details'][$r['id_file']]["TM_75_99"]+=array($words,$words_print);

				$w=$return_data[$jid]['file_details']["1703"]["TM_75_99"][0] + $words;
				$words_print = number_format($w, 0, ".", ",");
				$return_data[$jid]['file_details']["1703"]["TM_75_99"]=array($w, $words_print);

				$w=$return_data[$jid]['file_details']["1704"]["TM_75_99"][0] + $words;
				$words_print = number_format($w, 0, ".", ",");
				$return_data[$jid]['file_details']["1704"]["TM_75_99"]=array($w, $words_print);

				$w=$return_data[$jid]['totals']["TM_75_99"][0] + $words;
				$words_print = number_format($w, 0, ".", ",");
				$return_data[$jid]['totals']["TM_75_99"]=array($w, $words_print);
				continue;
			}

			if ($r['suggestion_match'] < 75) {
				//$words_print = number_format($w, 0, ".", ",");
				//$return_data[$jid]['file_details'][$r['id_file']]["NEW"]+=array($words,$words_print);

				$w=$return_data[$jid]['file_details']["1703"]["NEW"][0]+ $words;
				$words_print = number_format($w, 0, ".", ",");
				$return_data[$jid]['file_details']["1703"]["NEW"][0]=array($w, $words_print);

				$w=$return_data[$jid]['file_details']["1704"]["NEW"][0] + $words;
				$words_print = number_format($w, 0, ".", ",");
				$return_data[$jid]['file_details']["1704"]["NEW"][0]=array($w, $words_print);

				$w=$return_data[$jid]['totals']["NEW"][0] + $words;
				$words_print = number_format($w, 0, ".", ",");
				$return_data[$jid]['totals']["NEW"][0]=array($w, $words_print);
				continue;
			}
		}
		//echo "<pre>";print_r ($return_data);exit;
		$this->result['data'] = $return_data;
	}

}

/*
   $a=new getVolumeAnalisysController();
   print_r ($a->doAction());
 */
?>
