<?php

namespace unit\Model\Segments;

use Model\Segments\SegmentMetadataCollection;
use Model\Segments\SegmentMetadataMapper;
use Model\Segments\SegmentMetadataMarshaller;
use Model\Segments\SegmentMetadataStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class SegmentMetadataMapperTest extends AbstractTest
{
    private SegmentMetadataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new SegmentMetadataMapper();
    }

    #[Test]
    public function testFromTransUnitAttributesReturnsCollection(): void
    {
        $result = $this->mapper->fromTransUnitAttributes([]);

        self::assertInstanceOf(SegmentMetadataCollection::class, $result);
    }

    #[Test]
    public function testFromTransUnitAttributesReturnsEmptyCollectionWhenNoAllowedAttributes(): void
    {
        $attrs = ['id' => 'tu-1', 'translate' => 'yes', 'custom' => 'value'];

        $result = $this->mapper->fromTransUnitAttributes($attrs);

        self::assertCount(0, $result);
        self::assertTrue($result->isEmpty());
    }

    #[Test]
    public function testFromTransUnitAttributesReturnsSizeRestrictionStruct(): void
    {
        $attrs = ['id' => 'tu-1', 'sizeRestriction' => '42'];

        $result = $this->mapper->fromTransUnitAttributes($attrs);

        self::assertCount(1, $result);

        $structs = iterator_to_array($result);
        self::assertInstanceOf(SegmentMetadataStruct::class, $structs[0]);
        self::assertSame('sizeRestriction', $structs[0]->meta_key);
        self::assertSame('42', $structs[0]->meta_value);
    }

    #[Test]
    public function testFromTransUnitAttributesFiltersSizeRestrictionZero(): void
    {
        $attrs = ['id' => 'tu-1', 'sizeRestriction' => '0'];

        $result = $this->mapper->fromTransUnitAttributes($attrs);

        self::assertCount(0, $result);
    }

    #[Test]
    public function testFromTransUnitAttributesReturnsMultipleAllowedAttributes(): void
    {
        $attrs = [
            'id'              => 'tu-1',
            'id_request'      => 'REQ-123',
            'id_content'      => 'CNT-456',
            'sizeRestriction' => '80',
            'translate'       => 'yes',
        ];

        $result = $this->mapper->fromTransUnitAttributes($attrs);

        self::assertCount(3, $result);

        $keys = array_map(fn(SegmentMetadataStruct $s) => $s->meta_key, iterator_to_array($result));
        self::assertContains('id_request', $keys);
        self::assertContains('id_content', $keys);
        self::assertContains('sizeRestriction', $keys);
    }

    #[Test]
    public function testFromTransUnitAttributesReturnsEmptyCollectionForEmptyInput(): void
    {
        $result = $this->mapper->fromTransUnitAttributes([]);

        self::assertCount(0, $result);
        self::assertTrue($result->isEmpty());
    }

    #[Test]
    public function testFromTransUnitAttributesReturnsAllSixKeysWhenPresent(): void
    {
        $attrs = [
            'id'              => 'tu-1',
            'id_request'      => 'REQ-1',
            'id_content'      => 'CNT-1',
            'id_order'        => '5',
            'id_order_group'  => 'GRP-A',
            'screenshot'      => 'http://example.com/shot.png',
            'sizeRestriction' => '120',
        ];

        $result = $this->mapper->fromTransUnitAttributes($attrs);

        self::assertCount(6, $result);
    }

    #[Test]
    public function testFromTransUnitAttributesCastsValuesToString(): void
    {
        $attrs = ['id_order' => 123];

        $result = $this->mapper->fromTransUnitAttributes($attrs);

        self::assertCount(1, $result);

        $structs = iterator_to_array($result);
        self::assertSame('123', $structs[0]->meta_value);
    }

    #[Test]
    public function testFromTransUnitAttributesPreservesKeyValueMapping(): void
    {
        $attrs = ['screenshot' => 'http://example.com/img.png'];

        $result = $this->mapper->fromTransUnitAttributes($attrs);

        self::assertCount(1, $result);

        $structs = iterator_to_array($result);
        self::assertSame('screenshot', $structs[0]->meta_key);
        self::assertSame('http://example.com/img.png', $structs[0]->meta_value);
    }

    #[Test]
    public function testFromTransUnitAttributesCollectionSupportsFindViaCollection(): void
    {
        $attrs = ['sizeRestriction' => '42', 'id_request' => 'REQ-1'];

        $result = $this->mapper->fromTransUnitAttributes($attrs);

        self::assertSame('42', $result->find(SegmentMetadataMarshaller::SIZE_RESTRICTION));
        self::assertSame('REQ-1', $result->find(SegmentMetadataMarshaller::ID_REQUEST));
        self::assertNull($result->find(SegmentMetadataMarshaller::SCREENSHOT));
    }
}
