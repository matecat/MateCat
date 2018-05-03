<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 03/07/2017
 * Time: 16:16
 */

namespace Features\Dqf\Model\CachedAttributes;

use Exception;
use INIT;

abstract class AbstractCachedAttribute {

    protected $resource_name;

    /**
     * @var array
     */
    protected $resource_json ;

    static $instance ;

    public function __construct() {
        $path = INIT::$ROOT . '/inc/dqf/cachedAttributes/' . $this->resource_name . '.json' ;
        $this->resource_json = json_decode( file_get_contents( $path ), true );
    }

    public static function obtain() {
        if ( !isset( self::$instance ) ) {
            self::$instance = new static() ;
        }
        return self::$instance ;
    }

    public function getArray() {
        return $this->resource_json ;
    }

    public function getIds() {
        return array_filter( array_map( function( $item ) {
            return $item['id'];
        }, $this->getArray() ) ) ;
    }

    public function getByName( $name ) {
        $result = array_filter($this->resource_json, function($item) use ($name) {
            return $item['name'] == $name ;
        }) ;

        if ( empty( $result ) ) {
            throw new Exception('object not found by name ' . $name );
        }

        return current( $result );
    }



}