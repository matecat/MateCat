<?php

namespace Utils\Engines;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\Projects\MetadataDao;
use Model\Users\UserStruct;
use Utils\Constants\EngineConstants;
use Utils\Engines\Results\MyMemory\AnalyzeResponse;
use Utils\Engines\Results\MyMemory\AuthKeyResponse;
use Utils\Engines\Results\MyMemory\CheckGlossaryResponse;
use Utils\Engines\Results\MyMemory\CreateUserResponse;
use Utils\Engines\Results\MyMemory\DeleteGlossaryResponse;
use Utils\Engines\Results\MyMemory\DomainsResponse;
use Utils\Engines\Results\MyMemory\ExportResponse;
use Utils\Engines\Results\MyMemory\FileImportAndStatusResponse;
use Utils\Engines\Results\MyMemory\GetGlossaryResponse;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\MyMemory\KeysGlossaryResponse;
use Utils\Engines\Results\MyMemory\Matches;
use Utils\Engines\Results\MyMemory\SearchGlossaryResponse;
use Utils\Engines\Results\MyMemory\SetContributionResponse;
use Utils\Engines\Results\MyMemory\SetGlossaryResponse;
use Utils\Engines\Results\MyMemory\TagProjectionResponse;
use Utils\Engines\Results\MyMemory\UpdateGlossaryResponse;
use Utils\Engines\Results\TMSAbstractResponse;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/02/15
 * Time: 18.53
 *
 */
class MyMemory extends AbstractEngine {

    /**
     * @inheritdoc
     * @see AbstractEngine::$_isAdaptiveMT
     * @var bool
     */
    protected bool $_isAdaptiveMT = false;

    public function isTMS(): bool {
        return true;
    }

    /**
     * @var string
     */
    protected string $content_type = 'json';

    /**
     * @var array
     */
    protected array $_config = [
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
        if ( $this->getEngineRecord()->type != EngineConstants::TM ) {
            throw new Exception( "Engine {$this->getEngineRecord()->id} is not a TMS engine, found {$this->getEngineRecord()->type} -> {$this->getEngineRecord()->class_load}" );
        }
    }

    /**
     * @param       $rawValue
     * @param array $parameters
     * @param null  $function
     *
     * @return TMSAbstractResponse
     */
    protected function _decode( $rawValue, array $parameters = [], $function = null ): TMSAbstractResponse {

        $functionName = $function;

        if ( is_string( $rawValue ) ) {
            $decoded = json_decode( $rawValue, true );
        } else {
            $decoded = $rawValue; // already decoded in case of error
        }

        $dataRefMap = $this->_config[ 'dataRefMap' ] ?? [];

        switch ( $functionName ) {

            case 'glossary_domains_relative_url':
                $result_object = DomainsResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_check_relative_url':
                $result_object = CheckGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_update_relative_url':
                $result_object = UpdateGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_delete_relative_url':
                $result_object = DeleteGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_set_relative_url':
                $result_object = SetGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_get_relative_url':
                $result_object = GetGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_search_relative_url':
                $result_object = SearchGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'glossary_keys_relative_url':
                $result_object = KeysGlossaryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'tags_projection' :
                $result_object = TagProjectionResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'api_key_check_auth_url':
                $result_object = AuthKeyResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'api_key_create_user_url':
                $result_object = CreateUserResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;

            case 'glossary_import_relative_url':
            case 'tmx_import_relative_url':
            case 'tmx_status_relative_url':
                $result_object = FileImportAndStatusResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'tmx_export_email_url' :
            case 'glossary_export_relative_url' :
                $result_object = ExportResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'analyze_url':
                $result_object = AnalyzeResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            case 'contribute_relative_url':
            case 'update_relative_url':
                $result_object = SetContributionResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
            default:

                if ( !empty( $decoded[ 'matches' ] ) ) {
                    foreach ( $decoded[ 'matches' ] as $pos => $match ) {
                        $decoded[ 'matches' ][ $pos ][ 'segment' ]     = $match[ 'segment' ];
                        $decoded[ 'matches' ][ $pos ][ 'translation' ] = $match[ 'translation' ];
                    }
                }

                $result_object = GetMemoryResponse::getInstance( $decoded, $this->featureSet, $dataRefMap );
                break;
        }

        return $result_object;
    }

    private function possiblyOverrideMtPenalty(): void {
        if ( !empty( $this->result->matches ) ) {
            /** @var $match Matches */
            foreach ( $this->result->matches as $match ) {
                if ( stripos( $match->created_by, InternalMatchesConstants::MT ) !== false ) {
                    $match->match = $this->getStandardMtPenaltyString();
                }
            }
        }
    }


