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

    public function __construct() {
        parent::__construct();
        $this->file_name = $this->get_from_get_post('file_name');
        $this->source_lang = $this->get_from_get_post("source_lang");
        $this->target_lang = $this->get_from_get_post("target_lang");
    }

    public function doAction() {
        
        if (empty($this->file_name)) {
            $this->result['errors'][] = array("code" => -1, "message" => "Missing file name.");
            return false;
        }
        $intDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/upload/' . $_COOKIE['upload_session'];

        $ext = pathinfo($this->file_name, PATHINFO_EXTENSION);
        //log::doLog($ext);
        //if ($this->file_name)

        $file_path = $intDir . '/' . $this->file_name;


        if (!file_exists($file_path)) {
            $this->result['errors'][] = array("code" => -6, "message" => "Error during upload. Please retry.");
            return -1;
        }
        $original_content = file_get_contents($file_path);
        $sha1 = sha1($original_content);

        $xliffContent = getXliffBySHA1($sha1, $this->source_lang, $this->target_lang,$this->cache_days);
        if (!empty($xliffContent)) {
            //log::doLog("xliff content from cache");
            //log::doLog("---deflated : ".$xliffContent);
            $xliffContent=  gzinflate($xliffContent);
            //log::doLog("inflated : ".$xliffContent);
            $res = $this->put_xliff_on_file($xliffContent, $intDir);
            if ($res == -1) {
                return -1;
            }
            return 0;
        } else {
            $original_content_zipped = gzdeflate($original_content, 5);
            unset($original_content);

            $converter = new fileFormatConverter();
            //$filename = pathinfo($this->file_name, PATHINFO_FILENAME);
//log::doLog("fp is $file_path");
            $convertResult = $converter->convertToSdlxliff($file_path, $this->source_lang, $this->target_lang);
//		log::doLog("CONVERT RESULT : " . $convertResult['isSuccess']);

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
                  if (!is_dir($intDir . "_converted")) {
                  mkdir($intDir . "_converted");
                  };
                  $this->result['code'] = 1;

                  file_put_contents("$intDir" . "_converted/$this->file_name.sdlxliff", $xliffContent);
                 * 
                 */
                $res_insert = insertFileIntoMap($sha1, $this->source_lang, $this->target_lang, $original_content_zipped, $xliffContentZipped);
                unset ($xliffContentZipped);
                $res = $this->put_xliff_on_file($xliffContent, $intDir);
                if ($res == -1) {
                    return -1;
                }
                return 0;
            } else {
                if ($convertResult['errorMessage'] == "Failed to create SDLXLIFF.") {
                    $convertResult['errorMessage'] = "Failed importing file.";
                }
                $this->result['code'] = 0;
                $this->result['errors'][] = array("code" => -1, "message" => $convertResult['errorMessage']);
                log::doLog("ERROR MESSAGE : " . $convertResult['errorMessage']);



                return -1;
            }
        }
    }

    private function put_xliff_on_file($xliffContent, $intDir) {
        $file_path = $intDir . '/' . $this->file_name;
        log::doLog(__FUNCTION__ ." -- $file_path");
        if (!$this->checkSegmentsNumber($xliffContent)) {
            $this->result['code'] = 0;
            $this->result['errors'][] = array("code" => -2, "message" => 'No segments found in this file!');
            unlink($file_path);
            return -1;
        }
        if (!is_dir($intDir . "_converted")) {
            mkdir($intDir . "_converted");
        };

        file_put_contents("$intDir" . "_converted/$this->file_name.sdlxliff", $xliffContent);
        $this->result['code'] = 1;
        return 1;
    }

    private function checkSegmentsNumber($xliffContent) {
        return 1; // this function is bypassed because this is not the right way to tempt to find translatable content: the g tag could not appear but the file could still contain translatable content
    	log::doLog(__FUNCTION__. "--  $xliffContent");
        $found = preg_match_all('/<g id="[^"]+">/', $xliffContent, $res);
        log::doLog("preg found : $found");
        log::doLog("preg result", $res);
        if (!$found or $found == 0) {
            return 0;
        }
        return 1; //segnaposto
    }

}

?>
