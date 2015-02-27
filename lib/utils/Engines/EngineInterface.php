<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/02/15
 * Time: 11.55
 * 
 */

interface Engines_EngineInterface {

    public function get( $config );
    public function set( $config );
    public function update( $config );
    public function delete( $config );

}