    /**
     * @param array $_config
     *
     * @return GetMemoryResponse
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ValidationError
     */
    public function get( array $_config ): GetMemoryResponse {

        $parameters                 = [];
        $parameters[ 'q' ]          = $_config[ 'segment' ];
        $parameters[ 'langpair' ]   = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'de' ]         = $_config[ 'email' ];
        $parameters[ 'mt' ]         = $_config[ 'get_mt' ];
        $parameters[ 'numres' ]     = $_config[ 'num_result' ];
        $parameters[ 'client_id' ]  = $_config[ 'uid' ] ?? 0;

        // TM prioritization
        $parameters[ 'priority_key' ] = ( isset( $_config[ 'priority_key' ] ) && $_config[ 'priority_key' ] ) ? 1 : 0;

        // public_tm_penalty
        if ( isset( $_config[ 'public_tm_penalty' ] ) && is_numeric( $_config[ 'public_tm_penalty' ] ) ) {
            $_config[ 'penalty_key' ][] = [
                    'key'     => 'public',
                    'penalty' => $_config[ 'public_tm_penalty' ] / 100,
            ];
        }

        if ( !empty( $_config[ 'penalty_key' ] ) ) {
            $parameters[ 'penalty_key' ] = json_encode( $_config[ 'penalty_key' ] );
        }

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

        // Here we pass the subfiltering configuration to the API.
        // This value can be an array or null, if null, no filters will be loaded, if the array is empty, the default filters list will be loaded.
        // We use the JSON to pass a nullable value.
        $parameters[ MetadataDao::SUBFILTERING_HANDLERS ] = json_encode( $_config[ MetadataDao::SUBFILTERING_HANDLERS ] ?? null ); // null coalescing operator to avoid warnings, we want to propagate null when it is not set.

        $parameters = $this->featureSet->filter( 'filterMyMemoryGetParameters', $parameters, $_config );

        $this->call( "translate_relative_url", $parameters, true );

        $this->possiblyOverrideMtPenalty();

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
        $parameters[ 'mt' ]        = $_config[ 'set_mt' ] ?? true;
        $parameters[ 'client_id' ] = $_config[ 'uid' ] ?? 0;
        $parameters[ 'prop' ]      = $_config[ 'prop' ];

        if ( !empty( $_config[ 'context_after' ] ) || !empty( $_config[ 'context_before' ] ) ) {
            $parameters[ 'context_after' ]  = preg_replace( "/^(-?@-?)/", "", $_config[ 'context_after' ] ?? '' );
            $parameters[ 'context_before' ] = preg_replace( "/^(-?@-?)/", "", $_config[ 'context_before' ] ?? '' );
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

        return $this->result->responseDetails[ 0 ]; // return the Match ID

    }

