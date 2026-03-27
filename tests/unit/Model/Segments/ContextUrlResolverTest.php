<?php

namespace unit\Model\Segments;

use Model\Segments\ContextUrlResolver;
use Model\Segments\SegmentMetadataCollection;
use Model\Segments\SegmentMetadataMarshaller;
use Model\Segments\SegmentMetadataStruct;
use PHPUnit\Framework\TestCase;

class ContextUrlResolverTest extends TestCase
{
    // --- Helpers ---

    private function makeCollection(array $metadata = []): SegmentMetadataCollection
    {
        $structs = [];
        foreach ($metadata as $key => $value) {
            $s = new SegmentMetadataStruct();
            $s->id_segment = '1';
            $s->meta_key = $key;
            $s->meta_value = $value;
            $structs[] = $s;
        }

        return new SegmentMetadataCollection($structs);
    }

    // --- Priority tests ---

    public function testSegmentLevelWins(): void
    {
        $collection = $this->makeCollection([
            SegmentMetadataMarshaller::CONTEXT_URL->value => 'https://segment.example.com'
        ]);

        $result = ContextUrlResolver::resolve($collection, 'https://file.example.com', 'https://project.example.com');

        $this->assertSame('https://segment.example.com', $result);
    }

    public function testFileLevelFallback(): void
    {
        $collection = $this->makeCollection([]);

        $result = ContextUrlResolver::resolve($collection, 'https://file.example.com', 'https://project.example.com');

        $this->assertSame('https://file.example.com', $result);
    }

    public function testProjectLevelFallback(): void
    {
        $collection = $this->makeCollection([]);

        $result = ContextUrlResolver::resolve($collection, null, 'https://project.example.com');

        $this->assertSame('https://project.example.com', $result);
    }

    public function testReturnsNullWhenNoContextUrl(): void
    {
        $collection = $this->makeCollection([]);

        $result = ContextUrlResolver::resolve($collection, null, null);

        $this->assertNull($result);
    }

    // --- Edge cases ---

    public function testSegmentLevelWithNullFileAndProject(): void
    {
        $collection = $this->makeCollection([
            SegmentMetadataMarshaller::CONTEXT_URL->value => 'https://segment-only.example.com'
        ]);

        $result = ContextUrlResolver::resolve($collection, null, null);

        $this->assertSame('https://segment-only.example.com', $result);
    }

    public function testEmptyCollectionWithOtherMetadata(): void
    {
        // Collection has metadata but NOT context-url
        $collection = $this->makeCollection([
            SegmentMetadataMarshaller::RESNAME->value => '//html/body/div[1]',
            SegmentMetadataMarshaller::SIZE_RESTRICTION->value => '42',
        ]);

        $result = ContextUrlResolver::resolve($collection, null, 'https://project.example.com');

        $this->assertSame('https://project.example.com', $result);
    }

    public function testSegmentLevelSkipsWhenFileAndProjectAreNull(): void
    {
        // No context-url at any level
        $collection = $this->makeCollection([
            SegmentMetadataMarshaller::RESNAME->value => 'content-block',
        ]);

        $result = ContextUrlResolver::resolve($collection, null, null);

        $this->assertNull($result);
    }
}
