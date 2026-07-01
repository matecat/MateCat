<?php

namespace Matecat\Core\Model\WordCount;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\WordCount\WordCounterDao;
use Model\WordCount\WordCountStruct;
use PHPUnit\Framework\Attributes\Group;

/**
 * Real-SQL coverage for WordCounterDao (plan dao-realsql-90.md, Wave 5 / T12).
 *
 * WordCounterDao is NOT self-committing and opens NO transaction: updateWordCount() and
 * initializeWordCount() issue a single prepared UPDATE on `jobs`; getStatsForJob() is a
 * read-only multi-table JOIN (jobs INNER JOIN files_job INNER JOIN segments LEFT JOIN
 * segment_translations). Per C-1 the harness wraps NO transaction; cleanup is the seed-safe
 * id-list DELETE over the rows the builder created. The residue gate is the whole-table
 * COUNT(*) delta over every tableDep.
 *
 * Every public SQL method is called DIRECTLY and asserted on real returned data (DoD b). The
 * two write methods mutate fixture `jobs` rows the builder INSERTed (never a seeded job), so
 * the residue gate still returns to baseline once the fixture job is deleted. NO assertion on
 * absolute generated id values (M-3) - identity is verified by round-tripping the rows.
 *
 * Uses the dedicated RealSqlDaoTestTrait (S-4), NOT bare AbstractTest behaviour, so the 666
 * AbstractTest subclasses are unperturbed.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class WordCounterDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    /** Census tableDeps for WordCounterDao: jobs it UPDATEs + the JOIN chain it reads. */
    private const array TABLE_DEPS = ['jobs', 'files_job', 'files', 'projects', 'segments', 'segment_translations'];

    private WordCounterDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new WordCounterDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------------------------------

    /**
     * Build a project + file + files_job link + a job with zeroed word counters.
     *
     * @return array{id_project:int,id_file:int,id_job:int,password:string}
     */
    private function makeJobWithFile(array $jobOverrides = []): array
    {
        $project = $this->fixtures->makeProject();
        $file    = $this->fixtures->makeFile($project['id']);
        $job     = $this->fixtures->makeJob($project['id'], $jobOverrides);
        $this->fixtures->makeFilesJob($job['id'], $file['id']);

        return [
            'id_project' => $project['id'],
            'id_file'    => $file['id'],
            'id_job'     => $job['id'],
            'password'   => $job['password'],
        ];
    }

    private function newWordCountStruct(int $idJob, string $password, array $words = []): WordCountStruct
    {
        $s = new WordCountStruct();
        $s->setIdJob($idJob);
        $s->setJobPassword($password);
        $s->setNewWords((float)($words['new'] ?? 0));
        $s->setDraftWords((float)($words['draft'] ?? 0));
        $s->setTranslatedWords((float)($words['translated'] ?? 0));
        $s->setApprovedWords((float)($words['approved'] ?? 0));
        $s->setApproved2Words((float)($words['approved2'] ?? 0));
        $s->setRejectedWords((float)($words['rejected'] ?? 0));
        $s->setNewRawWords((float)($words['new_raw'] ?? 0));
        $s->setDraftRawWords((float)($words['draft_raw'] ?? 0));
        $s->setTranslatedRawWords((float)($words['translated_raw'] ?? 0));
        $s->setApprovedRawWords((float)($words['approved_raw'] ?? 0));
        $s->setApproved2RawWords((float)($words['approved2_raw'] ?? 0));
        $s->setRejectedRawWords((float)($words['rejected_raw'] ?? 0));

        return $s;
    }

    /** Read back the *_words counters for a job straight from the DB. */
    private function readJobWords(int $idJob): array
    {
        $stmt = $this->realSqlDb()->getConnection()->prepare(
            'SELECT new_words, draft_words, translated_words, approved_words, approved2_words,
                    rejected_words, new_raw_words, draft_raw_words, translated_raw_words,
                    approved_raw_words, approved2_raw_words, rejected_raw_words
             FROM jobs WHERE id = :id'
        );
        $stmt->execute(['id' => $idJob]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------------------------------
    // updateWordCount (incremental UPDATE)
    // -----------------------------------------------------------------------------------------

    public function testUpdateWordCountIncrementsCountersAndReturnsAffectedRows(): void
    {
        $ctx    = $this->makeJobWithFile();
        $struct = $this->newWordCountStruct($ctx['id_job'], $ctx['password'], [
            'new'        => 10,
            'draft'      => 5,
            'translated' => 3,
            'new_raw'    => 20,
        ]);

        $affected = $this->dao->updateWordCount($struct);

        $this->assertSame(1, $affected, 'one job row should be updated');

        $row = $this->readJobWords($ctx['id_job']);
        $this->assertSame(10.0, (float)$row['new_words']);
        $this->assertSame(5.0, (float)$row['draft_words']);
        $this->assertSame(3.0, (float)$row['translated_words']);
        $this->assertSame(20.0, (float)$row['new_raw_words']);
    }

    public function testUpdateWordCountIsCumulativeAcrossCalls(): void
    {
        $ctx = $this->makeJobWithFile();

        $this->dao->updateWordCount($this->newWordCountStruct($ctx['id_job'], $ctx['password'], ['new' => 4]));
        $this->dao->updateWordCount($this->newWordCountStruct($ctx['id_job'], $ctx['password'], ['new' => 6]));

        $row = $this->readJobWords($ctx['id_job']);
        $this->assertSame(10.0, (float)$row['new_words'], 'increments must accumulate (col = col + :v)');
    }

    public function testUpdateWordCountWrongPasswordAffectsNoRow(): void
    {
        $ctx    = $this->makeJobWithFile();
        $struct = $this->newWordCountStruct($ctx['id_job'], 'wrong-password-xyz', ['new' => 99]);

        $affected = $this->dao->updateWordCount($struct);

        $this->assertSame(0, $affected, 'password mismatch in WHERE => 0 rows updated');
        $row = $this->readJobWords($ctx['id_job']);
        $this->assertSame(0.0, (float)$row['new_words']);
    }

    // -----------------------------------------------------------------------------------------
    // initializeWordCount (absolute UPDATE via Database::update)
    // -----------------------------------------------------------------------------------------

    public function testInitializeWordCountSetsAbsoluteValues(): void
    {
        $ctx = $this->makeJobWithFile();

        // seed a non-zero starting state so we can prove initialize OVERWRITES (not adds).
        $this->dao->updateWordCount($this->newWordCountStruct($ctx['id_job'], $ctx['password'], ['new' => 100]));

        $struct = $this->newWordCountStruct($ctx['id_job'], $ctx['password'], [
            'new'        => 7,
            'translated' => 2,
            'new_raw'    => 11,
        ]);

        $affected = $this->dao->initializeWordCount($struct);

        $this->assertSame(1, $affected, 'one job row should be initialized');

        $row = $this->readJobWords($ctx['id_job']);
        $this->assertSame(7.0, (float)$row['new_words'], 'initialize must set absolute, not add');
        $this->assertSame(2.0, (float)$row['translated_words']);
        $this->assertSame(11.0, (float)$row['new_raw_words']);
    }

    public function testInitializeWordCountWrongPasswordAffectsNoRow(): void
    {
        $ctx    = $this->makeJobWithFile();
        $struct = $this->newWordCountStruct($ctx['id_job'], 'nope-password', ['new' => 50]);

        $affected = $this->dao->initializeWordCount($struct);

        $this->assertSame(0, $affected, 'password mismatch => 0 rows updated');
    }

    // -----------------------------------------------------------------------------------------
    // getStatsForJob (read-only multi-table JOIN aggregation)
    // -----------------------------------------------------------------------------------------

    public function testGetStatsForJobAggregatesSegmentTranslationsByStatus(): void
    {
        $project = $this->fixtures->makeProject();
        $file    = $this->fixtures->makeFile($project['id']);

        // Three segments with known raw word counts.
        $segA = $this->fixtures->makeSegmentWithWords($file['id'], 10.0);
        $segB = $this->fixtures->makeSegmentWithWords($file['id'], 20.0);
        $segC = $this->fixtures->makeSegmentWithWords($file['id'], 30.0);

        // Job window must contain all three segment ids (s.id BETWEEN first AND last).
        $first = min($segA['id'], $segB['id'], $segC['id']);
        $last  = max($segA['id'], $segB['id'], $segC['id']);
        $job   = $this->fixtures->makeJob($project['id'], [
            'job_first_segment' => $first,
            'job_last_segment'  => $last,
        ]);
        $this->fixtures->makeFilesJob($job['id'], $file['id']);

        // Translations: A=TRANSLATED(eq 8), B=APPROVED(eq 18), C left without a row (NULL => NEW bucket).
        $this->fixtures->makeSegmentTranslationWithWords($segA['id'], $job['id'], 8.0, 'TRANSLATED');
        $this->fixtures->makeSegmentTranslationWithWords($segB['id'], $job['id'], 18.0, 'APPROVED');

        $stats = $this->dao->getStatsForJob($job['id']);

        $this->assertCount(1, $stats, 'GROUP BY j.id => exactly one aggregate row');
        $row = $stats[0];

        $this->assertSame($job['id'], (int)$row['id']);
        // eq_word_count buckets.
        $this->assertSame(26.0, (float)$row['TOTAL'], 'TOTAL eq = 8 + 18 (C has no eq row => 0)');
        $this->assertSame(8.0, (float)$row['TRANSLATED']);
        $this->assertSame(18.0, (float)$row['APPROVED']);
        // C has NULL status => counts into NEW bucket (eq is 0 because no st row).
        $this->assertSame(0.0, (float)$row['NEW']);
        // raw_word_count buckets sum over segments.
        $this->assertSame(60.0, (float)$row['TOTAL_RAW'], 'TOTAL_RAW = 10 + 20 + 30');
        $this->assertSame(10.0, (float)$row['TRANSLATED_RAW']);
        $this->assertSame(20.0, (float)$row['APPROVED_RAW']);
        $this->assertSame(30.0, (float)$row['NEW_RAW'], 'C has NULL status => NEW_RAW bucket');
    }

    public function testGetStatsForJobFiltersByPassword(): void
    {
        $project = $this->fixtures->makeProject();
        $file    = $this->fixtures->makeFile($project['id']);
        $seg     = $this->fixtures->makeSegmentWithWords($file['id'], 5.0);
        $job     = $this->fixtures->makeJob($project['id'], [
            'job_first_segment' => $seg['id'],
            'job_last_segment'  => $seg['id'],
        ]);
        $this->fixtures->makeFilesJob($job['id'], $file['id']);
        $this->fixtures->makeSegmentTranslationWithWords($seg['id'], $job['id'], 5.0, 'TRANSLATED');

        // Correct password => one aggregate row.
        $ok = $this->dao->getStatsForJob($job['id'], null, $job['password']);
        $this->assertCount(1, $ok);
        $this->assertSame(5.0, (float)$ok[0]['TOTAL_RAW']);

        // Wrong password => the WHERE j.password predicate yields no rows; SUM over empty set
        // returns a single all-NULL aggregate row.
        $none = $this->dao->getStatsForJob($job['id'], null, 'wrong-pass');
        $this->assertCount(1, $none);
        $this->assertNull($none[0]['id'], 'no matching job => aggregate id is NULL');
    }

    public function testGetStatsForJobFiltersByIdFile(): void
    {
        $project = $this->fixtures->makeProject();
        $fileA   = $this->fixtures->makeFile($project['id']);
        $fileB   = $this->fixtures->makeFile($project['id']);
        $segA    = $this->fixtures->makeSegmentWithWords($fileA['id'], 12.0);
        $segB    = $this->fixtures->makeSegmentWithWords($fileB['id'], 99.0);

        $first = min($segA['id'], $segB['id']);
        $last  = max($segA['id'], $segB['id']);
        $job   = $this->fixtures->makeJob($project['id'], [
            'job_first_segment' => $first,
            'job_last_segment'  => $last,
        ]);
        $this->fixtures->makeFilesJob($job['id'], $fileA['id']);
        $this->fixtures->makeFilesJob($job['id'], $fileB['id']);
        $this->fixtures->makeSegmentTranslationWithWords($segA['id'], $job['id'], 12.0, 'TRANSLATED');
        $this->fixtures->makeSegmentTranslationWithWords($segB['id'], $job['id'], 99.0, 'TRANSLATED');

        // Restrict to fileA only => only the 12.0 raw segment is counted.
        $stats = $this->dao->getStatsForJob($job['id'], $fileA['id']);

        $this->assertCount(1, $stats);
        $this->assertSame(12.0, (float)$stats[0]['TOTAL_RAW'], 'id_file filter restricts to fileA');
    }

    public function testGetStatsForJobUnknownJobReturnsNullAggregate(): void
    {
        // A job id that exists in no fixture and is above the seeded band.
        $stats = $this->dao->getStatsForJob(1_999_999_999);

        $this->assertCount(1, $stats, 'aggregate query always yields exactly one row');
        $this->assertNull($stats[0]['id'], 'no rows matched => NULL aggregate');
    }
}
