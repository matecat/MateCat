<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\LQA\EntryCommentDao;
use Model\LQA\EntryCommentStruct;
use Model\LQA\EntryStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use RuntimeException;
use View\API\V2\Json\SegmentTranslationIssue;

#[CoversClass(SegmentTranslationIssue::class)]
class SegmentTranslationIssueTest extends AbstractTest
{
    private EntryCommentDao&Stub $commentStub;
    private SegmentTranslationIssue $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->commentStub = $this->createStub(EntryCommentDao::class);
        $this->commentStub->method('findByIssueId')->willReturn([]);
        $this->view = new SegmentTranslationIssue($this->commentStub);
    }

    private function makeEntryStruct(int $id = 1): EntryStruct
    {
        $struct                      = new EntryStruct();
        $struct->id                  = $id;
        $struct->uid                 = 42;
        $struct->id_segment          = 100;
        $struct->id_job              = 200;
        $struct->id_category         = 3;
        $struct->severity            = 'major';
        $struct->translation_version = 1;
        $struct->start_node          = 0;
        $struct->start_offset        = 0;
        $struct->end_node            = 0;
        $struct->end_offset          = 0;
        $struct->is_full_segment     = 0;
        $struct->penalty_points      = 1.0;
        $struct->comment             = 'test comment';
        $struct->create_date         = '2024-01-15 10:00:00';
        $struct->target_text         = 'target text';
        $struct->source_page         = 2;

        return $struct;
    }

    private function makeComment(string $comment = 'A comment'): EntryCommentStruct
    {
        $c              = new EntryCommentStruct();
        $c->comment     = $comment;
        $c->create_date = '2024-01-15 11:00:00';

        return $c;
    }

    public function testRenderItemReturnsExpectedKeys(): void
    {
        $entry    = $this->makeEntryStruct(5);
        $comments = [$this->makeComment()];

        $dao = $this->createMock(EntryCommentDao::class);
        $dao->expects($this->once())
            ->method('findByIssueId')
            ->with(5)
            ->willReturn($comments);

        $view   = new SegmentTranslationIssue($dao);
        $result = $view->renderItem($entry);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('uid', $result);
        $this->assertArrayHasKey('id_segment', $result);
        $this->assertArrayHasKey('id_job', $result);
        $this->assertArrayHasKey('id_category', $result);
        $this->assertArrayHasKey('severity', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('comments', $result);
        $this->assertArrayHasKey('revision_number', $result);
        $this->assertSame(5, $result['id']);
        $this->assertSame(42, $result['uid']);
        $this->assertSame(100, $result['id_segment']);
        $this->assertSame('major', $result['severity']);
        $this->assertSame($comments, $result['comments']);
    }

    public function testRenderItemThrowsWhenIdIsNull(): void
    {
        $entry     = $this->makeEntryStruct(1);
        $entry->id = null;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing issue id');

        $this->view->renderItem($entry);
    }

    public function testRenderItemCreatedAtIsFormattedDate(): void
    {
        $entry  = $this->makeEntryStruct(7);
        $result = $this->view->renderItem($entry);

        // created_at should be a valid ISO 8601 date string
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $result['created_at']);
    }

    public function testRenderReturnsArrayOfItems(): void
    {
        $entry1 = $this->makeEntryStruct(1);
        $entry2 = $this->makeEntryStruct(2);

        $result = $this->view->render([$entry1, $entry2]);

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame(2, $result[1]['id']);
    }

    public function testRenderEmptyArrayReturnsEmpty(): void
    {
        $result = $this->view->render([]);
        $this->assertSame([], $result);
    }

    public function testGenCSVTmpFileCreatesFile(): void
    {
        $entry = $this->makeEntryStruct(10);

        // category_label is accessed inside the inner foreach loop only when
        // comments are returned; EntryStruct does not define that property
        // (it comes from a JOIN in production). Use empty comments to cover
        // the outer loop and CSV header-writing code paths safely.
        $path = $this->view->genCSVTmpFile([$entry]);

        $this->assertFileExists($path);
        $contents = file_get_contents($path);
        $this->assertStringContainsString('ID Segment', $contents);

        $this->view->cleanDownloadResource();
    }

    public function testGenCSVTmpFileSkipsRecordWithNullId(): void
    {
        $entry     = $this->makeEntryStruct(1);
        $entry->id = null;

        $dao = $this->createMock(EntryCommentDao::class);
        $dao->expects($this->never())->method('findByIssueId');

        $view = new SegmentTranslationIssue($dao);
        $path = $view->genCSVTmpFile([$entry]);

        $this->assertFileExists($path);
        $view->cleanDownloadResource();
    }

    public function testGenCSVTmpFileReturnsStringPath(): void
    {
        $entry = $this->makeEntryStruct(3);
        $path  = $this->view->genCSVTmpFile([$entry]);

        $this->assertIsString($path);
        $this->assertNotEmpty($path);
        $this->view->cleanDownloadResource();
    }

    public function testConstructorWithoutDaoStillWorks(): void
    {
        // Verifies backward-compatible construction (no args)
        $view = new SegmentTranslationIssue();
        $this->assertInstanceOf(SegmentTranslationIssue::class, $view);
    }
}
