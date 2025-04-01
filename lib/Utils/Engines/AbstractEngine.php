<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/02/15
 * Time: 11.59
 *
 */
abstract class  Engines_AbstractEngine implements Engines_EngineInterface {

    /**
     * @var EnginesModel_EngineStruct
     */
    protected EnginesModel_EngineStruct $engineRecord;

    protected $className;
    protected $_config = [];
    protected $result  = [];
    protected $error   = [];

    protected $curl_additional_params = [];

    protected $_patterns_found = [];

    protected $_isAnalysis   = false;
    protected $_skipAnalysis = false;

    /**
     * @var bool True if the engine can receive contributions through a `set/update` method.
     */
    protected bool $_isAdaptiveMT = false;

    /**
     * @var bool
     */
    protected $logging      = true;
    protected $content_type = 'xml';

    protected $featureSet;

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
     * @return EnginesModel_EngineStruct
     */
    public function getEngineRecord(): EnginesModel_EngineStruct {
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
    public function _call( $url, array $curl_options = [] ) {

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
            ]; //return negative number
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

    public function call( $function, array $parameters = [], $isPostRequest = false, $isJsonRequest = false ) {

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
            ]; //return negative number

            return;
        }

        if ( $isPostRequest ) {
            $function = strtolower( trim( $function ) );
            $url      = "{$this->engineRecord['base_url']}/" . $this->$function;
            $curl_opt = [
                    CURLOPT_POSTFIELDS  => ( !$isJsonRequest ? $parameters : json_encode( $parameters ) ),
                    CURLINFO_HEADER_OUT => true,
                    CURLOPT_TIMEOUT     => 120
            ];
        } else {
            $function = strtolower( trim( $function ) );
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
         * they does not return original segment, only the translation.
         * Taken when needed as "variadic function parameter" ( func_get_args )
         * 
         * Pass the called $function also
        */
        $this->result = $this->_decode( $rawValue, $parameters, $function );

    }

    public function _setAdditionalCurlParams( array $curlOptParams = [] ) {

        /*
         * Append array elements from the second array
         * to the first array while not overwriting the elements from
         * the first array and not re-indexing
         *
         * In this case we CAN NOT use the + array union operator because if there is a file handler in the $curlOptParams
         * the resource is duplicated and the reference to the first one is lost with + operator, in this way the CURLOPT_FILE does not works
         */
        foreach ( $curlOptParams as $key => $value ) {
            $this->curl_additional_params[ $key ] = $value;
        }

    }

    public function getConfigStruct() {
        return $this->_config;
    }

    public function getPenalty() {
        return $this->engineRecord->penalty;
    }

    public function getName() {
        return $this->engineRecord->name;
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

            /**
             * Added in PHP 5.5.0 with FALSE as the default value.
             * PHP 5.6.0 changes the default value to TRUE.
             */
            if ( version_compare( PHP_VERSION, '7.0.0' ) < 0 ) {
                $options[ CURLOPT_SAFE_UPLOAD ] = true;
                $this->_setAdditionalCurlParams( $options );
            }

            return new CURLFile( realpath( $file ) );
        }

        return "@" . realpath( $file );
    }

    /**
     * @param $_config
     *
     * @return array|Engines_Results_AbstractResponse
     */
    protected function GoogleTranslateFallback( $_config ) {

        /**
         * Create a record of type GoogleTranslate
         */
        $newEngineStruct = EnginesModel_GoogleTranslateStruct::getStruct();

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
     * @param string           $filePath
     * @param string           $memoryKey
     * @param Users_UserStruct $user
     *
     * @return void
     */
    public function importMemory( string $filePath, string $memoryKey, Users_UserStruct $user ) {

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
     * @param TmKeyManagement_MemoryKeyStruct $memoryKey The memory key structure to be checked.
     *
     * @return ?array Returns the memory, otherwise null.
     * @throws Exception
     */
    public function memoryExists( TmKeyManagement_MemoryKeyStruct $memoryKey ): ?array {
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
     * @param TmKeyManagement_MemoryKeyStruct $memoryKey *
     *
     * @return array|null Returns the memory key if the caller owns the memory, false otherwise.
     */
    public function getMemoryIfMine( TmKeyManagement_MemoryKeyStruct $memoryKey): ?array {
        return null;
    }

    /**
     * @param $source
     * @param $target
     * @param $sentence
     * @param $translation
     * @return float|null
     */
    public function getQualityEstimation($source, $target, $sentence, $translation): ?float
    {
        return null;
    }

    /**
     * @param array $all_args
     * @param $decoded
     * @param int $layerNum
     * @return array|Engines_Results_MT
     * @throws Exception
     */
    protected function _composeResponseAsMatch( array $all_args, $decoded, $layerNum = 1 ) {

        $mt_result = new Engines_Results_MT( $decoded );

        if ( $mt_result->error->code < 0 ) {
            $mt_result            = $mt_result->get_as_array();
            $mt_result[ 'error' ] = (array)$mt_result[ 'error' ];

            return $mt_result;
        }

        $mt_match_res = new Engines_Results_MyMemory_Matches([
            'raw_segment' => $all_args[ 1 ][ 'text' ],
            'raw_translation' => $mt_result->translatedText,
            'match' => 100 - $this->getPenalty() . "%",
            'created-by' => "MT-" . $this->getName(),
            'create-date' => date( "Y-m-d" )
        ]);

        $source = null;
        $target = null;

        return $mt_match_res->getMatches($layerNum, [], $source, $target);
    }
}
