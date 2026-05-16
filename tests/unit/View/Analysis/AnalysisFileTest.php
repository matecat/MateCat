<?php

declare(strict_types=1);

namespace Tests\Unit\View\Analysis;

use Model\Analysis\Constants\StandardMatchTypeNamesConstants;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use View\API\App\Json\Analysis\AnalysisFile;

class AnalysisFileTest extends AbstractTest
{
    private function makeFile(int $id = 1, ?string $idFilePart = null, array $metadata = []): AnalysisFile
    {
        return new AnalysisFile(
            $id,
            $idFilePart,
            'test_file.xliff',
            'test_file_original.docx',
            new StandardMatchTypeNamesConstants(),
            $metadata
        );
    }

    #[Test]
    public function constructorSetsIdAndName(): void
    {
        $file = $this->makeFile(42);

        self::assertSame(42, $file->getId());
        self::assertSame('test_file.xliff', $file->getName());
    }

    #[Test]
    public function jsonSerializeReturnsExpectedStructure(): void
    {
        $file = $this->makeFile(7, '3');
        $json = $file->jsonSerialize();

        self::assertSame(7, $json['id']);
        self::assertSame('3', $json['id_file_part']);
        self::assertSame('test_file.xliff', $json['name']);
        self::assertSame('test_file_original.docx', $json['original_name']);
        self::assertSame(0, $json['total_raw']);
        self::assertSame(0.0, $json['total_equivalent']);
        self::assertIsArray($json['matches']);
        self::assertCount(13, $json['matches']);
        self::assertSame([], $json['metadata']);
    }

    #[Test]
    public function getMatchReturnsCorrectMatchType(): void
    {
        $file = $this->makeFile();
        $match = $file->getMatch(StandardMatchTypeNamesConstants::_ICE);

        self::assertSame('ice', $match->name());
    }

    #[Test]
    public function incrementRawAccumulates(): void
    {
        $file = $this->makeFile();

        $file->incrementRaw(100);
        $file->incrementRaw(50);

        $json = $file->jsonSerialize();
        self::assertSame(150, $json['total_raw']);
    }

    #[Test]
    public function incrementEquivalentAccumulates(): void
    {
        $file = $this->makeFile();

        $file->incrementEquivalent(10.5);
        $file->incrementEquivalent(20.3);

        $json = $file->jsonSerialize();
        self::assertSame(31.0, $json['total_equivalent']);
    }

    #[Test]
    public function metadataFromObjectsIsRenderedCorrectly(): void
    {
        $meta1 = (object)['key' => 'author', 'value' => 'John'];
        $meta2 = (object)['key' => 'version', 'value' => 3];

        $file = $this->makeFile(1, null, [$meta1, $meta2]);
        $json = $file->jsonSerialize();

        self::assertCount(2, $json['metadata']);
        self::assertSame(['key' => 'author', 'value' => 'John'], $json['metadata'][0]->jsonSerialize());
        self::assertSame(['key' => 'version', 'value' => '3'], $json['metadata'][1]->jsonSerialize());
    }

    #[Test]
    public function metadataWithArrayValueIsJsonEncoded(): void
    {
        $meta = (object)['key' => 'config', 'value' => ['a' => 1, 'b' => 2]];

        $file = $this->makeFile(1, null, [$meta]);
        $json = $file->jsonSerialize();

        self::assertCount(1, $json['metadata']);
        self::assertSame('config', $json['metadata'][0]->jsonSerialize()['key']);
        self::assertSame('{"a":1,"b":2}', $json['metadata'][0]->jsonSerialize()['value']);
    }

    #[Test]
    public function metadataWithMissingKeyOrValueIsSkipped(): void
    {
        $meta1 = (object)['key' => 'valid', 'value' => 'yes'];
        $meta2 = (object)['key' => 'no_value'];
        $meta3 = (object)['value' => 'no_key'];

        $file = $this->makeFile(1, null, [$meta1, $meta2, $meta3]);
        $json = $file->jsonSerialize();

        self::assertCount(1, $json['metadata']);
    }

    #[Test]
    public function matchesAreIndexedSequentiallyInJson(): void
    {
        $file = $this->makeFile();
        $json = $file->jsonSerialize();

        self::assertSame(0, array_key_first($json['matches']));
        self::assertSame(12, array_key_last($json['matches']));
    }
}
