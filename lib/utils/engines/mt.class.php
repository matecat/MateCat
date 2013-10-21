<?


//include_once INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$UTILS_ROOT."/engines/engine.class.php";
include_once INIT::$UTILS_ROOT."/cat.class.php";


class MT_ERROR {

	public $code=0;
	public $message="";

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
    public $sentence_confidence;
	public $error = "";

	public function __construct($result) {
		$this->error = new MT_ERROR();
		if (is_array($result) and array_key_exists("data", $result)) {
			$this->translatedText = $result['data']['translations'][0]['translatedText'];
			$this->translatedText =CatUtils::rawxliff2view($this->translatedText);
            if( isset( $result['data']['translations'][0]['sentence_confidence'] ) ) {
                $this->sentence_confidence = $result['data']['translations'][0]['sentence_confidence'];
            }
		}

		if (is_array($result) and array_key_exists("error", $result)) {
			$this->error = new MT_ERROR($result['error']);
		}
	}

	public function get_as_array() {
		return (array) $this;
	}

}

class MT extends Engine {

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

	public function get($segment, $source_lang, $target_lang, $key = "", $segId = null ) {
		$source_lang = $this->fix_language($source_lang);
		$target_lang = $this->fix_language($target_lang);


		$parameters = array();
		$parameters['q'] = $segment;
		$parameters['source'] = $source_lang;
		$parameters['target'] = $target_lang;
		$parameters['key'] = $key;
        ( is_numeric($segId) ? $parameters['segid'] = $segId : null );


		$this->doQuery("get", $parameters);

		$this->result = new MT_RESULT($this->raw_result);
		return array(0, $this->result->translatedText, $this->result->sentence_confidence);
	}

	public function set($segment, $translation, $source_lang, $target_lang, $email = '', $extra='', $segId = null) {
		//if class is uncapable of SET method, exit immediately
		if(NULL==$this->set_url) return true;

		$source_lang = $this->fix_language($source_lang);
		$target_lang = $this->fix_language($target_lang);

		$parameters = array();
		$parameters['segment'] = $segment;
		$parameters['translation'] = $translation;
		$parameters['source']=$source_lang;
		$parameters['target']=$target_lang;
		$parameters['key']="TESTKEY";
		$parameters['de'] = $email;
		$parameters['extra']=$extra;
        ( is_numeric($segId) ? $parameters['segid'] = $segId : null );

		$this->doQuery("set", $parameters);
		$this->result = new MT_RESULT($this->raw_result);
		if ($this->result->error->code != "") {            
			return array("code"=>$this->result->error->code , "message"=>$this->result->error->message);
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
		$this->result = new MT_RESULT($this->raw_result);
		if ($this->result->error->code != "") {            
			return array("code"=>$this->result->error->code , "message"=>$this->result->error->message);
		}
		return true;
	}

}
