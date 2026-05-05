<?php

declare(strict_types=1);

namespace unit\Structs;

use Model\Segments\SegmentUIStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Tools\CatUtils;

/**
 * Tests that SegmentUIStruct array properties have correct PHPDoc types
 * matching the actual data structures used at runtime.
 */
class SegmentUIStructTypesTest extends AbstractTest
{
    #[Test]
    public function parsedTimeToEditReturnsExpectedStructure(): void
    {
        // CatUtils::parse_time_to_edit returns [hours:string, minutes:string, seconds:string, usec:int]
        $result = CatUtils::parse_time_to_edit(3661500); // 1h 1m 1s 500ms

        self::assertCount(4, $result);
        self::assertIsString($result[0]); // hours
        self::assertIsString($result[1]); // minutes
        self::assertIsString($result[2]); // seconds
        self::assertIsInt($result[3]);    // milliseconds remainder

        self::assertSame('01', $result[0]);
        self::assertSame('01', $result[1]);
        self::assertSame('01', $result[2]);
        self::assertSame(500, $result[3]);
    }

    #[Test]
    public function parsedTimeToEditZeroReturnsStringZeros(): void
    {
        $result = CatUtils::parse_time_to_edit(0);

        self::assertSame(['00', '00', '00', '00'], $result);
    }

    #[Test]
    public function parsedTimeToEditCanBeAssignedToStruct(): void
    {
        $struct = new SegmentUIStruct();
        $struct->parsed_time_to_edit = CatUtils::parse_time_to_edit(5000);

        self::assertIsArray($struct->parsed_time_to_edit);
        self::assertCount(4, $struct->parsed_time_to_edit);
    }

    #[Test]
    public function sourceChunkLengthsDecodesToListOfInts(): void
    {
        // source_chunk_lengths is stored as JSON string, decoded to list<int>
        $json = '[10, 25, 30]';
        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertCount(3, $decoded);
        foreach ($decoded as $value) {
            self::assertIsInt($value);
        }

        // Assign to struct — both forms are valid
        $struct = new SegmentUIStruct();
        $struct->source_chunk_lengths = $json; // string form
        self::assertIsString($struct->source_chunk_lengths);

        $struct->source_chunk_lengths = $decoded; // array form
        self::assertIsArray($struct->source_chunk_lengths);
    }

    #[Test]
    public function targetChunkLengthsDecodesToAssocArray(): void
    {
        // target_chunk_lengths decoded has 'len' (list<int>) and 'statuses' (list<string>)
        $json = '{"len":[10,25],"statuses":["DRAFT","TRANSLATED"]}';
        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('len', $decoded);
        self::assertArrayHasKey('statuses', $decoded);

        foreach ($decoded['len'] as $len) {
            self::assertIsInt($len);
        }
        foreach ($decoded['statuses'] as $status) {
            self::assertIsString($status);
        }

        $struct = new SegmentUIStruct();
        $struct->target_chunk_lengths = $decoded;
        self::assertIsArray($struct->target_chunk_lengths);
    }

    #[Test]
    public function notesMatchesSegmentNoteAggregatedStructure(): void
    {
        // Notes assigned via attachNotes: list<array{id: int, note: string}>
        // From PDO::FETCH_GROUP|FETCH_ASSOC grouped by id_segment, inner is list of assoc rows
        $notes = [
            ['id' => 1, 'note' => 'First note'],
            ['id' => 2, 'note' => 'Second note'],
        ];

        $struct = new SegmentUIStruct();
        $struct->notes = $notes;

        self::assertIsArray($struct->notes);
        self::assertCount(2, $struct->notes);
        self::assertArrayHasKey('id', $struct->notes[0]);
        self::assertArrayHasKey('note', $struct->notes[0]);
        self::assertIsInt($struct->notes[0]['id']);
        self::assertIsString($struct->notes[0]['note']);
    }

    #[Test]
    public function dataRefMapDecodesToStringStringMap(): void
    {
        // data_ref_map is XLIFF 2.0 data-ref ID → replacement value
        $json = '{"d1":"&lt;b&gt;","d2":"&lt;/b&gt;"}';
        $decoded = json_decode($json, true);

        self::assertIsArray($decoded);
        foreach ($decoded as $key => $value) {
            self::assertIsString($key);
            self::assertIsString($value);
        }

        $struct = new SegmentUIStruct();
        $struct->data_ref_map = $decoded;
        self::assertIsArray($struct->data_ref_map);
    }

    #[Test]
    public function metadataMatchesSegmentMetadataCollectionJsonSerialize(): void
    {
        // metadata comes from SegmentMetadataCollection::jsonSerialize()
        // Returns list<array{id_segment: int, meta_key: string, meta_value: mixed}>
        $metadata = [
            ['id_segment' => 100, 'meta_key' => 'context_url', 'meta_value' => 'https://example.com'],
            ['id_segment' => 100, 'meta_key' => 'character_count', 'meta_value' => 42],
        ];

        $struct = new SegmentUIStruct();
        $struct->metadata = $metadata;

        self::assertIsArray($struct->metadata);
        self::assertCount(2, $struct->metadata);
        self::assertArrayHasKey('id_segment', $struct->metadata[0]);
        self::assertArrayHasKey('meta_key', $struct->metadata[0]);
        self::assertArrayHasKey('meta_value', $struct->metadata[0]);
    }

    #[Test]
    public function structSupportsArrayAccess(): void
    {
        $struct = new SegmentUIStruct();
        $struct->sid = '123';
        $struct->segment = 'Hello world';

        // ArrayAccess with string keys
        self::assertSame('123', $struct['sid']);
        self::assertSame('Hello world', $struct['segment']);

        // Set via array access
        $struct['status'] = 'TRANSLATED';
        self::assertSame('TRANSLATED', $struct->status);
    }
}
