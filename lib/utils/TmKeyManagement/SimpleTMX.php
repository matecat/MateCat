<?

include_once INIT::$UTILS_ROOT . "/engines/engine.class.php";

class TmKeyManagement_SimpleTMX extends Engine {


    public function __construct( $id ) {
        parent::__construct( $id );
    }

    public function import( $file, $key, $name = false ) {

        $postFields = array(
                'tmx'  => "@" . realpath( $file ),
                'name' => $name
        );

        $postFields[ 'key' ] = trim( $key );

        //query db
        $this->doQuery( 'tmx_import', $postFields, true );

        return $this->raw_result;
    }

    public function getStatus( $key, $name = false ) {

        $parameters = array();
        $parameters[ 'key' ] = trim( $key );

        //if provided, add name parameter
        if ( $name ) {
            $parameters[ 'name' ] = $name;
        }

        $this->doQuery( 'tmx_status', $parameters, false );

        return $this->raw_result;
    }

    /**
     * Memory Export creation request.
     *
     * <ul>
     *  <li>key: MyMemory key</li>
     *  <li>source: all segments with source language ( default 'all' )</li>
     *  <li>target: all segments with target language ( default 'all' )</li>
     *  <li>strict: strict check for languages ( no back translations ), only source-target and not target-source
     * </ul>
     *
     * @param string       $key
     * @param null|string  $source
     * @param null|string  $target
     * @param null|boolean $strict
     *
     * @return array
     */
    public function createExport( $key, $source = null, $target = null, $strict = null ) {

        $parameters = array();

        $parameters[ 'key' ] = trim( $key );
        ( !empty( $source ) ? $parameters[ 'source' ] = $source : null );
        ( !empty( $target ) ? $parameters[ 'target' ] = $target : null );
        ( !empty( $strict ) ? $parameters[ 'strict' ] = $strict : null );

        $this->doQuery( 'tmx_export_create', $parameters, false );

        return $this->raw_result;

    }

    /**
     * Memory Export check for status,
     * <br />invoke with the same parameters of createExport
     *
     * @see TmKeyManagement_SimpleTMX::createExport
     *
     * @param      $key
     * @param null $source
     * @param null $target
     * @param null $strict
     *
     * @return array
     */
    public function checkExport(  $key, $source = null, $target = null, $strict = null  ){

        $parameters = array();

        $parameters[ 'key' ] = trim( $key );
        ( !empty( $source ) ? $parameters[ 'source' ] = $source : null );
        ( !empty( $target ) ? $parameters[ 'target' ] = $target : null );
        ( !empty( $strict ) ? $parameters[ 'strict' ] = $strict : null );

        $this->doQuery( 'tmx_export_check', $parameters, false );

        return $this->raw_result;

    }

    /**
     * Get the zip file with the TM inside or a "EMPTY ARCHIVE" message
     * <br> if there are not segments inside the TM
     *
     * @param $key
     * @param $hashPass
     *
     * @return resource
     *
     * @throws Exception
     */
    public function downloadExport( $key, $hashPass ){

        $parameters = array();

        $parameters[ 'key' ] = trim( $key );
        $parameters[ 'pass' ] = trim( $hashPass );

        $this->buildGetQuery( 'tmx_export_download', $parameters );

        $parsed_url = parse_url ( $this->url );

        $isSSL = stripos( $parsed_url['scheme'], "https" ) !== false;

        if( $isSSL ){
            $fp = fsockopen( "ssl://" . $parsed_url['host'], 443, $errno, $err_str, 120 );
        } else {
            $fp = fsockopen( $parsed_url['host'], 80, $errno, $err_str, 120 );
        }

        if (!$fp) {
            throw new Exception( "$err_str ($errno)" );
        }

        $out = "GET " . $parsed_url['path'] . "?" . $parsed_url['query'] .  " HTTP/1.1\r\n";
        $out .= "Host: {$parsed_url['host']}\r\n";
        $out .= "Connection: Close\r\n\r\n";

        Log::doLog( $out );

        fwrite($fp, $out);

        return $fp;

    }

}
