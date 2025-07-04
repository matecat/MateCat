<?php

use Model\Engines\EngineStruct;
use Model\Engines\GoogleTranslateStruct;
use Model\FeaturesBase\FeatureSet;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/02/15
 * Time: 11.59
 *
 */
abstract class  Engines_AbstractEngine implements Engines_EngineInterface {

    /**
     * @var EngineStruct
     */
    protected EngineStruct $engineRecord;

    protected string $className;
    protected array  $_config = [];
    protected        $result  = []; // this cannot be forced to be an array, engines may use different types
    protected array  $error   = [];

    protected array $curl_additional_params = [];

    protected bool $_isAnalysis   = false;
    protected bool $_skipAnalysis = false;

    /**
     * @var bool True if the engine can receive contributions through a `set/update` method.
     */
    protected bool $_isAdaptiveMT = false;

    /**
     * @var bool
     */
    protected bool   $logging      = true;
    protected string $content_type = 'xml';

    protected ?FeatureSet $featureSet = null;
    protected ?int        $mt_penalty = null;

    const GET_REQUEST_TIMEOUT = 10;

    public function __construct( $engineRecord ) {
        $this->engineRecord = $engineRecord;
        $this->className    = get_class( $this );

        $this->curl_additional_params = [
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 10, // a timeout to call itself should not be too much higher :D
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
        ];

        $this->featureSet = new FeatureSet();
    }

    /**
     * @param int|null $mt_penalty
     *
     * @return $this
     */
    public function setMTPenalty( ?int $mt_penalty = null ): Engines_AbstractEngine {
        $this->mt_penalty = $mt_penalty;

        return $this;
    }

    public function setFeatureSet( FeatureSet $fSet = null ) {
        if ( $fSet != null ) {
            $this->featureSet = $fSet;
        }
    }

