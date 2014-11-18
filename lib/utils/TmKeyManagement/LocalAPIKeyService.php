<?
include_once INIT::$UTILS_ROOT."/CatUtils.php";


class TmKeyManagement_LocalAPIKeyService extends Engine {

    public function createMyMemoryKey(){

        //query db
        $this->doQuery('api_key_create_user');

        if ( empty( $this->raw_result ) || $this->raw_result['error'] || $this->raw_result['code'] != 200 ) {
            throw new Exception( "Private TM key .", -1 );
        }

        return $this->raw_result;

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
    public function checkCorrectKey( $apiKey ) {

        $url = 'https://api.mymemory.translated.net/authkey?key=' . $apiKey;
        $ch  = curl_init();

        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_USERAGENT, "user agent" );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );

        curl_setopt( $ch, CURLOPT_HTTPGET, true );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 5 ); //we can wait max 5 seconds

        //if it's an HTTPS call
        //verify CA in certificate
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
        curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );

        $result = curl_exec( $ch );

        $curl_errno = curl_errno( $ch );
        $curl_error = curl_error( $ch );
        curl_close( $ch );

        Log::doLog( "Response KEY VALIDATION $url ->'$result'" );

        if ( $curl_errno > 0 ) {
            Log::doLog( "Error: The check for MyMemory private key correctness failed: " . $curl_error . " ErrNum: " . $curl_errno );
            throw new Exception( "Error: The check for correctness of the private TM key failed. Please check you inserted key.", -2 );
        }

        $isValidKey = filter_var( $result, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

        if ( $isValidKey === null ) {
            throw new Exception( "Error: The check for correctness of the private TM key failed.", -3 );
        }

        return $isValidKey;

    }

}
