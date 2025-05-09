<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/02/17
 * Time: 16.39
 *
 */

namespace API\Commons;


use Exception;
use INIT;
use Throwable;

class Error {

    private array $data;

    /**
     * Error constructor.
     *
     * @param Exception[] $exceptions
     */
    public function __construct( array $exceptions = [] ) {
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
                if ( INIT::$PRINT_ERRORS ) {
                    $row[ 'errors' ][ 0 ][ 'trace' ] = $error->getTrace();
                }
            } else {
                $code   = -1000;
                $output = $error;
            }

            $row[ 'errors' ][ 0 ][ 'code' ] = $code;
            $row[ 'errors' ][ 0 ][ 'message' ] = $output;

        }

        return $row;
    }

}