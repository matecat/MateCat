<?php

use API\V2\Exceptions\AuthenticationError;
use Exceptions\NotFoundException;
use Exceptions\ValidationError;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/02/15
 * Time: 18.53
 *
 */
class Engines_MyMemory extends Engines_AbstractEngine {

    /**
     * @var string
     */
    protected $content_type = 'json';

    /**
     * @var array
     */
    protected $_config = [
            'dataRefMap'    => [],
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
    ];

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
     * @param       $rawValue
     * @param array $parameters
     * @param null  $function
     *
     * @return Engines_Results_AbstractResponse
     */
    protected function _decode( $rawValue, array $parameters = [], $function = null ) {

        $functionName = $function;

        if ( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
        } else {
            $decoded = $rawValue; // already decoded in case of error
        }

        $dataRefMap = isset( $this->_config[ 'dataRefMap' ] ) ? $this->_config[ 'dataRefMap' ] : [];

        switch ( $functionName ) {

            case 'glossary_domains_relative_url':
                $result_object = Engines_Results_MyMemory_DomainsResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_check_relative_url':
                $result_object = Engines_Results_MyMemory_CheckGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_update_relative_url':
                $result_object = Engines_Results_MyMemory_UpdateGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_delete_relative_url':
                $result_object = Engines_Results_MyMemory_DeleteGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_set_relative_url':
                $result_object = Engines_Results_MyMemory_SetGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_get_relative_url':
                $result_object = Engines_Results_MyMemory_GetGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_search_relative_url':
                $result_object = Engines_Results_MyMemory_SearchGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_keys_relative_url':
                $result_object = Engines_Results_MyMemory_KeysGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'tags_projection' :
                $result_object = Engines_Results_MyMemory_TagProjectionResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'api_key_check_auth_url':
                $result_object = Engines_Results_MyMemory_AuthKeyResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'api_key_create_user_url':
                $result_object = Engines_Results_MyMemory_CreateUserResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;

            case 'glossary_import_status_relative_url':
            case 'glossary_import_relative_url':
            case 'tmx_import_relative_url':
            case 'tmx_status_relative_url':
                $result_object = Engines_Results_MyMemory_TmxResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'tmx_export_email_url' :
            case 'glossary_export_relative_url' :
                $result_object = Engines_Results_MyMemory_ExportResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'analyze_url':
                $result_object = Engines_Results_MyMemory_AnalyzeResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'contribute_relative_url':
            case 'update_relative_url':
                $result_object = Engines_Results_MyMemory_SetContributionResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            default:

                if ( !empty( $decoded[ 'matches' ] ) ) {
                    foreach ( $decoded[ 'matches' ] as $pos => $match ) {
                        $decoded[ 'matches' ][ $pos ][ 'segment' ]     = $this->_resetSpecialStrings( $match[ 'segment' ] );
                        $decoded[ 'matches' ][ $pos ][ 'translation' ] = $this->_resetSpecialStrings( $match[ 'translation' ] );
                    }
                }

                $result_object = Engines_Results_MyMemory_TMS::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
        }

        return $result_object;
    }

    /**
     * This method is used for help to rebuild result from MyMemory.
     * Because when in CURL you send something using method POST and value's param start with "@"
     * he assumes you are sending a file.
     *
     * Passing prefix you left before, this method, rebuild result putting prefix at start of translated phrase.
     *
     * @param $prefix
     *
     * @return array
     */
    private function rebuildResult( $prefix ) {

        if ( !empty( $this->result->responseData[ 'translatedText' ] ) ) {
            $this->result->responseData[ 'translatedText' ] = $prefix . $this->result->responseData[ 'translatedText' ];
        }

        if ( !empty( $this->result->matches ) ) {
            $matches_keys = [ 'raw_segment', 'segment', 'translation', 'raw_translation' ];
            foreach ( $this->result->matches as $match ) {
                foreach ( $matches_keys as $match_key ) {
                    $match->$match_key = $prefix . $match->$match_key;
                }
            }
        }

        return $this->result;

    }

