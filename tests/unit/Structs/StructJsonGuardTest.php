<?php

use Model\Segments\SegmentOriginalDataStruct;
use Model\LQA\CategoryStruct;
use Model\LQA\ChunkReviewStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class StructJsonGuardTest extends AbstractTest
{
    #[Test]
    public function segmentOriginalDataStruct_setMap_handles_json_encode_correctly(): void
    {
        $struct = new SegmentOriginalDataStruct();
        $result = $struct->setMap(['key' => 'value']);

        $this->assertInstanceOf(SegmentOriginalDataStruct::class, $result);
        $this->assertSame(['key' => 'value'], $struct->getMap());
    }

    #[Test]
    public function segmentOriginalDataStruct_getMap_returns_empty_array_for_empty_map(): void
    {
        $struct = new SegmentOriginalDataStruct();

        $this->assertSame([], $struct->getMap());
    }

    #[Test]
    public function chunkReviewStruct_getUndoData_returns_null_when_undo_data_is_null(): void
    {
        $struct = new ChunkReviewStruct();
        $struct->undo_data = null;

        $this->assertNull($struct->getUndoData());
    }

    #[Test]
    public function chunkReviewStruct_getUndoData_decodes_valid_json(): void
    {
        $struct = new ChunkReviewStruct();
        $struct->undo_data = '{"key":"value"}';

        $this->assertSame(['key' => 'value'], $struct->getUndoData());
    }

    #[Test]
    public function categoryStruct_toArrayWithJsonDecoded_handles_null_options(): void
    {
        $struct = new CategoryStruct();
        $struct->severities = '[{"label":"Minor","penalty":1}]';
        $struct->options = null;

        $result = $struct->toArrayWithJsonDecoded();

        $this->assertIsArray($result);
        $this->assertNull($result['options']);
    }

    #[Test]
    public function categoryStruct_toArrayWithJsonDecoded_decodes_valid_json(): void
    {
        $struct = new CategoryStruct();
        $struct->severities = '[{"label":"Minor","penalty":1}]';
        $struct->options = '{"key":"value"}';

        $result = $struct->toArrayWithJsonDecoded();

        $this->assertCount(1, $result['severities']);
        $this->assertSame('Minor', $result['severities'][0]['label']);
        $this->assertSame('value', $result['options']['key']);
    }
}
