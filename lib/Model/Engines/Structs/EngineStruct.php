<?php

namespace Model\Engines\Structs;

use ArrayAccess;
use DomainException;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use Stringable;

/**
 * @phpstan-consistent-constructor
 * @implements ArrayAccess<string, mixed>
 */
class EngineStruct
    extends AbstractDaoObjectStruct
    implements IDaoStruct, ArrayAccess, Stringable
{

    public ?int $id = null;

    public ?string $name = null;

    /**
     * @var ?string A string from the ones in Constants_EngineType
     * @see Constants_EngineType
     */
    public ?string $type = null;

    public ?string $description = null;

    public ?string $base_url = null;

    public ?string $translate_relative_url = null;

    public ?string $contribute_relative_url = null;

    public ?string $update_relative_url = null;

    public ?string $delete_relative_url = null;

    /** @var array<string, mixed>|string|null */
    public string|array|null $others = [];

    public ?string $class_load = null;

    /** @var array<string, mixed>|string|null */
    public string|array|null $extra_parameters = [];

    public ?int $google_api_compliant_version = null;

    public ?int $penalty = null;

    public ?bool $active = null;

    public ?int $uid = null;

    public static function getStruct(): static
    {
        return new static();
    }

    public function offsetExists(mixed $offset): bool
    {
        return property_exists($this, $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->$offset;
    }

    /**
     * @throws DomainException
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->$offset = $value;
    }

    /**
     * @throws DomainException
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->$offset = null;
    }

    public function __toString(): string
    {
        return $this->id . $this->name . $this->description;
    }

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

    /** @return array<string, mixed> */
    public function arrayRepresentation(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'extra' => $this->extra_parameters,
            'engine_type' => $this->getEngineType(),
        ];
    }

    public function getEngineType(): ?string
    {
        if ($this->class_load === null) {
            return null;
        }

        $engine_type = explode("\\", $this->class_load);

        return array_pop($engine_type);
    }

}
