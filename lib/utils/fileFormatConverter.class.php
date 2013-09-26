<?php

set_time_limit(0);
define ("BOM","\xEF\xBB\xBF");

include INIT::$UTILS_ROOT . "/langs/languages.class.php";


class fileFormatConverter {

	private $ip; //current converter chosen for this job
	private $port = "8732"; //port the convertrs listen to
	private $toXliffFunction = "AutomationService/original2xliff"; //action string for the converters to convert to XLIFF
	private $fromXliffFunction = "AutomationService/xliff2original";//action string for the converters to convert to original
	private $opt = array(); //curl options
	private $lang_handler; //object that exposes language utilities
	private $converters; //list of available converters

	public function __construct() {
		if (!class_exists("INIT")) {
			include_once ("../../inc/config.inc.php");
			INIT::obtain();
		}
		$this->opt['httpheader'] = array("Content-Type: application/x-www-form-urlencoded;charset=UTF-8");
		$this->lang_handler=  Languages::getInstance();

		$this->converters=array('10.11.0.10'=>1,'10.11.0.18'=>1,'10.11.0.26'=>1,'10.11.0.34'=>1,'10.11.0.42'=>1);
	//	$this->converters=array('10.30.1.247'=>1);//forcing a particular VM just for debugging purposes
	}

	//add UTF-8 BOM
	private function addBOM($string) {
		return BOM . $string;
	}

	//check if it has BOM
	private function hasBOM($string) {
		return (substr($string, 0, 3) == BOM);
	}

	//get a converter at random, weighted on number of CPUs per node
	private function pickRandConverter(){
		//get total cpu count
		$cpus=array_values($this->converters);
		$tot_cpu=0;
		foreach($cpus as $cpu){
			$tot_cpu+=$cpu;
		}
		unset($cpus);

		//pick random
		$num=rand(0,$tot_cpu-1);

		//scroll in a roulette fashion through node->#cpu list until you stop on a cpu
		/*
		   imagine an array: each node has a number of cells on it equivalent to # of cpus; the random number is the cell on which to stop
		   scroll the list_of_nodes, decrementing the random number with number of cpus; 
		   if any time the random is 0, pick that node; 
		   otherwise, keep scrolling
		 */
		$picked_node='';
		foreach($this->converters as $node=>$cpus){
			$num-=$cpus;
			if($num<=0){
				//current node is the one; break
				$picked_node=$node;
				break;
			}
		}

		return $picked_node;
	}

	private function checkNodeLoad($ip){
		//connect
		$ch=curl_init("$ip:8082");
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		//execute
		$res="";
		//since sometimes it can fail, try again util we get something meaningful
		while(strlen($res)==0){
		$res=curl_exec($ch);
		}
		//close
		curl_close($ch);
		//parse response
		$processes=json_decode($res,true);
		//sum up total machine load
		foreach($processes as $process){
			$top+=$process[0];
		}

		//zero load is impossible (at least, there should be the java monitor); try again
		if(0==$top){
			log::doLog("suspicious zero load for $ip, recursive call");
			usleep(500*1000); //200ms
			$top=$this->checkNodeLoad($ip);
		}
		
		return $top;
	}

	private function pickIdlestConverter(){
		//scan each server load
		foreach($this->converters as $ip=>$weight){
			$load=$this->checkNodeLoad($ip);
			log::doLog("load for $ip is $load");
			//add load as numeric index to an array
			$loadList["".(10*(float)$load)]=$ip;
		}
		//sort to pick lowest
		ksort($loadList,SORT_NUMERIC);

		//pick lowest
		$ip=array_shift($loadList);
		return $ip;
	}

	private function extractUidandExt(&$content) {
		$pattern = '|<file original="\w:\\\\.*?\\\\.*?\\\\(.*?)\\\\(.*?)\.(.*?)".*?>|';
		$matches = array();
		preg_match($pattern, $content, $matches);

		return array($matches[1], $matches[3]);
	}

	private function is_assoc($array) {
		return is_array($array) AND (bool) count(array_filter(array_keys($array), 'is_string'));
	}

