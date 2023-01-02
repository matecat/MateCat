<?php
/**
 * Created by PhpStorm.
 * User: davide
 * Date: 03/10/17
 * Time: 14:14
 */

namespace Engines\MMT;

use CURLFile;
use Log;
use MultiCurlHandler;

class MMTServiceApi {

    const DEFAULT_BASE_URL = 'https://api.modernmt.com';

    private $baseUrl;
    private $license;
    private $client = 0;
    private $pluginVersion;
    private $platform;
    private $platformVersion;

    /**
     * @param string|null $baseUrl
     *
     * @return MMTServiceApi
     */
    public static function newInstance( $baseUrl = null ) {
        $baseUrl = $baseUrl == null ? self::DEFAULT_BASE_URL : rtrim( $baseUrl, "/" );

        return new static( $baseUrl );
    }

    /**
     * MMTServiceApi constructor.
     *
     * @param string $baseUrl
     */
    private function __construct( $baseUrl ) {
        $this->baseUrl = $baseUrl;
    }

    /**
     * @param string $pluginVersion   the plugin version (i.e. "2.4")
     * @param string $platform        the platform name (i.e. "Matecat")
     * @param string $platformVersion the platform version (i.e. "1.10.7")
     *
     * @return MMTServiceApi
     */
    public function setIdentity( $pluginVersion = null, $platform = null, $platformVersion = null ) {
        $this->pluginVersion   = $pluginVersion;
        $this->platform        = $platform;
        $this->platformVersion = $platformVersion;

        return $this;
    }

    /**
     * @param string $license
     *
     * @return MMTServiceApi
     */
    public function setLicense( $license ) {
        $this->license = $license;

        return $this;
    }

    /**
     * @param int $client
     *
     * @return MMTServiceApi
     */
    public function setClient( $client ) {
        $this->client = $client;

        return $this;
    }

    /* - Instance --------------------------------------------------------------------------------------------------- */

    /**
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getAvailableLanguages() {
        return $this->send( 'GET', "$this->baseUrl/languages" );
    }

    /* - User ------------------------------------------------------------------------------------------------------- */

    /**
     * @param $name
     * @param $email
     * @param $stripeToken
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function signup( $name, $email, $stripeToken ) {
        return $this->send( 'POST', "$this->baseUrl/users", [
                'name' => $name, 'email' => $email, 'stripe_token' => $stripeToken
        ] );
    }

    /**
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function me() {
        return $this->send( 'GET', "$this->baseUrl/users/me" );
    }

    /* Memory ------------------------------------------------------------------------------------------------------- */

    /**
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getAllMemories() {
        return $this->send( 'GET', "$this->baseUrl/memories" );
    }

    /**
     * @param $id
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getMemoryById( $id ) {
        return $this->send( 'GET', "$this->baseUrl/memories/$id" );
    }

    /**
     * @param             $name
     * @param string|null $description
     * @param null        $externalId
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function createMemory( $name, $description = null, $externalId = null ) {
        return $this->send( 'POST', "$this->baseUrl/memories", [
                'name' => $name, 'description' => $description, 'external_id' => $externalId
        ] );
    }

    /**
     * @param $id
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function deleteMemory( $id ) {
        return $this->send( 'DELETE', "$this->baseUrl/memories/$id" );
    }

    /**
     * @param $id
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getMemory( $id ) {
        return $this->send( 'GET', "$this->baseUrl/memories/$id" );
    }

    /**
     * @param             $id
     * @param             $name
     * @param string|null $description
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function updateMemory( $id, $name, $description = null ) {
        return $this->send( 'PUT', "$this->baseUrl/memories/$id", [
                'name' => $name, 'description' => $description
        ] );
    }

    /**
     * @param $externalIds
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function connectMemories( $externalIds ) {
        return $this->send( 'POST', "$this->baseUrl/memories/connect", [
                'external_ids' => implode( ',', $externalIds )
        ] );
    }

    /**
     * @param $id
     * @param $source
     * @param $target
     * @param $sentence
     * @param $translation
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function addToMemoryContent( $id, $source, $target, $sentence, $translation ) {
        if ( is_array( $id ) ) {
            return $this->send( 'POST', "$this->baseUrl/memories/content", [
                    'memories' => empty( $id ) ? null : implode( ',', $id ),
                    'source'   => $source, 'target' => $target, 'sentence' => $sentence, 'translation' => $translation
            ] );
        } else {
            return $this->send( 'POST', "$this->baseUrl/memories/$id/content", [
                    'source' => $source, 'target' => $target, 'sentence' => $sentence, 'translation' => $translation
            ] );
        }
    }

    /**
     * @param $tuid
     * @param string[] $memory_keys
     * @param $source
     * @param $target
     * @param $sentence
     * @param $translation
     *
     * @return void
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function updateMemoryContent( $tuid, $memory_keys, $source, $target, $sentence, $translation ) {
        foreach ( $memory_keys as $memory ) {
            $debug = $this->send( 'PUT', "$this->baseUrl/memories/$memory/content", [
                    'tuid'        => $tuid,
                    'source'      => $source,
                    'target'      => $target,
                    'sentence'    => $sentence,
                    'translation' => $translation
            ] );
        }
    }

    /**
     * @param             $id
     * @param             $tmx
     * @param string|null $compression
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function importIntoMemoryContent( $id, $tmx, $compression = null ) {
        return $this->send( 'POST', "$this->baseUrl/memories/$id/content", [
                'tmx' => $this->_setCulFileUpload( $tmx ), 'compression' => $compression
        ], true );
    }

    /**
     * @param $uuid
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getImportJob( $uuid ) {
        return $this->send( 'GET', "$this->baseUrl/import-jobs/$uuid" );
    }

    /* Translation -------------------------------------------------------------------------------------------------- */

