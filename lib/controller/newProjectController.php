<?php

include_once INIT::$MODEL_ROOT . "/queries.php";

class newProjectController extends viewcontroller {

    private $guid = '';
    private $mt_engines;
    private $tms_engines;

    public function __construct() {
        parent::__construct();
        if (!isset($_REQUEST['fork'])) {
            parent::makeTemplate("upload.html");
        } else {
            parent::makeTemplate("upload_cloud.html");
        }
        $this->guid = $this->create_guid();
    }

    public function doAction() {
        if (!isset($_COOKIE['upload_session'])) {            
            setcookie("upload_session", $this->guid, time() + 86400);
        } else {
            $this->guid = $_COOKIE['upload_session'];
        }

        $intDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/upload/' . $this->guid . '/';
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

    private function getExtensionsPartiallySupported() {
        $ext_ret = array();
        foreach (INIT::$SUPPORTED_FILE_TYPES as $k => $v) {
            foreach ($v as $kk => $vv) {
                if (!isset($vv[1]) or empty($vv[1])) {
                    continue;
                }
                $ext_ret[] = array("format" => "$kk", "message" => "$vv[1]");
            }
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
        $this->template->upload_session_id = $this->guid;
        $this->template->mt_engines = $this->mt_engines;
        $this->template->tms_engines = $this->tms_engines;

        $this->template->conversion_enabled = INIT::$CONVERSION_ENABLED;
        if (INIT::$CONVERSION_ENABLED) {
            $this->template->allowed_file_types = $this->getExtensions("");
        } else {
            $this->template->allowed_file_types = $this->getExtensions("default");
        }

        $this->template->supported_file_types_array = $this->getCategories();

        $this->template->partially_supported_file_types = $this->getExtensionsPartiallySupported();
        $this->template->formats_number = $this->countExtensions(); //count(explode('|', INIT::$CONVERSION_FILE_TYPES));
        $this->template->volume_analysis_enabled = INIT::$VOLUME_ANALYSIS_ENABLED;
        
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
