<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 30/03/17
 * Time: 18.30
 *
 */

namespace API\V2\Exceptions;


class NotFoundException extends \Exceptions\NotFoundException {

    // Redefine the exception so message isn't optional
    public function __construct( $message = null, $code = 404, \Exception $previous = null ) {
        // make sure everything is assigned properly
        parent::__construct( $message, $code, $previous );
    }

}