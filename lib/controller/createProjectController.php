<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$ROOT . "/lib/utils/segmentExtractor.php";

define('DEFAULT_NUM_RESULTS', 2);

class createProjectController extends ajaxcontroller {

	private $file_name;
	private $project_name;
	private $source_language;
	private $target_language;
	private $mt_engine;
	private $tms_engine;
	private $private_tm_key;
	private $private_tm_user;
	private $private_tm_pass;
	private $analysis_status;

	public function __construct() {
		parent::__construct();
		$this->file_name = $this->get_from_get_post('file_name'); // da cambiare
		$this->project_name = $this->get_from_get_post('project_name');
		$this->source_language = $this->get_from_get_post('source_language');
		$this->target_language = $this->get_from_get_post('target_language');
		$this->mt_engine = $this->get_from_get_post('mt_engine'); // null è ammesso
		$this->tms_engine = $this->get_from_get_post('tms_engine'); // se empty allora MyMemory
		$this->private_tm_key = $this->get_from_get_post('private_tm_key');
		$this->private_tm_user = $this->get_from_get_post('private_tm_user');
		$this->private_tm_pass = $this->get_from_get_post('private_tm_pass');
		session_start();
	}

	public function __destruct(){
		session_write_close();
	}

	public function doAction() {

		if (empty($this->file_name)) {
			$this->result['errors'][] = array("code" => -1, "message" => "Missing file name.");
			return false;
		}
		$arFiles = explode('@@SEP@@', $this->file_name);
		$default_project_name = $arFiles[0];
		if (count($arFiles) > 1) {
			$default_project_name = "MATECAT_PROJ-" . date("Ymdhi");
		}


		if (empty($this->project_name)) {
			$this->project_name = $default_project_name; //'NO_NAME'.$this->create_project_name();
		}

		if (empty($this->source_language)) {
			$this->result['errors'][] = array("code" => -3, "message" => "Missing source language.");
			return false;
		}

		if (empty($this->target_language)) {
			$this->result['errors'][] = array("code" => -4, "message" => "Missing target language.");
			return false;
		}

		if (empty($this->tms_engine)) {
			$this->tms_engine = 1; // default MyMemory
		}

		$sourceLangHistory = $_COOKIE["sourceLang"];
		$targetLangHistory = $_COOKIE["targetLang"];

		// SET SOURCE COOKIE

		if ($sourceLangHistory == '_EMPTY_')
			$sourceLangHistory = "";
		$sourceLangAr = explode('||', urldecode($sourceLangHistory));

		if (($key = array_search($this->source_language, $sourceLangAr)) !== false) {
			unset($sourceLangAr[$key]);
		}
		//log::doLog('SOURCE LANG AR: ' , $sourceLangAr);
		array_unshift($sourceLangAr, $this->source_language);
		//log::doLog('SOURCE LANG AR 1: ' , $sourceLangAr);
		if ($sourceLangAr == '_EMPTY_')
			$sourceLangAr = "";
		$newCookieVal = "";
		$sourceLangAr = array_slice($sourceLangAr, 0, 3);
		$sourceLangAr = array_reverse($sourceLangAr);

		foreach ($sourceLangAr as $key => $link) {
			if ($sourceLangAr[$key] == '') {
				unset($sourceLangAr[$key]);
			}
		}

		foreach ($sourceLangAr as $lang) {
			if ($lang != "")
				$newCookieVal = $lang . "||" . $newCookieVal;
		}

		setcookie("sourceLang", $newCookieVal, time() + (86400 * 365));


		// SET TARGET COOKIE

		if ($targetLangHistory == '_EMPTY_')
			$targetLangHistory = "";
		$targetLangAr = explode('||', urldecode($targetLangHistory));

		if (($key = array_search($this->target_language, $targetLangAr)) !== false) {
			unset($targetLangAr[$key]);
		}
		//log::doLog('TARGET LANG AR: ' , $targetLangAr);
		array_unshift($targetLangAr, $this->target_language);
		//log::doLog('TARGET LANG AR 1: ' , $targetLangAr);
		if ($targetLangAr == '_EMPTY_')
			$targetLangAr = "";
		$newCookieVal = "";
		$targetLangAr = array_slice($targetLangAr, 0, 3);
		$targetLangAr = array_reverse($targetLangAr);

		foreach ($targetLangAr as $key => $link) {
			if ($targetLangAr[$key] == '') {
				unset($targetLangAr[$key]);
			}
		}

		foreach ($targetLangAr as $lang) {
			if ($lang != "")
				$newCookieVal = $lang . "||" . $newCookieVal;
		}

		setcookie("targetLang", $newCookieVal, time() + (86400 * 365));



		/*
		   $serializedArLang = $_COOKIE["languages"];
		   if($serializedArLang == '_EMPTY_') $serializedArLang = "";
		   $arLang = explode('||',urldecode($serializedArLang));

		   $prova = array("foo", "bar", "hallo", "world");
		   $provaSerialized = serialize($prova);
		   $provaUnserialized = unserialize($provaSerialized);

		 */

		/*
		//log::doLog('LANGUAGES COOKIE: ' . $serializedArLang);
		//log::doLog('ARLANG UNSERIALIZED LENGTH: ' . count($arLang));
		if($serializedArLang == '') {
		//			$newLangValue =
		}
		 */



		// aggiungi path file in caricamento al cookie"pending_upload"a
		// add her the cookie mangement for remembere the last 3 choosed languages
		// project name sanitize
		//$this->project_name = preg_replace('/["\' \(\)\&\[\]\{\}\+\*,:|#]/', "_", $this->project_name);
		$this->project_name = preg_replace('/[^\p{L}0-9a-zA-Z_\.\-]/u', "_", $this->project_name);
		$this->project_name = preg_replace('/[_]{2,}/', "_", $this->project_name);
		$this->project_name = str_replace('_.', ".", $this->project_name);
		//echo $this->project_name; 
		// project name validation        
		$pattern = "/^[\p{L}\ 0-9a-zA-Z_\.\-]+$/u";
		if (!preg_match($pattern, $this->project_name, $rr)) {
			$kkk = str_split($this->project_name);
			foreach ($kkk as $kk) {
				//log::doLog($kk, ord($kk));
			}
			$this->result['errors'][] = array("code" => -5, "message" => "Invalid Project Name $this->project_name: it should only contain numbers and letters!");
			//	        $this->result['project_name_error'] = $this->project_name;
			return false;
		}

		// create project
		$analysis_status = (INIT::$VOLUME_ANALYSIS_ENABLED) ? 'NEW' : 'NOT_TO_ANALYZE';
		$ppassword = CatUtils::generate_password();

		$ip = Utils::getRealIpAddr();

		$id_customer='translated_user';

		$pid = insertProject($id_customer, $this->project_name, $analysis_status, $ppassword, $ip);
		//create user (Massidda 2013-01-24)
		//this is done only if an API key is provided
		if (!empty($this->private_tm_key)) {
			//the base case is when the user clicks on "generate private TM" button: 
			//a (user, pass, key) tuple is generated and can be inserted
			//if it comes with it's own key without querying the creation api, create a (key,key,key) user 
			if (empty($this->private_tm_user)) {
				$this->private_tm_user = $this->private_tm_key;
				$this->private_tm_pass = $this->private_tm_key;
			}
			$user_id = insertTranslator($this->private_tm_user, $this->private_tm_pass, $this->private_tm_key);
			$this->private_tm_user = $user_id;
		}


		$intDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/upload/' . $_COOKIE['upload_session'];
		$fidList=array();
		foreach ($arFiles as $file) {


			$fileSplit = explode('.', $file);
			$mimeType = strtolower($fileSplit[count($fileSplit) - 1]);
			//echo $mimeType; exit;
			//log::doLog('MIMETYPE: ' . $mimeType);

			$original_content = "";
			if (($mimeType != 'sdlxliff') && ($mimeType != 'xliff') && ($mimeType != 'xlf') && (INIT::$CONVERSION_ENABLED)) {
				//log::doLog('NON XLIFF');
				$fileDir = $intDir . '_converted';
				$filename_to_catch = $file . '.sdlxliff';

				$original_content = file_get_contents("$intDir/$file");
				$sha1_original = sha1($original_content);
				//unset($original_content);
			} else {
				$sha1_original = "";
				$fileDir = $intDir;
				$filename_to_catch = $file;
			}

			if (!empty($original_content)) {
				$original_content = gzdeflate($original_content, 5);
			}

			$filename = $fileDir . '/' . $filename_to_catch;
			//echo $filename;exit;
			//log::doLog('FILENAME: ' . $filename);

			if (!file_exists($filename)) {
				$this->result['errors'][] = array("code" => -6, "message" => "File not found on server after upload.");
			}
			$contents = file_get_contents($filename);
			$fid = insertFile($pid, $file, $this->source_language, $mimeType, $contents, $sha1_original, $original_content);
			$fidList[]=$fid;


			$insertSegments = extractSegments($fileDir, $filename_to_catch, $pid, $fid);
		}

		//create job


		$this->target_language = explode(',',$this->target_language);

		foreach ($this->target_language as $target){
			$password = CatUtils::generate_password();

			//if user is logged, create the project on his behalf
			if(isset($_SESSION['cid']) and !empty($_SESSION['cid'])){
				$owner=$_SESSION['cid'];
			}else{
				//default user
				$owner='';
			}
			$jid = insertJob($password, $pid, $this->private_tm_user, $this->source_language, $target, $this->mt_engine, $this->tms_engine,$owner);
			foreach ($fidList as $fid){
				insertFilesJob($jid, $fid);
			}
		}



		//log::doLog('DELETING DIR: ' . $intDir);
		$this->deleteDir($intDir);
		if (is_dir($intDir . '_converted')) {
			$this->deleteDir($intDir . '_converted');
		}


		if ($insertSegments == 1) {
			changeProjectStatus($pid, "NEW");
			$this->result['code'] = 1;
			$this->result['data'] = "OK";
			$this->result['password'] = $password;
			$this->result['ppassword'] = $ppassword;
			$this->result['id_job'] = $jid;
			$this->result['id_project'] = $pid;
			$this->result['project_name'] = $this->project_name;
			$this->result['source_language'] = $this->source_language;
			$this->result['target_language'] = $this->target_language;
		} else {
			if ($insertSegments == -1) {
				$this->result['errors'][] = array("code" => -7, "message" => "No segments found in your XLIFF file. ($file)");
			} else {
				$this->result['errors'][] = array("code" => -7, "message" => "Not able to import this XLIFF file. ($file)");
			}
		}
		// tolgo la path in pending_uploads

		//log::doLog($this->result);

		// print_r ( $this->result); exit;
		setcookie("upload_session", "", time() - 10000);
	}

