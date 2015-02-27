<?
include_once INIT::$UTILS_ROOT."/CatUtils.php";
include_once INIT::$UTILS_ROOT . "/Engines/engine.class.php";

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

        $postFields = array(
                'key' => trim($apiKey)
        );

        //query db
        $this->doQuery( 'api_key_check_auth', $postFields );


        if ( @$this->raw_result['error']['code'] != 0 ) {
            Log::doLog( "Error: The check for MyMemory private key correctness failed: " . $this->raw_result['error']['message'] . " ErrNum: " . $this->raw_result['error']['code'] );
            throw new Exception( "Error: The private TM key you entered seems to be invalid. Please, check that the key is correct.", -2 );
        }

        $isValidKey = filter_var( $this->raw_result, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

        if ( $isValidKey === null ) {
            throw new Exception( "Error: The private TM key you entered seems to be invalid.", -3 );
        }

        return $isValidKey;

    }

}
