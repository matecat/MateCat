<?php

namespace API\V2\Exceptions  ;
class ExternalServiceException extends \Exception {

    // Redefine the exception so message isn't optional
    public function __construct( $message = null, $code = 503, \Exception $previous = null ) {
        // make sure everything is assigned properly
        parent::__construct( $message, $code, $previous );
    }

}
