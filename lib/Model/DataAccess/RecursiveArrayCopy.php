<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 06/11/24
 * Time: 11:17
 *
 */

namespace Model\DataAccess;

use ReflectionObject;
use ReflectionProperty;

trait RecursiveArrayCopy
{

    /**
     * Converts the public properties of an object into an associative array.
     *
     * This method uses reflection to retrieve all public properties of the given object
     * (or the current object if no class is provided) and converts them into an array.
     * If a mask is provided, only the properties specified in the mask will be included.
     * Nested objects and arrays are recursively converted into arrays.
     *
     * @param array|null $mask An optional array of property names to include in the result.
     * @param object|null $class An optional object to reflect. Defaults to the current object.
     *
     * @return array An associative array of the object's public properties.
     */
    public function toArray(array $mask = null, object $class = null): array
    {
        $attributes = [];
        $reflectable = $class ?? $this;
        $reflectionClass = new ReflectionObject($reflectable);

        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();

            // Skip properties not included in the mask, if a mask is provided.
            if ($mask && !in_array($propertyName, $mask)) {
                continue;
            }

            // Check if the property is initialized and retrieve its value.
            if ($property->isInitialized($reflectable)) {
                $value = $property->getValue($reflectable);
            } else {
                $value = null;
            }

            // Recursively convert objects to arrays.
            if (is_object($value)) {
                $attributes[$propertyName] = $this->toArray([], $value);
            } // Recursively process arrays, preserving keys.
            elseif (is_array($value)) {
                if (empty($value)) {
                    $attributes[$propertyName] = [];
                    continue;
                }

                foreach ($value as $k => $v) {
                    $attributes[$propertyName][$k] = is_object($v) ? $this->toArray([], $v) : $v;
                }
            } // Assign scalar values directly.
            else {
                $attributes[$propertyName] = $value;
            }
        }

        return $attributes;
    }

}