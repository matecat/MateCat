<?php

namespace Model\Segments;

class SegmentMetadataMapper
{
    /** @param array<string, mixed> $transUnitAttributes */
    public function fromTransUnitAttributes(array $transUnitAttributes): SegmentMetadataCollection
    {
        $structs = [];

        foreach ($transUnitAttributes as $key => $value) {
            $case = SegmentMetadataMarshaller::tryFrom($key);

            if ($case === null) {
                continue;
            }

            $castValue = $case->marshall($value);

            if ($castValue === null) {
                continue;
            }

            $struct             = new SegmentMetadataStruct();
            $struct->meta_key   = $case->value;
            $struct->meta_value = $castValue;

            $structs[] = $struct;
        }

        return new SegmentMetadataCollection($structs);
    }
}
