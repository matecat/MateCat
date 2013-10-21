<?php

set_time_limit(0);
define ("BOM","\xEF\xBB\xBF");

include_once INIT::$UTILS_ROOT . "/langs/languages.class.php";


class fileFormatConverter {

	private $ip; //current converter chosen for this job
	private $port = "8732"; //port the convertrs listen to
	private $toXliffFunction = "AutomationService/original2xliff"; //action string for the converters to convert to XLIFF
	private $fromXliffFunction = "AutomationService/xliff2original";//action string for the converters to convert to original
	private $opt = array(); //curl options
	private $lang_handler; //object that exposes language utilities
	private $storage_lookup_map;

    private $conversionObject;

	private static $Storage_Lookup_IP_Map = array(
			//'10.11.0.10' => '10.11.0.11',
			'10.11.0.18' => '10.11.0.19',
			'10.11.0.26' => '10.11.0.27',
			'10.11.0.34' => '10.11.0.35',
			'10.11.0.42' => '10.11.0.43',
			);

	public static $converters = array(
			//'10.11.0.10' => 1,
			'10.11.0.18' => 1,
			'10.11.0.26' => 1,
			'10.11.0.34' => 1,
			'10.11.0.42' => 1
			);

	//public static $converters = array('10.11.0.10' => 1);//for debugging purposes

	public function __construct() {
		if (!class_exists("INIT")) {
			include_once ("../../inc/config.inc.php");
			INIT::obtain();
		}
		$this->opt['httpheader'] = array("Content-Type: application/x-www-form-urlencoded;charset=UTF-8");
		$this->lang_handler=  Languages::getInstance();

		$this->storage_lookup_map = self::$Storage_Lookup_IP_Map;

        $this->conversionObject = new ArrayObject( array(
            'ip_machine'    => null,
            'ip_client'     => null,
            'path_name'     => null,
            'file_name'     => null,
            'path_backup'   => null,
            'direction'     => null,
            'error_message' => null,
            'src_lang'      => null,
            'trg_lang'      => null,
        ), ArrayObject::ARRAY_AS_PROPS );

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
	private function pickRandConverter() {
		//get total cpu count
		$cpus    = array_values( self::$converters );
		$tot_cpu = 0;
		foreach ( $cpus as $cpu ) {
			$tot_cpu += $cpu;
		}
		unset( $cpus );

		//pick random
		$num = rand( 0, $tot_cpu - 1 );

		//scroll in a roulette fashion through node->#cpu list until you stop on a cpu
		/*
		   imagine an array: each node has a number of cells on it equivalent to # of cpus; the random number is the cell on which to stop
		   scroll the list_of_nodes, decrementing the random number with number of cpus;
		   if any time the random is 0, pick that node;
		   otherwise, keep scrolling
		 */
		$picked_node = '';
		foreach ( self::$converters as $node => $cpus ) {
			$num -= $cpus;
			if ( $num <= 0 ) {
				//current node is the one; break
				$picked_node = $node;
				break;
			}
		}

		return $picked_node;
	}

	/**
	 * check top of a single node by ip
	 *
	 * @param $ip
	 *
	 * @return mixed
	 */
	public static function checkNodeLoad( &$ip ){

		$top = 0;
		$result = "";
		$processes = array();

		//since sometimes it can fail, try again util we get something meaningful
		$ch = curl_init("$ip:8082");
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_TIMEOUT,2); //we can wait max 2 seconds

		while( empty( $result ) || empty( $processes ) ){

			$result = curl_exec($ch);
			$curl_errno = curl_errno($ch);
			$curl_error = curl_error($ch);

			$processes = json_decode($result,true);

			//$curl_errno == 28 /* CURLE_OPERATION_TIMEDOUT */
			if( $curl_errno > 0 ){
				$top = 200; //exclude current converter by set it's top to an extreme large value
				break;
			}

		}

		//close
		curl_close($ch);

		//sum up total machine load
		foreach($processes as $process){
			$top += @$process[0];
		}

		//zero load is impossible (at least, there should be the java monitor); try again
		if(0==$top){
			log::doLog("suspicious zero load for $ip, recursive call");
			usleep(500*1000); //200ms
			$top=self::checkNodeLoad($ip);
		}

		return $top;
	}
	/**
	 * check process list of a single node by ip
	 *
	 * @param $ip
	 *
	 * @return mixed
	 */
	public static function checkNodeProcesses( &$ip ){

		$result = "";
		$processes = array();

		//since sometimes it can fail, try again util we get something meaningful
		$ch = curl_init("$ip:8082");
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_TIMEOUT,2); //we can wait max 2 seconds

		$trials=0;
		while($trials<3 ){

			$result = curl_exec($ch);
			$curl_errno = curl_errno($ch);
			$curl_error = curl_error($ch);

			$processes = json_decode($result,true);

			if( empty( $result ) || empty( $processes ) ){
				$trials++;
				sleep(1);
			}else{
				break;
			}
		}

		//close
		curl_close($ch);

