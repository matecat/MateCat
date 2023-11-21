<?php

namespace Engines\DeepL;

use Log;
use MultiCurlHandler;

class DeepLApiClient
{
    const DEFAULT_BASE_URL = 'https://api.deepl.com/v1';

    private $apiKey;

    /**
     * @param $apiKey
     * @return static
     */
    public static function newInstance( $apiKey )
    {
        return new static( $apiKey );
    }

    /**
     * DeepLApiClient constructor.
     * @param $apiKey
     */
    private function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
    }


    /**
     * @param $method
     * @param $url
     * @param null $params
     * @param null $timeout
     * @return mixed
     * @throws DeepLApiException
     */
    protected function send( $method, $url, $params = null, $timeout = null )
    {
        $headers[] = 'Authorization: DeepL-Auth-Key ' . $this->apiKey;
        $headers[] = 'Content-Type: application/json';

        $handler          = new MultiCurlHandler();
        $handler->verbose = true;

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ];

        if ( count( $headers ) > 0 ) {
            $options[ CURLOPT_HTTPHEADER ] = $headers;
        }

        // Every API call MUST be a POST
        // (X-HTTP-Method-Override will override the method)
        $options[ CURLOPT_POST ] = 1;

        if ( $params ) {
            $options[ CURLOPT_POSTFIELDS ] = $params;
        }

        if ( $timeout !== null ) {
            $options[ CURLOPT_TIMEOUT ] = $timeout;
        }

        $resourceHashId = $handler->createResource( $url, $options );
        $handler->multiExec();
        $handler->multiCurlCloseAll();

        if ( $handler->hasError( $resourceHashId ) ) {
            if ( $handler->getError( $resourceHashId )[ 'errno' ] == 28 ) {
                throw new DeepLApiException( "TimeoutException", 500, "Unable to contact upstream server ({$handler->getError( $resourceHashId )[ 'errno' ]})" );
            } elseif ( $handler->getError( $resourceHashId )[ 'http_code' ] ) {
                throw new DeepLApiException( "ServiceException", $handler->getError( $resourceHashId )[ 'http_code' ], "Request denied ({$handler->getError( $resourceHashId )[ 'http_code' ]})" );
            } else {
                throw new DeepLApiException( "ConnectionException", 500, "Unable to contact upstream server ({$handler->getError( $resourceHashId )[ 'errno' ]})" );
            }
        }

        $result            = $handler->getSingleContent( $resourceHashId );
        $log               = $handler->getSingleLog( $resourceHashId );
        $log[ 'response' ] = $result;

        Log::doJsonLog( $log, "deepl.log" );

        return $this->parse( $result );
    }
}