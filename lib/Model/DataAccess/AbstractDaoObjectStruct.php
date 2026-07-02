<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/10/14
 * Time: 15.49
 *
 */

namespace Model\DataAccess;

use Countable;
use ReflectionObject;
use ReflectionProperty;
use stdClass;

abstract class AbstractDaoObjectStruct extends stdClass implements IDaoStruct, Countable
{

    use RecursiveArrayCopy;
    use MemoizeTrait;

    /**
     * @param array<string, mixed> $array_params
     */
    public function __construct(array $array_params = [])
    {
        if ($array_params != null) {
            foreach ($array_params as $property => $value) {
                $this->$property = $value;
            }
        }
    }

    /**
     * Strict setter: validates that the property exists, then assigns.
     *
     * Direct property writes (`$this->unknownProp = ...`) throw for
     * undefined properties. Array-access writes use {@see offsetSet()}
     * instead, which routes unknown keys to the overflow map.
     *
     * @param string $name
     * @param mixed $value
     *
     * @throws UnknownPropertyException
     */
    public function __set(string $name, mixed $value): void
    {
        if (!property_exists($this, $name)) {
            throw new UnknownPropertyException($name);
        }
        $this->$name = $value;
    }

    /**
     * @param string $name
     *
     * @return mixed
     * @throws UnknownPropertyException
     */
    public function __get(string $name): mixed
    {
        if (!property_exists($this, $name)) {
            throw new UnknownPropertyException($name);
        }

        return $this->$name;
    }

    /**
     * @param string $attribute
     * @param int    $timestamp
     *
     * @return void
     * @throws UnknownPropertyException
     */
    public function setTimestamp(string $attribute, int $timestamp): void
    {
        $this->$attribute = date('c', $timestamp);
    }

    /**
     * Compatibility with ArrayObject
     *
     * @return array<string, mixed>
     */
    public function getArrayCopy(): array
    {
        return $this->toArray();
    }

    public function count(): int
    {
        $reflectionClass = new ReflectionObject($this);

        return count($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC));
    }

} 