<?php

namespace Utils\Engines;

use Utils\Engines\Results\MyMemory\GetMemoryResponse;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/08/17
 * Time: 12.16
 *
 */
class NONE extends AbstractEngine {

    public function get( array $_config ) {
        return new GetMemoryResponse( [ 'responseStatus' => 200, 'responseData' => [] ] );
    }

    public function set( $_config ) {
        return new GetMemoryResponse( [ 'responseStatus' => 200, 'responseData' => [] ] );
    }

    public function update( $_config ) {
        return new GetMemoryResponse( [ 'responseStatus' => 200, 'responseData' => [] ] );
    }

    public function delete( $_config ) {
        return new GetMemoryResponse( [ 'responseStatus' => 200, 'responseData' => [] ] );
    }

    protected function _decode( $rawValue, array $parameters = [], $function = null ) {
    }

    public function getConfigurationParameters(): array {
        return [];
    }
}