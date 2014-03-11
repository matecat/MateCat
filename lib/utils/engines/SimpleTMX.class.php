<?

include_once INIT::$UTILS_ROOT . "/engines/engine.class.php";

class SimpleTMX extends Engine{


	public function __construct($id){
        parent::__construct($id);
	}

	public function import($file,$key,$name=false){

		$postfields=array(
				'key'=>$key,
				'tmx'=>"@".realpath($file),
				'name'=>$name
				);
		//query db
		$this->doQuery('tmx_import', $postfields,true);


	}

	public function getStatus($key){

		$parameters=array('key'=>$key);
		$this->doQuery('tmx_status', $parameters,false);
		return $this->raw_result;
	}

}

?>
