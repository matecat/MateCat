<?php

namespace Matecat\Core\Model\ProjectCreation;

use Matecat\SubFiltering\Enum\InjectableFiltersTags;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use PHPUnit\Framework\Attributes\Test;
use Utils\Logger\MatecatLogger;

/**
 * The fast-analysis payload is the Layer 1 text every MT/TM call in the analysis
 * eventually sees: ProjectManager converts it once at creation time and persists it,
 * FastAnalysis reads it back and TMAnalysisWorker forwards it to the engines.
 *
 * A source segment carrying valid complex ICU syntax must therefore be converted with
 * the ICU-compliant handler set only, exactly as the editor does when it renders the
 * same segment. Segments without complex ICU keep the project handlers, so a bare
 * {placeholder} is still wrapped in a PH tag.
 */
class FastAnalysisIcuSubfilteringTest extends AbstractTest
{
    private const SOURCE_LANG = 'en-US';

    /**
     * Complex ICU plural block plus a simple argument. The simple argument is the
     * discriminating part: the single-curly handler cannot match the plural block
     * (its regex rejects spaces and nesting) but it does match {SEARCH_TERM}.
     */
    private const ICU_SEGMENT = 'You have {NUM_RESULTS, plural, one {1 result} other {# results}} for "{SEARCH_TERM}".';

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * @param array<string> $handlers
     */
    private function projectManager(bool $icuEnabled, array $handlers): TestableProjectManager
    {
        $filter = MateCatFilter::getInstance(
            null,
            self::SOURCE_LANG,
            (string)json_encode(['it-IT']),
            [],
            $handlers
        );

        $pm = new TestableProjectManager();
        $pm->initForTest(
            $filter,
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class)
        );

        $pm->setProjectStructureValue('source_language', self::SOURCE_LANG);
        $pm->setProjectStructureValue('target_language', ['it-IT']);
        $pm->setProjectStructureValue('subfiltering_handlers', (string)json_encode($handlers));
        $pm->setProjectStructureValue('metadata', [
            ProjectsMetadataMarshaller::ICU_ENABLED->value => $icuEnabled,
        ]);

        return $pm;
    }

    /**
     * @return array<string>
     */
    private function handlersWithSingleCurly(): array
    {
        return [
            InjectableFiltersTags::single_curly->value,
            InjectableFiltersTags::markup->value,
            InjectableFiltersTags::sprintf->value,
        ];
    }

    #[Test]
    public function complexIcuSourceIsNotSubfilteredWithNonIcuCompliantHandlers(): void
    {
        $pm = $this->projectManager(true, $this->handlersWithSingleCurly());

        $layer1 = $pm->callSubfilterForAnalysis(self::ICU_SEGMENT);

        $this->assertSame(
            self::ICU_SEGMENT,
            $layer1,
            'A valid complex ICU source must reach the engines as ICU, not as PH tags'
        );
        $this->assertStringNotContainsString('<ph ', $layer1);
    }

    #[Test]
    public function simpleCurlyWithoutComplexIcuIsStillWrappedInPh(): void
    {
        $pm = $this->projectManager(true, $this->handlersWithSingleCurly());

        $layer1 = $pm->callSubfilterForAnalysis('Hello {NAME}, welcome.');

        $this->assertStringContainsString('<ph ', $layer1);
        $this->assertStringContainsString('ctype="x-curly-brackets"', $layer1);
        $this->assertStringNotContainsString('{NAME}', $layer1);
    }

    #[Test]
    public function icuDisabledLeavesTheProjectHandlersInPlace(): void
    {
        $pm = $this->projectManager(false, $this->handlersWithSingleCurly());

        $layer1 = $pm->callSubfilterForAnalysis(self::ICU_SEGMENT);

        // The plural block is skipped by the handler regex, the simple argument is not.
        $this->assertStringContainsString('{NUM_RESULTS, plural,', $layer1);
        $this->assertStringContainsString('ctype="x-curly-brackets"', $layer1);
        $this->assertStringNotContainsString('{SEARCH_TERM}', $layer1);
    }

    #[Test]
    public function invalidComplexIcuFallsBackToTheProjectHandlers(): void
    {
        $pm = $this->projectManager(true, $this->handlersWithSingleCurly());

        // Unbalanced braces: complex syntax is present but the pattern does not parse,
        // so it is not an ICU segment and must keep the ordinary project handlers.
        $layer1 = $pm->callSubfilterForAnalysis('Broken {NUM, plural, one {x} for "{TOKEN}".');

        $this->assertStringContainsString('ctype="x-curly-brackets"', $layer1);
        $this->assertStringNotContainsString('{TOKEN}', $layer1);
    }

    #[Test]
    public function withoutSingleCurlyTheIcuSegmentIsUntouchedEitherWay(): void
    {
        $handlers = [InjectableFiltersTags::markup->value, InjectableFiltersTags::sprintf->value];

        $icuOn = $this->projectManager(true, $handlers)->callSubfilterForAnalysis(self::ICU_SEGMENT);
        $icuOff = $this->projectManager(false, $handlers)->callSubfilterForAnalysis(self::ICU_SEGMENT);

        $this->assertSame(self::ICU_SEGMENT, $icuOn);
        $this->assertSame($icuOn, $icuOff);
    }
}
