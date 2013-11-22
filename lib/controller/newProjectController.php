<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/langs/languages.class.php";


class newProjectController extends viewcontroller {

	private $guid = '';
	private $mt_engines;
	private $tms_engines;
	private $lang_handler;

        private $sourceLangArray=array();
        private $targetLangArray=array();
	public function __construct() {
		parent::__construct(false);
		if (!isset($_REQUEST['fork'])) {
			parent::makeTemplate("upload.html");
		} else {
			parent::makeTemplate("upload_cloud.html");
		}
		$this->guid = $this->create_guid();
		$this->lang_handler=Languages::getInstance();
	}

	public function doAction() {
		if (!isset($_COOKIE['upload_session'])) {            
			setcookie("upload_session", $this->guid, time() + 86400);
		} else {
			$this->guid = $_COOKIE['upload_session'];
		}

		if (isset ($_COOKIE["sourceLang"]) and $_COOKIE["sourceLang"] == "_EMPTY_") {
			$this->noSourceLangHistory = true;
		} else if (!isset($_COOKIE['sourceLang'])) {   
			setcookie("sourceLang", "_EMPTY_", time() + (86400 * 365));
			$this->noSourceLangHistory = true;
		} else if($_COOKIE["sourceLang"] != "_EMPTY_") {
			$this->noSourceLangHistory = false;
			$this->sourceLangHistory = $_COOKIE["sourceLang"];
			$this->sourceLangAr = explode('||',urldecode($this->sourceLangHistory));
			$tmpSourceAr = array();
			$tmpSourceArAs = array();
			foreach($this->sourceLangAr as $key=>$lang) {
				if($lang != '')	{
					$tmpSourceAr[$lang] = $this->lang_handler->getLocalizedName($lang);

					$ar = array();
					$ar['name'] = $this->lang_handler->getLocalizedName($lang);
					$ar['code'] = $lang;
					$ar['selected'] = ($key == '0')? 1 : 0;
					array_push($tmpSourceArAs, $ar);						
				}
			}
			$this->sourceLangAr = $tmpSourceAr;
			asort($this->sourceLangAr);

			$this->array_sort_by_column($tmpSourceArAs, 'name');
			$this->sourceLangArray = $tmpSourceArAs;

		}

		if (isset($_COOKIE["targetLang"]) and $_COOKIE["targetLang"] == "_EMPTY_") {
			$this->noTargetLangHistory = true;
		} else if (!isset($_COOKIE['targetLang'])) {
			setcookie("targetLang", "_EMPTY_", time() + (86400 * 365));
			$this->noTargetLangHistory = true;
		} else if($_COOKIE["targetLang"] != "_EMPTY_") {
			$this->noTargetLangHistory = false;
			$this->targetLangHistory = $_COOKIE["targetLang"];
			$this->targetLangAr = explode('||',urldecode($this->targetLangHistory));

			$tmpTargetAr = array();
			$tmpTargetArAs = array();

			foreach($this->targetLangAr as $key=>$lang) {
				if($lang != '')	{
					$prova = explode(',',urldecode($lang));	

					$cl = "";
					foreach($prova as $ll) {
						$cl .= $this->lang_handler->getLocalizedName($ll).',';
					}
					$cl = substr_replace($cl ,"",-1);


					$tmpTargetAr[$lang] = $cl;
					//					$tmpTargetAr[$lang] = $this->lang_handler->getLocalizedName($lang,'en');

					$ar = array();
					$ar['name'] = $cl;
					$ar['code'] = $lang;
					$ar['selected'] = ($key == '0')? 1 : 0;
					array_push($tmpTargetArAs, $ar);						
				}
			}
			$this->targetLangAr = $tmpTargetAr;
			asort($this->targetLangAr);

			$this->array_sort_by_column($tmpTargetArAs, 'name');
			$this->targetLangArray = $tmpTargetArAs;

		}

		$intDir = INIT::$UPLOAD_REPOSITORY.'/'.$this->guid.'/';
		if (!is_dir($intDir)) {
			mkdir($intDir, 0775, true);

			// ANTONIO: le due istruzioni seguenti non funzionano
			// ma sarebbe opportuno che i permessi fossero quelli indicati nelle istruzioni in oggetto
			//chown($intDir, "matecat");
			//chgrp($intDir, "matecat");
		}

		$this->mt_engines = getEngines('MT');
		$this->tms_engines = getEngines('TM');
	}

	public function sortByOrder($a, $b) {
		return strcmp($a["name"], $b["name"]);

		//    	return $b['name'] - $a['name'];
	}

