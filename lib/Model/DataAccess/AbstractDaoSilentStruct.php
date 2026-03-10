<?php

namespace Model\DataAccess;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/10/14
 * Time: 15.49
 *
 */
abstract class AbstractDaoSilentStruct extends AbstractDaoObjectStruct
{

    public function __set($name, $value)
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

}