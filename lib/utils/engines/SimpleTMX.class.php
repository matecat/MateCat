<?

include_once INIT::$UTILS_ROOT . "/engines/engine.class.php";

class SimpleTMX extends Engine{


	public function __construct($id){
		parent::__construct($id);
	}

	public function import($file,$key=false,$name=false){

		$postfields=array(
				'tmx'=>"@".realpath($file),
				'name'=>$name
				);
		if(!empty($key)){
			$postfields['key']=trim($key);
		}
		//query db
		$this->doQuery('tmx_import', $postfields,true);
		return $this->raw_result;
	}

	public function getStatus($key=false,$name=false){

		$parameters=array();
		if(!empty($key)){
			$parameters['key']=trim($key);
		}

		//if provided, add name parameter
		if($name) $parameters['name']=$name;

		$this->doQuery('tmx_status', $parameters,false);
		return $this->raw_result;
	}

}
