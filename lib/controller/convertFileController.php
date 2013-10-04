<?php

set_time_limit(0);

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$UTILS_ROOT . "/fileFormatConverter.class.php";

class convertFileController extends ajaxcontroller {

	private $file_name;
	private $source_lang;
	private $target_lang;

	private $cache_days=10;

	private $intDir;
	private $errDir;

	public function __construct() {
		parent::__construct();
		$this->file_name = $this->get_from_get_post('file_name');
		$this->source_lang = $this->get_from_get_post("source_lang");
		$this->target_lang = $this->get_from_get_post("target_lang");

		$this->intDir = INIT::$UPLOAD_REPOSITORY.'/' . $_COOKIE['upload_session'];
		$this->errDir = INIT::$STORAGE_DIR.'/conversion_errors/' . $_COOKIE['upload_session'];

	}

	public function doAction() {
		if (empty($this->file_name)) {
			$this->result['errors'][] = array("code" => -1, "message" => "Error: missing file name.");
			return false;
		}



		$ext = pathinfo($this->file_name, PATHINFO_EXTENSION);

		$file_path = $this->intDir . '/' . $this->file_name;

		if (!file_exists($file_path)) {
			$this->result['errors'][] = array("code" => -6, "message" => "Error during upload. Please retry.");
			return -1;
		}
		$original_content = file_get_contents($file_path);
		$sha1 = sha1($original_content);

		if( INIT::$SAVE_SHASUM_FOR_FILES_LOADED ){
		$xliffContent = getXliffBySHA1($sha1, $this->source_lang, $this->target_lang,$this->cache_days);
		}
		if (!empty($xliffContent)) {
			$xliffContent=  gzinflate($xliffContent);
			$res = $this->put_xliff_on_file($xliffContent, $this->intDir);
			if ($res == -1) {
				return -1;
			}
			return 0;
		} else {
			$original_content_zipped = gzdeflate($original_content, 5);
			unset($original_content);

			$converter = new fileFormatConverter();
			if(strpos($this->target_lang,',')!==FALSE){
				$single_language=explode(',',$this->target_lang);
				$single_language=$single_language[0];
			}else{
				$single_language=$this->target_lang;

			}
			$convertResult = $converter->convertToSdlxliff($file_path, $this->source_lang, $single_language);

			if ($convertResult['isSuccess'] == 1) {
				//$uid = $convertResult['uid']; // va inserito nel database
				$xliffContent = $convertResult['xliffContent'];
				$xliffContentZipped = gzdeflate($xliffContent, 5);
				/* if (!$this->checkSegmentsNumber($xliffContent)) {
				   $this->result['code'] = 0;
				   $this->result['errors'][] = array("code" => -2, "message" => 'No segments found in this file!');
				   unlink($file_path);
				   return -1;
				   }
				   if (!is_dir($this->intDir . "_converted")) {
				   mkdir($this->intDir . "_converted");
				   };
				   $this->result['code'] = 1;

				   file_put_contents("$this->intDir" . "_converted/$this->file_name.sdlxliff", $xliffContent);
				 * 
				 */
				if( INIT::$SAVE_SHASUM_FOR_FILES_LOADED ){
					$res_insert = insertFileIntoMap($sha1, $this->source_lang, $this->target_lang, $original_content_zipped, $xliffContentZipped);
				}
				unset ($xliffContentZipped);
				$res = $this->put_xliff_on_file($xliffContent, $this->intDir);
				if ($res == -1) {
					return -1;
				}
				return 0;
			} else {
				if (stripos($convertResult['errorMessage'] ,"failed to create SDLXLIFF.")!==false) {
					$convertResult['errorMessage'] = "Error: failed importing file.";
				}
				$this->result['code'] = 0;
				$this->result['errors'][] = array("code" => -1, "message" => $convertResult['errorMessage']);
				log::doLog("ERROR MESSAGE : " . $convertResult['errorMessage']);


				$this->notifyError();



				return -1;
			}
		}
	}


