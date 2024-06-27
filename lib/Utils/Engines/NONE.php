<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/08/17
 * Time: 12.16
 *
 */


class Engines_NONE  extends Engines_AbstractEngine {

    public function get( $_config ) {
        return new Engines_Results_MyMemory_TMS( [ 'responseStatus' => 200, 'responseData' => [] ] );
    }

    public function set( $_config ) {
        return new Engines_Results_MyMemory_TMS( [ 'responseStatus' => 200, 'responseData' => [] ] );
    }

    public function update( $_config ) {
        return new Engines_Results_MyMemory_TMS( [ 'responseStatus' => 200, 'responseData' => [] ] );
    }

    public function delete( $_config ) {
        return new Engines_Results_MyMemory_TMS( [ 'responseStatus' => 200, 'responseData' => [] ] );
    }

    protected function _decode( $rawValue, array $parameters = [], $function = null ) {}

}