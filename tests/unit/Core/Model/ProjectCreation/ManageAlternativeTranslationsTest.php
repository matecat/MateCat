<?php

namespace Matecat\Core\Model\ProjectCreation;

use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use Model\ProjectCreation\ProjectStructure;
use Utils\Engines\MyMemory;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Covers SegmentExtractor::manageAlternativeTranslations() — the import of
 * XLIFF <alt-trans> entries into the private TM.
 *
 * Focus of these tests (the recently changed behaviour):
 *  - the AppConfig::$IMPORT_ALT_TRANS_FROM_XLIFF feature flag (off by default);
 *  - the match-quality guard `!isset(...) || (float)... < 50`, which must skip
 *    an alt-trans whose match-quality is absent (the external filters converter
 *    strips it when it is 0% or non-numeric, e.g. "high"/"xhigh") or below 50.
 *
 * The engine is stubbed via TestableSegmentExtractor::setStubEngine(), so the
 * behaviour is observed directly on MyMemory::setMulti(): an entry that survives
 * every guard produces a setMulti() contribution; a skipped entry does not.
 */
class ManageAlternativeTranslationsTest extends AbstractTest
{
    private ProjectStructure $projectStructure;

    /** @var array<string, mixed>|null */
    private ?array $fileAttributes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectStructure = new ProjectStructure([
            'id_project'      => 42,
            'source_language' => 'en-US',
            // one writable private TM key so the "public area" guard passes
            'private_tm_key'  => [['key' => 'test-private-key', 'r' => 1, 'w' => 1]],
        ]);

        $this->fileAttributes = [
            'source-language' => 'en-US',
            'target-language' => 'de-DE',
        ];
    }

    protected function tearDown(): void
    {
        // static flag: reset so the state never leaks into other tests
        AppConfig::$IMPORT_ALT_TRANS_FROM_XLIFF = false;
        parent::tearDown();
    }

    /**
     * @param MyMemory $engine stub engine wired into the extractor
     * @param MateCatFilter|null $filter optional filter override (defaults to a stub)
     */
    private function buildExtractor(MyMemory $engine, ?MateCatFilter $filter = null): TestableSegmentExtractor
    {
        $extractor = new TestableSegmentExtractor(
            $this->projectStructure,
            $filter ?? $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
        $extractor->setStubEngine($engine);

        return $extractor;
    }

    /**
     * A stub engine whose getConfigStruct() returns an empty config array.
     */
    private function stubEngine(): MyMemory
    {
        $engine = $this->createMock(MyMemory::class);
        $engine->method('getConfigStruct')->willReturn([]);

        return $engine;
    }

    /**
     * @param string $matchQuality
     * @return array<string, mixed>
     */
    private function transUnitWithMatchQuality(string $matchQuality): array
    {
        return [
            'source'    => ['raw-content' => 'A system for automated oven configuration.'],
            'alt-trans' => [
                [
                    'attr'   => ['match-quality' => $matchQuality],
                    'source' => 'A system for automated oven configuration.',
                    'target' => 'Ein System zur automatisierten Ofenkonfiguration.',
                ],
            ],
        ];
    }

    /**
     * Feature disabled (the default): the method must return before resolving the
     * engine, so setMulti() is never called even for a valid high-quality alt-trans.
     */
    public function testDisabledByFeatureFlagImportsNothing(): void
    {
        AppConfig::$IMPORT_ALT_TRANS_FROM_XLIFF = false;

        $engine = $this->stubEngine();
        $engine->expects($this->never())->method('setMulti');

        $this->buildExtractor($engine)
            ->callManageAlternativeTranslations($this->transUnitWithMatchQuality('90'), $this->fileAttributes);
    }

    /**
     * Flag on, but the alt-trans has no match-quality attribute (the converter
     * drops it for 0% / non-numeric values): the `!isset` branch must skip it.
     */
    public function testAltTransWithoutMatchQualityIsSkipped(): void
    {
        AppConfig::$IMPORT_ALT_TRANS_FROM_XLIFF = true;

        $engine = $this->stubEngine();
        $engine->expects($this->never())->method('setMulti');

        $transUnit = [
            'source'    => ['raw-content' => 'A system for automated oven configuration.'],
            'alt-trans' => [
                [
                    // no 'match-quality' key at all
                    'attr'   => ['extype' => 'MACHINE-TRANSLATION'],
                    'source' => 'A system for automated oven configuration.',
                    'target' => 'Ein System zur automatisierten Ofenkonfiguration.',
                ],
            ],
        ];

        $this->buildExtractor($engine)
            ->callManageAlternativeTranslations($transUnit, $this->fileAttributes);
    }

    /**
     * Flag on, match-quality present but below the 50 threshold: the `< 50`
     * branch must skip it.
     */
    public function testAltTransBelowThresholdIsSkipped(): void
    {
        AppConfig::$IMPORT_ALT_TRANS_FROM_XLIFF = true;

        $engine = $this->stubEngine();
        $engine->expects($this->never())->method('setMulti');

        $this->buildExtractor($engine)
            ->callManageAlternativeTranslations($this->transUnitWithMatchQuality('30'), $this->fileAttributes);
    }

    /**
     * A bare "0" (PHP empty("0") === true) must also be skipped by the `< 50`
     * branch — the reason the guard uses `!isset || < 50` rather than `!empty`.
     */
    public function testAltTransWithZeroMatchQualityIsSkipped(): void
    {
        AppConfig::$IMPORT_ALT_TRANS_FROM_XLIFF = true;

        $engine = $this->stubEngine();
        $engine->expects($this->never())->method('setMulti');

        $this->buildExtractor($engine)
            ->callManageAlternativeTranslations($this->transUnitWithMatchQuality('0'), $this->fileAttributes);
    }

    /**
     * A match-quality >= 50 passes the guard and is submitted to the TM engine.
     * The contribution must carry the segment/translation (filtered to layer 0)
     * and the match-quality inside its serialized 'prop'.
     */
    public function testAltTransAtOrAboveThresholdIsImported(): void
    {
        AppConfig::$IMPORT_ALT_TRANS_FROM_XLIFF = true;

        $filter = $this->createStub(MateCatFilter::class);
        $filter->method('fromRawXliffToLayer0')->willReturnArgument(0);

        $engine = $this->stubEngine();
        $engine->expects($this->once())
            ->method('setMulti')
            ->with($this->callback(function (array $configsList): bool {
                $this->assertCount(1, $configsList);
                $contribution = $configsList[0];
                $this->assertSame('A system for automated oven configuration.', $contribution['segment']);
                $this->assertSame('Ein System zur automatisierten Ofenkonfiguration.', $contribution['translation']);
                $this->assertSame('{"match-quality":"75"}', $contribution['prop']);

                return true;
            }));

        $this->buildExtractor($engine, $filter)
            ->callManageAlternativeTranslations($this->transUnitWithMatchQuality('75'), $this->fileAttributes);
    }
}