    /**
     * @param $_config
     *
     * @return array
     * @throws NotFoundException
     * @throws AuthenticationError
     * @throws ValidationError
     * @throws EndQueueException
     * @throws ReQueueException
     */
    public function get( $_config ) {

        $_config[ 'segment' ] = $this->_preserveSpecialStrings( $_config[ 'segment' ] );
        if ( preg_match( "/^(-?@-?)/", $_config[ 'segment' ], $segment_file_chr ) ) {
            $_config[ 'segment' ] = preg_replace( "/^(-?@-?)/", "", $_config[ 'segment' ] );
        }

        $parameters                = [];
        $parameters[ 'q' ]         = $_config[ 'segment' ];
        $parameters[ 'langpair' ]  = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]        = $_config[ 'email' ];
        $parameters[ 'mt' ]        = $_config[ 'get_mt' ];
        $parameters[ 'numres' ]    = $_config[ 'num_result' ];
        $parameters[ 'client_id' ] = isset( $_config[ 'uid' ] ) ? $_config[ 'uid' ] : 0;

        if ( isset( $_config[ 'dialect_strict' ] ) ) {
            $parameters[ 'dialect_strict' ] = $_config[ 'dialect_strict' ];
        }

        ( !empty( $_config[ 'onlyprivate' ] ) ? $parameters[ 'onlyprivate' ] = 1 : null );
        ( !empty( $_config[ 'isConcordance' ] ) ? $parameters[ 'conc' ] = 'true' : null );
        ( !empty( $_config[ 'isConcordance' ] ) ? $parameters[ 'extended' ] = '1' : null );
        ( !empty( $_config[ 'mt_only' ] ) ? $parameters[ 'mtonly' ] = '1' : null );

        if ( !empty( $_config[ 'context_after' ] ) || !empty( $_config[ 'context_before' ] ) ) {
            $parameters[ 'context_after' ]  = ltrim( $_config[ 'context_after' ], "@-" );
            $parameters[ 'context_before' ] = ltrim( $_config[ 'context_before' ], "@-" );
        }

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = [ $_config[ 'id_user' ] ];
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        $parameters = $this->featureSet->filter( 'filterMyMemoryGetParameters', $parameters, $_config );

        $this->call( "translate_relative_url", $parameters, true );

        if ( isset( $segment_file_chr[ 1 ] ) ) {
            $this->rebuildResult( $segment_file_chr[ 1 ] );
        }

