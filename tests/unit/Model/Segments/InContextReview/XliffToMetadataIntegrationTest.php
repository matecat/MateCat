<?php

namespace unit\Model\Segments\InContextReview;

use Matecat\XliffParser\XliffParser;
use Model\Segments\SegmentMetadataCollection;
use Model\Segments\SegmentMetadataMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

/**
 * Integration test: parse real XLIFF files through XliffParser → SegmentMetadataMapper
 * and verify the resulting SegmentMetadataStruct arrays are correct.
 *
 * Exercises the full pipeline: XLIFF bytes → parser → trans-unit attrs → marshaller whitelist → typed structs.
 */
class XliffToMetadataIntegrationTest extends AbstractTest
{
    private const string FIXTURES_DIR = TEST_DIR . '/resources/files/in-context-review/';

    private SegmentMetadataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new SegmentMetadataMapper();
    }

    private function parseXliff(string $filename): array
    {
        $content = file_get_contents(self::FIXTURES_DIR . $filename);
        return (new XliffParser())->xliffToArray($content);
    }

    /**
     * Extract metadata collections from parsed XLIFF, keyed by trans-unit id.
     *
     * @return array<string, SegmentMetadataCollection>
     */
    private function extractAllMetadata(array $xliff): array
    {
        $result = [];

        foreach ($xliff['files'] as $file) {
            foreach ($file['trans-units'] as $transUnit) {
                $collection    = $this->mapper->fromTransUnitAttributes($transUnit['attr'] ?? []);
                $id            = $transUnit['attr']['id'] ?? 'unknown';
                $result[ $id ] = $collection;
            }
        }

        return $result;
    }

    /**
     * Convert a SegmentMetadataCollection to a sorted associative array for comparison.
     *
     * @return array<string, string>
     */
    private function collectionToArray(SegmentMetadataCollection $collection): array
    {
        $arr = [];

        foreach ($collection as $struct) {
            $arr[ $struct->meta_key ] = $struct->meta_value;
        }

        ksort($arr);

        return $arr;
    }

    public static function xliffVersionProvider(): array
    {
        return [
            'XLIFF 1.2' => ['test-context-mapping.xlf'],
            'XLIFF 2.0' => ['test-context-mapping-2.0.xlf'],
        ];
    }

    #[Test]
    public function testBothXliffVersionsProduceIdenticalMetadata(): void
    {
        $v1 = $this->extractAllMetadata($this->parseXliff('test-context-mapping.xlf'));
        $v2 = $this->extractAllMetadata($this->parseXliff('test-context-mapping-2.0.xlf'));

        self::assertSame(
            array_keys($v1),
            array_keys($v2),
            'Both XLIFF versions must produce the same trans-unit IDs'
        );

        foreach ($v1 as $id => $collection) {
            self::assertSame(
                $this->collectionToArray($collection),
                $this->collectionToArray($v2[ $id ]),
                "Metadata mismatch for trans-unit '$id' between XLIFF 1.2 and 2.0"
            );
        }
    }

    #[Test]
    #[DataProvider('xliffVersionProvider')]
    public function testMultiFileXliffParsedCorrectly(string $filename): void
    {
        $xliff = $this->parseXliff($filename);

        self::assertCount(2, $xliff['files'], 'XLIFF must contain 2 <file> elements');
        self::assertCount(12, $xliff['files'][1]['trans-units'], 'File 1 must have 12 trans-units');
        self::assertCount(6, $xliff['files'][2]['trans-units'], 'File 2 must have 6 trans-units');
    }

    #[Test]
    #[DataProvider('xliffVersionProvider')]
    public function testEveryTransUnitHasResnameAndRestype(string $filename): void
    {
        $metadata = $this->extractAllMetadata($this->parseXliff($filename));

        foreach ($metadata as $tuId => $collection) {
            $arr = $this->collectionToArray($collection);
            self::assertArrayHasKey('resname', $arr, "Trans-unit '$tuId' must have resname");
            self::assertArrayHasKey('restype', $arr, "Trans-unit '$tuId' must have restype");
        }
    }

    #[Test]
    #[DataProvider('xliffVersionProvider')]
    public function testAllFiveRestypeStrategiesExtracted(string $filename): void
    {
        $metadata = $this->extractAllMetadata($this->parseXliff($filename));

        $expectedRestypes = [
            't1' => 'x-path',
            't5' => 'x-client_nodepath',
            't7' => 'x-tag-id',
            't8' => 'x-css_class',
            't9' => 'x-attribute_name_value',
        ];

        foreach ($expectedRestypes as $tuId => $expectedRestype) {
            $arr = $this->collectionToArray($metadata[ $tuId ]);
            self::assertSame(
                $expectedRestype,
                $arr['restype'] ?? null,
                "Trans-unit '$tuId' should have restype='$expectedRestype'"
            );
        }
    }

    #[Test]
    #[DataProvider('xliffVersionProvider')]
    public function testXClientNameExtractedWithClientNodepath(string $filename): void
    {
        $metadata = $this->extractAllMetadata($this->parseXliff($filename));

        foreach (['t5', 't6'] as $tuId) {
            $arr = $this->collectionToArray($metadata[ $tuId ]);
            self::assertSame('x-client_nodepath', $arr['restype'], "Trans-unit '$tuId' restype");
            self::assertSame('dot-notation', $arr['x-client-name'], "Trans-unit '$tuId' x-client-name");
            self::assertCount(
                3,
                iterator_to_array($metadata[ $tuId ]),
                "Trans-unit '$tuId' must have exactly 3 metadata entries (resname + restype + x-client-name)"
            );
        }
    }

    #[Test]
    #[DataProvider('xliffVersionProvider')]
    public function testScreenshotAttributeExtracted(string $filename): void
    {
        $metadata = $this->extractAllMetadata($this->parseXliff($filename));

        $arr = $this->collectionToArray($metadata['t18']);
        self::assertSame(
            'https://example.com/screenshots/footer-legal.png',
            $arr['screenshot'],
            "Trans-unit 't18' screenshot URL"
        );
        self::assertCount(
            3,
            iterator_to_array($metadata['t18']),
            "Trans-unit 't18' must have exactly 3 metadata entries (resname + restype + screenshot)"
        );
    }

    #[Test]
    #[DataProvider('xliffVersionProvider')]
    public function testNonMetadataAttributesFilteredOut(string $filename): void
    {
        $metadata = $this->extractAllMetadata($this->parseXliff($filename));

        $forbiddenKeys = ['id', 'xml:lang', 'approved', 'translate'];

        foreach ($metadata as $tuId => $collection) {
            $arr = $this->collectionToArray($collection);

            foreach ($forbiddenKeys as $key) {
                self::assertArrayNotHasKey(
                    $key,
                    $arr,
                    "Trans-unit '$tuId': '$key' must be filtered out by the marshaller"
                );
            }
        }
    }
}
