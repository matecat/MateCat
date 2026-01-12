<?php

namespace Utils\Validator\Contracts;

use ArrayAccess;
use Model\DataAccess\ArrayAccessTrait;
use stdClass;

class ValidatorObject implements ArrayAccess
{

    use ArrayAccessTrait;

    protected array $store = [];

    /**
     * @param stdClass $object
     *
     * @return ValidatorObject
     */
    public static function fromObject(stdClass $object): ValidatorObject
    {
        $that = new static();
        foreach (get_object_vars($object) as $key => $value) {
            $that->store[$key] = $value;
        }

        return $that;
    }

    /**
     * @param array $array
     *
     * @return ValidatorObject
     */
    public static function fromArray(array $array): ValidatorObject
    {
        $that = new static();
        foreach ($array as $key => $value) {
            $that->store[$key] = $value;
        }

        return $that;
    }

    /**
     * Magic setter
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, mixed $value)
    {
        $this->store[$name] = $value;
    }

    /**
     * Magic getter
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        if (!array_key_exists($name, $this->store)) {
            return null;
        }

        return $this->store[$name];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->store);
    }

}
