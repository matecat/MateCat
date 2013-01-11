<?

//{"error": 
//  {
//  "code": 400, 
//  "message": "Required parameter: key", 
//  "errors": 
//      [
//          {
//          "locationType": "parameter", 
//          "domain": "global", 
//          "message": "Required parameter: key", 
//          "reason": "required", 
//          "location": "key"
//          }, 
//          {
//          "locationType": "parameter", 
//          "domain": "global", 
//          "message": "Required parameter: source", 
//          "reason": "required", 
//          "location": "source"
//          }
//      ]
//  }
//}
//{"data": 
//  {"translations": 
//      [
//          {"translatedText": "ciao a tutti"}
//       ]
//  }
//}

//include_once INIT::$UTILS_ROOT . "/cat.class.php";
include_once "engine.class.php";
include_once ("utils/cat.class.php");


class MT_ERROR {

    public $code=0;
    public $message="";

    //public $errors;


    public function __construct($result = array()) {
        if (!empty($result)) {
            $this->code = $result['code'];
            $this->message = $result['message'];
        }
    }

    public function get_as_array() {
        return (array) $this;
    }

}

class MT_RESULT {

    public $translatedText = "";
    public $error = "";

    public function __construct($result) {
        //  print_r($result);
	$this->error = new MT_ERROR();
        if (is_array($result) and array_key_exists("data", $result)) {
            $this->translatedText = $result['data']['translations'][0]['translatedText'];
	    $this->translatedText =CatUtils::rawxliff2view($this->translatedText);
        }

        if (is_array($result) and array_key_exists("error", $result)) {
            $this->error = new MT_ERROR($result['error']);
        }
    }

    public function get_as_array() {
        return (array) $this;
    }

}

class MT extends engine {

    private $result = array();

    public function __construct($id) {
        parent::__construct($id);
        if ($this->type != "MT") {
            throw new Exception("not a MT engine");
        }
    }

    private function fix_language($lang) {

        if (empty($lang) or strlen($lang) == 2) {
            return strtolower(trim($lang));
        }

        $l = strtolower(trim($lang));

        if (strpos($l, "en") !== false) {
            $l = 'en';
        }

        if (strpos($l, "it") !== false) {
            $l = 'it';
        }

        if (strpos($l, "de") !== false) {
            $l = 'de';
        }

        if (strpos($l, "fr") !== false) {
            $l = 'fr';
        }

        return $l;
    }

    public function get($segment, $source_lang, $target_lang, $key = "") {
        $source_lang = $this->fix_language($source_lang);
        $target_lang = $this->fix_language($target_lang);


        $parameters = array();
        $parameters['q'] = $segment;
        $parameters['source'] = $source_lang;
        $parameters['target'] = $target_lang;
        $parameters['key'] = $key;


        $this->doQuery("get", $parameters);
        // echo "--- $this->raw_result --";

	
        $this->result = new MT_RESULT($this->raw_result);
	log::doLog("--------------------------------------------------------------------------------------");
	//log::doLog($this->result->error);
       /* if (!empty($this->result->error->code) and $this->result->error->code != "200") {
            return array(-1, $this->result->error->message);
        }*/
        return array(0, $this->result->translatedText);
    }

    public function set($segment, $translation, $source_lang, $target_lang, $email = "") {
        $source_lang = $this->fix_language($source_lang);
        $target_lang = $this->fix_language($target_lang);

        $parameters = array();
        $parameters['seg'] = $segment;
        $parameters['tra'] = $translation;
        $parameters['langpair'] = "$source_lang|$target_lang";
        $parameters['de'] = $email;

        $this->doQuery("set", $parameters);
        $this->result = new TMS_RESULT($this->raw_result);
        if ($this->result->responseStatus != "200") {
            return false;
        }
        return true;
    }

    public function delete($segment, $translation, $source_lang, $target_lang, $email = "") {
        $source_lang = $this->fix_language($source_lang);
        $target_lang = $this->fix_language($target_lang);

        $parameters = array();
        $parameters['seg'] = $segment;
        $parameters['tra'] = $translation;
        $parameters['langpair'] = "$source_lang|$target_lang";
        $parameters['de'] = $email;

        $this->doQuery("delete", $parameters);
        $this->result = new TMS_RESULT($this->raw_result);
        if ($this->result->responseStatus != "200") {
            return false;
        }
        return true;
    }

}

/*
$segment = "Control panel";
$translation = "pannello di controllo ooooooo";
$source_lang = "en";
$target_lang = "it";
$email = "antonio@translated.net";


$a = new MT(2);
print_r($a->get($segment, $source_lang, $target_lang, $email));
print_r($a->getError());
//print_r($a->getRawResults());
*/
