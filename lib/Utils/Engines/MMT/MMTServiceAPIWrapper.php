<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 10/10/17
 * Time: 13.54
 *
 */

namespace Engines\MMT;

use Log;

class MMTServiceAPIWrapper extends MMTServiceApi {

    /**
     * @var \MultiCurlHandler
     */
    protected  $curlHandler;

    /**
     * @var string
     */
    private  $resourceHash;


    protected function exec_curl( $curl ) {

        $handler = $this->curlHandler = new \MultiCurlHandler();
        $handler->verbose = true;
        $resource = $this->resourceHash = $handler->addResource( $curl );
        $handler->multiExec();
        $handler->multiCurlCloseAll();
        $rawContent = $handler->getSingleContent( $resource );
        Log::doLog( "$resource ... Received... " . var_export( $rawContent, true ) );
        return $rawContent;

    }

    public function close_curl( $curl ) {}

    protected function send( $method, $url, $params = null, $multipart = FALSE, $timeout = null) {
        if ( !$multipart && $params ) {
            Log::doLog( "... Request Parameters ... " . var_export( http_build_query( $params ), true ) );
        }
        return parent::send( $method, $url, $params, $multipart, $timeout );
    }

    protected function curl_get_error_number( $curlResource ){
        return $this->curlHandler->getError( $this->resourceHash )[ 'errno' ];
    }

}