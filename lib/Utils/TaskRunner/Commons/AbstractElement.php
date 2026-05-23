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
use Model\DataAccess\UnknownPropertyException;
use stdClass;
use Stringable;

/**
 * Class AbstractElement
 *
 * Generic class for an element queue
 *
 * @package TaskRunner\Commons
 * @implements ArrayAccess<string, mixed>
 */
abstract class AbstractElement extends stdClass implements ArrayAccess, Stringable
{

    /**
     * AbstractElement constructor.
     *
     * @param array<string, mixed> $array_params
     *
     * @throws DomainException
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
     * @param mixed $value
     *
     * @throws UnknownPropertyException
     */
    public function __set(string $name, mixed $value): void
    {
        throw new UnknownPropertyException($name);
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
     *
     * @throws DomainException
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
     *
     * @throws DomainException
     */
    public function offsetUnset(mixed $offset): void
    {
        if ($this->offsetExists($offset)) {
            $this->$offset = null;
        }
    }

    /**
     * Recursive Object to Array conversion method
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $nestedParamsObject = [];
        foreach (get_object_vars($this) as $key => $item) {
            if ($item instanceof AbstractElement) {
                $nestedParamsObject[$key] = $item->toArray();
            } else {
                $nestedParamsObject[$key] = $item;
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
        return json_encode($this) ?: '{}';
    }

}