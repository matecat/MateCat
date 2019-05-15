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

        $log = $handler->getSingleLog( $resource );
        $log[ 'response' ] = $rawContent;
        Log::doJsonLog( $log );

        return $rawContent;

    }

    public function close_curl( $curl ) {}

    protected function curl_get_error_number( $curlResource ){
        return $this->curlHandler->getError( $this->resourceHash )[ 'errno' ];
    }

}