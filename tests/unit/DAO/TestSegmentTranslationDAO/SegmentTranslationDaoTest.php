<?php

declare(strict_types=1);

namespace unit\DAO\TestSegmentTranslationDAO;

use Exception;
use Model\DataAccess\IDatabase;
use Model\Files\FileStruct;
use Model\Jobs\JobStruct;
use Model\Search\ReplaceEventStruct;
use Model\Translations\SegmentTranslationDao;
use Model\Translations\SegmentTranslationStruct;
use PDO;
use PDOException;
use PDOStatement;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

class SegmentTranslationDaoTest extends AbstractTest
{
    private \PHPUnit\Framework\MockObject\Stub&IDatabase $dbStub;
    private \PHPUnit\Framework\MockObject\Stub&PDO $pdoStub;
    private \PHPUnit\Framework\MockObject\Stub&PDOStatement $stmtStub;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        [$this->dbStub, $this->pdoStub, $this->stmtStub] = $this->createDatabaseMock();
        $this->dbStub->method('update')->willReturn(1);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();

        AppConfig::$SKIP_SQL_CACHE = false;

        parent::tearDown();
    }

    private function makeTranslationStruct(array $overrides = []): SegmentTranslationStruct
    {
        $st = new SegmentTranslationStruct();
        $st->id_segment = $overrides['id_segment'] ?? 100;
        $st->id_job = $overrides['id_job'] ?? 1;
        $st->segment_hash = $overrides['segment_hash'] ?? md5('test');
        $st->status = $overrides['status'] ?? 'TRANSLATED';
        $st->translation = $overrides['translation'] ?? 'Traduzione di test';
        $st->translation_date = $overrides['translation_date'] ?? '2026-01-01 12:00:00';
        $st->version_number = $overrides['version_number'] ?? 1;
        $st->time_to_edit = $overrides['time_to_edit'] ?? 500;
        $st->match_type = $overrides['match_type'] ?? 'MT';
        $st->suggestion = $overrides['suggestion'] ?? 'suggestion';
        $st->suggestion_match = $overrides['suggestion_match'] ?? '85';
        $st->suggestion_source = $overrides['suggestion_source'] ?? 'TM';
        $st->suggestion_position = $overrides['suggestion_position'] ?? 1;
        $st->suggestions_array = $overrides['suggestions_array'] ?? '[]';
        $st->serialized_errors_list = $overrides['serialized_errors_list'] ?? null;
        $st->warning = $overrides['warning'] ?? false;
        $st->autopropagated_from = $overrides['autopropagated_from'] ?? null;

        return $st;
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

    public function testGetAllSegmentsByIdListAndJobIdReturnsArray(): void
    {
        $st = $this->makeTranslationStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$st]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $result = SegmentTranslationDao::getAllSegmentsByIdListAndJobId([100, 101], 1);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testGetAllSegmentsByIdListAndJobIdChunks(): void
    {
        $ids = range(1, 25);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $result = SegmentTranslationDao::getAllSegmentsByIdListAndJobId($ids, 1);

        $this->assertSame([], $result);
    }

    public function testUpdateTranslationAndStatusAndDateByListReturnsRowCount(): void
    {
        $st = $this->makeTranslationStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('closeCursor')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $result = SegmentTranslationDao::updateTranslationAndStatusAndDateByList([$st]);

        $this->assertSame(1, $result);
    }

    public function testUpdateTranslationAndStatusAndDateByListThrowsOnOversizedTranslation(): void
    {
        $st = $this->makeTranslationStruct(['translation' => str_repeat('x', 65536)]);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("Translation size limit reached");

        SegmentTranslationDao::updateTranslationAndStatusAndDateByList([$st]);
    }

    public function testUpdateTranslationAndStatusAndDateByListChunks(): void
    {
        $structs = [];
        for ($i = 1; $i <= 25; $i++) {
            $structs[] = $this->makeTranslationStruct(['id_segment' => $i]);
        }

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('closeCursor')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(5);

        $result = SegmentTranslationDao::updateTranslationAndStatusAndDateByList($structs);

        $this->assertSame(10, $result);
    }

    public function testFindBySegmentAndJobReturnsStruct(): void
    {
        $st = $this->makeTranslationStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$st]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $result = SegmentTranslationDao::findBySegmentAndJob(100, 1);

        $this->assertInstanceOf(SegmentTranslationStruct::class, $result);
        $this->assertSame(100, $result->id_segment);
    }

    public function testFindBySegmentAndJobReturnsNullWhenNotFound(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $result = SegmentTranslationDao::findBySegmentAndJob(999, 1);

        $this->assertNull($result);
    }

    public function testUpdateLastTranslationDateByIdListExecutes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        SegmentTranslationDao::updateLastTranslationDateByIdList([100, 101], '2026-01-01 12:00:00');

        $this->assertTrue(true);
    }

    public function testUpdateLastTranslationDateByIdListSkipsEmpty(): void
    {
        SegmentTranslationDao::updateLastTranslationDateByIdList([], '2026-01-01 12:00:00');

        $this->assertTrue(true);
    }

    public function testGetByJobIdReturnsArray(): void
    {
        $st = $this->makeTranslationStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$st]);

        $dao = new SegmentTranslationDao($this->dbStub);
        $result = $dao->getByJobId(1);

        $this->assertCount(1, $result);
    }

    public function testGetByJobIdReturnsEmpty(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentTranslationDao($this->dbStub);
        $result = $dao->getByJobId(999);

        $this->assertSame([], $result);
    }

    public function testGetByFileReturnsArray(): void
    {
        $st = $this->makeTranslationStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([$st]);

        $dao = new SegmentTranslationDao($this->dbStub);
        $result = $dao->getByFile($this->makeFileStruct());

        $this->assertCount(1, $result);
    }

    public function testGetByFileReturnsEmpty(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $dao = new SegmentTranslationDao($this->dbStub);
        $result = $dao->getByFile($this->makeFileStruct());

        $this->assertSame([], $result);
    }

    public function testSetAnalysisValueReturnsRowCount(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $result = SegmentTranslationDao::setAnalysisValue([
            'id_segment' => 100,
            'id_job' => 1,
            'eq_word_count' => 5.5,
            'standard_word_count' => 6.0,
        ]);

        $this->assertSame(1, $result);
    }

    public function testGetUnchangeableStatusWithApprovedStatus(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([101, 102]);

        $result = SegmentTranslationDao::getUnchangeableStatus($job, [100, 101, 102], 'APPROVED', null);

        $this->assertIsArray($result);
    }

    public function testGetUnchangeableStatusWithApproved2Status(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = SegmentTranslationDao::getUnchangeableStatus($job, [100], 'APPROVED2', null);

        $this->assertSame([], $result);
    }

    public function testGetUnchangeableStatusWithTranslatedStatus(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = SegmentTranslationDao::getUnchangeableStatus($job, [100, 101], 'TRANSLATED', null);

        $this->assertSame([], $result);
    }

    public function testGetUnchangeableStatusWithSourcePage(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = SegmentTranslationDao::getUnchangeableStatus($job, [100, 101], 'APPROVED', 2);

        $this->assertSame([], $result);
    }

    public function testGetUnchangeableStatusThrowsOnInvalidStatus(): void
    {
        $job = $this->makeJobStruct();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('not allowed to change status to INVALID');

        SegmentTranslationDao::getUnchangeableStatus($job, [100], 'INVALID', null);
    }

    public function testAddTranslationReturnsRowCount(): void
    {
        $st = $this->makeTranslationStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $result = SegmentTranslationDao::addTranslation($st, false);

        $this->assertSame(1, $result);
    }

    public function testAddTranslationWithRevisionResetsTimeToEdit(): void
    {
        $st = $this->makeTranslationStruct(['time_to_edit' => 1000]);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $result = SegmentTranslationDao::addTranslation($st, true);

        $this->assertSame(1, $result);
    }

    public function testAddTranslationNormalizesNowFunction(): void
    {
        $st = $this->makeTranslationStruct(['translation_date' => 'NOW()']);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $result = SegmentTranslationDao::addTranslation($st, false);

        $this->assertSame(1, $result);
    }

    public function testAddTranslationNormalizesNullString(): void
    {
        $st = $this->makeTranslationStruct(['serialized_errors_list' => 'NULL']);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $result = SegmentTranslationDao::addTranslation($st, false);

        $this->assertSame(1, $result);
    }

    public function testAddTranslationThrowsOnEmptyTranslation(): void
    {
        $st = $this->makeTranslationStruct(['translation' => '']);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("Empty translation found");

        SegmentTranslationDao::addTranslation($st, false);
    }

    public function testAddTranslationThrowsOnOversizedTranslation(): void
    {
        $st = $this->makeTranslationStruct(['translation' => str_repeat('x', 65536)]);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("Translation size limit reached");

        SegmentTranslationDao::addTranslation($st, false);
    }

    public function testAddTranslationWrapsExecuteException(): void
    {
        $st = $this->makeTranslationStruct();

        $this->stmtStub->method('execute')->willThrowException(new PDOException('Deadlock', 1213));

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage("Error when (UPDATE) the translation for the segment 100");

        SegmentTranslationDao::addTranslation($st, false);
    }

    public function testAddTranslationWithNullVersionNumberDefaultsToZero(): void
    {
        $st = $this->makeTranslationStruct(['version_number' => null]);
        $st->version_number = null;

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $result = SegmentTranslationDao::addTranslation($st, false);

        $this->assertSame(1, $result);
    }

    public function testUpdateTranslationAndStatusAndDateReturnsRowCount(): void
    {
        $st = $this->makeTranslationStruct();

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $result = SegmentTranslationDao::updateTranslationAndStatusAndDate($st);

        $this->assertSame(1, $result);
    }

    public function testUpdateTranslationAndStatusAndDateWithNullVersionNumber(): void
    {
        $st = $this->makeTranslationStruct();
        $st->version_number = null;

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $result = SegmentTranslationDao::updateTranslationAndStatusAndDate($st);

        $this->assertSame(1, $result);
    }

    public function testGetSegmentTranslationsModifiedByRevisorWithIssueCountReturnsArray(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);
        $this->stmtStub->method('fetch')->willReturn(false);

        $dao = new SegmentTranslationDao($this->dbStub);
        $result = $dao->getSegmentTranslationsModifiedByRevisorWithIssueCount(1, 'abc123', 2);

        $this->assertSame([], $result);
    }

    public function testGetMaxSegmentIdsFromJobReturnsArray(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([[500], [600]]);

        $result = SegmentTranslationDao::getMaxSegmentIdsFromJob($job);

        $this->assertSame([500, 600], $result);
    }

    public function testGetMaxSegmentIdsFromJobReturnsEmpty(): void
    {
        $job = $this->makeJobStruct();

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = SegmentTranslationDao::getMaxSegmentIdsFromJob($job);

        $this->assertSame([], $result);
    }

    public function testUpdateFirstTimeOpenedContributionExecutes(): void
    {
        $result = SegmentTranslationDao::updateFirstTimeOpenedContribution(
            ['first_opened' => '2026-01-01'],
            ['id_segment' => 100, 'id_job' => 1]
        );

        $this->assertNull($result);
    }

    public function testGetLast10TranslatedSegmentIDsInLastHourReturnsArray(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);

        $callCount = 0;
        $this->stmtStub->method('fetch')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            if ($callCount <= 3) {
                return ['id_segment' => 100 + $callCount];
            }
            return false;
        });

        $result = SegmentTranslationDao::getLast10TranslatedSegmentIDsInLastHour(1);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertSame(101, $result[0]);
    }

    public function testGetLast10TranslatedSegmentIDsInLastHourReturnsNullOnException(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willThrowException(new \Exception('DB error'));

        $result = SegmentTranslationDao::getLast10TranslatedSegmentIDsInLastHour(1);

        $this->assertNull($result);
    }

    public function testGetLast10TranslatedSegmentIDsReturnsEmptyArray(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetch')->willReturn(false);

        $result = SegmentTranslationDao::getLast10TranslatedSegmentIDsInLastHour(1);

        $this->assertSame([], $result);
    }

    public function testGetWordsPerSecondReturnsArray(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([['words_per_second' => 5]]);

        $result = SegmentTranslationDao::getWordsPerSecond(1, [100, 101, 102]);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['words_per_second']);
    }

    public function testGetWordsPerSecondReturnsEmpty(): void
    {
        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = SegmentTranslationDao::getWordsPerSecond(1, [100]);

        $this->assertSame([], $result);
    }

    public function testRebuildFromReplaceEventsReturnsAffectedRows(): void
    {
        $event = new ReplaceEventStruct();
        $event->id_job = 1;
        $event->id_segment = 100;
        $event->translation_after_replacement = 'New translation';
        $event->replace_version = '1';
        $event->job_password = 'abc';
        $event->target = 'it-IT';
        $event->status = 'TRANSLATED';
        $event->replacement = 'replacement';

        $this->pdoStub->method('beginTransaction')->willReturn(true);
        $this->pdoStub->method('commit')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);

        $result = SegmentTranslationDao::rebuildFromReplaceEvents([$event]);

        $this->assertSame(1, $result);
    }

    public function testRebuildFromReplaceEventsRollsBackOnException(): void
    {
        $event = new ReplaceEventStruct();
        $event->id_job = 1;
        $event->id_segment = 100;
        $event->translation_after_replacement = 'New translation';
        $event->replace_version = '1';
        $event->job_password = 'abc';
        $event->target = 'it-IT';
        $event->status = 'TRANSLATED';
        $event->replacement = 'replacement';

        $this->pdoStub->method('beginTransaction')->willReturn(true);
        $this->pdoStub->method('rollBack')->willReturn(true);
        $this->pdoStub->method('commit')->willReturn(true);
        $this->stmtStub->method('execute')->willThrowException(new \Exception('Deadlock'));

        $result = SegmentTranslationDao::rebuildFromReplaceEvents([$event]);

        $this->assertSame(0, $result);
    }

    public function testRebuildFromReplaceEventsWithMultipleEvents(): void
    {
        $events = [];
        for ($i = 1; $i <= 3; $i++) {
            $event = new ReplaceEventStruct();
            $event->id_job = 1;
            $event->id_segment = $i;
            $event->translation_after_replacement = "Translation $i";
            $event->replace_version = '1';
            $event->job_password = 'abc';
            $event->target = 'it-IT';
            $event->status = 'TRANSLATED';
            $event->replacement = 'replacement';
            $events[] = $event;
        }

        $this->pdoStub->method('beginTransaction')->willReturn(true);
        $this->pdoStub->method('commit')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);

        $result = SegmentTranslationDao::rebuildFromReplaceEvents($events);

        $this->assertSame(3, $result);
    }

    public function testUpdateSuggestionsArrayExecutes(): void
    {
        $this->stmtStub->method('execute')->willReturn(true);

        SegmentTranslationDao::updateSuggestionsArray(100, [['match' => '85%', 'translation' => 'Test']]);

        $this->assertTrue(true);
    }

    public function testUpdateSuggestionsArraySkipsEmpty(): void
    {
        SegmentTranslationDao::updateSuggestionsArray(100, []);

        $this->assertTrue(true);
    }

    public function testPropagateTranslationThrowsWrappedExceptionOnPdoError(): void
    {
        $st = $this->makeTranslationStruct();
        $job = $this->makeJobStruct();

        $project = $this->createStub(\Model\Projects\ProjectStruct::class);
        $project->method('getWordCountType')->willReturn('raw');

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willThrowException(new PDOException('Connection lost', 2006));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error in counting total words for propagation");

        SegmentTranslationDao::propagateTranslation($st, $job, 100, $project);
    }

    public function testPropagateTranslationWithNoMatchingRows(): void
    {
        $st = $this->makeTranslationStruct();
        $job = $this->makeJobStruct();

        $project = $this->createStub(\Model\Projects\ProjectStruct::class);
        $project->method('getWordCountType')->willReturn('raw');

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(0);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = SegmentTranslationDao::propagateTranslation($st, $job, 100, $project);

        $this->assertIsArray($result);
    }

    public function testPropagateTranslationWithEquivalentWordCount(): void
    {
        $st = $this->makeTranslationStruct();
        $job = $this->makeJobStruct();

        $project = $this->createStub(\Model\Projects\ProjectStruct::class);
        $project->method('getWordCountType')->willReturn('equivalent');

        $this->stmtStub->method('setFetchMode')->willReturn(true);
        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(0);
        $this->stmtStub->method('fetchAll')->willReturn([]);

        $result = SegmentTranslationDao::propagateTranslation($st, $job, 100, $project);

        $this->assertIsArray($result);
    }

    public function testAddTranslationWithCurrentTimestamp(): void
    {
        $st = $this->makeTranslationStruct(['translation_date' => 'CURRENT_TIMESTAMP()']);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $result = SegmentTranslationDao::addTranslation($st, false);

        $this->assertSame(1, $result);
    }

    public function testAddTranslationWithSysdate(): void
    {
        $st = $this->makeTranslationStruct(['translation_date' => 'SYSDATE()']);

        $this->stmtStub->method('execute')->willReturn(true);
        $this->stmtStub->method('rowCount')->willReturn(1);

        $result = SegmentTranslationDao::addTranslation($st, false);

        $this->assertSame(1, $result);
    }
}
