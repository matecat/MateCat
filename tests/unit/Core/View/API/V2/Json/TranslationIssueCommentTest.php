<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\LQA\EntryCommentStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\V2\Json\TranslationIssueComment;

#[CoversClass(TranslationIssueComment::class)]
class TranslationIssueCommentTest extends AbstractTest
{
    private function makeComment(
        int $id = 1,
        int $uid = 10,
        int $idQaEntry = 99,
        string $createDate = '2024-01-15 12:00:00',
        string $comment = 'Test comment',
        int $sourcePage = 1
    ): EntryCommentStruct {
        $struct               = new EntryCommentStruct();
        $struct->id           = $id;
        $struct->uid          = $uid;
        $struct->id_qa_entry  = $idQaEntry;
        $struct->create_date  = $createDate;
        $struct->comment      = $comment;
        $struct->source_page  = $sourcePage;

        return $struct;
    }

    public function testRenderItemReturnsExpectedKeys(): void
    {
        $view   = new TranslationIssueComment();
        $record = $this->makeComment();
        $result = $view->renderItem($record);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('uid', $result);
        $this->assertArrayHasKey('id_issue', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('source_page', $result);
    }

    public function testRenderItemCastsIdToInt(): void
    {
        $view   = new TranslationIssueComment();
        $record = $this->makeComment(42, 7, 55);
        $result = $view->renderItem($record);

        $this->assertSame(42, $result['id']);
        $this->assertSame(7, $result['uid']);
        $this->assertSame(55, $result['id_issue']);
    }

    public function testRenderItemFormatsDate(): void
    {
        $view   = new TranslationIssueComment();
        $record = $this->makeComment(1, 1, 1, '2024-01-15 12:00:00');
        $result = $view->renderItem($record);

        $this->assertIsString($result['created_at']);
        // ISO 8601 format contains 'T'
        $this->assertStringContainsString('T', $result['created_at']);
    }

    public function testRenderItemHandlesNullCreateDate(): void
    {
        $view                = new TranslationIssueComment();
        $record              = $this->makeComment();
        $record->create_date = null;
        $result              = $view->renderItem($record);

        $this->assertIsString($result['created_at']);
    }

    public function testRenderItemReturnsMessage(): void
    {
        $view   = new TranslationIssueComment();
        $record = $this->makeComment(1, 1, 1, '2024-01-15 12:00:00', 'Hello world', 2);
        $result = $view->renderItem($record);

        $this->assertSame('Hello world', $result['message']);
        $this->assertSame(2, $result['source_page']);
    }

    public function testRenderReturnsEmptyArrayForEmptyInput(): void
    {
        $view   = new TranslationIssueComment();
        $result = $view->render([]);

        $this->assertSame([], $result);
    }

    public function testRenderReturnsOneItemPerRecord(): void
    {
        $view    = new TranslationIssueComment();
        $records = [
            $this->makeComment(1, 10, 100),
            $this->makeComment(2, 20, 200),
            $this->makeComment(3, 30, 300),
        ];
        $result = $view->render($records);

        $this->assertCount(3, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame(2, $result[1]['id']);
        $this->assertSame(3, $result[2]['id']);
    }
}