	private function parseOutput($res) {
		$ret = array();
		$ret['isSuccess'] = $res['isSuccess'];
		$is_success = $res['isSuccess'];
		if (!$is_success) {
			$ret['errorMessage'] = $res['errorMessage'];
			return $ret;
		}
		if (array_key_exists("documentContent", $res)) {
			$res['documentContent'] = base64_decode($res['documentContent']);
		}

		unset($res['errorMessage']);
		return $res;
	}

	private function curl_post($url, $d, $opt = array()) {
		if (!$this->is_assoc($d)) {
			throw new Exception("The input data to " . __FUNCTION__ . "must be an associative array", -1);
		}
		$ch = curl_init();

		$data = http_build_query($d);
		$d = null;

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_USERAGENT, "Matecat-Cattool/v" . INIT::$BUILD_NUMBER);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		if ($this->is_assoc($opt) and !empty($opt)) {
			foreach ($opt as $k => $v) {

				if (stripos($k, "curlopt_") === false or stripos($k, "curlopt_") !== 0) {
					$k = "curlopt_$k";
				}
				$const_name = strtoupper($k);
				if (defined($const_name)) {
					curl_setopt($ch, constant($const_name), $v);
				}
			}
		}


		$output = curl_exec($ch);

		$info = curl_getinfo($ch);

		// Chiude la risorsa curl
		curl_close($ch);
		return $output;
	}

	public function convertToSdlxliff($file_path, $source_lang, $target_lang, $chosen_by_user_machine=false) {
		if (!file_exists($file_path)) {
			throw new Exception("Conversion Error : the file <$file_path> not exists");
		}
		$fileContent = file_get_contents($file_path);
		$extension = pathinfo($file_path, PATHINFO_EXTENSION);
		$filename = pathinfo($file_path, PATHINFO_FILENAME);
		if (strtoupper($extension) == 'TXT') {
			$encoding=mb_detect_encoding($fileContent);
			if ($encoding!='UTF-8'){
				$fileContent=  iconv($encoding, "UTF-8", $fileContent);
			}

			if (!$this->hasBOM($fileContent)) {
				$fileContent = $this->addBOM($fileContent);
			}
		}


		$data['documentContent'] = base64_encode($fileContent);
		$fileContent = null;
		//assign converter
		if(!$chosen_by_user_machine){
			$this->ip=$this->pickIdlestConverter();
		}else{
			$this->ip=$chosen_by_user_machine;
		}

		$url = "$this->ip:$this->port/$this->toXliffFunction";

		$data['fileExtension'] = $extension;
		$data['fileName'] = "$filename.$extension";
		$data['sourceLocale'] = $this->lang_handler->getSDLStudioCode($source_lang);
		$data['targetLocale'] = $this->lang_handler->getSDLStudioCode($target_lang);

		log::doLog($this->ip." start conversion to xliff of $file_path");
		$start_time=time();
		$curl_result = $this->curl_post($url, $data, $this->opt);
		$end_time=time();
		$time_diff=$end_time-$start_time;
		log::doLog($this->ip." took $time_diff secs for $file_path");

		$decode = json_decode($curl_result, true);
		$curl_result = null;
		$res = $this->parseOutput($decode);

		return $res;
	}

	public function convertToOriginal($xliffContent, $chosen_by_user_machine=false) {

		$base64Content = base64_encode($xliffContent);

		//assign converter
		if(!$chosen_by_user_machine){
			$this->ip=$this->pickIdlestConverter();
		}else{
			$this->ip=$chosen_by_user_machine;
		}

		$url = "$this->ip:$this->port/$this->fromXliffFunction";

		$uid_ext = $this->extractUidandExt($xliffContent);
		$data['uid'] = $uid_ext[0];
		$data['xliffContent'] = $xliffContent;

		log::doLog($this->ip." start conversion back to original");
		$start_time=time();
		$curl_result = $this->curl_post($url, $data, $this->opt);
		$end_time=time();
		$time_diff=$end_time-$start_time;
		log::doLog($this->ip." took $time_diff secs");

		$decode = json_decode($curl_result, true);
		unset($curl_result);
		$res = $this->parseOutput($decode);
		unset($decode);


		return $res;
	}

}

?>
