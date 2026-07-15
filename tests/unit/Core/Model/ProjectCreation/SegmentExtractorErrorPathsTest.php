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
    private function makeExtractor(?ProjectStructure $config = null, bool $returnFalseContent = false): SegmentExtractor
    {
        $config ??= new ProjectStructure();
        $filter = $this->createStub(\Matecat\SubFiltering\MateCatFilter::class);
        $features = $this->createStub(\Model\FeaturesBase\FeatureSet::class);
        $metadataDao = $this->createStub(\Model\Files\MetadataDao::class);
        $mapper = $this->createStub(SegmentMetadataMapper::class);
        $logger = $this->createStub(\Utils\Logger\MatecatLogger::class);

        return new class($config, $filter, $features, $metadataDao, $mapper, $logger, $returnFalseContent) extends SegmentExtractor {
            private bool $returnFalse;

            public function __construct(
                ProjectStructure $config,
                \Matecat\SubFiltering\MateCatFilter $filter,
                \Model\FeaturesBase\FeatureSet $features,
                \Model\Files\MetadataDao $metadataDao,
                SegmentMetadataMapper $mapper,
                \Utils\Logger\MatecatLogger $logger,
                bool $returnFalse
            ) {
                parent::__construct($config, $filter, $features, $metadataDao, $mapper, $logger);
                $this->returnFalse = $returnFalse;
            }

            protected function getXliffFileContent(string $xliffFilePath): false|string
            {
                return $this->returnFalse ? false : 'not-valid-xliff';
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
    public function extractThrowsOnInvalidXliff(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to parse');

        $config = new ProjectStructure();
        $config->id_project = 1;
        $config->source_language = 'en-US';

        $extractor = $this->makeExtractor($config);
        $extractor->extract(1, ['path_cached_xliff' => '/fake.xliff', 'original_filename' => 'test.xliff'], new ProjectStructure());
    }

    #[Test]
    public function extractNotesAndContextsCollectsErrorInsteadOfThrowing(): void
    {
        $projectStructure = new ProjectStructure();
        $extractor        = $this->makeExtractor();

        $trans_unit = [
            'attr'  => ['id' => 'unit-1'],
            'notes' => array_fill(0, 11, ['raw-content' => 'a note']),
        ];

        $method = (new ReflectionClass(SegmentExtractor::class))->getMethod('extractNotesAndContexts');
        $method->setAccessible(true);

        // Must not throw: the error is recorded on the ProjectStructure instead.
        $method->invoke($extractor, $trans_unit, 1, $projectStructure);

        self::assertCount(1, $projectStructure->result['errors']);
        self::assertSame(ProjectCreationError::TOO_MANY_NOTES->value, $projectStructure->result['errors'][0]['code']);
        self::assertStringContainsString('maximum of 10 notes', $projectStructure->result['errors'][0]['message']);
    }
}
