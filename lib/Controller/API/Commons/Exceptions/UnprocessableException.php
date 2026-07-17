<?php

namespace Controller\API\Commons\Exceptions;

use Exception;

class UnprocessableException extends Exception
{

    // Redefine the exception so message isn't optional
    public function __construct(string $message = '', int $code = 422, ?Exception $previous = null)
    {
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }

}
