<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/02/17
 * Time: 16.39
 *
 */

namespace API\V2\Json;


use Exception;
use Throwable;

class Error {

    private $data;

    /**
     * Error constructor.
     * @param Exception[] $exceptions
     */
    public function __construct($exceptions = [] ) {
        $this->data = $exceptions;
    }

    public function render( $data = null ) {

        $row = [
            "errors" => [],
            "data"   => []
        ];

        if ( empty( $data ) ) {
            $data = $this->data;
        }

        foreach ( $data as $error ) {

            if ( $error instanceof Throwable ) {
                $code   = $error->getCode();
                $output = $error->getMessage();
            } else {
                $code   = -1000;
                $output = $error;
            }

            $row[ 'errors' ][] = [
                "code"    => $code,
                "message" => $output
            ];

        }

        return $row;
    }

}