    public function update( $_config ) {

        $parameters                 = [];
        $parameters[ 'seg' ]        = preg_replace( "/^(-?@-?)/", "", $_config[ 'segment' ] );
        $parameters[ 'tra' ]        = preg_replace( "/^(-?@-?)/", "", $_config[ 'translation' ] );
        $parameters[ 'newseg' ]     = preg_replace( "/^(-?@-?)/", "", $_config[ 'newsegment' ] );
        $parameters[ 'newtra' ]     = preg_replace( "/^(-?@-?)/", "", $_config[ 'newtranslation' ] );
        $parameters[ 'langpair' ]   = $_config[ 'source' ] . "|" . $_config[ 'target' ];
        $parameters[ 'prop' ]       = $_config[ 'prop' ];
        $parameters[ 'client_id' ]  = $_config[ 'uid' ] ?? 0;
        $parameters[ 'de' ]         = $_config[ 'email' ];
        $parameters[ 'mt' ]         = $_config[ 'set_mt' ] ?? true;
        $parameters[ 'spiceMatch' ] = $_config[ 'spiceMatch' ];

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
    public function delete( $_config ): bool {

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
     * Check the entry status on myMemory
     *
     * @param string $uuid
     *
     * @return FileImportAndStatusResponse
     */
    public function entryStatus( string $uuid ): TMSAbstractResponse {

        // 1 second timeout
        $this->_setAdditionalCurlParams( [
                        CURLOPT_TIMEOUT => 1
                ]
        );

        $this->call( "entry_status_relative_url", [ 'uuid' => $uuid ] );

        return $this->result;
    }

    /**
     * Post a file to myMemory
     *
     * Remove the first line from csv (source and target)
     * and rewrite the csv because Match doesn't want the header line
     *
     * @param string $file
     * @param string $key
     * @param string $name
     *
     * @return FileImportAndStatusResponse
     */
    public function glossaryImport( string $file, string $key, string $name = '' ): FileImportAndStatusResponse {

        $postFields = [
                'glossary' => $this->getCurlFile( $file ),
                'key'      => trim( $key ),
                'de'       => AppConfig::$MYMEMORY_API_KEY,
        ];

        if ( $name and $name !== '' ) {
            $postFields[ 'key_name' ] = $name;
        }

        $this->call( "glossary_import_relative_url", $postFields, true );

        /**
         * @var FileImportAndStatusResponse
         */
        return $this->result;
    }

    /**
     * @param string $key
     * @param string $keyName
     * @param string $userEmail
     * @param string $userName
     *
     * @return ExportResponse
     */
    public function glossaryExport( string $key, string $keyName, string $userEmail, string $userName ): ExportResponse {
        $this->call( 'glossary_export_relative_url', [
                'key'        => $key,
                'key_name'   => $keyName,
                'user_name'  => $userName,
                'user_email' => $userEmail,
        ], true );

        return $this->result;
    }

    /**
     * @param string     $source
     * @param string     $target
     * @param string     $sourceLanguage
     * @param string     $targetLanguage
     * @param array|null $keys
     *
     * @return CheckGlossaryResponse
     */
    public function glossaryCheck( string $source, string $target, string $sourceLanguage, string $targetLanguage, ?array $keys = [] ): CheckGlossaryResponse {
        $payload = [
                'de'              => AppConfig::$MYMEMORY_API_KEY,
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
     * @param array|null $keys
     *
     * @return DomainsResponse
     */
    public function glossaryDomains( ?array $keys = [] ): DomainsResponse {
        $payload = [
                'de'   => AppConfig::$MYMEMORY_API_KEY,
                'keys' => $keys,
        ];
        $this->call( "glossary_domains_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param string $idSegment
     * @param string $idJob
     * @param string $password
     * @param array  $term
     *
     * @return DeleteGlossaryResponse
     */
    public function glossaryDelete( string $idSegment, string $idJob, string $password, array $term ): DeleteGlossaryResponse {
        $payload = [
                'de'         => AppConfig::$MYMEMORY_API_KEY,
                "id_segment" => $idSegment,
                "id_job"     => $idJob,
                "password"   => $password,
                "term"       => $term,
        ];
        $this->call( "glossary_delete_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param string     $id_job
     * @param string     $id_segment
     * @param string     $source
     * @param string     $sourceLanguage
     * @param string     $targetLanguage
     * @param array|null $keys
     *
     * @return GetGlossaryResponse
     */
    public function glossaryGet( string $id_job, string $id_segment, string $source, string $sourceLanguage, string $targetLanguage, ?array $keys = [] ): GetGlossaryResponse {
        $payload = [
                'de'              => AppConfig::$MYMEMORY_API_KEY,
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
     * @param string     $source
     * @param string     $sourceLanguage
     * @param string     $targetLanguage
     * @param array|null $keys
     *
     * @return SearchGlossaryResponse
     */
    public function glossarySearch( string $source, string $sourceLanguage, string $targetLanguage, ?array $keys = [] ): SearchGlossaryResponse {
        $payload = [
                'de'              => AppConfig::$MYMEMORY_API_KEY,
                "source"          => $source,
                "source_language" => $sourceLanguage,
                "target_language" => $targetLanguage,
                "keys"            => $keys,
        ];

        $this->call( "glossary_search_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param string     $sourceLanguage
     * @param string     $targetLanguage
     * @param array|null $keys
     *
     * @return KeysGlossaryResponse
     */
    public function glossaryKeys( string $sourceLanguage, string $targetLanguage, ?array $keys = [] ): KeysGlossaryResponse {
        $payload = [
                'de'              => AppConfig::$MYMEMORY_API_KEY,
                'source_language' => $sourceLanguage,
                'target_language' => $targetLanguage,
                'keys'            => $keys,
        ];
        $this->call( "glossary_keys_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param string $idSegment
     * @param string $idJob
     * @param string $password
     * @param array  $term
     *
     * @return SetGlossaryResponse
     */
    public function glossarySet( string $idSegment, string $idJob, string $password, array $term ): SetGlossaryResponse {
        $payload = [
                'de'         => AppConfig::$MYMEMORY_API_KEY,
                "id_segment" => $idSegment,
                "id_job"     => $idJob,
                "password"   => $password,
                "term"       => $term,
        ];

        $this->call( "glossary_set_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     * @param string $idSegment
     * @param string $idJob
     * @param string $password
     * @param array  $term
     *
     * @return UpdateGlossaryResponse
     */
    public function glossaryUpdate( string $idSegment, string $idJob, string $password, array $term ): UpdateGlossaryResponse {
        $payload = [
                'de'         => AppConfig::$MYMEMORY_API_KEY,
                "id_segment" => $idSegment,
                "id_job"     => $idJob,
                "password"   => $password,
                "term"       => $term,
        ];
        $this->call( "glossary_update_relative_url", $payload, true, true );

        return $this->result;
    }

    /**
     *
     * @param string     $filePath
     * @param string     $memoryKey
     * @param UserStruct $user * Not used
     *
     * @return array|mixed
     */
    public function importMemory( string $filePath, string $memoryKey, UserStruct $user ) {

        $postFields = [
                'tmx' => $this->getCurlFile( $filePath ),
                'key' => trim( $memoryKey )
        ];

        $this->call( "tmx_import_relative_url", $postFields, true );

        return $this->result;
    }

    public function getImportStatus( $uuid ) {

        $parameters = [ 'uuid' => trim( $uuid ) ];
        $this->call( 'tmx_status_relative_url', $parameters );

        return $this->result;
    }

    /**
     * Calls the Match endpoint to send the TMX download URL to the user e-mail
     *
     * @param string    $key
     * @param string    $name
     * @param string    $userEmail
     * @param string    $userName
     * @param string    $userSurname
     * @param bool|null $strip_tags
     *
     * @return ExportResponse
     * @throws Exception
     */
    public function emailExport( string $key, string $name, string $userEmail, string $userName, string $userSurname, ?bool $strip_tags = false ): ExportResponse {
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
         * $result ExportResponse
         */
        if ( $this->result->responseStatus >= 400 ) {
            throw new Exception( $this->result->error->message, $this->result->responseStatus );
        }

        $this->logger->debug( 'TMX exported to E-mail.' );

        return $this->result;
    }

    /*****************************************/
    /**
     * @throws Exception
     */
    public function createMyMemoryKey() {

        //query db
        $this->call( 'api_key_create_user_url' );

        if ( !$this->result instanceof CreateUserResponse ) {
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
     * Checks for Match Api Key correctness
     *
     * Filter Validate returns true/false for correct/not correct key and NULL is returned for all non-boolean values. ( 404, html, etc. )
     *
     * @param string $apiKey
     *
     * @return bool|null
     * @throws Exception
     */
    public function checkCorrectKey( string $apiKey ): ?bool {

        $postFields = [
                'key' => trim( $apiKey )
        ];

        $this->call( 'api_key_check_auth_url', $postFields );

        if ( !$this->result->responseStatus == 200 ) {
            $this->logger->debug( "Error: The check for Match private key correctness failed: " . $this->result[ 'error' ][ 'message' ] . " ErrNum: " . $this->result[ 'error' ][ 'code' ] );
            throw new Exception( "Error: The private TM key you entered ($apiKey) appears to be invalid. Please check that the key is correct.", -2 );
        }

        $isValidKey = filter_var( $this->result->responseData, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );

        if ( $isValidKey === null ) {
            throw new Exception( "Error: The private TM key you entered seems to be invalid: $apiKey", -3 );
        }

        return $isValidKey;

    }

    /******************************************/
    /**
     * Calls the Match Fast Analysis endpoint to analyze a document
     *
     * @param array $segs_array
     *
     * @return AnalyzeResponse
     * @throws Exception
     */
    public function fastAnalysis( array $segs_array ): AnalyzeResponse {

        $this->_setAdditionalCurlParams( [
                        CURLOPT_TIMEOUT => 300
                ]
        );

        $this->getEngineRecord()[ 'base_url' ] = "https://analyze.mymemory.translated.net/api/v1";

        $this->call( "analyze_url", array_values( $segs_array ), true, true );

        return $this->result;

    }

    /**
     * Match private endpoint
     *
     * @param array $config
     *
     * @return array|TagProjectionResponse
     */
    public function getTagProjection( array $config ) {

        // set dataRefMap needed to instance
        // TagProjectionResponse class
        $this->_config[ 'dataRefMap' ] = $config[ 'dataRefMap' ] ?? [];

        //tag replace
        $source_string = $config[ 'source' ];
        $target_string = $config[ 'target' ];

        //formatting strip
        $re = '(&#09;|\p{Zs}|&#10;|\n|\t|⇥|\xc2\xa0|\xE2|\x81|\xA0)+';
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

        $this->getEngineRecord()->base_url                    = parse_url( $this->getEngineRecord()->base_url, PHP_URL_HOST ) . ":10000";
        $this->getEngineRecord()->others[ 'tags_projection' ] .= '/' . $config[ 'source_lang' ] . "/" . $config[ 'target_lang' ] . "/";
        $this->call( 'tags_projection', $parameters );

        if ( !empty( $this->result->responseData ) ) {
            $this->result->responseData = $l_matches . $this->result->responseData . $r_matches;
        }

        return $this->result;

    }
}
