<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/02/15
 * Time: 18.53
 *
 */
class Engines_MyMemory extends Engines_AbstractEngine implements Engines_EngineInterface {

    protected $_config = array(
            'segment'       => null,
            'translation'   => null,
            'tnote'         => null,
            'source'        => null,
            'target'        => null,
            'email'         => null,
            'prop'          => null,
            'get_mt'        => 1,
            'id_user'       => null,
            'num_result'    => 3,
            'mt_only'       => false,
            'isConcordance' => false,
            'isGlossary'    => false,
    );

    /**
     * @param $engineRecord
     *
     * @throws Exception
     */
    public function __construct( $engineRecord ) {
        parent::__construct( $engineRecord );
        if ( $this->engineRecord->type != "TM" ) {
            throw new Exception( "Engine {$this->engineRecord->id} is not a TMS engine, found {$this->engineRecord->type} -> {$this->engineRecord->class_load}" );
        }
    }

    /**
     * @param $rawValue
     *
     * @return Engines_Results_AbstractResponse
     */
    protected function _decode( $rawValue ) {
        $args         = func_get_args();
        $functionName = $args[ 2 ];

        if ( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
        } else {
            $decoded = $rawValue; // already decoded in case of error
        }

        $result_object = null;

        switch ( $functionName ) {
            case 'api_key_check_auth_url':
                $result_object = Engines_Results_MyMemory_AuthKeyResponse::getInstance( $decoded );
                break;
            case 'api_key_create_user_url':
                $result_object = Engines_Results_MyMemory_CreateUserResponse::getInstance( $decoded );
                break;
            case 'glossary_import_relative_url':
            case 'tmx_import_relative_url':
            case 'tmx_status_relative_url':
                $result_object = Engines_Results_MyMemory_TmxResponse::getInstance( $decoded );
                break;
            case 'tmx_export_create_url' :
            case 'tmx_export_check_url' :
                $result_object = Engines_Results_MyMemory_ExportResponse::getInstance( $decoded );
                break;
            case 'analyze_url':
                $result_object = Engines_Results_MyMemory_AnalyzeResponse::getInstance( $decoded );
                break;
            case 'contribute_relative_url':
                $result_object = Engines_Results_MyMemory_SetContributionResponse::getInstance( $decoded );
                break;
            default:

                if ( isset( $decoded[ 'matches' ] ) ) {
                    foreach ( $decoded[ 'matches' ] as $pos => $match ) {
                        $decoded[ 'matches' ][ $pos ][ 'segment' ]     = $this->_resetSpecialStrings( $match[ 'segment' ] );
                        $decoded[ 'matches' ][ $pos ][ 'translation' ] = $this->_resetSpecialStrings( $match[ 'translation' ] );
                    }
                }

                $result_object = Engines_Results_MyMemory_TMS::getInstance( $decoded );
                break;
        }

        return $result_object;
    }

    /**
     * @param $_config
     *
     * @return Engines_Results_MyMemory_TMS
     */
    public function get( $_config ) {

        $_config[ 'segment' ] = $this->_preserveSpecialStrings( $_config[ 'segment' ] );

        $parameters               = array();
        $parameters[ 'q' ]        = $_config[ 'segment' ];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];
        $parameters[ 'mt' ]       = $_config[ 'get_mt' ];
        $parameters[ 'numres' ]   = $_config[ 'num_result' ];

        ( $_config[ 'isConcordance' ] ? $parameters[ 'conc' ] = 'true' : null );
        ( $_config[ 'isConcordance' ] ? $parameters[ 'extended' ] = '1' : null );
        ( $_config[ 'mt_only' ] ? $parameters[ 'mtonly' ] = '1' : null );

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = array( $_config[ 'id_user' ] );
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        ( !$_config[ 'isGlossary' ] ? $function = "translate_relative_url" : $function = "gloss_get_relative_url" );


        $this->call( $function, $parameters );