    /**
     * @param            $source
     * @param            $targets
     * @param            $text
     * @param array|null $hints
     * @param mixed      $limit
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getContextVectorFromText( $source, $targets, $text, $hints = null, $limit = null ) {
        return $this->send( 'GET', "$this->baseUrl/context-vector", [
                'source' => $source, 'targets' => implode( ',', $targets ), 'text' => $text,
                'hints'  => ( $hints ? implode( ',', $hints ) : null ), 'limit' => $limit
        ] );
    }

    /**
     * @param             $source
     * @param             $targets
     * @param             $file
     * @param string|null $compression
     * @param array|null  $hints
     * @param mixed       $limit
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function getContextVectorFromFile( $source, $targets, $file, $compression = null, $hints = null, $limit = null ) {
        return $this->send( 'GET', "$this->baseUrl/context-vector", [
                'source'      => $source, 'targets' => implode( ',', $targets ), 'content' => $this->_setCulFileUpload( $file ),
                'compression' => $compression, 'hints' => ( $hints ? implode( ',', $hints ) : null ), 'limit' => $limit
        ], true );
    }

    /**
     * @param             $source
     * @param             $target
     * @param             $text
     * @param string|null $contextVector
     * @param array|null  $hints
     * @param int|null    $projectId
     * @param int         $timeout
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    public function translate( $source, $target, $text, $contextVector = null, $hints = null, $projectId = null, $timeout = null, $priority = null ) {
        return $this->send( 'GET', "$this->baseUrl/translate", [
            'source'  => $source,
            'target' => $target,
            'q' => $text,
            'context_vector' => $contextVector,
            'hints'   => ( $hints ? implode( ',', $hints ) : null ),
            'project_id' => $projectId,
            'timeout' => ( $timeout ? ( $timeout * 1000 ) : null ),
            'priority' => ( $priority ?: 'normal' )
        ], false, $timeout );
    }

    /* - Low level utils -------------------------------------------------------------------------------------------- */

    /**
     * @param $file
     *
     * @return CURLFile|string
     */
    protected function _setCulFileUpload( $file ) {
        if ( version_compare( PHP_VERSION, '5.5.0' ) >= 0 ) {
            return new CURLFile( realpath( $file ) );
        } else {
            return '@' . realpath( $file );
        }
    }

    /**
     * @param string $method
     * @param string $url
     * @param array  $params
     * @param bool   $multipart
     * @param int    $timeout
     *
     * @return mixed
     * @throws MMTServiceApiException
     */
    protected function send( $method, $url, $params = null, $multipart = false, $timeout = null ) {
        if ( $params ) {
            $params = array_filter( $params, function ( $value ) {
                return $value !== null;
            } );
        }

        if ( empty( $params ) ) {
            $params = null;
        }

        $headers = [ "X-HTTP-Method-Override: $method" ];

        if ( $multipart ) {
            $headers[] = 'Content-Type: multipart/form-data';
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=utf-8';
            if ( $params ) {
                $params = http_build_query( $params );
            }
        }

        if ( $this->license ) {
            $headers[] = "MMT-ApiKey: $this->license";
        }
        if ( $this->client > 0 ) {
            $headers[] = "MMT-ApiClient: $this->client";
        }
        if ( $this->pluginVersion ) {
            $headers[] = "MMT-PluginVersion: $this->pluginVersion";
        }
        if ( $this->platform ) {
            $headers[] = "MMT-Platform: $this->platform";
        }
        if ( $this->platformVersion ) {
            $headers[] = "MMT-PlatformVersion: $this->platformVersion";
        }

        $handler          = new MultiCurlHandler();
        $handler->verbose = true;

        $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
        ];

        if ( version_compare( PHP_VERSION, '5.5.0' ) >= 0 ) {
            /**
             * Added in PHP 5.5.0 with FALSE as the default value.
             * PHP 5.6.0 changes the default value to TRUE.
             * For php >= 5.5.0 we use \CURLFile , so we can force and set safe upload to true
             *
             * @see MMTServiceApi::_setCulFileUpload()
             */
            $options[ CURLOPT_SAFE_UPLOAD ] = true;
        }

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
                throw new MMTServiceApiException( "TimeoutException", 500, "Unable to contact upstream server ({$handler->getError( $resourceHashId )[ 'errno' ]})" );
            } elseif ( $handler->getError( $resourceHashId )[ 'http_code' ] ) {
                throw new MMTServiceApiRequestException( "ServiceException", $handler->getError( $resourceHashId )[ 'http_code' ], "Request denied ({$handler->getError( $resourceHashId )[ 'http_code' ]})" );
            } else {
                throw new MMTServiceApiException( "ConnectionException", 500, "Unable to contact upstream server ({$handler->getError( $resourceHashId )[ 'errno' ]})" );
            }

        }

        $result            = $handler->getSingleContent( $resourceHashId );
        $log               = $handler->getSingleLog( $resourceHashId );
        $log[ 'response' ] = $result;

        Log::doJsonLog( $log, "mmt.log" );

        return $this->parse( $result );

    }

    /**
     * @param string $body
     *
     * @return mixed|null
     * @throws MMTServiceApiException
     */
    private function parse( $body ) {
        $json = json_decode( $body, true );

        if ( json_last_error() != JSON_ERROR_NONE ) {
            throw new MMTServiceApiException( "ConnectionException", 500, "Unable to decode server response: '$body'" );
        }

        $status = $json[ "status" ];
        if ( !( 200 <= $status and $status < 300 ) ) {
            throw MMTServiceApiException::fromJSONResponse( $json );
        }

        return isset( $json[ 'data' ] ) ? $json[ 'data' ] : null;
    }

}
