<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/04/18
 * Time: 16.01
 *
 */

namespace Model\DataAccess;


use DataAccess\ArrayAccessTrait;
use DataAccess_AbstractDaoObjectStruct;

class SilentAccessStruct extends DataAccess_AbstractDaoObjectStruct {

    use ArrayAccessTrait;

    public function __set( $name, $value ) {
        $this->$name = $value;
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    public function __get( $name ) {
        return @$this->$name;
    }

}