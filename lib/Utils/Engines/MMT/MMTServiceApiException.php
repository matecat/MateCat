<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 10/10/17
 * Time: 13.01
 *
 */

namespace Engines\MMT;

/**
 * Created by PhpStorm.
 * User: davide davide.caroselli@translated.net
 * Date: 04/10/17
 * Time: 08:59
 */

class MMTServiceApiException extends \Exception {

    public static function fromJSONResponse( $json ) {
        $code    = isset( $json[ 'status' ] ) ? intval( $json[ 'status' ] ) : 500;
        $type    = isset( $json[ 'error' ][ 'type' ] ) ? $json[ 'error' ][ 'type' ] : 'UnknownException';
        $message = isset( $json[ 'error' ][ 'message' ] ) ? $json[ 'error' ][ 'message' ] : '';

        return new self( $type, $code, $message );
    }

    private $type;

    public function __construct( $type, $code, $message = "" ) {
        parent::__construct( $message, $code );
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

}