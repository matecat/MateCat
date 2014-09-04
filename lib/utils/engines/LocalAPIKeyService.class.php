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

        $newUser = json_decode( file_get_contents( 'http://mymemory.translated.net/api/createranduser' ) );
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

        $defaults = array(
                CURLOPT_URL => 'http://api.mymemory.translated.net/authkey?key=' . $apiKey,
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_TIMEOUT => 2
        );

        $ch = curl_init();
        curl_setopt_array( $ch, $defaults );
        $result = curl_exec( $ch );

        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if( $curl_errno > 0 ) {
            Log::doLog( "Error: The check for MyMemory private key correctness failed: " . $curl_error );
            throw new Exception( "Error: The check for correctness of the private TM key failed. Please try to recreate the project.", -2 );
        }

        $isValidKey = filter_var( $result, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

        if( $isValidKey === null ){
            throw new Exception( "Error: The check for correctness of the private TM key failed, service not available.", -3 );
        }

        return $isValidKey;

    }

}
