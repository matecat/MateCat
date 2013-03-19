<?php

class ERRORS {

    private static $instance;
    private $errors = array();

    private function __construct() {
        $this->errors['e01']['level'] = "warning";
        $this->errors['e01']['desc_en'] = "Tag mismatch";
    }

    public static function obtain() {
        if (!self::$instance) {
            self::$instance = new ERRORS();
        }
        return self::$instance;
    }

    public static function getErrors() {
        return $this->errors;
    }

    public static function getError($id) {
        if (array_key_exists($id, $this->errors)) {
            return ($this->errors[$id]);
        }
        return null;
    }

    public  function getErrorDesc($id, $lang = 'en') {
        $ret = $this->getError($id);
        if (!is_null($ret)) {
            if (array_key_exists($ret, "desc_$lang")) {
                return $ret["desc_$lang"];
            } else {
                return $ret["desc_en"];
            }
        }
        return null;
    }

    public function getErrorLevel($id) {
        $ret = self::getError($id);
        if (!is_null($ret)) {
            return $ret["level"];
        }
        return null;
    }

    public  function getAllErrors(){
        return $this->errors;
    }
}

?>