		return $processes;
	}

	private function pickIdlestConverter(){
		//scan each server load
		foreach(self::$converters as $ip=>$weight){
			$load=self::checkNodeLoad($ip);
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

	public function getValidStorage(){
		return $this->storage_lookup_map[$this->ip];
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
            $this->conversionObject->error_message = $res['errorMessage'];

            $backUp_dir = INIT::$STORAGE_DIR.'/conversion_errors/' . $_COOKIE['upload_session'];
            $this->conversionObject->path_backup = $backUp_dir . "/" . $this->conversionObject->file_name;

            if ( !is_dir( $backUp_dir ) ) {
                mkdir( $backUp_dir, 0755, true );
            }

            rename( $this->conversionObject->path_name , $this->conversionObject->path_backup );
            $this->__saveConversionErrorLog();
            $this->__notifyError();

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

		if($this->checkOpenService($url)){

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

		} else {
			$output=json_encode(array("isSuccess"=>false,"errorMessage"=>"port closed"));
		}
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

        $this->conversionObject->ip_machine = $this->ip;
        $this->conversionObject->ip_client  = Utils::getRealIpAddr();
        $this->conversionObject->path_name  = $file_path;
        $this->conversionObject->file_name  = $data['fileName'];
        $this->conversionObject->direction  = 'fw';
        $this->conversionObject->src_lang   = $data['sourceLocale'];
        $this->conversionObject->trg_lang   = $data['targetLocale'];

		$curl_result = $this->curl_post($url, $data, $this->opt);
		$end_time=time();
		$time_diff=$end_time-$start_time;
		log::doLog($this->ip." took $time_diff secs for $file_path");

		$decode = json_decode($curl_result, true);
		$curl_result = null;
		$res = $this->parseOutput($decode);

		return $res;
	}

	private function checkOpenService($url){
		//default is failure
		$open=false;

		//get address only
		$url=substr($url,0,strpos($url,':'));

		//attempt to connect
		$connection = @fsockopen($url, $this->port);
		if ($connection) {
			//success
			$open=true;
			//close port
			fclose($connection);
		} 
		return $open;
	}

	public function convertToOriginal($xliffVector, $chosen_by_user_machine=false) {

        $xliffContent = $xliffVector['content'];
        $xliffName    = $xliffVector['out_xliff_name'];

//        Log::dolog( $xliffName );

		//assign converter
		if(!$chosen_by_user_machine){
			$this->ip=$this->pickIdlestConverter();
			$storage      = $this->getValidStorage();

			//add trados to replace/regexp pattern because whe have more than 1 replacement
			//http://stackoverflow.com/questions/2222643/php-preg-replace
			$xliffContent = self::replacedAddress( $storage, $xliffContent );
		}else{
			$this->ip=$chosen_by_user_machine;
		}

		$url = "$this->ip:$this->port/$this->fromXliffFunction";

		$uid_ext = $this->extractUidandExt($xliffContent);
		$data['uid'] = $uid_ext[0];
		$data['xliffContent'] = $xliffContent;

        $this->conversionObject->ip_machine = $this->ip;
        $this->conversionObject->ip_client  = Utils::getRealIpAddr();
        $this->conversionObject->path_name  = $xliffVector['out_xliff_name'];
        $this->conversionObject->file_name  = pathinfo( $xliffVector['out_xliff_name'], PATHINFO_BASENAME );
        $this->conversionObject->direction  = 'bw';
        $this->conversionObject->src_lang   = $xliffVector['source'];
        $this->conversionObject->trg_lang   = $xliffVector['target'];

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


	//http://stackoverflow.com/questions/2222643/php-preg-replace
	private static $Converter_Regexp = '/=\"\\\\\\\\10\.11\.0\.[1-9][13579]{1,2}\\\\tr/';

	/**
	 * Replace the storage address in xliff content with the right associated storage ip
	 *
	 * @param $storageIP string
	 * @param $xliffContent string
	 *
	 * @return string
	 */
	public static function replacedAddress( $storageIP, $xliffContent ){
		return preg_replace( self::$Converter_Regexp, '="\\\\\\\\' . $storageIP . '\\\\tr', $xliffContent );
	}

    private function __notifyError(){

        $remote_user = ( isset( $_SERVER[ 'REMOTE_USER' ] ) ) ? $_SERVER[ 'REMOTE_USER' ] : "N/A";
        $link_file   = "http://" . $_SERVER[ 'SERVER_NAME' ] . "/" . INIT::$CONVERSIONERRORS_REPOSITORY_WEB . "/" . $_COOKIE[ 'upload_session' ] . "/" . rawurlencode( $this->conversionObject->file_name );
        $message     = "MATECAT : conversion error notifier\n\nDetails:
    - machine_ip : " . $this->conversionObject->ip_machine . "
    - client ip :  " . $this->conversionObject->ip_client . "
    - source :     " . $this->conversionObject->src_lang . "
    - target :     " . $this->conversionObject->trg_lang . "
    - client user (if any used) : $remote_user
    - direction : " . $this->conversionObject->direction . "
    Download file clicking to $link_file
	";

        Utils::sendErrMailReport( $message );

    }

    private function __saveConversionErrorLog(){

        try {
            $_connection = new PDO('mysql:dbname=matecat_conversions_log;host=' . INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS,
                array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'',
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_ORACLE_NULLS => true
                ) );
        } catch ( Exception $ex ){
            Log::doLog('Unable to open database connection');
            Log::doLog($ex->getMessage());
            return;
        }

        $data = $this->conversionObject->getArrayCopy();

        unset ( $data['path_name'] );
        unset ( $data['file_name'] );

        $data_keys = implode( ", ", array_keys( $data ) );
        $data_values = array_values( $data );
        $data_placeholders = implode( ", ", array_fill( 0, count($data), "?" ) );
        $query = "INSERT INTO failed_conversions_log ($data_keys) VALUES ( $data_placeholders );";

        $sttmnt = $_connection->prepare( $query );
        $sttmnt->execute($data_values);

        Log::doLog( $this->conversionObject );

    }

}

?>
