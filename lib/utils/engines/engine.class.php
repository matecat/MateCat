<?
include_once INIT::$UTILS_ROOT."/cat.class.php";
abstract class Engine {

	protected $id = "";
	protected $name = "";
	protected $type = "";
	private $description = "";
	protected $base_url = "";
	protected $get_url = "";
	protected $set_url = "";
	protected $delete_url = "";
	protected $extra_parameters = array();
	private $default_penalty;
	protected $url = "";
	protected $error = array('code' => 0, 'description' => '');
	protected $raw_result = array();

	protected function __construct($id) {
		$this->id = $id;
		if ( is_null($this->id) || $this->id == '' ) {
			$this->error = array(-1, "Missing id engine");
			return 0;
		}

		$data = getEngineData($id);
		if (empty($data)) {
			$this->error = array(-2, "Engine not found");
			return 0;
		}



		$this->name = $data['name'];
		$this->description = $data['description'];
		$this->base_url = $data['base_url'];
		$this->get_url = $data['translate_relative_url'];
		$this->set_url = $data['contribute_relative_url'];
		$this->delete_url = $data['delete_relative_url'];
		$this->extra_parameters = json_decode($data['extra_parameters']);
		$this->type = $data['type'];
		$this->default_penalty = empty($data['penalty']) ? 0 : $data['penalty'];
	}

	private function curl($url) {
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_USERAGENT, "user agent");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//        curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_HTTPGET, true);


		// Scarica l'URL e lo passa al browser

		$output = curl_exec($ch);
		$info = curl_getinfo($ch);
		// Chiude la risorsa curl
		curl_close($ch);
		return $output;
	}

	protected function doQuery($function, $parameters = array()) {
		$this->error = array(); // reset last error
		if (!$this->existsFunction($function)) {
			return false;
		}

		$this->buildQuery($function, $parameters);
		$res=$this->curl($this->url);
		$this->raw_result = json_decode($res,true);
	}

	private function buildQuery($function, $parameters) {
		$function = strtolower(trim($function));
		$this->url = "$this->base_url/" . $this->{$function . "_url"} . "?";
		if (is_array($this->extra_parameters) and !empty($this->extra_parameters)) {
			$parameters = array_merge($parameters, $this->extra_parameters);
		}
		$parameters_query_string = http_build_query($parameters);        
		$this->url.=$parameters_query_string;
	}

	private function existsFunction($type) {
		$type = strtolower(trim($type));
		if (empty($type)) {
			$this->error['code'] = -2;
			$this->error['description'] = "no operation defined";
			return false;
		}

		$ret = (isset($this->{$type . "_url"}) and !empty($this->{$type . "_url"}));
		if (!$ret) {
			$this->error['code'] = -2;
			$this->error['description'] = "operation $type not defined for this engine";
		}
		return $ret;
	}

	public function getRawResults() {
		return $this->raw_result;
	}

	public function getError() {
		return $this->error;
	}

	public function getPenalty() {
		return $this->default_penalty;
	}

	public function getDescription() {
		return $this->description;
	}

	public function getName() {
		return $this->name;
	}

}

?>
