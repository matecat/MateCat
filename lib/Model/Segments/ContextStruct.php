<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/09/18
 * Time: 11.11
 *
 */

namespace Segments;


use ArrayAccess;
use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class ContextStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, ArrayAccess {

    public $id;
    public $id_project;
    public $id_segment;
    public $id_file;
    public $context_json;

    public function __construct( array $array_params = [], $decode = true ) {
        parent::__construct( $array_params );
        if( $decode ){
            $this->context_json = json_decode( $this->context_json );
        }
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists( $offset ) {
        return property_exists( $this, $offset );
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet( $offset ) {
        return $this->$offset;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet( $offset, $value ) {
        $this->$offset = $value;
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset( $offset ) {
        $this->$offset = null;
    }

}