    /**
     * @param ?bool $bool
     *
     * @return $this
     */
    public function setAnalysis( ?bool $bool = true ): Engines_AbstractEngine {
        $this->_isAnalysis = filter_var( $bool, FILTER_VALIDATE_BOOLEAN );

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setSkipAnalysis( ?bool $bool = true ): Engines_AbstractEngine {
        $this->_skipAnalysis = $bool;

        return $this;
    }

    /**
     * Override when some string languages are different
     *
     * @param $lang
     *
     * @return mixed
     */
    protected function _fixLangCode( $lang ) {
        $l = explode( "-", strtolower( trim( $lang ) ) );

        return $l[ 0 ];
    }

    /**
     * @return EngineStruct
     */
    public function getEngineRecord(): EngineStruct {
        return $this->engineRecord;
    }

    /**
     * @param $key
     *
     * @return null
     */
    public function __get( $key ) {
        if ( property_exists( $this->engineRecord, $key ) ) {
            return $this->engineRecord->$key;
        } elseif ( array_key_exists( $key, $this->engineRecord->others ) ) {
            return $this->engineRecord->others[ $key ];
        } elseif ( array_key_exists( $key, $this->engineRecord->extra_parameters ) ) {
            return $this->engineRecord->extra_parameters[ $key ];
        } else {
            return null;
        }
    }

    /**
     * @param $key
     * @param $value
     */
    public function __set( $key, $value ) {
        if ( property_exists( $this->engineRecord, $key ) ) {
            $this->engineRecord->$key = $value;
        } elseif ( array_key_exists( $key, $this->engineRecord->others ) ) {
            $this->engineRecord->others[ $key ] = $value;
        } elseif ( array_key_exists( $key, $this->engineRecord->extra_parameters ) ) {
            $this->engineRecord->extra_parameters[ $key ] = $value;
        } else {
            throw new DomainException( "Property $key does not exists in " . get_class( $this ) );
        }
    }

    abstract protected function _decode( $rawValue, array $parameters = [], $function = null );

    /**
     * @param string $url
     * @param array  $curl_options
     *
     * @return array|bool|string|null
     */
    public function _call( string $url, array $curl_options = [] ) {

        $mh       = new MultiCurlHandler();
        $uniq_uid = uniqid( '', true );

        /*
         * Append array elements from the second array
         * to the first array while not overwriting the elements from
         * the first array and not re-indexing
         *
         * Use the + array union operator
         */
        $resourceHash = $mh->createResource( $url,
                $this->curl_additional_params + $curl_options, $uniq_uid
        );

        $mh->multiExec();

        if ( $mh->hasError( $resourceHash ) ) {
            $curl_error       = $mh->getError( $resourceHash );
            $responseRawValue = $mh->getSingleContent( $resourceHash );
            $rawValue         = [
                    'error'          => [
                            'code'     => -$curl_error[ 'errno' ],
                            'message'  => " {$curl_error[ 'error' ]} - Server Error (http status " . $curl_error[ 'http_code' ] . ")",
                            'response' => $responseRawValue // Some useful info might still be contained in the response body
                    ],
                    'responseStatus' => $curl_error[ 'http_code' ]
            ]; //return a negative number
        } else {
            $rawValue = $mh->getSingleContent( $resourceHash );
        }

        $mh->multiCurlCloseAll();

        if ( $this->logging ) {
            $log = $mh->getSingleLog( $resourceHash );
            if ( $this->content_type == 'json' ) {
                $log[ 'response' ] = json_decode( $rawValue, true );
            } else {
                $log[ 'response' ] = $rawValue;
            }
            Log::doJsonLog( $log );
        }

        return $rawValue;

    }

    /**
     * @param string $function
     * @param array  $parameters
     * @param bool   $isPostRequest
     * @param bool   $isJsonRequest
     *
     * @return void
     */
    public function call( string $function, array $parameters = [], bool $isPostRequest = false, bool $isJsonRequest = false ) {

        if ( $this->_isAnalysis && $this->_skipAnalysis ) {
            $this->result = [];

            return;
        }

        $this->error = []; // reset last error
        if ( !$this->$function ) {
            $this->result = [
                    'error' => [
                            'code'    => -43,
                            'message' => " Bad Method Call. Requested method '$function' not Found."
                    ]
            ]; //return a negative number

            return;
        }

        $function = strtolower( trim( $function ) );

        if ( $isPostRequest ) {
            $url      = "{$this->engineRecord['base_url']}/" . $this->$function;
            $curl_opt = [
                    CURLOPT_POSTFIELDS  => ( !$isJsonRequest ? $parameters : json_encode( $parameters ) ),
                    CURLINFO_HEADER_OUT => true,
                    CURLOPT_TIMEOUT     => 120
            ];
        } else {
            $url      = "{$this->engineRecord['base_url']}/" . $this->$function . "?";
            $url      .= http_build_query( $parameters );
            $curl_opt = [
                    CURLOPT_HTTPGET => true,
                    CURLOPT_TIMEOUT => static::GET_REQUEST_TIMEOUT
            ];
        }

        $rawValue = $this->_call( $url, $curl_opt );

        /*
         * $parameters['segment'] is used in MT engines,
         * they do not return the original segment, only the translation.
         * Taken when needed as "variadic function parameter" (func_get_args)
         * 
         * Pass the called $function also
        */
        $this->result = $this->_decode( $rawValue, $parameters, $function );

    }

    public function _setAdditionalCurlParams( array $curlOptParams = [] ) {

        /*
         * Append array elements from the second array to the first array while not
         * overwriting the elements from the first array and not re-indexing.
         *
         * In this case, we cannot use the + array union operator because if there is
         * a file handler in the $curlOptParams, the resource is duplicated, and the
         * reference to the first one is lost.
         * In this way, the CURLOPT_FILE does not work.
         */
        foreach ( $curlOptParams as $key => $value ) {
            $this->curl_additional_params[ $key ] = $value;
        }

    }

    public function getConfigStruct(): array {
        return $this->_config;
    }

    public function getMtPenalty(): int {
        return $this->mt_penalty ?? ( $this->engineRecord->penalty ?: 14 );
    }

    /**
     * @return string
     */
    public function getStandardPenaltyString(): string { //YYY check all engines to honor this new feature (variable penalty)
        return 100 - $this->getMtPenalty() . "%";
    }

    public function getName(): string {
        return $this->engineRecord->name;
    }

    public function getMTName(): string {
        return "MT-" . $this->getName();
    }

    public function isTMS(): bool {
        return false;
    }

    public function isAdaptiveMT(): bool {
        return $this->_isAdaptiveMT && !$this->isTMS();
    }

    /**
     * This function is PHP7 compatible
     *
     * @param $file
     *
     * @return CURLFile|string
     */
    protected function getCurlFile( $file ) {
        if ( version_compare( PHP_VERSION, '5.5.0' ) >= 0 and class_exists( '\\CURLFile' ) ) {
            return new CURLFile( realpath( $file ) );
        }

        return "@" . realpath( $file );
    }

    /**
     * @param $_config
     *
     * @return array|Engines_Results_AbstractResponse
     * @throws Exception
     */
    protected function GoogleTranslateFallback( $_config ) {

        /**
         * Create a record of type GoogleTranslate
         */
        $newEngineStruct = GoogleTranslateStruct::getStruct();

        $newEngineStruct->name                                = "Generic";
        $newEngineStruct->uid                                 = 0;
        $newEngineStruct->type                                = Constants_Engines::MT;
        $newEngineStruct->extra_parameters[ 'client_secret' ] = $_config[ 'secret_key' ];
        $newEngineStruct->others                              = [];

        $gtEngine = Engine::createTempInstance( $newEngineStruct );

        /**
         * @var $gtEngine Engines_GoogleTranslate
         */
        return $gtEngine->get( $_config );

    }

    /**
     * @param string     $filePath
     * @param string     $memoryKey
     * @param UserStruct $user
     *
     * @return void
     */
    public function importMemory( string $filePath, string $memoryKey, UserStruct $user ) {

    }

    /**
     * @param array      $projectRow
     * @param array|null $segments
     *
     * @return void
     */
    public function syncMemories( array $projectRow, ?array $segments = [] ) {

    }

    /**
     * @param MemoryKeyStruct $memoryKey The memory key structure to be checked.
     *
     * @return ?array Returns the memory, otherwise null.
     * @throws Exception
     */
    public function memoryExists( MemoryKeyStruct $memoryKey ): ?array {
        return null;
    }

    /**
     * @param array $memoryKey
     *
     * @return array
     * @throws Exception
     */
    public function deleteMemory( array $memoryKey ): array {
        return [];
    }

    /**
     * Determines if the provided memory belongs to the caller.
     *
     *
     * @param MemoryKeyStruct $memoryKey *
     *
     * @return array|null Returns the memory key if the caller owns the memory, false otherwise.
     */
    public function getMemoryIfMine( MemoryKeyStruct $memoryKey ): ?array {
        return null;
    }

    /**
     * @param string $source
     * @param string $target
     * @param string $sentence
     * @param string $translation
     * @param string $mt_qe_engine_id
     *
     * @return float|null
     */
    public function getQualityEstimation( string $source, string $target, string $sentence, string $translation, string $mt_qe_engine_id = 'default' ): ?float {
        return null;
    }

    /**
     * @param string $raw_segment
     * @param        $decoded
     * @param int    $layerNum
     *
     * @return array
     * @throws Exception
     */
    protected function _composeMTResponseAsMatch( string $raw_segment, $decoded, int $layerNum = 1 ): array {

        $mt_result = new Engines_Results_MT( $decoded );

        if ( $mt_result->error->code < 0 ) {
            $mt_result            = $mt_result->get_as_array();
            $mt_result[ 'error' ] = (array)$mt_result[ 'error' ];

            return $mt_result;
        }

        $mt_match_res = new Engines_Results_MyMemory_Matches( [
                'raw_segment'     => $raw_segment,
                'raw_translation' => $mt_result->translatedText,
                'match'           => $this->getStandardPenaltyString(),
                'created-by'      => $this->getMTName(),
                'create-date'     => date( "Y-m-d" )
        ] );

        return $mt_match_res->getMatches( $layerNum );
    }
}
