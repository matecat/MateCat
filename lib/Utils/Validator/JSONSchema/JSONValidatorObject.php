<?php

namespace Utils\Validator\JSONSchema;

use Exception;
use Model\DataAccess\ArrayAccessTrait;
use Utils\Validator\Contracts\ValidatorObjectInterface;

class JSONValidatorObject implements ValidatorObjectInterface
{

    use ArrayAccessTrait;

    /** @var array<string, mixed> */
    protected array $store = [];

    protected string $json;

    protected mixed $decoded;

    protected bool $isDecoded = false;

    public function __construct(?string $json = null)
    {
        $this->json = $json ?? '';
    }

    /**
     * @return mixed|null
     * @throws Exception
     */
    public function decode(): mixed
    {
        if ($this->isDecoded) {
            return $this->decoded;
        }

        $this->decoded = json_decode($this->json == '' ? 'null' : $this->json, false, 512, JSON_THROW_ON_ERROR);
        $this->isDecoded = true;

        return $this->decoded;
    }

    /**
     * @return mixed|null
     * @throws Exception
     */
    public function getValue(bool $associative = false): mixed
    {
        $val = $this->decode();

        if ($val === null) {
            return null;
        }

        if ($associative) {
            return $this->toArray((object)$val);
        }

        return $val;
    }

    /**
     * @param object $object
     *
     * @return array<string, mixed>
     */
    private function toArray(object $object): array
    {
        $collector = [];
        foreach ((array)$object as $key => $value) {
            $isStructured = is_array($value) || is_object($value);

            if ($isStructured) {
                $collector[$key] = $this->toArray((object)$value);
            } else {
                $collector[$key] = $value;
            }
        }

        return $collector;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->store[$name] = $value;
    }

    public function __get(string $name): mixed
    {
        if (!array_key_exists($name, $this->store)) {
            return null;
        }

        return $this->store[$name];
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->store);
    }

}
