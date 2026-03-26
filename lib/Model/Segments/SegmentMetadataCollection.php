<?php

namespace Model\Segments;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;

/** @implements IteratorAggregate<int, SegmentMetadataStruct> */
class SegmentMetadataCollection implements IteratorAggregate, Countable, JsonSerializable
{
    /** @var SegmentMetadataStruct[] */
    private array $structs;

    /**
     * @param SegmentMetadataStruct[] $structs
     */
    public function __construct(array $structs = [])
    {
        $this->structs = $structs;
    }

    /**
     * Searches for a match in the internal structs array where the meta_key equals the given key's value.
     *
     * @param SegmentMetadataMarshaller $key The object containing the value to be matched against struct meta_key.
     * @return string|null Returns the corresponding meta_value if a match is found, or null if no match exists.
     */
    public function find(SegmentMetadataMarshaller $key): ?string
    {
        foreach ($this->structs as $struct) {
            if ($struct->meta_key === $key->value) {
                return $struct->meta_value;
            }
        }

        return null;
    }

    /**
     * Finds and returns a typed object based on the provided key.
     *
     * @param SegmentMetadataMarshaller $key The key used to find the matching typed object.
     * @return mixed The unmarshalled typed object if found, or null if no match exists.
     */
    public function findTyped(SegmentMetadataMarshaller $key): mixed
    {
        foreach ($this->structs as $struct) {
            if ($struct->meta_key === $key->value) {
                return SegmentMetadataMarshaller::unmarshall($struct);
            }
        }

        return null;
    }

    /** @return ArrayIterator<int, SegmentMetadataStruct> */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->structs);
    }

    public function count(): int
    {
        return count($this->structs);
    }

    public function isEmpty(): bool
    {
        return empty($this->structs);
    }

    public function jsonSerialize(): array
    {
        return array_map(fn(SegmentMetadataStruct $s) => [
            'id_segment' => $s->id_segment,
            'meta_key'   => $s->meta_key,
            'meta_value' => SegmentMetadataMarshaller::unmarshall($s),
        ], $this->structs);
    }
}
