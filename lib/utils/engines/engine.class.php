<?
include_once INIT::$UTILS_ROOT."/CatUtils.php";
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

    protected $gloss_get_url;
    protected $gloss_set_url;
    protected $gloss_delete_url;
    protected $gloss_update_url;

    protected $tmx_import_url;
    protected $tmx_status_url;

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

        $this->name             = $data[ 'name' ];
        $this->description      = $data[ 'description' ];
        $this->base_url         = $data[ 'base_url' ];
        $this->get_url          = $data[ 'translate_relative_url' ];
        $this->set_url          = $data[ 'contribute_relative_url' ];
        $this->delete_url       = $data[ 'delete_relative_url' ];
        $this->extra_parameters = json_decode( $data[ 'extra_parameters' ] );
        $this->type             = $data[ 'type' ];
        $this->default_penalty  = empty( $data[ 'penalty' ] ) ? 0 : $data[ 'penalty' ];

        $this->gloss_get_url    = $data[ 'gloss_get_relative_url' ];
        $this->gloss_set_url    = $data[ 'gloss_set_relative_url' ];
        $this->gloss_update_url = $data[ 'gloss_update_relative_url' ];
        $this->gloss_delete_url = $data[ 'gloss_delete_relative_url' ];

        $this->tmx_import_url = $data[ 'tmx_import_relative_url' ];
        $this->tmx_status_url = $data[ 'tmx_status_relative_url' ];

    }

	protected function curl($url,$postfields=false) {
		$ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, "user agent");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //if some postfields are passed, switch to post mode, set parameters and prolong timeout
        if ( $postfields ) {
            curl_setopt( $ch, CURLOPT_POST, true );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $postfields );
            curl_setopt( $ch, CURLOPT_TIMEOUT, 120 ); //wait max 2 mins
        } else {
            curl_setopt( $ch, CURLOPT_HTTPGET, true );
            curl_setopt( $ch, CURLOPT_TIMEOUT, 10 ); //we can wait max 10 seconds
        }


        $output = curl_exec($ch);
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);

        if( $curl_errno > 0 ){
            Log::doLog('Curl Error: ' . $curl_errno . " - " . $curl_error . " " . var_export( parse_url( $url ) ,true) );
            $output = json_encode( array( 'error' => array( 'code' => - $curl_errno , 'message' => " Server Not Available" ) ) ); //return negative number
        }

        // Chiude la risorsa curl
        curl_close($ch);
		return $output;
	}

	protected function doQuery($function, $parameters = array(),$isPost=false) {

        $this->error = array(); // reset last error
        if ( !$this->existsFunction( $function ) ) {
            Log::doLog( 'Requested method ' . $function . ' not Found.' );
            return false;
        }

        $uniquid = uniqid();

        if ( $isPost ) {
            //compose the POST
            $this->buildPostQuery( $function );
            //Log::doLog( $uniquid . " ... " . $this->url );
            $res = $this->curl( $this->url, $parameters );
        } else {
            //compose the GET string
            $this->buildGetQuery( $function, $parameters );
            //Log::doLog( $uniquid . " ... " . $this->url );
            $res = $this->curl( $this->url );
        }

        $this->raw_result = json_decode( $res, true );
        //Log::doLog( $uniquid . " ... Received... " . $res );

	}

	private function buildPostQuery($function){
		$function = strtolower(trim($function));
		$this->url = "$this->base_url/" . $this->{$function . "_url"};
	}	

	private function buildGetQuery($function, $parameters) {
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

		$ret = ( isset( $this->{$type . "_url"} ) and !empty( $this->{$type . "_url"} ) );
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
