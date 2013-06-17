<?php

set_time_limit(0);
define ("BOM","\xEF\xBB\xBF");

//include INIT::$UTILS_ROOT . "/cat.class.php";
include INIT::$UTILS_ROOT . "/langs/languages.class.php";


class fileFormatConverter {

    #private $ip = "10.30.1.247";
    private $ip = "149.7.212.128";
    private $port = "8732";
    private $toXliffFunction = "AutomationService/original2xliff";
    private $fromXliffFunction = "AutomationService/xliff2original";
    private $opt = array();
    private $lang_handler;

    public function __construct() {
        if (!class_exists("INIT")) {
            include_once ("../../inc/config.inc.php");
            INIT::obtain();
        }
        $this->opt['httpheader'] = array("Content-Type: application/x-www-form-urlencoded;charset=UTF-8");
    }

    private function addBOM($string) {
        return BOM . $string;
    }

    private function hasBOM($string) {
        return (substr($string, 0, 3) == BOM);
    }

    private function extractUidandExt(&$content) {
        $pattern = '|<file original="\w:\\\\.*?\\\\.*?\\\\(.*?)\\\\(.*?)\.(.*?)".*?>|';
        $matches = array();
        preg_match($pattern, $content, $matches);

        //print_r ($matches);exit;
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
        //echo "1 - " . memory_get_usage(true)/1024/1024;
        //echo "\n";
        if (!$this->is_assoc($d)) {
            throw new Exception("The input data to " . __FUNCTION__ . "must be an associative array", -1);
        }
        $ch = curl_init();

        $data = http_build_query($d);
        $d = null;
//echo "2 - " . memory_get_usage(true)/1024/1024;
//echo "\n";

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
                //echo $const_name;exit;
                if (defined($const_name)) {
                    curl_setopt($ch, constant($const_name), $v);
                }
            }
        }


        $output = curl_exec($ch);

//echo "2 - " . memory_get_usage(true)/1024/1024;
        $info = curl_getinfo($ch);

//        print_r($info);
        // Chiude la risorsa curl
        curl_close($ch);
        /* if ($output === false || $info != 200) {
          $output = null;
          } */

        //print_r ($info);
        //echo "$output\n\n";
        return $output;
    }

    public function convertToSdlxliff($file_path, $source_lang, $target_lang) {
        if (!file_exists($file_path)) {
            throw new Exception("Conversion Error : the file <$file_path> not exists");
        }
        $fileContent = file_get_contents($file_path);
        $extension = pathinfo($file_path, PATHINFO_EXTENSION);
        $filename = pathinfo($file_path, PATHINFO_FILENAME);
        if (strtoupper($extension) == 'TXT') {
            $encoding=mb_detect_encoding($fileContent);
            if ($encoding!='UTF-8'){
                log::doLog("convert from $encoding to UTF8");
                $fileContent=  iconv($encoding, "UTF-8", $fileContent);
            }
            log::doLog("is TXT ");

            if (!$this->hasBOM($fileContent)) {
                log::doLog("add BOM before");
                log::doLog($fileContent);
                $fileContent = $this->addBOM($fileContent);
                log::doLog("add BOM after");
                log::doLog($fileContent);
            }
        }


        $data['documentContent'] = base64_encode($fileContent);
//echo "-1 - " . memory_get_usage(true)/1024/1024;
        $fileContent = null;
//echo "-2 - " . memory_get_usage(true)/1024/1024;
        $url = "$this->ip:$this->port/$this->toXliffFunction";
//        echo $url;

        $data['fileExtension'] = $extension;
        $data['fileName'] = "$filename.$extension";
        $data['sourceLocale'] = $this->lang_handler->getSDLStudioCode($source_lang);
        $data['targetLocale'] = $this->lang_handler->getSDLStudioCode($target_lang);

        //print_r ($data);
        //$curl_result = CatUtils::curl_post($url, $data, $this->opt);
        $curl_result = $this->curl_post($url, $data, $this->opt);
//        print_r($curl_result);exit;
        $decode = json_decode($curl_result, true);
        $curl_result = null;
        $res = $this->parseOutput($decode);

//        echo "________TO XLIFF________\n";
        //print_r($res);

        return $res;
    }

    public function convertToOriginal($xliffContent) {
        //if (!is_dir($intDir)) {
        //    throw new Exception("Conversion Error : the folder <$intDir> not exists");
        //}

        $base64Content = base64_encode($xliffContent);

        $url = "$this->ip:$this->port/$this->fromXliffFunction";
        // echo $url;

        $uid_ext = $this->extractUidandExt($xliffContent);
        $data['uid'] = $uid_ext[0];
        $data['xliffContent'] = $xliffContent;



        // print_r($data);
        // exit;
        //$curl_result = CatUtils::curl_post($url, $data, $this->opt);
        $curl_result = $this->curl_post($url, $data, $this->opt);

        $decode = json_decode($curl_result, true);
        unset($curl_result);
        $res = $this->parseOutput($decode);
        unset($decode);


        //echo "________TO ORIG________\n";
        //   print_r($res);
        //exit;
        return $res;
    }

}

/*
  // test
  $a = new fileFormatConverter();
  $path = "/Users/antonio/Dropbox/SDLXLIFF Conversion service/041-HTML/041-HTML-default_definitions.htm";
  //$file = file_get_contents($path);
  //echo $file;exit;
  //$filename = pathinfo($path, PATHINFO_FILENAME);
  //$extension = pathinfo($path, PATHINFO_EXTENSION);
  //echo $extension;exit;

  $source_lang = "en-US";
  $target_lang = "it-IT";


  $intDir = "";

  $xliff = $a->convertToSdlxliff($path, $source_lang, $target_lang);

  print_r ($xliff);
  exit;

  $original = $a->convertToOriginal($intDir, $xliff['xliffContent']);
  $filenameOrig = $original['fileName'];
  echo "CCCCCCCCCCC $filenameOrig\n";
  $original_content = $original['documentContent'];

  file_put_contents($filenameOrig, $original_content);
 */
?>
