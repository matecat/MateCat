<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 03/07/2017
 * Time: 16:16
 */

namespace Features\Dqf\Model\CachedAttributes;

use INIT;

abstract class AbstractCachedAttribute {

    protected $resource_name;

    /**
     * @var array
     */
    protected $resource_json ;

    public function __construct() {
        $path = INIT::$ROOT . '/inc/dqf/cachedAttributes/' . $this->resource_name . '.json' ;
        $this->resource_json = json_decode( file_get_contents( $path ), true );
    }

    public function getArray() {
        return $this->resource_json ;
    }

}