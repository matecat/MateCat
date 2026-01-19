<?php

namespace Utils\Collections;

use ArrayIterator;
use ArrayObject;
use Stringable;

/**
 * User: domenico
 * Date: 23/10/13
 * Time: 16.14
 *
 */
class RecursiveArrayObject extends ArrayObject implements Stringable
{

    /**
     * Overwrites the ArrayObject constructor for
     * iteration through the "array". When the item
     * is an array, it creates another static() instead
     * of an array
     *
     * @param array $array
     * @param int $flag
     * @param string $iteratorClass
     */
    public function __construct(array $array = [], int $flag = 0, string $iteratorClass = ArrayIterator::class)
    {
        parent::__construct([], $flag, $iteratorClass);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $value = new static($value, $flag, $iteratorClass);
            }
            $this->offsetSet($key, $value);
        }
    }

    /**
     * returns Array when printed (like "echo array();")
     * instead of an error
     *
     * @return string
     */
    public function __toString(): string
    {
        return 'Array';
    }

    /**
     * @param array|null $mask a list of keys to extract from the ArrayObject
     *
     * @return array
     */
    public function toArray(array $mask = null): array
    {
        $collector = [];
        foreach ($this as $key => $value) {
            if (!empty($mask) && !in_array($key, $mask)) {
                continue;
            }
            if ($value instanceof RecursiveArrayObject) {
                $collector[$key] = $value->toArray();
            } else {
                $collector[$key] = $value;
            }
        }

        return $collector;
    }

}