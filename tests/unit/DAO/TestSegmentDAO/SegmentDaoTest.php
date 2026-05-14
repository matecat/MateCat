<?php

declare(strict_types=1);

namespace unit\DAO\TestSegmentDAO;

use Exception;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Files\FileStruct;
use Model\Jobs\JobStruct;
use Model\QualityReport\QualityReportSegmentStruct;
use Model\Segments\SegmentDao;
use Model\Segments\SegmentStruct;
use Model\Segments\SegmentUIStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Utils\Registry\AppConfig;

class SegmentDaoTest extends TestCase
{
    private \PHPUnit\Framework\MockObject\Stub&IDatabase $dbStub;
    private \PHPUnit\Framework\MockObject\Stub&PDO $pdoStub;
    private \PHPUnit\Framework\MockObject\Stub&PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        $this->stmtStub = $this->createStub(PDOStatement::class);
        $this->stmtStub->queryString = '';

        $this->pdoStub = $this->createStub(PDO::class);
        $this->pdoStub->method('prepare')->willReturn($this->stmtStub);

        $this->dbStub = $this->createStub(IDatabase::class);
        $this->dbStub->method('getConnection')->willReturn($this->pdoStub);

        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, $this->dbStub);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        AppConfig::$SKIP_SQL_CACHE = false;

        parent::tearDown();
    }

    private function makeFileStruct(array $overrides = []): FileStruct
    {
        $file = new FileStruct();
        $file->id = $overrides['id'] ?? 1;
        $file->id_project = $overrides['id_project'] ?? 10;
        $file->filename = $overrides['filename'] ?? 'test.xlf';
        $file->source_language = $overrides['source_language'] ?? 'en-US';
        $file->mime_type = $overrides['mime_type'] ?? 'application/xliff+xml';
        $file->sha1_original_file = $overrides['sha1_original_file'] ?? sha1('test');
        $file->is_converted = $overrides['is_converted'] ?? false;

        return $file;
    }

    private function makeJobStruct(array $overrides = []): JobStruct
    {
        $job = new JobStruct();
        $job->id = $overrides['id'] ?? 1;
        $job->password = $overrides['password'] ?? 'abc123';
        $job->id_project = $overrides['id_project'] ?? 10;
        $job->job_first_segment = $overrides['job_first_segment'] ?? 100;
        $job->job_last_segment = $overrides['job_last_segment'] ?? 200;
        $job->source = $overrides['source'] ?? 'en-US';
        $job->target = $overrides['target'] ?? 'it-IT';

        return $job;
    }

    private function makeSegmentStruct(array $overrides = []): SegmentStruct
    {
        $seg = new SegmentStruct();
        $seg->id = $overrides['id'] ?? 100;
        $seg->id_file = $overrides['id_file'] ?? 1;
        $seg->id_file_part = $overrides['id_file_part'] ?? null;
        $seg->internal_id = $overrides['internal_id'] ?? 'tu1';
        $seg->segment = $overrides['segment'] ?? 'Hello world';
        $seg->segment_hash = $overrides['segment_hash'] ?? md5('Hello world');
        $seg->raw_word_count = $overrides['raw_word_count'] ?? 2;
        $seg->xliff_mrk_id = $overrides['xliff_mrk_id'] ?? null;
        $seg->xliff_ext_prec_tags = $overrides['xliff_ext_prec_tags'] ?? null;
        $seg->xliff_ext_succ_tags = $overrides['xliff_ext_succ_tags'] ?? null;
        $seg->show_in_cattool = $overrides['show_in_cattool'] ?? true;
        $seg->xliff_mrk_ext_prec_tags = $overrides['xliff_mrk_ext_prec_tags'] ?? null;
        $seg->xliff_mrk_ext_succ_tags = $overrides['xliff_mrk_ext_succ_tags'] ?? null;

        return $seg;
    }


    public function testCountByFileReturnsInteger(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn([0 => '5']);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->countByFile($this->makeFileStruct());

        $this->assertSame(5, $result);
    }

    public function testCountByFileReturnsZeroWhenEmpty(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn([0 => '0']);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->countByFile($this->makeFileStruct());

        $this->assertSame(0, $result);
    }


    public function testGetByChunkIdAndSegmentIdReturnsSegmentStruct(): void
    {
        $seg = $this->makeSegmentStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$seg]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getByChunkIdAndSegmentId(1, 'abc123', 100);

        $this->assertInstanceOf(SegmentStruct::class, $result);
        $this->assertSame(100, $result->id);
    }

    public function testGetByChunkIdAndSegmentIdReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getByChunkIdAndSegmentId(1, 'abc123', 999);

        $this->assertNull($result);
    }


    public function testGetByChunkIdReturnsArray(): void
    {
        $seg = $this->makeSegmentStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$seg]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getByChunkId(1, 'abc123');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testGetByChunkIdReturnsEmptyWhenNoSegments(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getByChunkId(1, 'abc123');

        $this->assertSame([], $result);
    }


    public function testGetContextAndSegmentByIDsReturnsObject(): void
    {
        $seg = $this->makeSegmentStruct(['id' => 50]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([(array)$seg]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getContextAndSegmentByIDs([
            'id_before' => 49,
            'id_segment' => 50,
            'id_after' => 51,
        ]);

        $this->assertIsObject($result);
    }

    public function testGetContextAndSegmentByIDsWithNoResultsKeepsOriginalIds(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getContextAndSegmentByIDs([
            'id_before' => 49,
            'id_segment' => 50,
            'id_after' => 51,
        ]);

        $this->assertSame(49, $result->id_before);
        $this->assertSame(50, $result->id_segment);
        $this->assertSame(51, $result->id_after);
    }


    public function testGetSegmentsIdForQRAfter(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            ['__sid' => 101],
            ['__sid' => 102],
        ]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'after');

        $this->assertSame([101, 102], $result);
    }

    public function testGetSegmentsIdForQRBefore(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            ['__sid' => 99],
        ]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'before');

        $this->assertSame([99], $result);
    }

    public function testGetSegmentsIdForQRCenter(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            ['__sid' => 100],
            ['__sid' => 101],
        ]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'center');

        $this->assertSame([100, 101], $result);
    }

    public function testGetSegmentsIdForQRThrowsOnInvalidDirection(): void
    {
        $job = $this->makeJobStruct();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("No direction selected");

        $dao = new SegmentDao($this->dbStub);
        $dao->getSegmentsIdForQR($job, 10, 100, 'invalid');
    }

    public function testGetSegmentsIdForQRWithStatusFilter(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([['__sid' => 105]]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'after', [
            'filter' => ['status' => 'TRANSLATED'],
        ]);

        $this->assertSame([105], $result);
    }

    public function testGetSegmentsIdForQRWithIssuesFilter(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([['__sid' => 110]]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'after', [
            'filter' => ['issues_in_r' => 1],
        ]);

        $this->assertSame([110], $result);
    }

    public function testGetSegmentsIdForQRWithIssueCategoryArray(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([['__sid' => 111]]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'after', [
            'filter' => ['issue_category' => [1, 2, 3]],
        ]);

        $this->assertSame([111], $result);
    }

    public function testGetSegmentsIdForQRWithIssueCategoryString(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([['__sid' => 112]]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'after', [
            'filter' => ['issue_category' => 'terminology'],
        ]);

        $this->assertSame([112], $result);
    }

    public function testGetSegmentsIdForQRWithIssueCategoryAll(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([['__sid' => 113]]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'after', [
            'filter' => ['issue_category' => 'all'],
        ]);

        $this->assertSame([113], $result);
    }

    public function testGetSegmentsIdForQRWithSeverityFilter(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([['__sid' => 114]]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'after', [
            'filter' => ['issue_category' => 'all', 'severity' => 'minor'],
        ]);

        $this->assertSame([114], $result);
    }

    public function testGetSegmentsIdForQRWithIdSegmentFilter(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([['__sid' => 150]]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'after', [
            'filter' => ['id_segment' => 150],
        ]);

        $this->assertSame([150], $result);
    }

    public function testGetSegmentsIdForQRReturnsEmptyArray(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'after');

        $this->assertSame([], $result);
    }


    public function testGetSegmentsForQrReturnsArray(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsForQr([100, 101, 102], 1, 'abc123');

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function testGetSegmentsForQrWithSingleSegment(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsForQr([100], 1, 'abc123');

        $this->assertSame([], $result);
    }


    public function testCreateListInsertsSegments(): void
    {
        $segments = [
            $this->makeSegmentStruct(['id' => 1]),
            $this->makeSegmentStruct(['id' => 2]),
        ];

        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new SegmentDao($this->dbStub);
        $dao->createList($segments);

        $this->assertTrue(true);
    }

    public function testCreateListHandlesChunking(): void
    {
        $segments = [];
        for ($i = 1; $i <= 150; $i++) {
            $segments[] = $this->makeSegmentStruct(['id' => $i]);
        }

        $this->stmtStub->method('execute')->willReturn(true);

        $dao = new SegmentDao($this->dbStub);
        $dao->createList($segments);

        $this->assertTrue(true);
    }

    public function testCreateListThrowsOnOversizedSegment(): void
    {
        $seg = $this->makeSegmentStruct();
        $seg->segment = str_repeat('x', 65536);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Segment size limit reached");

        $dao = new SegmentDao($this->dbStub);
        $dao->createList([$seg]);
    }

    public function testCreateListThrowsWrappedPdoException(): void
    {
        $seg = $this->makeSegmentStruct();

        $this->stmtStub->method('execute')->willThrowException(new \PDOException('Connection lost'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Segment import - DB Error: Connection lost");

        $dao = new SegmentDao($this->dbStub);
        $dao->createList([$seg]);
    }

    public function testCreateListWithEmptyArray(): void
    {
        $dao = new SegmentDao($this->dbStub);
        $dao->createList([]);

        $this->assertTrue(true);
    }


    public function testGetPaginationSegmentsAfter(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getPaginationSegments($job, 10, 100, 'after');

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function testGetPaginationSegmentsBefore(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getPaginationSegments($job, 10, 100, 'before');

        $this->assertSame([], $result);
    }

    public function testGetPaginationSegmentsCenter(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getPaginationSegments($job, 10, 100, 'center');

        $this->assertSame([], $result);
    }

    public function testGetPaginationSegmentsDefaultIsCenter(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getPaginationSegments($job, 10, 100, null);

        $this->assertSame([], $result);
    }

    public function testGetPaginationSegmentsWithOptionalFields(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getPaginationSegments($job, 10, 100, 'after', [
            'optional_fields' => ['st.version_number', 'st.match_type'],
        ]);

        $this->assertSame([], $result);
    }


    public function testGetSegmentsDownloadReturnsArray(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            ['sid' => 100, 'segment' => 'Hello', 'translation' => 'Ciao', 'status' => 'TRANSLATED'],
        ]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsDownload($job, 1);

        $this->assertCount(1, $result);
        $this->assertSame(100, $result[0]['sid']);
    }

    public function testGetSegmentsDownloadReturnsEmptyArray(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsDownload($job, 1);

        $this->assertSame([], $result);
    }


    public function testDestroyCacheForGlobalTranslationMismatchesReturnsBoolean(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->destroyCacheForGlobalTranslationMismatches($job);

        $this->assertIsBool($result);
    }


    public function testGetTranslationsMismatchesReturnsEmptyWhenJobNotFound(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getTranslationsMismatches(1, 'abc123');

        $this->assertSame([], $result);
    }

    public function testGetTranslationsMismatchesWithSidReturnsLocalMismatches(): void
    {
        $job = $this->makeJobStruct();
        $jobArray = (array)$job;

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$jobArray]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getTranslationsMismatches(1, 'abc123', 150);

        $this->assertIsArray($result);
    }

    public function testGetTranslationsMismatchesGlobalWithTranslations(): void
    {
        $job = $this->makeJobStruct(['job_first_segment' => 100, 'job_last_segment' => 102]);
        $jobArray = (array)$job;

        $segData1 = ['id_segment' => 100, 'segment_hash' => 'hash1', 'translation' => 'Trad A', 'id_job' => 1];
        $segData2 = ['id_segment' => 101, 'segment_hash' => 'hash1', 'translation' => 'Trad B', 'id_job' => 1];
        $segData3 = ['id_segment' => 102, 'segment_hash' => 'hash2', 'translation' => 'Trad C', 'id_job' => 1];

        $callCount = 0;
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturnCallback(function () use (&$callCount, $jobArray, $segData1, $segData2, $segData3) {
            $callCount++;
            if ($callCount === 1) {
                return [$jobArray];
            }
            return [$segData1, $segData2, $segData3];
        });
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getTranslationsMismatches(1, 'abc123');

        $this->assertIsArray($result);
    }

    public function testGetTranslationsMismatchesGlobalFiltersNonMismatches(): void
    {
        $job = $this->makeJobStruct(['job_first_segment' => 100, 'job_last_segment' => 101]);
        $jobArray = (array)$job;

        $segData1 = ['id_segment' => 100, 'segment_hash' => 'hash1', 'translation' => 'Same', 'id_job' => 1];
        $segData2 = ['id_segment' => 101, 'segment_hash' => 'hash1', 'translation' => 'Same', 'id_job' => 1];

        $callCount = 0;
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturnCallback(function () use (&$callCount, $jobArray, $segData1, $segData2) {
            $callCount++;
            if ($callCount === 1) {
                return [$jobArray];
            }
            return [$segData1, $segData2];
        });
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getTranslationsMismatches(1, 'abc123');

        $this->assertSame([], $result);
    }


    public function testGetNextSegmentReturnsArray(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            ['id' => 101, 'status' => 'NEW'],
        ]);

        $result = SegmentDao::getNextSegment(100, 1, 'abc123');

        $this->assertCount(1, $result);
        $this->assertSame(101, $result[0]['id']);
        $this->assertSame('NEW', $result[0]['status']);
    }

    public function testGetNextSegmentWithTranslatedFilter(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([
            ['id' => 102, 'status' => 'TRANSLATED'],
        ]);

        $result = SegmentDao::getNextSegment(100, 1, 'abc123', true);

        $this->assertCount(1, $result);
        $this->assertSame('TRANSLATED', $result[0]['status']);
    }

    public function testGetNextSegmentReturnsEmptyWhenNone(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = SegmentDao::getNextSegment(100, 1, 'abc123');

        $this->assertSame([], $result);
    }


    public function testGetSegmentsForAnalysisFromIdJobAndPasswordReturnsArray(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $result = SegmentDao::getSegmentsForAnalysisFromIdJobAndPassword(1, 'abc123', 50, 0);

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function testGetSegmentsForAnalysisFromIdJobAndPasswordWithTtl(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $result = SegmentDao::getSegmentsForAnalysisFromIdJobAndPassword(1, 'abc123', 50, 10, 3600);

        $this->assertSame([], $result);
    }


    public function testGetSegmentsForAnalysisFromIdProjectAndPasswordReturnsArray(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $result = SegmentDao::getSegmentsForAnalysisFromIdProjectAndPassword(10, 'proj123', 50, 0);

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function testGetSegmentsForAnalysisFromIdProjectAndPasswordWithOffset(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $result = SegmentDao::getSegmentsForAnalysisFromIdProjectAndPassword(10, 'proj123', 25, 50, 7200);

        $this->assertSame([], $result);
    }


    public function testGetSegmentsIdForQRWithIssuesInR2(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([['__sid' => 120]]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'after', [
            'filter' => ['issues_in_r' => 2],
        ]);

        $this->assertSame([120], $result);
    }


    public function testGetSegmentsIdForQRWithInvalidIssuesInR(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([['__sid' => 121]]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'after', [
            'filter' => ['issues_in_r' => 5],
        ]);

        $this->assertSame([121], $result);
    }


    public function testGetSegmentsIdForQRBeforeWithStatusFilter(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([['__sid' => 95]]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'before', [
            'filter' => ['status' => 'APPROVED'],
        ]);

        $this->assertSame([95], $result);
    }


    public function testGetSegmentsIdForQRCenterWithStatusFilter(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([['__sid' => 100], ['__sid' => 99]]);

        $dao = new SegmentDao($this->dbStub);
        $result = $dao->getSegmentsIdForQR($job, 10, 100, 'center', [
            'filter' => ['status' => 'TRANSLATED'],
        ]);

        $this->assertSame([100, 99], $result);
    }
}
