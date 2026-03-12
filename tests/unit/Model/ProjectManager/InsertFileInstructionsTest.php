<?php

namespace unit\Model\ProjectManager;

use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Files\MetadataDao;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Logger\MatecatLogger;

/**
 * Unit tests for {@see \Model\ProjectManager\ProjectManager::insertFileInstructions()}.
 *
 * Verifies:
 * - Instructions are inserted when they exist for matching files
 * - No insertion when instructions are empty
 * - No insertion when file has no matching instruction index
 * - Multiple files with instructions
 * - File matching uses original_filename
 */
#[AllowMockObjectsWithoutExpectations]
class InsertFileInstructionsTest extends AbstractTest
{
    private TestableProjectManager $pm;
    /** @var list<array{0: int, 1: string}> */
    private array $insertedInstructions = [];

    protected function setUp(): void
    {
        $features = $this->createStub(FeatureSet::class);
        $features->method('filter')->willReturnArgument(1);

        $dao = $this->createMock(MetadataDao::class);
        $dao->method('insert')
            ->willReturnCallback(function ($projectId, $fid, $key, $value) {
                if ($key === 'instructions') {
                    $this->insertedInstructions[] = [$fid, $value];
                }
            });

        $this->pm = new TestableProjectManager();
        $this->pm->initForTest(
            $this->createStub(MateCatFilter::class),
            $features,
            $dao,
            $this->createStub(MatecatLogger::class),
        );

        $this->insertedInstructions = [];
    }

    // ── Tests ───────────────────────────────────────────────────────

    #[Test]
    public function insertsInstructionForMatchingFile(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['doc1.docx', 'doc2.docx']);
        $this->pm->setProjectStructureValue('instructions', [
            0 => 'Translate carefully',
            1 => 'Review thoroughly',
        ]);

        $totalFilesStructure = [
            100 => ['original_filename' => 'doc1.docx'],
            200 => ['original_filename' => 'doc2.docx'],
        ];

        $this->pm->callInsertFileInstructions($totalFilesStructure);

        $this->assertCount(2, $this->insertedInstructions);
        $this->assertSame([100, 'Translate carefully'], $this->insertedInstructions[0]);
        $this->assertSame([200, 'Review thoroughly'], $this->insertedInstructions[1]);
    }

    #[Test]
    public function skipsFileWithNoInstructions(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['doc1.docx', 'doc2.docx']);
        $this->pm->setProjectStructureValue('instructions', [
            0 => 'Translate carefully',
            // index 1 not set
        ]);

        $totalFilesStructure = [
            100 => ['original_filename' => 'doc1.docx'],
            200 => ['original_filename' => 'doc2.docx'],
        ];

        $this->pm->callInsertFileInstructions($totalFilesStructure);

        $this->assertCount(1, $this->insertedInstructions);
        $this->assertSame(100, $this->insertedInstructions[0][0]);
    }

    #[Test]
    public function skipsEmptyInstructions(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['doc1.docx']);
        $this->pm->setProjectStructureValue('instructions', [
            0 => '',
        ]);

        $totalFilesStructure = [
            100 => ['original_filename' => 'doc1.docx'],
        ];

        $this->pm->callInsertFileInstructions($totalFilesStructure);

        $this->assertCount(0, $this->insertedInstructions);
    }

    #[Test]
    public function noInstructionsKeyDoesNotCrash(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['doc1.docx']);
        // 'instructions' key not set at all

        $totalFilesStructure = [
            100 => ['original_filename' => 'doc1.docx'],
        ];

        $this->pm->callInsertFileInstructions($totalFilesStructure);

        $this->assertCount(0, $this->insertedInstructions);
    }

    #[Test]
    public function emptyFilesStructureDoesNothing(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['doc1.docx']);
        $this->pm->setProjectStructureValue('instructions', [0 => 'Some text']);

        $this->pm->callInsertFileInstructions([]);

        $this->assertCount(0, $this->insertedInstructions);
    }

    #[Test]
    public function fileNotInArrayFilesGetsNoInstructions(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['other.docx']);
        $this->pm->setProjectStructureValue('instructions', [0 => 'Instructions']);

        $totalFilesStructure = [
            100 => ['original_filename' => 'doc1.docx'],
        ];

        $this->pm->callInsertFileInstructions($totalFilesStructure);

        $this->assertCount(0, $this->insertedInstructions);
    }

    #[Test]
    public function matchesByOriginalFilename(): void
    {
        $this->pm->setProjectStructureValue('array_files', ['report.pdf', 'manual.docx']);
        $this->pm->setProjectStructureValue('instructions', [
            0 => 'PDF instructions',
            1 => 'DOCX instructions',
        ]);

        $totalFilesStructure = [
            50 => ['original_filename' => 'manual.docx'],
        ];

        $this->pm->callInsertFileInstructions($totalFilesStructure);

        $this->assertCount(1, $this->insertedInstructions);
        $this->assertSame(50, $this->insertedInstructions[0][0]);
        $this->assertSame('DOCX instructions', $this->insertedInstructions[0][1]);
    }
}
