<?php

namespace unit\Model\ProjectManager;

use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectManager\ProjectManager::sortFilesWithTmxFirst()}.
 *
 * Verifies that:
 * - TMX and glossary files are moved to the front of the array
 * - The first TMX filename is returned
 * - Non-memory files preserve their order at the end
 * - When multiple TMX files exist, empty string is returned
 * - array_files_meta is reordered in sync with array_files
 */
class SortFilesWithTmxFirstTest extends AbstractTest
{
    private TestableProjectManager $pm;

    protected function setUp(): void
    {
        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $this->createStub(FeatureSet::class),
            $this->createStub(MetadataDao::class),
            $this->createStub(MatecatLogger::class),
        );
    }

    // ── Helper ──────────────────────────────────────────────────────

    private function makeMeta(bool $getMemoryType, bool $isTMX, bool $mustBeConverted = false): array
    {
        return [
            'getMemoryType'    => $getMemoryType,
            'isTMX'            => $isTMX,
            'mustBeConverted'  => $mustBeConverted,
        ];
    }

    // ── Tests ───────────────────────────────────────────────────────

    #[Test]
    public function noFilesReturnsEmptyString(): void
    {
        $this->pm->setProjectStructureValue('array_files', []);
        $this->pm->setProjectStructureValue('array_files_meta', []);

        $result = $this->pm->callSortFilesWithTmxFirst();

        $this->assertSame('', $result);
        $this->assertSame([], (array) $this->pm->getTestProjectStructure()['array_files']);
    }

    #[Test]
    public function onlyRegularFilesPreserveOrder(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['a.docx', 'b.docx', 'c.docx']);
        $this->pm->setProjectStructureValue('array_files_meta', [
            $this->makeMeta(false, false),
            $this->makeMeta(false, false),
            $this->makeMeta(false, false),
        ]);

        $result = $this->pm->callSortFilesWithTmxFirst();

        $this->assertSame('', $result);
        $this->assertSame(['a.docx', 'b.docx', 'c.docx'], (array) $this->pm->getTestProjectStructure()['array_files']);
    }

    #[Test]
    public function singleTmxIsMovedToFrontAndReturned(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['a.docx', 'memory.tmx', 'b.docx']);
        $this->pm->setProjectStructureValue('array_files_meta', [
            $this->makeMeta(false, false),
            $this->makeMeta(true, true),
            $this->makeMeta(false, false),
        ]);

        $result = $this->pm->callSortFilesWithTmxFirst();

        $this->assertSame('memory.tmx', $result);

        $files = array_values((array) $this->pm->getTestProjectStructure()['array_files']);
        $this->assertSame('memory.tmx', $files[0]);
    }

    #[Test]
    public function multipleTmxFilesReturnEmptyString(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['first.tmx', 'a.docx', 'second.tmx']);
        $this->pm->setProjectStructureValue('array_files_meta', [
            $this->makeMeta(true, true),
            $this->makeMeta(false, false),
            $this->makeMeta(true, true),
        ]);

        $result = $this->pm->callSortFilesWithTmxFirst();

        // When multiple TMX files exist, firstTMXFileName is set to null then coalesced to ''
        $this->assertSame('', $result);
    }

    #[Test]
    public function glossaryFileIsMovedToFrontButNotReturnedAsTmx(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['a.docx', 'glossary.csv']);
        $this->pm->setProjectStructureValue('array_files_meta', [
            $this->makeMeta(false, false),
            $this->makeMeta(true, false),  // getMemoryType = true, isTMX = false (glossary)
        ]);

        $result = $this->pm->callSortFilesWithTmxFirst();

        // No TMX file, so return ''
        $this->assertSame('', $result);

        // But glossary should be first
        $files = array_values((array) $this->pm->getTestProjectStructure()['array_files']);
        $this->assertSame('glossary.csv', $files[0]);
    }

    #[Test]
    public function metaArrayIsSortedInSyncWithFileNames(): void
    {
        $metaRegular = $this->makeMeta(false, false);
        $metaTmx     = $this->makeMeta(true, true);
        $metaGloss   = $this->makeMeta(true, false);

        $this->pm->setProjectStructureValue('array_files', ['a.docx', 'memory.tmx', 'glossary.csv', 'b.docx']);
        $this->pm->setProjectStructureValue('array_files_meta', [
            $metaRegular, $metaTmx, $metaGloss, $metaRegular,
        ]);

        $this->pm->callSortFilesWithTmxFirst();

        $ps    = $this->pm->getTestProjectStructure();
        $files = array_values((array) $ps['array_files']);
        $metas = array_values((array) $ps['array_files_meta']);

        // Memory files should be at front (order among them: glossary pushed first, then tmx pushed first)
        // Regular files keep order at end
        $this->assertCount(4, $files);
        $this->assertCount(4, $metas);

        // All memory-type files should be before non-memory files
        $memoryIdx = [];
        $regularIdx = [];
        foreach ($metas as $i => $m) {
            if ($m['getMemoryType']) {
                $memoryIdx[] = $i;
            } else {
                $regularIdx[] = $i;
            }
        }

        // Memory files come before regular ones
        if (!empty($memoryIdx) && !empty($regularIdx)) {
            $this->assertLessThan(min($regularIdx), max($memoryIdx));
        }
    }

    #[Test]
    public function tmxAndGlossaryBothMovedToFrontTmxReturned(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['a.docx', 'glossary.csv', 'memory.tmx']);
        $this->pm->setProjectStructureValue('array_files_meta', [
            $this->makeMeta(false, false),
            $this->makeMeta(true, false),
            $this->makeMeta(true, true),
        ]);

        $result = $this->pm->callSortFilesWithTmxFirst();

        $this->assertSame('memory.tmx', $result);

        // Both memory files should be at front
        $files = array_values((array) $this->pm->getTestProjectStructure()['array_files']);
        $this->assertSame('a.docx', end($files));
    }

    #[Test]
    public function singleFileProjectWithTmxReturnsFilename(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['only.tmx']);
        $this->pm->setProjectStructureValue('array_files_meta', [
            $this->makeMeta(true, true),
        ]);

        $result = $this->pm->callSortFilesWithTmxFirst();

        $this->assertSame('only.tmx', $result);
    }

    #[Test]
    public function singleRegularFileReturnsEmptyString(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['test.docx']);
        $this->pm->setProjectStructureValue('array_files_meta', [
            $this->makeMeta(false, false),
        ]);

        $result = $this->pm->callSortFilesWithTmxFirst();

        $this->assertSame('', $result);
    }
}
