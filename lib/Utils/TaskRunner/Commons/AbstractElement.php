<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 23/12/15
 * Time: 21.00
 *
 */

namespace Utils\TaskRunner\Commons;

use ArrayAccess;
use DomainException;
use stdClass;
use Stringable;

/**
 * Class AbstractElement
 *
 * Generic class for an element queue
 *
 * @package TaskRunner\Commons
 */
abstract class AbstractElement extends stdClass implements ArrayAccess, Stringable
{

    /**
     * AbstractElement constructor.
     *
     * @param array $array_params
     */
    public function __construct(array $array_params = [])
    {
        if ($array_params != null) {
            foreach ($array_params as $property => $value) {
                if (is_array($value)) {
                    $value = new Params($value);
                }
                $this->$property = $value;
            }
        }
    }

    /**
     * __set() is run when writing data to inaccessible properties
     *
     * @param string $name
     * @param mixed  $value
     */
    public function __set(string $name, mixed $value): void
    {
        throw new DomainException('Unknown property ' . $name);
    }

    /**
     * ArrayAccess interface implementation
     *
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, $offset);
    }

    /**
     * ArrayAccess interface implementation
     *
     * @param mixed $offset
     *
     * @return null
     */
    public function offsetGet(mixed $offset): mixed
    {
        if ($this->offsetExists($offset)) {
            return $this->$offset;
        }

        return null;
    }

    /**
     * ArrayAccess interface implementation
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($this->offsetExists($offset)) {
            $this->$offset = $value;
        }
    }

    /**
     * ArrayAccess interface implementation
     *
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->offsetExists($offset)) {
            $this->$offset = null;
        }
    }

    /**
     * Recursive Object to Array conversion method
     */
    public function toArray(): array
    {
        $nestedParamsObject = [];
        foreach ($this as $key => $item) {
            if ($item instanceof AbstractElement) {
                $nestedParamsObject[ $key ] = $item->toArray();
            } else {
                $nestedParamsObject[ $key ] = $item;
            }
        }

        return $nestedParamsObject;
    }

    /**
     * Magic to string method
     *
     * @return string
     */
    public function __toString(): string
    {
        return json_encode($this);
    }

}