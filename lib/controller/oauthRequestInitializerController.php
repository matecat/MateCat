<?php
header("Cache-Control: no-store, no-cache, must-revalidate");  // HTTP/1.1
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
ini_set("error_reporting",E_ALL);

//set_include_path("../src/google-api-php-client-master/" . PATH_SEPARATOR . get_include_path());

require_once INIT::$UTILS_ROOT.'/Google/Client.php';

class oauthRequestInitializerController extends helperController{

	private $openid;
	private $client;
	private $plus;

	private static $client_id = '828918069802-dpdv0b7jeptbvr5lm0gsgmg3056up974.apps.googleusercontent.com';
	private static $client_secret = 'q2ViHWW4TxXx38H5QXeAT-gf';
	private static $redirect_uri = 'https://matecatoauth.translated.net/oauth2callback';

	private $code;
	private $access_token;
	private $logout;
	private $authURL;

	private static $scopes = array(
		'https://www.googleapis.com/auth/userinfo.email',
		'https://www.googleapis.com/auth/userinfo.profile'
	);

	public function __construct(){
		parent::__construct();
		$client_app_name = "Mate";

		//instantiate openid client
		//$this->openid = new LightOpenID(INIT::$HTTPHOST);

		$this->client = new Google_Client();
		$this->client->setApplicationName($client_app_name);
		$this->client->setClientId(self::$client_id);
		$this->client->setClientSecret(self::$client_secret);
		$this->client->setRedirectUri(self::$redirect_uri);
		$this->client->setScopes(self::$scopes);


		$this->plus = new Google_Auth_OAuth2($this->client);


		$filterArgs = array(
			'access_token'        => array( 'filter' => FILTER_SANITIZE_STRING ),
			'logout'      => array( 'filter' => FILTER_UNSAFE_RAW ),
			'code'    => array( 'filter' => FILTER_SANITIZE_STRING),
		);

		$__postInput = filter_input_array( INPUT_GET, $filterArgs );
var_dump($__postInput);
		//NOTE: This is for debug purpose only,
		//NOTE: Global $_POST Overriding from CLI
		//$__postInput = filter_var_array( $_POST, $filterArgs );

		$this->access_token = $__postInput[ 'access_token' ];
		$this->logout       = $__postInput[ 'logout' ];
		$this->code         = $__postInput[ 'code' ];
		//$this->authURL = $this->client->createAuthUrl();

	}

	public function doAction(){

		if(isset($this->access_token) && $this->access_token){
			echo "CONSTRUCT - IF";
			$this->client->setAccessToken(
			             json_encode(array("access_token"   => $this->access_token))
			);
		}
        echo "MI AUTENTICO";
        echo "MI AUTENTICO";

		if(isset($this->code) && $this->code){
			Log::doLog("MI AUTENTICO");

			$this->client->authenticate($this->code);
			$_SESSION['access_token'] = $this->client->getAccessToken();
			$redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
echo "HOHO";
			header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
			echo "IF CODE";
		}
		var_dump("<pre>",$this->client, $this->plus,$_SESSION);

		if ($this->client->getAccessToken()) echo "JACKED IN";

	}

}
?>
