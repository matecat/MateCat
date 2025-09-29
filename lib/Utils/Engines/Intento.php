<?php

namespace Utils\Engines;

use Exception;
use Model\Projects\MetadataDao;
use ReflectionException;
use Utils\Constants\EngineConstants;
use Utils\Engines\Results\MTResponse;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

class Intento extends AbstractEngine {

    const INTENTO_USER_AGENT   = 'Intento.MatecatPlugin/1.0.0';
    const INTENTO_PROVIDER_KEY = 'd3ic8QPYVwRhy6IIEHi6yiytaORI2kQk';
    const INTENTO_API_URL      = 'https://api.inten.to';

    protected array $_config = [
            'segment' => null,
            'source'  => null,
            'target'  => null
    ];

    private $apiKey;
    private $provider = [];
    private $providerKey;
    private $providerCategory;

    /**
     * @throws Exception
     */
    public function __construct( $engineRecord ) {
        parent::__construct( $engineRecord );

        if ( $this->getEngineRecord()->type != EngineConstants::MT ) {
            throw new Exception( "Engine {$this->getEngineRecord()->id} is not a MT engine, found {$this->getEngineRecord()->type} -> {$this->getEngineRecord()->class_load}" );
        }

        $extra = $engineRecord->getExtraParamsAsArray();

        $this->apiKey           = $extra[ 'apikey' ] ?? null;
        $this->provider         = $extra[ 'provider' ] ?? [];
        $this->providerKey      = $extra[ 'providerkey' ] ?? null;
        $this->providerCategory = $extra[ 'providercategory' ] ?? null;
    }

    /**
     * @param $lang
     *
     * @return mixed
     * @throws Exception
     */
    protected function _fixLangCode( $lang ) {
        $r = explode( "-", strtolower( trim( $lang ) ) );

        return $r[ 0 ];
    }

    /**
     * @param      $rawValue
     * @param null $parameters
     * @param null $function
     *
     * @return array|MTResponse
     * @throws Exception
     */
    protected function _decode( $rawValue, $parameters = null, $function = null ) {

        if ( is_string( $rawValue ) ) {
            $result = json_decode( $rawValue, false );

            // sync calls
            if ( isset( $result->results ) and !empty( $result->results[ 0 ] ) ) {
                $decoded = [
                        'data' => [
                                'translations' => [
                                        [ 'translatedText' => $result->results[ 0 ] ]
                                ]
                        ]
                ];
            } // async calls
            elseif ( $result and isset( $result->id ) ) {
                $id = $result->id;

                if ( isset( $result->response ) and !empty( $result->response ) and isset( $result->done ) and $result->done ) {
                    $text    = $result->response[ 0 ]->results[ 0 ];
                    $decoded = [
                            'data' => [
                                    'translations' => [
                                            [ 'translatedText' => $text ]
                                    ]
                            ]
                    ];

                } elseif ( isset( $result->done ) and !$result->done ) {
                    sleep( 2 );
                    $cnf = [ 'async' => true, 'id' => $id ];

                    return $this->_curl_async( $cnf, $parameters, $function );
                } elseif ( isset( $result->error ) and !empty( $result->error ) ) {

                    $httpCode = $result->error->data[ 0 ]->response->body->error->code ?? 500;
                    $message  = $result->error->data[ 0 ]->response->body->error->message ?? $result->error->reason ?? "Unknown error";

                    $decoded = [
                            'error' => [
                                    'code'      => -2,
                                    'message'   => $message,
                                    'http_code' => $httpCode
                            ]
                    ];
                } else {
                    $cnf = [ 'async' => true, 'id' => $id ];

                    return $this->_curl_async( $cnf, $parameters, $function );
                }
            } else {
                $decoded = [
                        'error' => [
                                'code'    => '-1',
                                'message' => ''
                        ]
                ];
            }

        } else {
            if ( $rawValue and array_key_exists( 'responseStatus', $rawValue ) and array_key_exists( 'error', $rawValue ) ) {
                $_response_error = json_decode( $rawValue[ 'error' ][ "response" ], true );
                $decoded         = [
                        'error' => [
                                'code'    => array_key_exists( 'error', $_response_error ) ? array_key_exists( 'code', $_response_error[ 'error' ] ) ? -$_response_error[ 'error' ][ 'code' ] : '-1' : '-1',
                                'message' => array_key_exists( 'error', $_response_error ) ? array_key_exists( 'message', $_response_error[ 'error' ] ) ? $_response_error[ 'error' ][ 'message' ] : '' : ''
                        ]
                ];
            } else {
                $decoded = [
                        'error' => [
                                'code'    => '-1',
                                'message' => ''
                        ]
                ];
            }

        }

        return $this->_composeMTResponseAsMatch( $parameters[ 'context' ][ 'text' ], $decoded );
    }

