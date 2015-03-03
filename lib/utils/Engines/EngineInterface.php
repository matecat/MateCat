<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/02/15
 * Time: 11.55
 * 
 */

interface Engines_EngineInterface {

    public function get( $_config );
    public function set( $_config );
    public function update( $_config );
    public function delete( $_config );
    public function getConfigStruct();

}