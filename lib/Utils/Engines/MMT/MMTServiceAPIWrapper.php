<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 10/10/17
 * Time: 13.54
 *
 */

namespace Engines\MMT;

class MMTServiceAPIWrapper extends MMTServiceApi {

    protected function exec_curl( $curl ) {

        $handler = new \MultiCurlHandler();
        $handler->verbose = true;
        $resource = $handler->addResource( $curl );
        $handler->multiExec();
        $handler->multiCurlCloseAll();
        return $handler->getSingleContent( $resource );

    }

    public function close_curl( $curl ) {}

}