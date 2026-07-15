<?php

namespace Matecat\Core\Model\ChunksCompletion;

use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\ChunksCompletion\ChunkCompletionEventDao;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\ChunksCompletion\ChunkCompletionUpdateDao;
use Model\ChunksCompletion\ChunkCompletionUpdateStruct;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionObject;

/**
 * Real-SQL characterization tests for ChunkCompletionEventDao (plan dao-realsql-90 v4, T13).
 *
 * Every public SQL method is called DIRECTLY against the real unittest schema and asserted on
 * the round-tripped data (DoD b/d; M-3: never asserts absolute generated id VALUES). Existing
 * mock tests are kept untouched; these are additive.
 *
 * Harness contract: RealSqlDaoTestTrait provides the fail-closed guard (C1/S-2/X-3), one
 * dedicated connection per test (C-2), id-scoped seed-safe DELETE cleanup with NO wrapping
 * transaction (C-1/M-1/M-2), whole-table COUNT(*) residue gate (A-1/A-2), flushDaoCache +
 * static reset (M-4/M-5).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class ChunkCompletionEventDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const ID_JOB     = 1900111111;
    private const ID_PROJECT = 1900222222;

    private ChunkCompletionEventDao $dao;

    /**
     * @return array<string,'autoincrement'|'assignable'>
     */
    protected function realSqlTableDeps(): array
    {
        // Both tables are AUTO_INCREMENT id -> track generated ids for DELETE (M-2).
        // chunk_completion_updates is a dep of lastCompletionRecord()'s LEFT JOIN.
        return [
            'chunk_completion_events'  => 'autoincrement',
            'chunk_completion_updates' => 'autoincrement',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();
        $this->dao = new ChunkCompletionEventDao($this->realSqlDb);
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------------------------------

    /**
     * Build a JobStruct whose getProject() is pre-memoized so createFromChunk() resolves
     * id_project WITHOUT a `projects` DB row (keeps tableDeps to the two chunk tables).
     */
    private function makeChunk(string $password): JobStruct
    {
        $chunk                    = new JobStruct();
        $chunk->id                = self::ID_JOB;
        $chunk->id_project        = self::ID_PROJECT;
        $chunk->password          = $password;
        $chunk->job_first_segment = 1;
        $chunk->job_last_segment  = 50;

        $project     = new ProjectStruct();
        $project->id = self::ID_PROJECT;

        // Pre-seed the memoize cache (key = JobStruct::getProject) so no ProjectDao query runs.
        $ref  = new ReflectionObject($chunk);
        $prop = $ref->getProperty('cached_results');
        $prop->setAccessible(true);
        $prop->setValue($chunk, [JobStruct::class . '::getProject' => $project]);

        return $chunk;
    }

    private function makeCompletionEventParams(bool $isReview): CompletionEventStruct
    {
        $params                    = new CompletionEventStruct();
        $params->uid               = 4242;
        $params->remote_ip_address = '127.0.0.1';
        $params->source            = 'user';
        $params->is_review         = $isReview;

        return $params;
    }

    /**
     * Insert one event via the DAO and return its generated id (tracked for cleanup).
     */
    private function insertEvent(JobStruct $chunk, bool $isReview): int
    {
        $id = (int)$this->dao->createFromChunk($chunk, $this->makeCompletionEventParams($isReview));
        $this->trackGeneratedId('chunk_completion_events', $id);

        return $id;
    }

    // -----------------------------------------------------------------------------------------
    // tests
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function dao_uses_the_injected_connection(): void
    {
        // C-2: DAO must hold the dedicated per-test connection, not the no-arg singleton.
        $this->assertInjectedConnection($this->dao);
    }

    #[Test]
    public function valid_sources_maps_to_struct_constants(): void
    {
        $sources = $this->dao->validSources();

        $this->assertSame(ChunkCompletionEventStruct::SOURCE_USER, $sources['user']);
        $this->assertSame(ChunkCompletionEventStruct::SOURCE_MERGE, $sources['merge']);
    }

    #[Test]
    public function create_from_chunk_persists_and_returns_round_trippable_row(): void
    {
        $chunk = $this->makeChunk('p0create1234567890123456789012');
        $id    = $this->insertEvent($chunk, false);

        $this->assertGreaterThan(0, $id, 'createFromChunk must return a generated id');

        // M-3: assert on round-tripped data, not the absolute id value.
        $row = $this->dao->getByIdAndChunk($id, $chunk);
        $this->assertInstanceOf(ChunkCompletionEventStruct::class, $row);
        $this->assertSame(self::ID_JOB, $row->id_job);
        $this->assertSame(self::ID_PROJECT, $row->id_project);
        $this->assertSame($chunk->password, $row->password);
        $this->assertSame(ChunkCompletionEventStruct::SOURCE_USER, $row->source);
        $this->assertSame(1, $row->job_first_segment);
        $this->assertSame(50, $row->job_last_segment);
        $this->assertSame(4242, $row->uid);
        $this->assertFalse($row->is_review);
    }

    #[Test]
    public function get_by_id_and_chunk_returns_false_when_not_found(): void
    {
        $chunk = $this->makeChunk('p0missing12345678901234567890');

        // No row inserted -> false.
        $this->assertFalse($this->dao->getByIdAndChunk(2147480000, $chunk));
    }

    #[Test]
    public function get_by_id_and_chunk_filters_by_job_and_password(): void
    {
        $chunk = $this->makeChunk('p0filter1234567890123456789012');
        $id    = $this->insertEvent($chunk, false);

        // Wrong password -> not found.
        $wrong           = $this->makeChunk('WRONGpw1234567890123456789012');
        $wrong->id       = self::ID_JOB;
        $this->assertFalse($this->dao->getByIdAndChunk($id, $wrong));

        // Correct chunk -> found.
        $this->assertInstanceOf(
            ChunkCompletionEventStruct::class,
            $this->dao->getByIdAndChunk($id, $chunk)
        );
    }

    #[Test]
    public function update_password_rewrites_matching_rows(): void
    {
        $old   = 'p0oldpw12345678901234567890ab';
        $new   = 'p0newpw12345678901234567890cd';
        $chunk = $this->makeChunk($old);
        $id    = $this->insertEvent($chunk, false);

        $affected = $this->dao->updatePassword(self::ID_JOB, $new, $old);
        $this->assertSame(1, $affected, 'exactly one row should be repointed');

        // Old password no longer resolves; new one does (round-trip, not id-value).
        $this->assertFalse($this->dao->getByIdAndChunk($id, $chunk));

        $rechunk           = $this->makeChunk($new);
        $found             = $this->dao->getByIdAndChunk($id, $rechunk);
        $this->assertInstanceOf(ChunkCompletionEventStruct::class, $found);
        $this->assertSame($new, $found->password);
    }

    #[Test]
    public function delete_event_removes_the_row_and_reports_count(): void
    {
        $chunk = $this->makeChunk('p0delete1234567890123456789012');
        $id    = $this->insertEvent($chunk, false);

        $event     = new ChunkCompletionEventStruct();
        $event->id = $id;

        $deleted = $this->dao->deleteEvent($event);
        $this->assertSame(1, $deleted);

        // Already gone -> the row is no longer fetchable, and a second delete affects 0 rows.
        $this->assertFalse($this->dao->getByIdAndChunk($id, $chunk));
        $this->assertSame(0, $this->dao->deleteEvent($event));
    }

    #[Test]
    public function last_completion_record_returns_empty_when_none(): void
    {
        $chunk = $this->makeChunk('p0nolast1234567890123456789012');

        $this->assertSame([], $this->dao->lastCompletionRecord($chunk, ['is_review' => false]));
    }

    #[Test]
    public function last_completion_record_returns_most_recent_translate_event(): void
    {
        $chunk = $this->makeChunk('p0lastrec123456789012345678901');
        $id    = $this->insertEvent($chunk, false);

        $record = $this->dao->lastCompletionRecord($chunk, ['is_review' => false]);
        $this->assertNotSame([], $record);
        $this->assertSame(self::ID_JOB, (int)$record['id_job']);
        $this->assertSame($chunk->password, $record['password']);
        $this->assertSame($id, (int)$record['id_event']);
        // is_review stored as tinyint -> 0
        $this->assertSame(0, (int)$record['is_review']);
    }

    #[Test]
    public function last_completion_record_is_suppressed_by_a_newer_update_row(): void
    {
        $chunk = $this->makeChunk('p0suppr1234567890123456789012a');

        // A translate completion event exists ...
        $this->insertEvent($chunk, false);

        // ... but an update row with a FUTURE last_translation_at invalidates it
        // (events.create_date > updates.last_translation_at must be false).
        $updateDao = new ChunkCompletionUpdateDao($this->realSqlDb);
        $struct                      = new ChunkCompletionUpdateStruct();
        $struct->id_project          = self::ID_PROJECT;
        $struct->id_job              = self::ID_JOB;
        $struct->password            = $chunk->password;
        $struct->job_first_segment   = 1;
        $struct->job_last_segment    = 50;
        $struct->source              = 'user';
        $struct->uid                 = 4242;
        $struct->is_review           = false;
        $struct->last_translation_at = '2999-01-01 00:00:00';
        $this->assertTrue($updateDao->createOrUpdateFromStruct($struct));
        $this->trackInsertedUpdateRow($chunk->password);

        $record = $this->dao->lastCompletionRecord($chunk, ['is_review' => false]);
        $this->assertSame([], $record, 'a newer update row must suppress the completion record');
    }

    #[Test]
    public function current_phase_defaults_to_translate_without_records(): void
    {
        $chunk = $this->makeChunk('p0phasenone12345678901234567890');

        $this->assertSame(ChunkCompletionEventDao::TRANSLATE, $this->dao->currentPhase($chunk));
    }

    #[Test]
    public function current_phase_reports_revise_after_a_translate_completion(): void
    {
        $chunk = $this->makeChunk('p0phaserev123456789012345678901');

        // A translate completion with no later revise completion -> phase is REVISE
        // (the translate step is done, revision is the current outstanding phase).
        $this->insertEvent($chunk, false);

        $this->assertSame(ChunkCompletionEventDao::REVISE, $this->dao->currentPhase($chunk));
    }

    #[Test]
    public function current_phase_reports_translate_when_revise_is_more_recent(): void
    {
        $chunk = $this->makeChunk('p0phasetr123456789012345678901');

        // A translate completion ... then a strictly LATER revise completion. With the revise
        // record newer than the translate record, currentPhase() returns TRANSLATE (the revise
        // step is done; translation is the current outstanding phase).
        $translateId = $this->insertEvent($chunk, false);

        // Force the translate event's create_date to be older than the revise event so the
        // DateTime comparison is deterministic at second precision.
        $back = $this->realSqlDb->getConnection()->prepare(
            "UPDATE chunk_completion_events SET create_date = :d WHERE id = :id"
        );
        $back->execute(['d' => '2000-01-01 00:00:00', 'id' => $translateId]);
        $back->closeCursor();

        $this->insertEvent($chunk, true); // revise completion, "now"

        $this->assertSame(ChunkCompletionEventDao::TRANSLATE, $this->dao->currentPhase($chunk));
    }

    /**
     * Track the AUTO_INCREMENT id of the chunk_completion_updates row just inserted for $password
     * so the residue gate stays clean.
     */
    private function trackInsertedUpdateRow(string $password): void
    {
        $stmt = $this->realSqlDb->getConnection()->prepare(
            "SELECT id FROM chunk_completion_updates WHERE id_job = :j AND password = :p"
        );
        $stmt->execute(['j' => self::ID_JOB, 'p' => $password]);
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            $this->trackGeneratedId('chunk_completion_updates', (int)$id);
        }
        $stmt->closeCursor();
    }
}
