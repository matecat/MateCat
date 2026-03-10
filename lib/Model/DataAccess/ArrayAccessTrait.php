<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/04/18
 * Time: 13.36
 *
 */

namespace Model\DataAccess;

/**
 * Trait ArrayAccessTrait
 *
 * Provides ArrayAccess implementation to access object properties as array offsets.
 * This allows interacting with the object's properties using array syntax.
 */
trait ArrayAccessTrait
{

    /**
     * Checks if the given offset exists.
     *
     * This method uses both property_exists() and isset() to ensure correct detection:
     * - property_exists() checks if the property is declared in the class, returning true even if it is null.
     * - isset() checks if the property is set and not null, which is necessary to detect magic properties via __isset().
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, $offset) || isset($this->$offset); // use both to check
    }

    /**
     * Retrieves the value at the given offset.
     *
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get($offset);
    }

    /**
     * Sets the value at the given offset.
     *
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set($offset, $value);
    }

    /**
     * Unsets the value at the given offset.
     *
     * @param mixed $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->__set($offset, null);
    }

}