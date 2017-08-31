<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/06/17
 * Time: 16.39
 *
 */

namespace DataAccess;


use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class ShapelessConcreteStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public function __set( $name, $value ) {
        $this->$name = $value;
    }

}