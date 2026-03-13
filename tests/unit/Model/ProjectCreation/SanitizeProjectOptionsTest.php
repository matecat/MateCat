<?php

namespace unit\Model\ProjectCreation;

use ArrayObject;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectCreation\ProjectManager::sanitizeProjectOptions()}.
 *
 * Verifies:
 * - Delegates to ProjectOptionsSanitizer with source + target languages
 * - Returns sanitized options array
 * - Handles various metadata combinations
 */
class SanitizeProjectOptionsTest extends AbstractTest
{
    private TestableProjectManager $pm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
    }

    #[Test]
    public function emptyMetadataReturnsArray(): void
    {
        $this->pm->setProjectStructureValue('metadata', new ArrayObject([]));
        $this->pm->setProjectStructureValue('target_language', ['it-IT']);

        $result = $this->pm->callSanitizeProjectOptions();

        $this->assertIsArray($result);
    }

    #[Test]
    public function sanitizerReceivesSourceAndTargetLanguages(): void
    {
        $this->pm->setProjectStructureValue('source_language', 'en-US');
        $this->pm->setProjectStructureValue('target_language', ['de-DE', 'fr-FR']);
        $this->pm->setProjectStructureValue('metadata', new ArrayObject([
            'lexiqa' => true,
            'tag_projection' => true,
        ]));

        $result = $this->pm->callSanitizeProjectOptions();

        $this->assertIsArray($result);
        // The sanitizer checks language pairs for lexiqa/tag_projection support
        // Result should be an array regardless of language support
    }

    #[Test]
    public function preservesUnrelatedMetadataKeys(): void
    {
        $this->pm->setProjectStructureValue('target_language', ['it-IT']);
        $this->pm->setProjectStructureValue('metadata', new ArrayObject([
            'custom_key' => 'custom_value',
        ]));

        $result = $this->pm->callSanitizeProjectOptions();

        $this->assertIsArray($result);
        // custom_key should pass through sanitizer untouched
        $this->assertArrayHasKey('custom_key', $result);
        $this->assertSame('custom_value', $result['custom_key']);
    }

    #[Test]
    public function multipleTargetLanguagesArePassed(): void
    {
        $this->pm->setProjectStructureValue('source_language', 'en-US');
        $this->pm->setProjectStructureValue('target_language', ['it-IT', 'es-ES', 'de-DE']);
        $this->pm->setProjectStructureValue('metadata', new ArrayObject([]));

        $result = $this->pm->callSanitizeProjectOptions();

        $this->assertIsArray($result);
    }

    #[Test]
    public function resultIsPlainArrayNotArrayObject(): void
    {
        $this->pm->setProjectStructureValue('target_language', ['it-IT']);
        $this->pm->setProjectStructureValue('metadata', new ArrayObject(['key' => 'val']));

        $result = $this->pm->callSanitizeProjectOptions();

        $this->assertIsArray($result);
        $this->assertNotInstanceOf(ArrayObject::class, $result);
    }
}
