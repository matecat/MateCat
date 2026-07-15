<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/06/17
 * Time: 16.39
 *
 */

namespace Model\DataAccess;

use ArrayAccess;

/**
 * @implements ArrayAccess<string, mixed>
 */
class ShapelessConcreteStruct extends AbstractDaoObjectStruct implements ArrayAccess
{

    use ArrayAccessTrait;

    public function __set(string $name, mixed $value): void
    {
        $this->$name = $value;
    }

    public function __get(string $name): mixed
    {
        if (!property_exists($this, $name)) {
            return null;
        }

        return $this->$name;
    }

    public function getArrayCopy(): array
    {
        return $this->toArray();
    }

}