<?php
include_once INIT::$MODEL_ROOT . "/queries.php";

define('DEFAULT_NUM_RESULTS', 2);

class changeInternalLanguageController extends ajaxcontroller {

    private $source_language;
    private $target_language;
	private $file_name;

    public function __construct() {
        parent::__construct();

        $this->source_language = $this->get_from_get_post('source_language');
        $this->target_language = $this->get_from_get_post('target_language');
		$this->file_name = $this->get_from_get_post('file_name'); 
	}

    public function doAction() {

        if (empty($this->file_name)) {
            $this->result['error'][] = array("code" => -1, "message" => "missing file_name");
        }

        if (empty($this->source_language)) {
            $this->result['error'][] = array("code" => -2, "message" => "missing source_language");
        }

        if (empty($this->target_language)) {
            $this->result['error'][] = array("code" => -3, "message" => "missing target_language");
        }

		$intDir=$_SERVER['DOCUMENT_ROOT'].'/storage/upload/'.$_COOKIE['upload_session'];
		$filename = $intDir.'/'.$this->file_name;
		
		if (file_exists($filename)) {
		} else {
            $this->result['error'][] = array("code" => -4, "message" => "file non trovato");
		}

		$preg_file_html = '|(<file original=".*?" source-language=")(.*?)(" datatype=".*?" target-language=")(.*?)(">)|m';
        $content = file_get_contents($filename);
		$replacement = "$1$this->source_language$3$this->target_language$5";
		$current = preg_replace($preg_file_html, $replacement, $content);		
		file_put_contents($filename, $current);

        $this->result['code'] = 1;
        $this->result['data'] = "OK";         			    

    }


}

?>
