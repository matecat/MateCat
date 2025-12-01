<?php

namespace Model\Engines\Structs;

use ArrayAccess;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use Stringable;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 23/02/15
 * Time: 14.54
 */
class EngineStruct
        extends AbstractDaoObjectStruct
        implements IDaoStruct, ArrayAccess, Stringable
{

    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var string|null
     */
    public ?string $name = null;


    /**
     * @var ?string A string from the ones in Constants_EngineType
     * @see Constants_EngineType
     */
    public ?string $type = null;

    /**
     * @var string|null
     */
    public ?string $description = null;

    /**
     * @var string|null
     */
    public ?string $base_url = null;

    /**
     * @var string|null
     */
    public ?string $translate_relative_url = null;

    /**
     * @var string|null
     */
    public ?string $contribute_relative_url = null;

    /**
     * @var string|null
     */
    public ?string $update_relative_url = null;

    /**
     * @var string|null
     */
    public ?string $delete_relative_url = null;

    /**
     * @var string|array|null
     */
    public string|array|null $others = [];

    /**
     * @var string|null
     */
    public ?string $class_load = null;


    /**
     * @var string|array|null
     */
    public string|array|null $extra_parameters = [];

    /**
     * @var int|null
     */
    public ?int $google_api_compliant_version = null;

    /**
     * @var int|null
     */
    public ?int $penalty = null;

    /**
     * @var ?bool
     */
    public ?bool $active = null;

    /**
     * @var int|null
     */
    public ?int $uid = null;

    /**
     *  An empty struct
     *
     * @template T
     * @return EngineStruct instance of EngineStruct
     */
    public static function getStruct(): static
    {
        return new EngineStruct();
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     *
     * @param mixed $offset <p>
     *                      An offset to check for.
     *                      </p>
     *
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, $offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     *
     * @param mixed $offset <p>
     *                      The offset to retrieve.
     *                      </p>
     *
     * @return mixed Can return all value types.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     *
     * @param mixed $offset <p>
     *                      The offset to assign the value to.
     *                      </p>
     * @param mixed $value  <p>
     *                      The value to set.
     *                      </p>
     *
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     *
     * @param mixed $offset <p>
     *                      The offset to unset.
     *                      </p>
     *
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->$offset = null;
    }

    /**
     * Cast an EnginesFactory to String. Useful for engine comparison inside a list ( catController )
     */
    public function __toString(): string
    {
        return $this->id . $this->name . $this->description;
    }

    /**
     * If, for some reasons, extra_parameters
     * if NOT an array but a JSON
     * this function normalize it
     *
     * @return array|mixed
     */
    public function getExtraParamsAsArray(): mixed
    {
        if (is_array($this->extra_parameters)) {
            return $this->extra_parameters;
        }

        if (empty($this->extra_parameters)) {
            return [];
        }

        return json_decode($this->extra_parameters, true);
    }

}