        return $this->result;

    }

    /**
     * @param $_config
     *
     * @return bool
     */
    public function set( $_config ) {

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'tnote' ]    = $_config[ 'tnote' ];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];
        $parameters[ 'prop' ]     = $_config[ 'prop' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = array( $_config[ 'id_user' ] );
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        ( !$_config[ 'isGlossary' ] ? $function = "contribute_relative_url" : $function = "gloss_set_relative_url" );

        $this->call( $function, $parameters );

        if ( $this->result->responseStatus != "200" ) {
            return false;
        }

        return true;

    }

    /**
     * @param $_config
     *
     * @return bool
     */
    public function delete( $_config ) {

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = array( $_config[ 'id_user' ] );
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        ( !$_config[ 'isGlossary' ] ? $function = "delete_relative_url" : $function = "gloss_delete_relative_url" );

        $this->call( $function, $parameters );

        /*
         * If the segment to be deleted is not present in the current TM,
         * MyMemory response is
         * {"responseData":{"translatedText":"NO ID FOUND"},
         *  "responseDetails":"NO ID FOUND",
         *  "responseStatus":"403",
         *  "matches":""
         * }
         *
         * but the result is the one expected: the segment is not present in the current TM.
         **/
        if ( $this->result->responseStatus != "200" &&
                ( $this->result->responseStatus != "404" ||
                        $this->result->responseDetails != "NO ID FOUND" )
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param $_config
     *
     * @return bool
     */
    public function update( $_config ) {

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'tnote' ]    = $_config[ 'tnote' ];
        $parameters[ 'prop' ]     = $_config[ 'prop' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = array( $_config[ 'id_user' ] );
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        $this->call( "gloss_update_relative_url", $parameters );

        if ( $this->result->responseStatus != "200" ) {
            return false;
        }

        return true;

    }

    /**
     * Post a file to myMemory
     *
     * Remove the first line from csv ( source and target )
     * and rewrite the csv because MyMemory doesn't want the header line
     *
     * @param            $file
     * @param            $key
     * @param bool|false $name
     *
     * @return Engines_Results_MyMemory_TmxResponse
     */
    public function glossaryImport( $file, $key, $name = false ) {

        try {

            $origFile = new SplFileObject( $file, 'r+' );
            $origFile->setFlags( SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD );

            $tmpFileName = tempnam( "/tmp", 'GLOS' );
            $newFile     = new SplFileObject( $tmpFileName, 'r+' );
            $newFile->setFlags( SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD );

            foreach ( $origFile as $line_num => $line ) {

                if ( count( $line ) < 2 ) {
                    throw new RuntimeException( "No valid glossary file provided. Field separator could be not valid." );
                }

                if ( $line_num == 0 ) {
                    list( $source_lang, $target_lang, ) = $line;

                    //eventually, remove BOM from source language
                    $bom = pack('H*','EFBBBF');
                    $source_lang = preg_replace("/^$bom/","",$source_lang);

                    if ( !Langs_Languages::isEnabled( $source_lang ) ) {
                        throw new RuntimeException( "The source language specified in the glossary is not supported: " . $source_lang );
                    }

                    if ( !Langs_Languages::isEnabled( $target_lang ) ) {
                        throw new RuntimeException( "The target language specified in the glossary is not supported: " . $target_lang );
                    }

                    if ( empty( $source_lang ) || empty( $target_lang ) ) {
                        throw new RuntimeException( "No language definition found in glossary file." );
                    }
                    continue;
                }

                //copy stream to stream
                $newFile->fputcsv( $line );

            }
            $newFile->fflush();

            $origFile = null; //close the file handle
            $newFile  = null; //close the file handle
            copy( $tmpFileName, $file );
            unlink( $tmpFileName );

        } catch ( RuntimeException $e ) {
            $this->result = new Engines_Results_MyMemory_TmxResponse( array(
                    "responseStatus"  => 406,
                    "responseData"    => null,
                    "responseDetails" => $e->getMessage()
            ) );

            return $this->result;
        }

        $postFields = array(
                'glossary'    => "@" . realpath( $file ),
                'source_lang' => $source_lang,
                'target_lang' => $target_lang,
                'name'        => $name,
        );

        $postFields[ 'key' ] = trim( $key );

        $this->call( "glossary_import_relative_url", $postFields, true );

        return $this->result;
    }

    public function import( $file, $key, $name = false ) {

        $postFields = array(
                'tmx'  => "@" . realpath( $file ),
                'name' => $name
        );

        $postFields[ 'key' ] = trim( $key );

        $this->call( "tmx_import_relative_url", $postFields, true );

        return $this->result;
    }

    public function getStatus( $key, $name = false ) {

        $parameters          = array();
        $parameters[ 'key' ] = trim( $key );

        //if provided, add name parameter
        if ( $name ) {
            $parameters[ 'name' ] = $name;
        }

        $this->call( 'tmx_status_relative_url', $parameters );

        return $this->result;
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

        $this->call( 'tmx_export_create_url', $parameters );

        return $this->result;

    }

    /**
     * Memory Export check for status,
     * <br />invoke with the same parameters of createExport
     *
     * @see Engines_MyMemory::createExport
     *
     * @param      $key
     * @param null $source
     * @param null $target
     * @param null $strict
     *
     * @return mixed
     */
    public function checkExport( $key, $source = null, $target = null, $strict = null ) {

        $parameters = array();

        $parameters[ 'key' ] = trim( $key );
        ( !empty( $source ) ? $parameters[ 'source' ] = $source : null );
        ( !empty( $target ) ? $parameters[ 'target' ] = $target : null );
        ( !empty( $strict ) ? $parameters[ 'strict' ] = $strict : null );

        $this->call( 'tmx_export_check_url', $parameters );

        return $this->result;

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
    public function downloadExport( $key, $hashPass ) {

        $parameters = array();

        $parameters[ 'key' ]  = trim( $key );
        $parameters[ 'pass' ] = trim( $hashPass );

//        $this->call( 'tmx_export_download_url', $parameters );

        $url = $this->base_url . "/" . $this->tmx_export_download_url . "?";
        $url .= http_build_query( $parameters );;

//        $parsed_url = parse_url ( $this->url );
        $parsed_url = parse_url( $url );

        $isSSL = stripos( $parsed_url[ 'scheme' ], "https" ) !== false;

//        if( $isSSL ){
//            $fp = fsockopen( "ssl://" . $parsed_url['host'], 443, $errno, $err_str, 120 );
//        } else {
//            $fp = fsockopen( $parsed_url['host'], 80, $errno, $err_str, 120 );
//        }
//
//        if (!$fp) {
//            throw new Exception( "$err_str ($errno)" );
//        }
//
//        $out = "GET " . $parsed_url['path'] . "?" . $parsed_url['query'] .  " HTTP/1.1\r\n";
//        $out .= "Host: {$parsed_url['host']}\r\n";
//        $out .= "Connection: Close\r\n\r\n";
//
//        Log::doLog( "Download TMX: " . $this->url );

//        fwrite($fp, $out);

        $streamFileName = tempnam( "/tmp", "TMX" );

        $handle = fopen( $streamFileName, "w+" );

        $ch = curl_init();

        // set URL and other appropriate options
//        curl_setopt( $ch, CURLOPT_URL, $this->url );
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_HEADER, 0 );
        curl_setopt( $ch, CURLOPT_FILE, $handle ); // write curl response to file
        curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );

        // grab URL and pass it to the browser
        curl_exec( $ch );

        rewind( $handle );

        return $handle;

    }

    /*****************************************/
    public function createMyMemoryKey() {

        //query db
        $this->call( 'api_key_create_user_url' );

        if ( !$this->result instanceof Engines_Results_MyMemory_CreateUserResponse ) {
            if ( empty( $this->result ) || $this->result[ 'error' ] || $this->result[ 'error' ][ 'code' ] != 200 ) {
                throw new Exception( "Private TM key .", -1 );
            }
        }

        unset( $this->result->responseStatus );
        unset( $this->result->responseDetails );
        unset( $this->result->responseData );

        return $this->result;

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
                'key' => trim( $apiKey )
        );

        //query db
//        $this->doQuery( 'api_key_check_auth', $postFields );
        $this->call( 'api_key_check_auth_url', $postFields );

        if ( !$this->result->responseStatus == 200 ) {
            Log::doLog( "Error: The check for MyMemory private key correctness failed: " . $this->result[ 'error' ][ 'message' ] . " ErrNum: " . $this->result[ 'error' ][ 'code' ] );
            throw new Exception( "Error: The private TM key you entered ( $apiKey ) seems to be invalid. Please, check that the key is correct.", -2 );
        }

        $isValidKey = filter_var( $this->result->responseData, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

        if ( $isValidKey === null ) {
            throw new Exception( "Error: The private TM key you entered seems to be invalid: $apiKey", -3 );
        }

        return $isValidKey;

    }

    /******************************************/

    public function fastAnalysis( &$segs_array ) {
        if ( !is_array( $segs_array ) ) {

            return null;
        }
        $json_segs = json_encode( $segs_array );

        $parameters[ 'fast' ] = "1";
        $parameters[ 'df' ]   = "matecat_array";
        $parameters[ 'segs' ] = $json_segs;

        $this->_setAdditionalCurlParams( array(
                        CURLOPT_TIMEOUT => 300
                )
        );


        $this->call( "analyze_url", $parameters, true );

        return $this->result;

    }

    /**
     * Detect language for an array of file's segments.
     *
     * @param $segs_array Array An array whose keys are file IDs and values are array of segments.
     *
     * @return mixed
     */
    public function detectLanguage( $segs_array, $lang_detect_files ) {
        //In this array we will put a significative string for each job.
        $segmentsToBeDetected = array();


        /**
         * @var $segs_array ArrayObject
         */
        $arrayIterator = $segs_array->getIterator();

        $counter = 0;
        //iterate through files and extract a significative
        //string long at least 150 characters for language detection
        while ( $arrayIterator->valid() ) {
            $currFileName = key( $lang_detect_files );

            if ( $lang_detect_files[ $currFileName ] == "skip" ) {
                //this will force google to answer with "und" language code
                $segmentsToBeDetected[] = "q[$counter]=1";

                next( $lang_detect_files );
                $arrayIterator->next();
                $counter++;
                continue;
            }

            $currFileId = $arrayIterator->key();

            $currFile = $arrayIterator->current();

            /**
             * @var $currFileIterator ArrayIterator
             */
            $segmentArray = $currFile->getIterator()->current();

            //take first 50 segments
            $segmentArray = array_slice( $segmentArray, 0, 50 );

            foreach ( $segmentArray as $i => $singleSegment ) {
                $singleSegment = explode( ",", $singleSegment );
                $singleSegment = array_slice( $singleSegment, 3, 1 );

                //remove tags, duplicated spaces and all not Unicode Letter
                $singleSegment[ 0 ] = preg_replace( array( "#<[^<>]*>#", "#\x20{2,}#", '#\PL+#u' ), array(
                        "", " ", " "
                ), $singleSegment[ 0 ] );

                //remove not useful spaces
                $singleSegment[ 0 ] = preg_replace( "#\x20{2,}#", " ", $singleSegment[ 0 ] );

                $segmentArray[ $i ] = $singleSegment[ 0 ];
            }

            if ( !function_exists( 'sortByStrLenAsc' ) ) {
                function sortByStrLenAsc( $a, $b ) {
                    return strlen( $a ) >= strlen( $b );
                }
            }

            usort( $segmentArray, array( 'sortByStrLenAsc' ) );

            $textToBeDetected = "";
            /**
             * take first 150 characters starting from the longest segment in the slice
             */
            for ( $i = count( $segmentArray ) - 1; $i >= 0; $i-- ) {
                $textToBeDetected .= " " . trim( $segmentArray[ $i ], "'" );
                if ( mb_strlen( $textToBeDetected ) > 150 ) {
                    break;
                }
            }
            $segmentsToBeDetected[] = "q[$counter]=" . urlencode( $textToBeDetected );

            next( $lang_detect_files );
            $arrayIterator->next();
            $counter++;
        }

        $curl_parameters = implode( "&", $segmentsToBeDetected ) . "&of=json";

        log::dolog( "DETECT LANG :", $segmentsToBeDetected );

        $options = array(
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => 0,
                CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $curl_parameters,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
        );

        $url = strtolower( $this->base_url . "/" . $this->detect_language_url );

        $mh        = new MultiCurlHandler();
        $tokenHash = $mh->createResource( $url, $options );
        Log::dolog( "DETECT LANG TOKENHASH: $tokenHash" );

        $mh->multiExec();

        $res = $mh->getAllContents();
        Log::dolog( "DETECT LANG RES:", $res );

        return json_decode( $res[ $tokenHash ], true );
    }

}
