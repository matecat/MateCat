<?php

set_time_limit(0);
define ("BOM","\xEF\xBB\xBF");

include INIT::$UTILS_ROOT . "/langs/languages.class.php";


class fileFormatConverter {

    private $ip = "10.30.1.247";
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
        $this->lang_handler=  Languages::getInstance();
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
                $fileContent=  iconv($encoding, "UTF-8", $fileContent);
            }

            if (!$this->hasBOM($fileContent)) {
                $fileContent = $this->addBOM($fileContent);
            }
        }


        $data['documentContent'] = base64_encode($fileContent);
        $fileContent = null;
        $url = "$this->ip:$this->port/$this->toXliffFunction";

        $data['fileExtension'] = $extension;
        $data['fileName'] = "$filename.$extension";
        $data['sourceLocale'] = $this->lang_handler->getSDLStudioCode($source_lang);
        $data['targetLocale'] = $this->lang_handler->getSDLStudioCode($target_lang);

        $curl_result = $this->curl_post($url, $data, $this->opt);
        $decode = json_decode($curl_result, true);
        $curl_result = null;
        $res = $this->parseOutput($decode);


        return $res;
    }

    public function convertToOriginal($xliffContent) {

        $base64Content = base64_encode($xliffContent);

        $url = "$this->ip:$this->port/$this->fromXliffFunction";

        $uid_ext = $this->extractUidandExt($xliffContent);
        $data['uid'] = $uid_ext[0];
        $data['xliffContent'] = $xliffContent;



        $curl_result = $this->curl_post($url, $data, $this->opt);

        $decode = json_decode($curl_result, true);
        unset($curl_result);
        $res = $this->parseOutput($decode);
        unset($decode);


        return $res;
    }

}

?>
