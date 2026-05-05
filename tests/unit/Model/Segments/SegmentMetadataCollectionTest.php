<?php

namespace unit\Model\Segments;

use Model\Segments\SegmentMetadataCollection;
use Model\Segments\SegmentMetadataMarshaller;
use Model\Segments\SegmentMetadataStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class SegmentMetadataCollectionTest extends AbstractTest
{
    private function makeStruct(string $key, string $value): SegmentMetadataStruct
    {
        $struct             = new SegmentMetadataStruct();
        $struct->meta_key   = $key;
        $struct->meta_value = $value;

        return $struct;
    }

    #[Test]
    public function testEmptyCollectionCountIsZero(): void
    {
        $collection = new SegmentMetadataCollection();

        self::assertCount(0, $collection);
        self::assertTrue($collection->isEmpty());
    }

    #[Test]
    public function testCountReflectsNumberOfStructs(): void
    {
        $collection = new SegmentMetadataCollection([
            $this->makeStruct('id_request', 'REQ-1'),
            $this->makeStruct('sizeRestriction', '42'),
        ]);

        self::assertCount(2, $collection);
        self::assertFalse($collection->isEmpty());
    }

    #[Test]
    public function testFindReturnsMetaValueWhenKeyPresent(): void
    {
        $collection = new SegmentMetadataCollection([
            $this->makeStruct('id_request', 'REQ-123'),
            $this->makeStruct('sizeRestriction', '80'),
        ]);

        self::assertSame('80', $collection->find(SegmentMetadataMarshaller::SIZE_RESTRICTION));
        self::assertSame('REQ-123', $collection->find(SegmentMetadataMarshaller::ID_REQUEST));
    }

    #[Test]
    public function testFindReturnsNullWhenKeyAbsent(): void
    {
        $collection = new SegmentMetadataCollection([
            $this->makeStruct('id_request', 'REQ-1'),
        ]);

        self::assertNull($collection->find(SegmentMetadataMarshaller::SIZE_RESTRICTION));
    }

    #[Test]
    public function testFindReturnsNullOnEmptyCollection(): void
    {
        $collection = new SegmentMetadataCollection();

        self::assertNull($collection->find(SegmentMetadataMarshaller::SIZE_RESTRICTION));
    }

    #[Test]
    public function testForeachYieldsAllStructs(): void
    {
        $s1 = $this->makeStruct('id_request', 'REQ-1');
        $s2 = $this->makeStruct('screenshot', 'http://example.com/img.png');

        $collection = new SegmentMetadataCollection([$s1, $s2]);

        $iterated = [];
        foreach ($collection as $struct) {
            $iterated[] = $struct;
        }

        self::assertCount(2, $iterated);
        self::assertSame($s1, $iterated[0]);
        self::assertSame($s2, $iterated[1]);
    }

    #[Test]
    public function testForeachOnEmptyCollectionYieldsNothing(): void
    {
        $collection = new SegmentMetadataCollection();

        $iterated = [];
        foreach ($collection as $struct) {
            $iterated[] = $struct;
        }

        self::assertSame([], $iterated);
    }

    #[Test]
    public function testFindReturnsFirstMatchWhenDuplicateKeysExist(): void
    {
        $collection = new SegmentMetadataCollection([
            $this->makeStruct('sizeRestriction', '42'),
            $this->makeStruct('sizeRestriction', '99'),
        ]);

        self::assertSame('42', $collection->find(SegmentMetadataMarshaller::SIZE_RESTRICTION));
    }

    #[Test]
    public function testFindWorksForAllMarshallerCases(): void
    {
        $collection = new SegmentMetadataCollection([
            $this->makeStruct('id_request', 'REQ-1'),
            $this->makeStruct('id_content', 'CNT-1'),
            $this->makeStruct('id_order', '5'),
            $this->makeStruct('id_order_group', 'GRP-A'),
            $this->makeStruct('screenshot', 'http://example.com/shot.png'),
            $this->makeStruct('sizeRestriction', '120'),
        ]);

        self::assertSame('REQ-1', $collection->find(SegmentMetadataMarshaller::ID_REQUEST));
        self::assertSame('CNT-1', $collection->find(SegmentMetadataMarshaller::ID_CONTENT));
        self::assertSame('5', $collection->find(SegmentMetadataMarshaller::ID_ORDER));
        self::assertSame('GRP-A', $collection->find(SegmentMetadataMarshaller::ID_ORDER_GROUP));
        self::assertSame('http://example.com/shot.png', $collection->find(SegmentMetadataMarshaller::SCREENSHOT));
        self::assertSame('120', $collection->find(SegmentMetadataMarshaller::SIZE_RESTRICTION));
    }

    #[Test]
    public function testFindTypedReturnsCastedValueForSizeRestriction(): void
    {
        $struct = new SegmentMetadataStruct();
        $struct->meta_key = SegmentMetadataMarshaller::SIZE_RESTRICTION->value;
        $struct->meta_value = '80';

        $collection = new SegmentMetadataCollection([$struct]);

        self::assertSame(80, $collection->findTyped(SegmentMetadataMarshaller::SIZE_RESTRICTION));
    }

    #[Test]
    public function testFindTypedReturnsStringForRegularKey(): void
    {
        $struct = new SegmentMetadataStruct();
        $struct->meta_key = SegmentMetadataMarshaller::ID_REQUEST->value;
        $struct->meta_value = 'REQ-123';

        $collection = new SegmentMetadataCollection([$struct]);

        self::assertSame('REQ-123', $collection->findTyped(SegmentMetadataMarshaller::ID_REQUEST));
    }

    #[Test]
    public function testFindTypedReturnsNullWhenKeyNotFound(): void
    {
        $collection = new SegmentMetadataCollection([]);
        self::assertNull($collection->findTyped(SegmentMetadataMarshaller::SIZE_RESTRICTION));
    }

    #[Test]
    public function testFindReturnsRawStringWhileFindTypedReturnsCasted(): void
    {
        $struct = new SegmentMetadataStruct();
        $struct->meta_key = SegmentMetadataMarshaller::SIZE_RESTRICTION->value;
        $struct->meta_value = '42';

        $collection = new SegmentMetadataCollection([$struct]);

        self::assertSame('42', $collection->find(SegmentMetadataMarshaller::SIZE_RESTRICTION));
        self::assertSame(42, $collection->findTyped(SegmentMetadataMarshaller::SIZE_RESTRICTION));
    }

    #[Test]
    public function testJsonSerializeReturnsTypedValues(): void
    {
        $s1 = new SegmentMetadataStruct();
        $s1->id_segment = '456';
        $s1->meta_key = SegmentMetadataMarshaller::ID_REQUEST->value;
        $s1->meta_value = 'REQ-1';

        $s2 = new SegmentMetadataStruct();
        $s2->id_segment = '456';
        $s2->meta_key = SegmentMetadataMarshaller::SIZE_RESTRICTION->value;
        $s2->meta_value = '120';

        $collection = new SegmentMetadataCollection([$s1, $s2]);

        $json = json_encode($collection);
        $decoded = json_decode($json, true);

        self::assertCount(2, $decoded);

        self::assertSame('id_request', $decoded[0]['meta_key']);
        self::assertSame('REQ-1', $decoded[0]['meta_value']);

        self::assertSame('sizeRestriction', $decoded[1]['meta_key']);
        self::assertSame(120, $decoded[1]['meta_value']);
    }

    #[Test]
    public function testJsonSerializeEmptyCollection(): void
    {
        $collection = new SegmentMetadataCollection([]);

        $json = json_encode($collection);
        $decoded = json_decode($json, true);

        self::assertSame([], $decoded);
    }
}
