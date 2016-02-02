<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/10/14
 * Time: 15.49
 *
 */

abstract class DataAccess_AbstractDaoSilentStruct extends DataAccess_AbstractDaoObjectStruct {

    /**
     * @return $this
     */
    public function clear() {
        $this->cached_results = array();
        return $this;
    }

    protected function cachable($method_name, $params, $function) {
      if ( !key_exists($method_name,  $this->cached_results) ) {
        $this->cached_results[$method_name] =
          call_user_func($function, $params);
      }
      return $this->cached_results[$method_name];
    }

    public function __get( $name ) {
        if (!property_exists( $this, $name )) {
            throw new DomainException( 'Trying to get an undefined property ' . $name );
        }
    }

    public function __set( $name, $value ) {
        if ( !property_exists( $this, $name ) ) {
            // TODO: write to logs once we'll be able to have
            // distinct log levels. Should go in DEBUG level.
            // Log::doLog("DEBUG: Unknown property $name");
        }
    }

}
