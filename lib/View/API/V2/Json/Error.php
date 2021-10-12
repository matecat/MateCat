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

class Error {

    private $data;

    public function __construct( $data = [] ) {
        $this->data = $data;
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

            if ( $error instanceof Exception ) {
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