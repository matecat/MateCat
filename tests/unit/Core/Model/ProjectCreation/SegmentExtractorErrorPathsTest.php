<?php


namespace Matecat\Core\Model\ProjectCreation;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\ProjectCreation\ProjectCreationError;
use Model\ProjectCreation\ProjectStructure;
use Model\ProjectCreation\SegmentExtractor;
use Model\Segments\SegmentMetadataMapper;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;

class SegmentExtractorErrorPathsTest extends AbstractTest
{
    private function makeExtractor(
        ?ProjectStructure $config = null,
        bool $returnFalseContent = false,
        string $content = 'not-valid-xliff'
    ): SegmentExtractor {
        $config ??= new ProjectStructure();
        $filter = $this->createStub(\Matecat\SubFiltering\MateCatFilter::class);
        $features = $this->createStub(\Model\FeaturesBase\FeatureSet::class);
        $metadataDao = $this->createStub(\Model\Files\MetadataDao::class);
        $mapper = $this->createStub(SegmentMetadataMapper::class);
        $logger = $this->createStub(\Utils\Logger\MatecatLogger::class);

        return new class($config, $filter, $features, $metadataDao, $mapper, $logger, $returnFalseContent, $content) extends SegmentExtractor {
            private bool $returnFalse;
            private string $content;

            public function __construct(
                ProjectStructure $config,
                \Matecat\SubFiltering\MateCatFilter $filter,
                \Model\FeaturesBase\FeatureSet $features,
                \Model\Files\MetadataDao $metadataDao,
                SegmentMetadataMapper $mapper,
                \Utils\Logger\MatecatLogger $logger,
                bool $returnFalse,
                string $content
            ) {
                parent::__construct($config, $filter, $features, $metadataDao, $mapper, obtainTestDatabase(), $logger);
                $this->returnFalse = $returnFalse;
                $this->content = $content;
            }

            protected function getXliffFileContent(string $xliffFilePath): false|string
            {
                return $this->returnFalse ? false : $this->content;
            }
        };
    }

    #[Test]
    public function extractThrowsWhenIdProjectNull(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('idProject and sourceLanguage must be set');

        $config = new ProjectStructure();
        $config->id_project = null;
        $config->source_language = null;

        $extractor = $this->makeExtractor($config);
        $extractor->extract(1, ['path_cached_xliff' => '/fake'], new ProjectStructure());
    }

    #[Test]
    public function extractThrowsWhenXliffFileUnreadable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to read XLIFF file');

        $config = new ProjectStructure();
        $config->id_project = 1;
        $config->source_language = 'en-US';

        $extractor = $this->makeExtractor($config, true);
        $extractor->extract(1, ['path_cached_xliff' => '/fake.xliff'], new ProjectStructure());
    }

    #[Test]
    public function extractThrowsAnXliffImportErrorOnInvalidXliff(): void
    {
        $config = new ProjectStructure();
        $config->id_project = 1;
        $config->source_language = 'en-US';

        $extractor = $this->makeExtractor($config);

        try {
            $extractor->extract(1, ['path_cached_xliff' => '/fake.xliff', 'original_filename' => 'test.xliff'], new ProjectStructure());
            self::fail('Expected Exception');
        } catch (Exception $e) {
            self::assertSame(ProjectCreationError::XLIFF_IMPORT_ERROR->value, $e->getCode());
            self::assertSame('XLIFF import error: Failed to parse test.xliff', $e->getMessage());
        }
    }

    #[Test]
    public function extractKeepsTheParserMessageWhenItRejectsTheXliffWithCode400(): void
    {
        $config = new ProjectStructure();
        $config->id_project = 1;
        $config->source_language = 'en-US';

        // Two trans-units sharing an id in the same <file>: the parser refuses it with code 400
        $xliff = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">'
            . '<file original="dup.txt" source-language="en-US" target-language="it-IT" datatype="plaintext"><body>'
            . '<trans-unit id="1"><source>One</source></trans-unit>'
            . '<trans-unit id="1"><source>Two</source></trans-unit>'
            . '</body></file></xliff>';

        $extractor = $this->makeExtractor($config, false, $xliff);

        try {
            $extractor->extract(1, ['path_cached_xliff' => '/fake.xliff', 'original_filename' => 'dup.xliff'], new ProjectStructure());
            self::fail('Expected Exception');
        } catch (Exception $e) {
            self::assertSame(ProjectCreationError::INVALID_XLIFF_PARAMETERS->value, $e->getCode());
            self::assertSame('Invalid trans-unit id, duplicate found. in Failed to parse dup.xliff', $e->getMessage());
            self::assertNotNull($e->getPrevious());
            self::assertSame(ProjectCreationError::INVALID_XLIFF_PARAMETERS->value, $e->getPrevious()->getCode());
            self::assertSame('Invalid trans-unit id, duplicate found.', $e->getPrevious()->getMessage());
        }
    }

    #[Test]
    public function addNotesThrowsWhenATransUnitHasTooManyNotes(): void
    {
        $projectStructure = new ProjectStructure();
        $extractor        = $this->makeExtractor();

        $trans_unit = [
            'attr'  => ['id' => 'unit-1'],
            'notes' => array_fill(0, 11, ['raw-content' => 'a note']),
        ];

        $method = (new ReflectionClass(SegmentExtractor::class))->getMethod('addNotesToProjectStructure');

        try {
            $method->invoke($extractor, $trans_unit, 'unit-1', $projectStructure);
            self::fail('Expected Exception');
        } catch (Exception $e) {
            self::assertSame(ProjectCreationError::TOO_MANY_NOTES->value, $e->getCode());
            self::assertSame('File upload failed: a segment can have a maximum of 10 notes.', $e->getMessage());
        }

        // createProject() records the one error, not the extractor
        self::assertEmpty($projectStructure->result['errors']);
    }

    #[Test]
    public function addNotesThrowsWhenANoteIsTooLarge(): void
    {
        $projectStructure = new ProjectStructure();
        $extractor        = $this->makeExtractor();

        $ref     = new ReflectionClass(SegmentExtractor::class);
        $maxSize = $ref->getConstant('SEGMENT_NOTES_MAX_SIZE');

        $trans_unit = [
            'attr'  => ['id' => 'unit-1'],
            'notes' => [['raw-content' => str_repeat('a', $maxSize + 1)]],
        ];

        try {
            $ref->getMethod('addNotesToProjectStructure')->invoke($extractor, $trans_unit, 'unit-1', $projectStructure);
            self::fail('Expected Exception');
        } catch (Exception $e) {
            self::assertSame(ProjectCreationError::SEGMENT_NOTE_TOO_LARGE->value, $e->getCode());
            self::assertSame('File upload failed: a segment note can be at most 65535 bytes.', $e->getMessage());
        }

        self::assertEmpty($projectStructure->result['errors']);
    }

    #[Test]
    public function addNotesAcceptsANoteOfExactlyTheMaximumSize(): void
    {
        $projectStructure = new ProjectStructure();
        $extractor        = $this->makeExtractor();

        $ref     = new ReflectionClass(SegmentExtractor::class);
        $maxSize = $ref->getConstant('SEGMENT_NOTES_MAX_SIZE');
        $note    = str_repeat('a', $maxSize);

        $trans_unit = [
            'attr'  => ['id' => 'unit-1'],
            'notes' => [['raw-content' => $note]],
        ];

        $ref->getMethod('addNotesToProjectStructure')->invoke($extractor, $trans_unit, 'unit-1', $projectStructure);

        self::assertSame([$note], $projectStructure->notes['unit-1']['entries']);
    }
}