    /**
     * @throws Exception
     */
    public function get( array $_config ) {

        if ( $this->_isAnalysis && $this->_skipAnalysis ) {
            return [];
        }

        $_config[ 'source' ] = $this->_fixLangCode( $_config[ 'source' ] );
        $_config[ 'target' ] = $this->_fixLangCode( $_config[ 'target' ] );

        $parameters = [];
        if ( !empty( $this->apiKey ) ) {
            $_headers = [ 'apikey: ' . $this->apiKey, 'Content-Type: application/json' ];
        }

        $parameters[ 'context' ][ 'from' ] = $_config[ 'source' ];
        $parameters[ 'context' ][ 'to' ]   = $_config[ 'target' ];
        $parameters[ 'context' ][ 'text' ] = $_config[ 'segment' ];
        $provider                          = $this->provider;
        $providerKey                       = $this->providerKey;
        $providerCategory                  = $this->providerCategory;

        if ( !empty( $provider ) ) {
            $parameters[ 'service' ][ 'async' ]    = true;
            $parameters[ 'service' ][ 'provider' ] = $provider[ 'id' ];

            if ( !empty( $providerKey ) ) {
                $parameters[ 'service' ][ 'auth' ][ $provider[ 'id' ] ] = [ json_decode( $providerKey, true ) ];
            }

            if ( !empty( $providerCategory ) ) {
                $parameters[ 'context' ][ 'category' ] = $providerCategory;
            }
        }

        // custom routing
        if ( isset( $_config[ 'pid' ] ) ) {
            $metadataDao   = new MetadataDao();
            $customRouting = $metadataDao->get( $_config[ 'pid' ], 'intento_routing', 86400 );

            if ( $customRouting !== null ) {
                $parameters[ 'service' ][ 'routing' ] = $customRouting->value;
            }
        }

        $this->_setIntentoUserAgent(); //Set Intento User Agent

        $this->_setAdditionalCurlParams(
                [
                        CURLOPT_POST       => true,
                        CURLOPT_POSTFIELDS => json_encode( $parameters ),
                        CURLOPT_HTTPHEADER => $_headers ?? []
                ]
        );

        $this->call( "translate_relative_url", $parameters, true );

        return $this->result;

    }

    /**
     * @throws Exception
     */
    protected function _curl_async( $config, $parameters = null, $function = null ) {
        $id = $config[ 'id' ];

        if ( !empty( $this->apiKey ) ) {
            $_headers = [ 'apikey: ' . $this->apiKey, 'Content-Type: application/json' ];
        }

        $this->_setIntentoUserAgent(); //Set Intento User Agent

        $this->_setAdditionalCurlParams(
                [
                        CURLOPT_HTTPHEADER => $_headers ?? []
                ]
        );

        $url      = self::INTENTO_API_URL . '/operations/' . $id;
        $curl_opt = [
                CURLOPT_HTTPGET => true,
                CURLOPT_TIMEOUT => static::GET_REQUEST_TIMEOUT
        ];
        $rawValue = $this->_call( $url, $curl_opt );

        return $this->_decode( $rawValue, $parameters, $function );

    }

