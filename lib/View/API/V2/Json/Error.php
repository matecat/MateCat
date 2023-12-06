<?php

namespace API\V2\Json;

use Exception;

class Error
{
    /**
     * @var Exception
     */
    private $exception;

    /**
     * Error constructor.
     * @param Exception $exception
     */
    public function __construct( Exception $exception )
    {
        $this->exception = $exception;
    }

    /**
     * @return array
     */
    public function render()
    {
        return [
            "errors" => [
                [
                    "code"    => $this->exception->getCode(),
                    "message" => $this->exception->getMessage()
                ]
            ],
            "data"   => []
        ];
    }

}