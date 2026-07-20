<?php

namespace Matecat\Core\Plugins\Features\ReviewExtended;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use Model\LQA\EntryDao;
use Model\LQA\EntryStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Model\Projects\ProjectDao;
use Plugins\Features\ReviewExtended\ChunkReviewModel;
use Plugins\Features\ReviewExtended\TranslationIssueModel;
use Plugins\Features\TranslationVersions\Model\TranslationVersionDao;
use Plugins\Features\TranslationVersions\Model\TranslationVersionStruct;

class TestableTranslationIssueModel extends TranslationIssueModel
{
    public ?ChunkReviewModel $mockChunkReviewModel = null;

    protected function createChunkReviewModel(ChunkReviewStruct $chunkReview): ChunkReviewModel
    {
        if ($this->mockChunkReviewModel !== null) {
            return $this->mockChunkReviewModel;
        }

        return parent::createChunkReviewModel($chunkReview);
    }
}

#[AllowMockObjectsWithoutExpectations]
class TranslationIssueModelTest extends AbstractTest
{
    private ChunkReviewDao&MockObject $chunkReviewDao;
    private EntryDao&MockObject $entryDao;
    private TranslationVersionDao&MockObject $translationVersionDao;
    private ChunkReviewModel&MockObject $chunkReviewModel;
    private ChunkReviewStruct $chunkReview;
    private JobStruct $chunk;
    private ProjectStruct $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chunkReviewDao = $this->createMock(ChunkReviewDao::class);
        [$dbStub] = $this->createDatabaseMock();
        $this->chunkReviewDao->method('getDatabaseHandler')->willReturn($dbStub);
        $this->entryDao = $this->createMock(EntryDao::class);
        $this->translationVersionDao = $this->createMock(TranslationVersionDao::class);
        $this->chunkReviewModel = $this->createMock(ChunkReviewModel::class);

        $this->project = $this->createStub(ProjectStruct::class);
        $this->chunk = $this->createStub(JobStruct::class);
        $this->chunk->id = 100;
        $this->chunk->password = 'pw100';
        $this->chunk->method('getProject')->willReturn($this->project);