        return $this->result;

    }

    /**
     * @param $_config
     *
     * @return array|bool
     */
    public function set( $_config ) {
        $parameters                = [];
        $parameters[ 'seg' ]       = preg_replace( "/^(-?@-?)/", "", $_config[ 'segment' ] );
        $parameters[ 'tra' ]       = preg_replace( "/^(-?@-?)/", "", $_config[ 'translation' ] );
        $parameters[ 'tnote' ]     = $_config[ 'tnote' ];
        $parameters[ 'langpair' ]  = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]        = $_config[ 'email' ];
        $parameters[ 'mt' ]        = isset( $_config[ 'set_mt' ] ) ? $_config[ 'set_mt' ] : true;
        $parameters[ 'client_id' ] = isset( $_config[ 'uid' ] ) ? $_config[ 'uid' ] : 0;
        $parameters[ 'prop' ]      = $_config[ 'prop' ];

        if ( !empty( $_config[ 'context_after' ] ) || !empty( $_config[ 'context_before' ] ) ) {
            $parameters[ 'context_after' ]  = preg_replace( "/^(-?@-?)/", "", @$_config[ 'context_after' ] );
            $parameters[ 'context_before' ] = preg_replace( "/^(-?@-?)/", "", @$_config[ 'context_before' ] );
        }

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = [ $_config[ 'id_user' ] ];
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        $this->call( 'contribute_relative_url', $parameters, true );

        if ( $this->result->responseStatus != "200" ) {
            return false;
        }

        return $this->result->responseDetails[ 0 ]; // return the MyMemory ID

    }

    public function update( $_config ) {

        $parameters                = [];
        $parameters[ 'seg' ]       = preg_replace( "/^(-?@-?)/", "", $_config[ 'segment' ] );
        $parameters[ 'tra' ]       = preg_replace( "/^(-?@-?)/", "", $_config[ 'translation' ] );
        $parameters[ 'newseg' ]    = preg_replace( "/^(-?@-?)/", "", $_config[ 'newsegment' ] );
        $parameters[ 'newtra' ]    = preg_replace( "/^(-?@-?)/", "", $_config[ 'newtranslation' ] );
        $parameters[ 'langpair' ]  = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'prop' ]      = $_config[ 'prop' ];
        $parameters[ 'client_id' ] = isset( $_config[ 'uid' ] ) ? $_config[ 'uid' ] : 0;
        $parameters[ 'de' ]        = $_config[ 'email' ];
        $parameters[ 'mt' ]        = isset( $_config[ 'set_mt' ] ) ? $_config[ 'set_mt' ] : true;

        if ( !empty( $_config[ 'context_after' ] ) || !empty( $_config[ 'context_before' ] ) ) {
            $parameters[ 'context_after' ]  = ( !empty( $_config[ 'context_after' ] ) ) ? preg_replace( "/^(-?@-?)/", "", $_config[ 'context_after' ] ) : null;
            $parameters[ 'context_before' ] = ( !empty( $_config[ 'context_before' ] ) ) ? preg_replace( "/^(-?@-?)/", "", $_config[ 'context_before' ] ) : null;
        }

        if ( !empty( $_config[ 'id_user' ] ) ) {
            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = [ $_config[ 'id_user' ] ];
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        $this->call( "update_relative_url", $parameters, true );

        // Let the caller handle the error management.
        return $this->result;

    }

    /**
     * @param $_config
     *
     * @return bool
     */
    public function delete( $_config ) {

        $parameters               = [];
        $parameters[ 'langpair' ] = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];

        if ( isset( $_config[ 'segment' ] ) and isset( $_config[ 'translation' ] ) ) {
            $parameters[ 'seg' ] = preg_replace( "/^(-?@-?)/", "", $_config[ 'segment' ] );
            $parameters[ 'tra' ] = preg_replace( "/^(-?@-?)/", "", $_config[ 'translation' ] );
        }

        if ( isset( $_config[ 'id_match' ] ) ) {
            $parameters[ 'id' ] = $_config[ 'id_match' ];
        }

        if ( !empty( $_config[ 'id_user' ] ) ) {

            if ( !is_array( $_config[ 'id_user' ] ) ) {
                $_config[ 'id_user' ] = [ $_config[ 'id_user' ] ];
            }
            $parameters[ 'key' ] = implode( ",", $_config[ 'id_user' ] );
        }

        $this->call( "delete_relative_url", $parameters, true );

        if ( $this->result->responseStatus != "200" &&
                ( $this->result->responseStatus != "404" ||
                        $this->result->responseDetails != "NO ID FOUND" )
        ) {
            return false;
        }

        return true;
    }

    /**
     * Post a file to myMemory
     *
     * Remove the first line from csv (source and target)
     * and rewrite the csv because MyMemory doesn't want the header line
     *
     * @param string $file
     * @param string $key
     * @param string $name
     *
     * @return Engines_Results_MyMemory_TmxResponse
     */
    public function glossaryImport( string $file, string $key, string $name = '' ): Engines_Results_MyMemory_TmxResponse {

        $postFields = [
                'glossary' => $this->getCurlFile( $file ),
                'key'      => trim( $key ),
        ];

        if ( $name and $name !== '' ) {
            $postFields[ 'key_name' ] = $name;
        }

        $this->call( "glossary_import_relative_url", $postFields, true );

        /**
         * @var Engines_Results_MyMemory_TmxResponse
         */
        return $this->result;
    }

    /**
     * @param $uuid
     *
     * @return array
     */
    public function getGlossaryImportStatus( $uuid ) {
        $this->call( 'glossary_import_status_relative_url', [
                'uuid' => $uuid
        ] );

        return $this->result;
    }

    /**
     * @param $key
     * @param $keyName
     * @param $userEmail
     * @param $userName
     *
     * @return Engines_Results_MyMemory_ExportResponse
     */
    public function glossaryExport( $key, $keyName, $userEmail, $userName ) {
        $this->call( 'glossary_export_relative_url', [
                'key'        => $key,
                'key_name'   => $keyName,
                'user_name'  => $userName,
                'user_email' => $userEmail,
        ], true );

        return $this->result;
    }

    /**
     * Poll MM for obtain the status of a write operation
     * using a cyclic barrier
     * (import, update, set, delete)
     *
     * @param $uuid
     * @param $relativeUrl
     */
    private function pollForStatus( $uuid, $relativeUrl ) {
        $limit     = 10;
        $sleep     = 1;
        $startTime = time();

        do {

            $this->call( $relativeUrl, [
                    'uuid' => $uuid
            ] );

            if ( $this->result->responseStatus === 202 ) {
                sleep( $sleep );
            }

        } while ( $this->result->responseStatus === 202 and ( time() - $startTime ) <= $limit );
    }

    /**
     * @param       $source
     * @param       $target
     * @param       $sourceLanguage
     * @param       $targetLanguage
     * @param array $keys
     *
     * @return array
     */
    public function glossaryCheck( $source, $target, $sourceLanguage, $targetLanguage, $keys = [] ) {
        $payload = [
                'de'              => INIT::$MYMEMORY_API_KEY,
                'source'          => $source,
                'target'          => $target,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'keys'            => $keys,
        ];
        $this->call( "glossary_check_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param array $keys
     *
     * @return array
     */
    public function glossaryDomains( $keys = [] ) {
        $payload = [
                'de'   => INIT::$MYMEMORY_API_KEY,
                'keys' => $keys,
        ];
        $this->call( "glossary_domains_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     * @param $term
     *
     * @return array
     */
    public function glossaryDelete( $idSegment, $idJob, $password, $term ) {
        $payload = [
                'de'         => INIT::$MYMEMORY_API_KEY,
                "id_segment" => $idSegment,
                "id_job"     => $idJob,
                "password"   => $password,
                "term"       => $term,
        ];
        $this->call( "glossary_delete_relative_url", $payload, true, true );

        if ( $this->result->responseData === 'OK' and isset( $this->result->responseDetails ) ) {
            $uuid = $this->result->responseDetails;
            $this->pollForStatus( $uuid, 'glossary_entry_status_relative_url' );
        }

        return $this->result;
    }

    /**
     * @param $id_job
     * @param $id_segment
     * @param $source
     * @param $sourceLanguage
     * @param $targetLanguage
     * @param $keys
     *
     * @return array
     */
    public function glossaryGet( $id_job, $id_segment, $source, $sourceLanguage, $targetLanguage, $keys ) {
        $payload = [
                'de'              => INIT::$MYMEMORY_API_KEY,
                "id_job"          => $id_job,
                "id_segment"      => $id_segment,
                "source"          => $source,
                "source_language" => $sourceLanguage,
                "target_language" => $targetLanguage,
                "keys"            => $keys,
        ];

        $this->call( "glossary_get_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param $source
     * @param $sourceLanguage
     * @param $targetLanguage
     * @param $keys
     *
     * @return array
     */
    public function glossarySearch( $source, $sourceLanguage, $targetLanguage, $keys ) {
        $payload = [
                'de'              => INIT::$MYMEMORY_API_KEY,
                "source"          => $source,
                "source_language" => $sourceLanguage,
                "target_language" => $targetLanguage,
                "keys"            => $keys,
        ];

        $this->call( "glossary_search_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param string $sourceLanguage
     * @param string $targetLanguage
     * @param array  $keys
     *
     * @return array
     */
    public function glossaryKeys( $sourceLanguage, $targetLanguage, $keys = [] ) {
        $payload = [
                'de'              => INIT::$MYMEMORY_API_KEY,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'keys'            => $keys,
        ];
        $this->call( "glossary_keys_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     * @param $term
     *
     * @return array
     */
    public function glossarySet( $idSegment, $idJob, $password, $term ) {
        $payload = [
                'de'         => INIT::$MYMEMORY_API_KEY,
                "id_segment" => $idSegment,
                "id_job"     => $idJob,
                "password"   => $password,
                "term"       => $term,
        ];

        $this->call( "glossary_set_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param $idSegment
     * @param $idJob
     * @param $password
     * @param $term
     *
     * @return array
     */
    public function glossaryUpdate( $idSegment, $idJob, $password, $term ) {
        $payload = [
                'de'         => INIT::$MYMEMORY_API_KEY,
                "id_segment" => $idSegment,
                "id_job"     => $idJob,
                "password"   => $password,
                "term"       => $term,
        ];
        $this->call( "glossary_update_relative_url", $payload, true, true );

        if ( $this->result->responseData === 'OK' and isset( $this->result->responseDetails ) ) {
            $uuid = $this->result->responseDetails;
            $this->pollForStatus( $uuid, 'glossary_entry_status_relative_url' );
        }

        return $this->result;
    }

    public function import( $file, $key, $name = false ) {

        $postFields = [
                'tmx'  => $this->getCurlFile( $file ),
                'name' => $name,
                'key'  => trim( $key )
        ];

        $this->call( "tmx_import_relative_url", $postFields, true );

        return $this->result;
    }

    public function getStatus( $uuid ) {

        $parameters = [ 'uuid' => trim( $uuid ) ];
        $this->call( 'tmx_status_relative_url', $parameters );

        return $this->result;
    }

    /**
     * Calls the MyMemory endpoint to send the TMX download URL to the user e-mail
     *
     * @param $key
     * @param $name
     * @param $userEmail
     * @param $userName
     * @param $userSurname
     * @param $strip_tags
     *
     * @return Engines_Results_MyMemory_ExportResponse
     * @throws Exception
     *
     */
    public function emailExport( $key, $name, $userEmail, $userName, $userSurname, $strip_tags = false ) {
        $parameters = [];

        $parameters[ 'key' ]        = trim( $key );
        $parameters[ 'user_email' ] = trim( $userEmail );
        $parameters[ 'user_name' ]  = trim( $userName ) . " " . trim( $userSurname );
        ( !empty( $name ) ? $parameters[ 'zip_name' ] = $name : $parameters[ 'zip_name' ] = $key );
        $parameters[ 'zip_name' ] = $parameters[ 'zip_name' ] . ".zip";

        if ( $strip_tags ) {
            $parameters[ 'strip_tags' ] = 1;
        }

        $this->call( 'tmx_export_email_url', $parameters );

        /**
         * $result Engines_Results_MyMemory_ExportResponse
         */
        if ( $this->result->responseStatus >= 400 ) {
            throw new Exception( $this->result->error->message, $this->result->responseStatus );
        }

        Log::doJsonLog( 'TMX exported to E-mail.' );

        return $this->result;
    }

    /*****************************************/
    /**
     * @throws Exception
     */
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

        $postFields = [
                'key' => trim( $apiKey )
        ];

        //query db
//        $this->doQuery( 'api_key_check_auth', $postFields );
        $this->call( 'api_key_check_auth_url', $postFields );

        if ( !$this->result->responseStatus == 200 ) {
            Log::doJsonLog( "Error: The check for MyMemory private key correctness failed: " . $this->result[ 'error' ][ 'message' ] . " ErrNum: " . $this->result[ 'error' ][ 'code' ] );
            throw new Exception( "Error: The private TM key you entered ( $apiKey ) seems to be invalid. Please, check that the key is correct.", -2 );
        }

        $isValidKey = filter_var( $this->result->responseData, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

        if ( $isValidKey === null ) {
            throw new Exception( "Error: The private TM key you entered seems to be invalid: $apiKey", -3 );
        }

        return $isValidKey;

    }

    /******************************************/

    public function fastAnalysis( $segs_array ) {

        if ( !is_array( $segs_array ) ) {
            return null;
        }

        $this->_setAdditionalCurlParams( [
                        CURLOPT_TIMEOUT => 300
                ]
        );

        $this->engineRecord[ 'base_url' ] = "https://analyze.mymemory.translated.net/api/v1";

        $this->call( "analyze_url", array_values( $segs_array ), true, true );

        return $this->result;

    }

    /**
     * MyMemory private endpoint
     *
     * @param $config
     *
     * @return array|Engines_Results_MyMemory_TagProjectionResponse
     */
    public function getTagProjection( $config ) {

        // set dataRefMap needed to instance
        // Engines_Results_MyMemory_TagProjectionResponse class
        $this->_config[ 'dataRefMap' ] = isset( $config[ 'dataRefMap' ] ) ? $config[ 'dataRefMap' ] : [];

        //tag replace
        $source_string = $config[ 'source' ];
        $target_string = $config[ 'target' ];
//        $re2 = '<ph id\s*=\s*["\']mtc_[0-9]+["\'] ctype\s*=\s*["\']x-([0-9a-zA-Z\-]+)["\'] equiv-text\s*=\s*["\']base64:([^"\']+)["\']\s*\/>';
//        preg_match_all("/" . $re2 .'/siU', $source_string, $source_matches_tag,PREG_OFFSET_CAPTURE, 0);
//        preg_match_all("/" . $re2 .'/siU', $target_string, $target_matches_tag,PREG_OFFSET_CAPTURE, 0);
//
//        $map=[];
//        foreach ($source_matches_tag[0] as $source_key=>$source_tag){
//            foreach ($target_matches_tag[0] as $target_tag){
//                if($source_tag[0] == $target_tag[0]){
//                    $replace = md5($source_matches_tag[2][$source_key][0]);
//                    $source_string = str_replace($source_tag[0], $replace, $source_string);
//                    $target_string = str_replace($source_tag[0], $replace, $target_string);
//                    $map[$replace] = $source_tag[0];
//                }
//            }
//        }

        //formatting strip
        $re = '(&#09;|\p{Zs}|&#10;|\n|\t|⇥|\x{21E5}|\xc2\xa0|\xE2|\x81|\xA0)+';
        //trim chars that would have been lost with the guess tag
        preg_match( "/" . $re . '$/', $target_string, $r_matches, PREG_OFFSET_CAPTURE );
        preg_match( "/^" . $re . '/', $target_string, $l_matches, PREG_OFFSET_CAPTURE );
        $r_index   = ( isset( $r_matches[ 0 ][ 1 ] ) ) ? $r_matches[ 0 ][ 1 ] : mb_strlen( $target_string );
        $l_index   = ( isset( $l_matches[ 0 ][ 1 ] ) ) ? (int)$l_matches[ 0 ][ 1 ] + mb_strlen( $l_matches[ 0 ][ 0 ] ) : 0;
        $r_matches = ( isset( $r_matches[ 0 ][ 0 ] ) ) ? $r_matches[ 0 ][ 0 ] : '';
        $l_matches = ( isset( $l_matches[ 0 ][ 0 ] ) ) ? $l_matches[ 0 ][ 0 ] : '';

        $parameters           = [];
        $parameters[ 's' ]    = $source_string;
        $parameters[ 't' ]    = mb_substr( $target_string, $l_index, $r_index - $l_index );
        $parameters[ 'hint' ] = $config[ 'suggestion' ];

        $this->_setAdditionalCurlParams( [
                CURLOPT_FOLLOWLOCATION => true,
        ] );

        $this->engineRecord->base_url                    = parse_url( $this->engineRecord->base_url, PHP_URL_HOST ) . ":10000";
        $this->engineRecord->others[ 'tags_projection' ] .= '/' . $config[ 'source_lang' ] . "/" . $config[ 'target_lang' ] . "/";
        $this->call( 'tags_projection', $parameters );

        if ( !empty( $this->result->responseData ) ) {
            //formatting replace
            $this->result->responseData = $l_matches . $this->result->responseData . $r_matches;
            //tag replace
//            foreach ($map as $key=>$value){
//                $this->result->responseData = str_replace($key, $value, $this->result->responseData);
//            }
        }

        return $this->result;

    }
}