	//    public function create_project_name($namespace = '') {
	//        return "";
	//        static $guid = '';
	//        $uid = uniqid("", true);
	//        $data = $namespace;
	//        $data .= $_SERVER['REQUEST_TIME'];
	//        $data .= $_SERVER['HTTP_USER_AGENT'];
	//        $data .= $_SERVER['REMOTE_ADDR'];
	//        $hash = strtoupper(hash('ripemd128', $uid . $guid . md5($data)));
	//        $guid = '' .
	//                substr($hash, 0, 2) .
	//                '' .
	//                substr($hash, 2, 2) .
	//                '' .
	//                substr($hash, 4, 2) .
	//                '' .
	//                substr($hash, 6, 2) .
	//                '' .
	//                substr($hash, 8, 2) .
	//                '';
	//        return "-$guid";
	//    }


	public static function deleteDir($dirPath) {
		return true;
		if (!is_dir($dirPath)) {
			throw new InvalidArgumentException('$dirPath must be a directory.');
		}
		if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
			$dirPath .= '/';
		}
		$files = glob($dirPath . '*', GLOB_MARK);
		foreach ($files as $file) {
			if (is_dir($file)) {
				self::deleteDir($file);
			} else {
				unlink($file);
			}
		}
		rmdir($dirPath);
	}

}

?>

