<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/02/17
 * Time: 16.39
 *
 */

namespace API\Commons;


use INIT;
use JsonSerializable;
use Throwable;

class Error implements JsonSerializable {

    private Throwable $data;

    /**
     * Error constructor.
     *
     * @param Throwable $exceptions
     */
    public function __construct( Throwable $exceptions ) {
        $this->data = $exceptions;
    }

    public function render( bool $force_print_errors = false ): array {

        $row = [
                "errors" => [],
                "data"   => []
        ];

        foreach ( $this->data as $error ) {

            if ( $error instanceof Throwable ) {

                $code   = $error->getCode();
                $output = $error->getMessage();

                if ( INIT::$PRINT_ERRORS || $force_print_errors ) {
                    $row[ 'errors' ][ 0 ][ 'file' ] = $error->getFile();
                    $row[ 'errors' ][ 0 ][ 'line' ] = $error->getLine();
                    $row[ 'errors' ][ 0 ][ 'trace' ] = $error->getTrace();
                    if ( $error->getPrevious() ) {
                        $row[ 'errors' ][ 0 ][ 'caused_by' ][ 'message' ] = $error->getPrevious()->getMessage();
                        $row[ 'errors' ][ 0 ][ 'caused_by' ][ 'code' ]    = $error->getPrevious()->getCode();
                        $row[ 'errors' ][ 0 ][ 'caused_by' ][ 'file' ]    = $error->getPrevious()->getFile();
                        $row[ 'errors' ][ 0 ][ 'caused_by' ][ 'line' ]    = $error->getPrevious()->getLine();
                        $row[ 'errors' ][ 0 ][ 'caused_by' ][ 'trace' ]   = $error->getPrevious()->getTrace();
                    }
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

    public function jsonSerialize(): array {
        return $this->render();
    }


}