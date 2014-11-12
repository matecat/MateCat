<?
include_once INIT::$UTILS_ROOT."/CatUtils.php";


class LocalAPIKeyService {

	public function __construct(){

	}

    /**
     * @param $id_translator
     *
     * @return null
     *
     * @deprecated
     */
    public function calculateMyMemoryKey($id_translator) {
        $key = getTranslatorKey($id_translator);
        return $key;
    }

    public function createMyMemoryKey(){

        $newUser = json_decode( file_get_contents( 'https://api.mymemory.translated.net/createranduser' ) );
        if ( empty( $newUser ) || $newUser->error || $newUser->code != 200 ) {
            throw new Exception( "Private TM key .", -1 );
        }

        return $newUser;

    }

    /**
     * Checks for MyMemory Api Key correctness
     *
     * Filter Validate returns true/false for correct/not correct key and NULL is returned for all non-boolean values. ( 404, html, etc. )
     *
     * @param $apiKey
     *
     * @return bool|null
     * @throws Exception
     */
    public function checkCorrectKey( $apiKey ){

	    $url = 'https://api.mymemory.translated.net/authkey?key=' . $apiKey;
	    $ch = curl_init();

	    curl_setopt($ch, CURLOPT_URL, $url);
	    curl_setopt($ch, CURLOPT_HEADER, 0);
	    curl_setopt($ch, CURLOPT_USERAGENT, "user agent");
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

	    curl_setopt( $ch, CURLOPT_HTTPGET, true );
	    curl_setopt( $ch, CURLOPT_TIMEOUT, 5 ); //we can wait max 5 seconds

	    //if it's an HTTPS call
	    if(strpos(trim($url),'https',0)===0){
		    //verify CA in certificate
		    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
/*
		    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		    //verify that the common name exists and that it matches the host name of the server
		    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		    //use these certificates as chain of trust
		    curl_setopt($ch, CURLOPT_CAPATH, '/etc/ssl/certs/');
*/
	    }

	    $result = curl_exec( $ch );

	    $curl_errno = curl_errno($ch);
	    $curl_error = curl_error($ch);
	    curl_close($ch);

	    Log::doLog( "Response KEY VALIDATION $url ->'$result'");

	    if( $curl_errno > 0 ) {
		    Log::doLog( "Error: The check for MyMemory private key correctness failed: " . $curl_error . " ErrNum: " . $curl_errno );
		    throw new Exception( "Error: The check for correctness of the private TM key failed. Please check you inserted key.", -2 );
	    }

	    $isValidKey = filter_var( $result, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

	    if( $isValidKey === null ){
		    throw new Exception( "Error: The check for correctness of the private TM key failed.", -3 );
	    }

	    return $isValidKey;

    }

}
