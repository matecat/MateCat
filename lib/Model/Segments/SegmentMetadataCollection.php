<?php

namespace Model\Segments;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/** @implements IteratorAggregate<int, SegmentMetadataStruct> */
class SegmentMetadataCollection implements IteratorAggregate, Countable
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

    public function find(SegmentMetadataMarshaller $key): ?string
    {
        foreach ($this->structs as $struct) {
            if ($struct->meta_key === $key->value) {
                return $struct->meta_value;
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
}