	public function array_sort_by_column(&$arr, $col, $dir = SORT_ASC) {
		$sort_col = array();
		foreach ($arr as $key=> $row) {
			$sort_col[$key] = $row[$col];
		}

		array_multisort($sort_col, $dir, $arr);
	}


	private function getExtensions($default = false) {
		$ext_ret = "";
		foreach (INIT::$SUPPORTED_FILE_TYPES as $k => $v) {
			foreach ($v as $kk => $vv) {
				if ($default) {
					if ($vv[0] != 'default') {
						continue;
					}
				}
				$ext_ret.="$kk|";
			}
		}
		$ext_ret = rtrim($ext_ret, "|");

		return $ext_ret;
	}

	private function getExtensionsUnsupported() {
		$ext_ret = array();
		foreach (INIT::$UNSUPPORTED_FILE_TYPES as $kk => $vv) {
			if (!isset($vv[1]) or empty($vv[1])) {
				continue;
			}
			$ext_ret[] = array("format" => "$kk", "message" => "$vv[1]");
		}
		$json = json_encode($ext_ret);

		return $json;
	}

	private function countExtensions() {
		$count = 0;
		foreach (INIT::$SUPPORTED_FILE_TYPES as $key => $value) {
			$count+=count($value);
		}
		return $count;
	}

	private function getCategories($output = "array") {
		$ret = array();
		foreach (INIT::$SUPPORTED_FILE_TYPES as $key => $value) {
			$val=  array_chunk(array_keys($value), 12);
			$ret[$key]=$val;
		}
		if ($output == "json") {
			return json_encode($ret);
		}
		return $ret;
	}

	public function setTemplateVars() {

        $this->template->languages          = $this->lang_handler->getEnabledLanguages( 'en' );
        $this->template->upload_session_id  = $this->guid;
        $this->template->mt_engines         = $this->mt_engines;
        $this->template->tms_engines        = $this->tms_engines;
        $this->template->conversion_enabled = INIT::$CONVERSION_ENABLED;
        if ( INIT::$CONVERSION_ENABLED ) {
            $this->template->allowed_file_types = $this->getExtensions( "" );
        } else {
            $this->template->allowed_file_types = $this->getExtensions( "default" );
        }

        $this->template->supported_file_types_array = $this->getCategories();
        $this->template->unsupported_file_types     = $this->getExtensionsUnsupported();
        $this->template->formats_number             = $this->countExtensions();
        $this->template->volume_analysis_enabled    = INIT::$VOLUME_ANALYSIS_ENABLED;
        $this->template->sourceLangHistory          = $this->sourceLangArray;
        $this->template->targetLangHistory          = $this->targetLangArray;
        $this->template->noSourceLangHistory        = $this->noSourceLangHistory;
        $this->template->noTargetLangHistory        = $this->noTargetLangHistory;
        $this->template->logged_user                = trim( $this->logged_user[ 'first_name' ] . " " . $this->logged_user[ 'last_name' ] );
        $this->template->build_number               = INIT::$BUILD_NUMBER;
        $this->template->maxFileSize               = INIT::$MAX_UPLOAD_FILE_SIZE;
        $this->template->maxNumberFiles             = INIT::$MAX_NUM_FILES;
        $this->template->incomingUrl                = '/login?incomingUrl=' . $_SERVER[ 'REQUEST_URI' ];

	}

	public function create_guid($namespace = '') {
		static $guid = '';
		$uid = uniqid("", true);
		$data = $namespace;
		$data .= $_SERVER['REQUEST_TIME'];
		$data .= $_SERVER['HTTP_USER_AGENT'];
		if (isset($_SERVER['LOCAL_ADDR'])) {
			$data .= $_SERVER['LOCAL_ADDR']; // Windows only
		}
		if (isset($_SERVER['LOCAL_PORT'])) {
			$data .= $_SERVER['LOCAL_PORT']; // Windows only
		}
		$data .= $_SERVER['REMOTE_ADDR'];
		$data .= $_SERVER['REMOTE_PORT'];
		$hash = strtoupper(hash('ripemd128', $uid . $guid . md5($data)));
		$guid = '{' .
			substr($hash, 0, 8) .
			'-' .
			substr($hash, 8, 4) .
			'-' .
			substr($hash, 12, 4) .
			'-' .
			substr($hash, 16, 4) .
			'-' .
			substr($hash, 20, 12) .
			'}';
		return $guid;
	}


}

?>