        $this->chunkReview = $this->createStub(ChunkReviewStruct::class);
        $this->chunkReview->method('getChunk')->willReturn($this->chunk);
    }

    private function makeModel(EntryStruct $issue): TestableTranslationIssueModel
    {
        $this->chunkReviewDao
            ->method('findByReviewPasswordAndJobId')
            ->willReturn($this->chunkReview);

        $model = new TestableTranslationIssueModel(
            100,
            'review_pw',
            $issue,
            $this->chunkReviewDao,
            $this->entryDao,
            $this->translationVersionDao,
            $this->createMock(ProjectDao::class)
        );

        $model->mockChunkReviewModel = $this->chunkReviewModel;

        return $model;
    }

    private function makeIssue(array $overrides = []): EntryStruct&MockObject
    {
        $issue = $this->createMock(EntryStruct::class);
        $issue->id_job = $overrides['id_job'] ?? 100;
        $issue->id_segment = $overrides['id_segment'] ?? 1;
        $issue->penalty_points = $overrides['penalty_points'] ?? 5.0;
        $issue->source_page = $overrides['source_page'] ?? 1;
        $issue->translation_version = $overrides['translation_version'] ?? 1;
        $issue->start_node = $overrides['start_node'] ?? null;
        $issue->end_node = $overrides['end_node'] ?? null;
        $issue->start_offset = $overrides['start_offset'] ?? 0;
        $issue->end_offset = $overrides['end_offset'] ?? 5;

        return $issue;
    }

    // ─── constructor ─────────────────────────────────────────────

    #[Test]
    public function constructorThrowsWhenChunkReviewNotFound(): void
    {
        $this->chunkReviewDao
            ->method('findByReviewPasswordAndJobId')
            ->willReturn(null);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('ChunkReview not found for job 100');

        new TestableTranslationIssueModel(
            100,
            'bad_pw',
            $this->makeIssue(),
            $this->chunkReviewDao,
            $this->entryDao,
            $this->translationVersionDao,
            $this->createMock(ProjectDao::class)
        );
    }

    #[Test]
    public function constructorSetsPropertiesWhenChunkReviewFound(): void
    {
        $model = $this->makeModel($this->makeIssue());
        $this->assertInstanceOf(TranslationIssueModel::class, $model);
    }

    // ─── setDiff ─────────────────────────────────────────────────

    #[Test]
    public function setDiffAcceptsNullAndArray(): void
    {
        $model = $this->makeModel($this->makeIssue());
        $model->setDiff(null);
        $model->setDiff(['key' => 'value']);
        $this->assertTrue(true);
    }

    // ─── save ────────────────────────────────────────────────────

    #[Test]
    public function saveCallsCreateEntryAndAddsPenaltyPoints(): void
    {
        $issue = $this->makeIssue(['penalty_points' => 3.0]);

        $this->entryDao->expects($this->once())
            ->method('createEntry')
            ->with($issue);

        $this->chunkReviewModel->expects($this->once())
            ->method('addPenaltyPoints')
            ->with(3.0, $this->project);

        $model = $this->makeModel($issue);
        $result = $model->save();

        $this->assertSame($issue, $result);
    }

    #[Test]
    public function saveSetsDefaultNodeValuesWhenNull(): void
    {
        $issue = $this->makeIssue(['start_node' => null, 'end_node' => null]);

        $this->entryDao->expects($this->once())
            ->method('createEntry')
            ->with($this->callback(function (EntryStruct $entry) {
                return $entry->start_node === 0 && $entry->end_node === 0;
            }));

        $model = $this->makeModel($issue);
        $model->save();
    }

    #[Test]
    public function saveHandlesNullPenaltyPoints(): void
    {
        $issue = $this->makeIssue();
        // Force null via reflection since mock properties can be tricky
        $ref = new \ReflectionProperty(EntryStruct::class, 'penalty_points');
        $ref->setValue($issue, null);

        $this->chunkReviewModel->expects($this->once())
            ->method('addPenaltyPoints')
            ->with(0.0, $this->project);

        $model = $this->makeModel($issue);
        $model->save();
    }

    // ─── editFrom ────────────────────────────────────────────────

    #[Test]
    public function editFromCallsModifyEntryAndReturnsIssue(): void
    {
        $issue = $this->makeIssue(['penalty_points' => 5.0]);
        $oldStruct = $this->makeIssue(['penalty_points' => 5.0]);

        $this->entryDao->expects($this->once())
            ->method('modifyEntry')
            ->with($issue);

        $model = $this->makeModel($issue);
        $result = $model->editFrom($oldStruct);

        $this->assertSame($issue, $result);
    }

    #[Test]
    public function editFromAddsPointsWhenPenaltyIncreased(): void
    {
        $issue = $this->makeIssue(['penalty_points' => 8.0]);
        $oldStruct = $this->makeIssue(['penalty_points' => 5.0]);

        $this->chunkReviewModel->expects($this->once())
            ->method('addPenaltyPoints')
            ->with(3.0, $this->project);

        $model = $this->makeModel($issue);
        $model->editFrom($oldStruct);
    }

    #[Test]
    public function editFromSubtractsPointsWhenPenaltyDecreased(): void
    {
        $issue = $this->makeIssue(['penalty_points' => 2.0]);
        $oldStruct = $this->makeIssue(['penalty_points' => 5.0]);

        $this->chunkReviewModel->expects($this->once())
            ->method('subtractPenaltyPoints')
            ->with(3.0, $this->project);

        $model = $this->makeModel($issue);
        $model->editFrom($oldStruct);
    }

    #[Test]
    public function editFromSkipsPenaltyUpdateWhenNoDiff(): void
    {
        $issue = $this->makeIssue(['penalty_points' => 5.0]);
        $oldStruct = $this->makeIssue(['penalty_points' => 5.0]);

        $this->chunkReviewModel->expects($this->never())->method('addPenaltyPoints');
        $this->chunkReviewModel->expects($this->never())->method('subtractPenaltyPoints');

        $model = $this->makeModel($issue);
        $model->editFrom($oldStruct);
    }

    // ─── save with diff ────────────────────────────────────────────

    #[Test]
    public function saveCallsSaveDiffWhenDiffIsSet(): void
    {
        $issue = $this->makeIssue();

        $this->translationVersionDao->expects($this->once())
            ->method('getVersionNumberForTranslation')
            ->with(100, 1, 1)
            ->willReturn(false);

        $this->translationVersionDao->expects($this->once())
            ->method('insertStruct');

        $model = $this->makeModel($issue);
        $model->setDiff(['added' => 'text']);
        $model->save();
    }

    #[Test]
    public function saveUpdatesDiffWhenVersionRecordExists(): void
    {
        $issue = $this->makeIssue();

        $versionRecord = $this->createStub(TranslationVersionStruct::class);

        $this->translationVersionDao->expects($this->once())
            ->method('getVersionNumberForTranslation')
            ->willReturn($versionRecord);

        $this->translationVersionDao->expects($this->once())
            ->method('updateStruct')
            ->with($versionRecord, ['fields' => ['raw_diff']]);

        $model = $this->makeModel($issue);
        $model->setDiff(['changed' => 'content']);
        $model->save();
    }

    // ─── editFrom with diff ─────────────────────────────────────

    #[Test]
    public function editFromCallsSaveDiffWhenDiffIsSet(): void
    {
        $issue = $this->makeIssue(['penalty_points' => 5.0]);
        $oldStruct = $this->makeIssue(['penalty_points' => 5.0]);

        $this->translationVersionDao->expects($this->once())
            ->method('getVersionNumberForTranslation')
            ->willReturn(false);

        $this->translationVersionDao->expects($this->once())
            ->method('insertStruct');

        $model = $this->makeModel($issue);
        $model->setDiff(['some' => 'diff']);
        $model->editFrom($oldStruct);
    }

    // ─── delete ──────────────────────────────────────────────────

    #[Test]
    public function deleteCallsDeleteEntryAndLooksUpChunkReview(): void
    {
        $issue = $this->makeIssue(['source_page' => 2, 'penalty_points' => 5.0]);

        $this->entryDao->expects($this->once())
            ->method('deleteEntry')
            ->with($issue);

        $this->chunkReviewDao
            ->method('findByIdJobAndPasswordAndSourcePage')
            ->with(100, 'pw100', 2)
            ->willReturn($this->chunkReview);

        $this->chunkReviewModel->expects($this->once())
            ->method('subtractPenaltyPoints')
            ->with(5.0, $this->project);

        $model = $this->makeModel($issue);
        $model->delete();
    }

    #[Test]
    public function deleteCallsSubtractEvenWhenPenaltyWouldExceedCurrentTotal(): void
    {
        // The chunk review's current total (10.0) is lower than this issue's own penalty
        // (15.0). The DAO's atomic SQL update clamps at zero on its own (GREATEST(...,0)),
        // so the model must always subtract rather than pre-checking and skipping.
        $issue = $this->makeIssue(['source_page' => 2, 'penalty_points' => 15.0]);

        $this->chunkReviewDao
            ->method('findByIdJobAndPasswordAndSourcePage')
            ->willReturn($this->chunkReview);

        $this->chunkReviewModel->expects($this->once())
            ->method('subtractPenaltyPoints')
            ->with(15.0, $this->project);

        $model = $this->makeModel($issue);
        $model->delete();
    }

    #[Test]
    public function deleteThrowsWhenChunkReviewNotFoundForSourcePage(): void
    {
        $issue = $this->makeIssue(['source_page' => 2]);

        $this->chunkReviewDao
            ->method('findByIdJobAndPasswordAndSourcePage')
            ->willReturn(null);

        $model = $this->makeModel($issue);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('ChunkReview not found for delete operation');

        $model->delete();
    }

    #[Test]
    public function deleteThrowsWhenChunkJobIdIsNull(): void
    {
        $issue = $this->makeIssue();
        $this->chunk->id = null;

        $model = $this->makeModel($issue);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing chunk job id');

        $model->delete();
    }

    #[Test]
    public function deleteThrowsWhenChunkPasswordIsNull(): void
    {
        $issue = $this->makeIssue();
        $this->chunk->password = null;

        $model = $this->makeModel($issue);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing chunk password');

        $model->delete();
    }
}