    public function set( $_config ): bool {

        //if engine does not implement SET method, exit
        return true;
    }

    public function update( $_config ): bool {

        //if engine does not implement UPDATE method, exit
        return true;
    }

    public function delete( $_config ): bool {

        //if engine does not implement DELETE method, exit
        return true;

    }

    /**
     *  Set Matecat + Intento user agent
     */
    private function _setIntentoUserAgent() {
        $this->curl_additional_params[ CURLOPT_USERAGENT ] = self::INTENTO_USER_AGENT . ' ' . AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER;
    }

    /**
     * Get user's routing list
     *
     * @return array
     */
    public function getRoutingList() {

        if ( empty( $this->apiKey ) ) {
            return [];
        }

        try {
            $redisHandler = new RedisHandler();
            $conn         = $redisHandler->getConnection();
            $cacheKey     = 'IntentoRoutings-' . $this->apiKey;
            $result       = $conn->get( $cacheKey );

            if ( $result ) {
                return json_decode( $result, true );
            }

            $_api_url = self::INTENTO_API_URL . '/routing-designer';
            $curl     = curl_init( $_api_url );
            $_params  = [
                    CURLOPT_HTTPHEADER     => [ 'apikey: ' . $this->apiKey, 'Content-Type: application/json' ],
                    CURLOPT_HEADER         => false,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_USERAGENT      => AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER . ' ' . self::INTENTO_USER_AGENT,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2
            ];

            curl_setopt_array( $curl, $_params );
            $response = curl_exec( $curl );
            $result   = json_decode( $response );
            curl_close( $curl );
            $_routings = [];

            if ( $result and $result->data ) {
                foreach ( $result->data as $item ) {
                    $_routings[ $item->name ] = [
                            'id'          => $item->rt_id,
                            'name'        => $item->name,
                            'description' => $item->description,
                    ];
                }
            }

            ksort( $_routings, SORT_STRING | SORT_FLAG_CASE );

            $conn->set( $cacheKey, json_encode( $_routings ) );
            $conn->expire( $cacheKey, 60 * 60 ); // 1 hour

            return $_routings;
        } catch ( Exception $exception ) {
            return [];
        }
    }

    /**
     * Get provider list
     * @throws ReflectionException
     */
    public static function getProviderList() {
        $redisHandler = new RedisHandler();
        $conn         = $redisHandler->getConnection();
        $result       = $conn->get( 'IntentoProviders' );
        if ( $result ) {
            return json_decode( $result, true );
        }

        $_api_url = self::INTENTO_API_URL . '/ai/text/translate?fields=auth&integrated=true&published=true';
        $curl     = curl_init( $_api_url );
        $_params  = [
                CURLOPT_HTTPHEADER     => [ 'apikey: ' . self::INTENTO_PROVIDER_KEY, 'Content-Type: application/json' ],
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER . ' ' . self::INTENTO_USER_AGENT,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
        ];
        curl_setopt_array( $curl, $_params );
        $response = curl_exec( $curl );
        $result   = json_decode( $response );
        curl_close( $curl );
        $_providers = [];
        if ( $result ) {
            foreach ( $result as $value ) {
                $example                  = (array)$value->auth;
                $example                  = json_encode( $example );
                $_providers[ $value->id ] = [ 'id' => $value->id, 'name' => $value->name, 'vendor' => $value->vendor, 'auth_example' => $example ];
            }
            ksort( $_providers );
        }
        $conn->set( 'IntentoProviders', json_encode( $_providers ) );
        $conn->expire( 'IntentoProviders', 60 * 60 * 24 );

        return $_providers;
    }

    /**
     * @inheritDoc
     */
    public function getExtraParams(): array {
        return [
                'pre_translate_files',
                'intento_routing',
        ];
    }
}