	private function notifyError(){
		if (!is_dir($this->errDir)){
			mkdir ($this->errDir,0755,true);
		}
		rename("$this->intDir/$this->file_name", "$this->errDir/$this->file_name");

		$remote_user=(isset($_SERVER['REMOTE_USER']))?$_SERVER['REMOTE_USER']:"N/A";
		$link_file="http://".$_SERVER['SERVER_NAME']."/".INIT::$CONVERSIONERRORS_REPOSITORY_WEB."/".$_COOKIE['upload_session']."/". rawurlencode($this->file_name);    
		$subject="MATECAT : conversion error notifier";
		$message="Details:
			- source : $this->source_lang
			- target : $this->target_lang
			- client ip : ".$_SERVER['REMOTE_ADDR']."
			- client user (if any used) : $remote_user

						       Download file clicking to $link_file
							       ";
		$this->send_mail("Matecat Alert System", "webmanager@translated.net", "Antonio Farina", "antonio@translated.net", $subject, $message);
		$this->send_mail("Matecat Alert System", "webmanager@translated.net", "Alessandro Cattelan", "alessandro@translated.net", $subject, $message);
		$this->send_mail("Matecat Alert System", "webmanager@translated.net", "Marco Trombetti", "marco@translated.net", $subject, $message);




	}

	private function put_xliff_on_file($xliffContent) {
		$file_path = $this->intDir . '/' . $this->file_name;
		if (!$this->checkSegmentsNumber($xliffContent)) {
			$this->result['code'] = 0;
			$this->result['errors'][] = array("code" => -2, "message" => 'Error: no segments found in this file!');
			unlink($file_path);
			return -1;
		}
		if (!is_dir($this->intDir . "_converted")) {
			mkdir($this->intDir . "_converted");
		};

		file_put_contents("$this->intDir" . "_converted/$this->file_name.sdlxliff", $xliffContent);
		$this->result['code'] = 1;
		return 1;
	}

	private function checkSegmentsNumber($xliffContent) {
		return 1; // this function is bypassed because this is not the right way to tempt to find translatable content: the g tag could not appear but the file could still contain translatable content
		$found = preg_match_all('/<g id="[^"]+">/', $xliffContent, $res);
		if (!$found or $found == 0) {
			return 0;
		}
		return 1; //segnaposto
	}








	// TEMPORARY LOCATION
	/* move to a class dedicated to the email sending*/

	private function send_mail($from_name, $from_email, $to_name, $to_email, $subject, $message, $charset = "utf-8") {  //my mails are not spam!!
		$all_emails = split("[ \,]", trim($to_email));
		$all_emails = array_filter($all_emails, 'trim');

		$from_name = str_replace(',', ' ', $from_name);

		$from_email_temp = split("[ \,]", trim($from_email));
		$from_email = $from_email_temp[0];

		// per garantire questi 10 traduttori che hanno due email
		foreach ($all_emails as $to_email) {
			$headers = "MIME-Version: 1.0\r\n";
			$headers .= "Content-type: text/plain; charset=" . $charset . "\r\n";
			$headers .= "X-Mailer: Translated Mailer\r\n";
			$headers .= "X-Sender: <" . $from_email . ">\r\n";
			$headers .= "Return-Path: <" . $from_email . ">\r\n";
			$headers .= "From: " . $from_name . " <" . $from_email . ">\r\n";
			$headers .= "To: " . $to_name . " <" . $to_email . ">\r\n";
			//              $headers .= "Bcc: $from_email\r\n";
			$result = $this->mailfrom($from_email, $to_email, $subject, $message, $headers, "ONLY_HEADERS");
			if (!$result) {
				return false;
			}   // SE ANCHE UN SOLO INDIRIZZO DA ERRORE DO ERRORE!
		}
		// BUG: BISOGNA LEGGERE LE SPECS SENDMAIL PER SAPERE SE E' ANDATO....
		return $result;
	}

	private function mailfrom($fromaddress, $toaddress, $subject, $body, $headers, $add_headers = "ADD_HEADERS") {
		$fp = popen('/usr/sbin/sendmail -f' . $fromaddress . ' ' . $toaddress, "w");
		if (!$fp)
			return false;

		if ($add_headers <> "ONLY_HEADERS") { // se headers contiene il to:
			fputs($fp, "To: $toaddress\n");
		}
		fputs($fp, "Subject: $subject\n");
		fputs($fp, $headers . "\n\n");
		fputs($fp, $body);
		fputs($fp, "\n");
		pclose($fp);
		return true;
	}


	// END

}

?>
