<?

include_once INIT::$UTILS_ROOT."/CatUtils.php";
include_once INIT::$UTILS_ROOT."/engines/mt_error.class.php";
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

		if ( is_array($result) and array_key_exists("error", $result) ) {
			$this->error = new MT_ERROR($result['error']);
		}
	}

	public function get_as_array() {
		return (array) $this;
	}

}

?>
