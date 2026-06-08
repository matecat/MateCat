<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\LQA\ChunkReviewDao;
use Model\LQA\ChunkReviewStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use Utils\Registry\AppConfig;
use View\API\V2\Json\ProjectUrls;

#[CoversClass(ProjectUrls::class)]
class ProjectUrlsTest extends AbstractTest
{
    private string $prevHttpHost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prevHttpHost   = AppConfig::$HTTPHOST;
        AppConfig::$HTTPHOST = 'https://example.com';
    }

    protected function tearDown(): void
    {
        AppConfig::$HTTPHOST = $this->prevHttpHost;
        parent::tearDown();
    }

    private function makeRecord(int $fileId = 1, int $jid = 10, string $jpassword = 'abc123'): ShapelessConcreteStruct
    {
        $record              = new ShapelessConcreteStruct();
        $record['id_file']   = $fileId;
        $record['filename']  = 'test.docx';
        $record['jid']       = $jid;
        $record['jpassword'] = $jpassword;
        $record['target']    = 'it-IT';
        $record['source']    = 'en-US';
        $record['name']      = 'Test Project';

        return $record;
    }

    /** @return Stub&ChunkReviewDao */
    private function makeChunkReviewDaoMock(array $reviews = []): Stub
    {
        $stub = $this->createStub(ChunkReviewDao::class);
        $stub->method('findChunkReviews')->willReturn($reviews);

        return $stub;
    }

    public function testConstructorStoresData(): void
    {
        $record = $this->makeRecord();
        $view   = new ProjectUrls([$record], $this->makeChunkReviewDaoMock());

        $this->assertSame([$record], $view->getData());
    }

    public function testGetDataReturnsOriginalData(): void
    {
        $r1   = $this->makeRecord(1, 10, 'pass1');
        $r2   = $this->makeRecord(2, 20, 'pass2');
        $view = new ProjectUrls([$r1, $r2], $this->makeChunkReviewDaoMock());

        $this->assertCount(2, $view->getData());
    }

    public function testRenderReturnsFilesAndJobsKeys(): void
    {
        $record = $this->makeRecord();
        $view   = new ProjectUrls([$record], $this->makeChunkReviewDaoMock());
        $result = $view->render();

        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('jobs', $result);
    }

    public function testRenderFileHasExpectedKeys(): void
    {
        $record = $this->makeRecord(5, 10, 'abc');
        $view   = new ProjectUrls([$record], $this->makeChunkReviewDaoMock());
        $result = $view->render();

        $file = $result['files'][0];
        $this->assertSame(5, $file['id']);
        $this->assertSame('test.docx', $file['name']);
        $this->assertArrayHasKey('original_download_url', $file);
        $this->assertArrayHasKey('translation_download_url', $file);
        $this->assertArrayHasKey('xliff_download_url', $file);
    }

    public function testRenderJobHasExpectedKeys(): void
    {
        $record = $this->makeRecord(1, 10, 'abc');
        $view   = new ProjectUrls([$record], $this->makeChunkReviewDaoMock());
        $result = $view->render();

        $job = $result['jobs'][0];
        $this->assertSame(10, $job['id']);
        $this->assertSame('it-IT', $job['target_lang']);
        $this->assertArrayHasKey('original_download_url', $job);
        $this->assertArrayHasKey('translation_download_url', $job);
        $this->assertArrayHasKey('xliff_download_url', $job);
        $this->assertArrayHasKey('chunks', $job);
    }

    public function testRenderChunkHasPasswordAndTranslateUrl(): void
    {
        $record = $this->makeRecord(1, 10, 'mypass');
        $view   = new ProjectUrls([$record], $this->makeChunkReviewDaoMock());
        $result = $view->render();

        $chunks = $result['jobs'][0]['chunks'];
        $this->assertCount(1, $chunks);
        $chunk = $chunks[0];
        $this->assertSame('mypass', $chunk['password']);
        $this->assertArrayHasKey('translate_url', $chunk);
        $this->assertStringContainsString('https://example.com', $chunk['translate_url']);
    }

    public function testRenderUrlsContainHost(): void
    {
        $record = $this->makeRecord(1, 10, 'abc');
        $view   = new ProjectUrls([$record], $this->makeChunkReviewDaoMock());
        $result = $view->render();

        $this->assertStringContainsString('https://example.com', $result['files'][0]['original_download_url']);
        $this->assertStringContainsString('https://example.com', $result['jobs'][0]['xliff_download_url']);
    }

    public function testRenderDeduplicatesFiles(): void
    {
        // Two records with same file, different jobs
        $r1 = $this->makeRecord(1, 10, 'pass1');
        $r2 = $this->makeRecord(1, 20, 'pass2');

        $view   = new ProjectUrls([$r1, $r2], $this->makeChunkReviewDaoMock());
        $result = $view->render();

        // File 1 should appear only once
        $this->assertCount(1, $result['files']);
        // But both jobs appear
        $this->assertCount(2, $result['jobs']);
    }

    public function testRenderDeduplicatesJobChunks(): void
    {
        // Two records with same job+password (same chunk) — should produce only one chunk entry
        $r1 = $this->makeRecord(1, 10, 'pass1');
        $r2 = $this->makeRecord(2, 10, 'pass1');

        $view   = new ProjectUrls([$r1, $r2], $this->makeChunkReviewDaoMock());
        $result = $view->render();

        $chunks = $result['jobs'][0]['chunks'];
        $this->assertCount(1, $chunks);
    }

    public function testRenderKeyAssocKeepsIndexAssociation(): void
    {
        $r1 = $this->makeRecord(1, 10, 'pass1');
        $r2 = $this->makeRecord(2, 20, 'pass2');

        $view   = new ProjectUrls([$r1, $r2], $this->makeChunkReviewDaoMock());
        $result = $view->render(true);

        // With keyAssoc=true, jobs and files are keyed by their IDs
        $this->assertArrayHasKey(10, $result['jobs']);
        $this->assertArrayHasKey(20, $result['jobs']);
        $this->assertArrayHasKey(1, $result['files']);
        $this->assertArrayHasKey(2, $result['files']);
    }

    public function testRenderWithReviewsAddsReviseUrls(): void
    {
        $review                  = new ChunkReviewStruct();
        $review->review_password = 'rev_pass';
        $review->source_page     = 2;

        $record = $this->makeRecord(1, 10, 'mypass');
        $view   = new ProjectUrls([$record], $this->makeChunkReviewDaoMock([$review]));
        $result = $view->render();

        $chunk = $result['jobs'][0]['chunks'][0];
        $this->assertArrayHasKey('revise_urls', $chunk);
        $this->assertCount(1, $chunk['revise_urls']);
        $this->assertArrayHasKey('revision_number', $chunk['revise_urls'][0]);
        $this->assertArrayHasKey('url', $chunk['revise_urls'][0]);
        $this->assertStringContainsString('https://example.com', $chunk['revise_urls'][0]['url']);
    }

    public function testRenderWithNullReviewPasswordHandledGracefully(): void
    {
        $review                  = new ChunkReviewStruct();
        $review->review_password = null;
        $review->source_page     = 2;

        $record = $this->makeRecord(1, 10, 'mypass');
        $view   = new ProjectUrls([$record], $this->makeChunkReviewDaoMock([$review]));
        // Should not throw — null password is coalesced to empty string
        $result = $view->render();

        $chunk = $result['jobs'][0]['chunks'][0];
        $this->assertArrayHasKey('revise_urls', $chunk);
    }

    public function testRenderEmptyDataReturnsEmptyFilesAndJobs(): void
    {
        $view   = new ProjectUrls([], $this->makeChunkReviewDaoMock());
        $result = $view->render();

        $this->assertSame([], $result['files']);
        $this->assertSame([], $result['jobs']);
    }
}
