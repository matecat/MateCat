<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 12.32
 *
 */

namespace Utils\TaskRunner\Commons;

/**
 * Class Params
 * Generic parameters container
 *
 * @package TaskRunner\Commons
 */
class Params extends AbstractElement
{

    /**
     * __set() is run when writing data to inaccessible (or not existent) properties
     *
     * @param string $name
     * @param mixed  $value
     */
    public function __set(string $name, mixed $value): void
    {
        $this->$name = $value;
    }

}