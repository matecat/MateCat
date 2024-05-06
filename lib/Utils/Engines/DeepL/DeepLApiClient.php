<?php

namespace Engines\DeepL;

use InvalidArgumentException;
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
    public static function newInstance($apiKey)
    {
        return new static($apiKey );
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
     * @param $text
     * @param $sourceLang
     * @param $targetLang
     * @param null $formality
     * @param null $idGlossary
     *
     * @return mixed
     * @throws DeepLApiException
     */
    public function translate($text, $sourceLang, $targetLang, $formality = null, $idGlossary = null)
    {
        $args = [
            'text' => [
                $text
            ],
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
        ];

        if($formality){
            $args['formality'] = $formality;
        }

        if($idGlossary){
            $args['glossary_id'] = $idGlossary;
        }

        return $this->send('POST', '/translate', $args);
    }

    /**
     * @return mixed
     * @throws DeepLApiException
     */
    public function allGlossaries()
    {
        return $this->send("GET", "/glossaries");
    }

    /**
     * @param $data
     * @return mixed
     * @throws DeepLApiException
     */
    public function createGlossary($data)
    {
        return $this->send("POST", "/glossaries", $data);
    }

    /**
     * @param $id
     * @return mixed
     * @throws DeepLApiException
     */
    public function deleteGlossary($id)
    {
        return $this->send("DELETE", "/glossaries/$id");
    }

    /**
     * @param $id
     * @return mixed
     * @throws DeepLApiException
     */
    public function getGlossary($id)
    {
        return $this->send("GET", "/glossaries/$id");
    }

    /**
     * @param $id
     * @return mixed
     * @throws DeepLApiException
     */
    public function getGlossaryEntries($id)
    {
        return $this->send("GET", "/glossaries/$id/entries");
    }

    /**
     * @param $method
     * @param $url
     * @param null $params
     * @param null $timeout
     * @return mixed
     * @throws DeepLApiException
     */
    private function send( $method, $url, $params = null, $timeout = null )
    {
        $allowedHttpVerbs = [
            'GET',
            'POST',
            'DELETE',
        ];

        if(!in_array($method, $allowedHttpVerbs)){
            throw new InvalidArgumentException("Invalid method. Supported: [GET, POST, DELETE]");
        }

        $headers = [
            'Authorization: DeepL-Auth-Key ' . $this->apiKey,
            'Content-Type: application/json'
        ];

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

        // Set up POST request
        if($method === 'POST'){
            $options[ CURLOPT_POST ] = 1;
        }

        // Set up DELETE request
        if($method === 'DELETE'){
            $options[ CURLOPT_CUSTOMREQUEST ] = 'DELETE';
        }

        if ( $params ) {
            $options[ CURLOPT_POSTFIELDS ] = json_encode($params);
        }

        if ( $timeout !== null ) {
            $options[ CURLOPT_TIMEOUT ] = $timeout;
        }

        $resourceHashId = $handler->createResource( self::DEFAULT_BASE_URL . $url, $options );
        $handler->multiExec();
        $handler->multiCurlCloseAll();

        if ( $handler->hasError( $resourceHashId ) ) {
            if ( $handler->getError( $resourceHashId )[ 'errno' ] == 28 ) {
                throw new DeepLApiException( "Unable to contact upstream server ({$handler->getError( $resourceHashId )[ 'errno' ]})", 500  );
            } elseif ( $handler->getError( $resourceHashId )[ 'http_code' ] ) {
                throw new DeepLApiException( "Request denied ({$handler->getError( $resourceHashId )[ 'http_code' ]})", $handler->getError( $resourceHashId )[ 'http_code' ] );
            } else {
                throw new DeepLApiException( "Unable to contact upstream server ({$handler->getError( $resourceHashId )[ 'errno' ]})", 500 );
            }
        }

        $result            = $handler->getSingleContent( $resourceHashId );
        $log               = $handler->getSingleLog( $resourceHashId );
        $log[ 'response' ] = $result;

        Log::doJsonLog( $log, "deepl.log" );

        return $this->parse( $result );
    }

    /**
     * @param string $body
     *
     * @return mixed|null
     * @throws DeepLApiException
     */
    private function parse( $body )
    {
        $json = json_decode( $body, true );

        if ( json_last_error() != JSON_ERROR_NONE ) {

            // is a TSV?
            $tsvAsArray = preg_split("/\t+/", $body);

            if(is_array($tsvAsArray)){
                return $tsvAsArray;
            }

            throw new DeepLApiException( "ConnectionException", 500, "Unable to decode server response: '$body'" );
        }

        return $json;
    }
}