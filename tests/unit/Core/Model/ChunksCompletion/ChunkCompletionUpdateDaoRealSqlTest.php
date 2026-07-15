<?php

namespace Matecat\Core\Model\ChunksCompletion;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\ChunksCompletion\ChunkCompletionUpdateDao;
use Model\ChunksCompletion\ChunkCompletionUpdateStruct;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Real-SQL characterization tests for ChunkCompletionUpdateDao (plan dao-realsql-90 v4, T13).
 *
 * Both public SQL methods (createOrUpdateFromStruct, updatePassword) are exercised directly
 * against the real unittest schema and asserted on round-tripped data (DoD b/d; M-3). The
 * INSERT and the `ON DUPLICATE KEY UPDATE` branch (unique key = id_job, password,
 * job_first_segment, job_last_segment, is_review) are both covered. Existing mock tests are
 * kept untouched.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class ChunkCompletionUpdateDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const ID_JOB     = 1900333333;
    private const ID_PROJECT = 1900444444;

    private ChunkCompletionUpdateDao $dao;

    /**
     * @return array<string,'autoincrement'|'assignable'>
     */
    protected function realSqlTableDeps(): array
    {
        return [
            'chunk_completion_updates' => 'autoincrement',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->realSqlSetUp();
        $this->dao = new ChunkCompletionUpdateDao($this->realSqlDb);
    }

    protected function tearDown(): void
    {
        $this->realSqlTearDown();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------------------------------

    private function makeUpdateStruct(string $password, bool $isReview = false): ChunkCompletionUpdateStruct
    {
        $struct                      = new ChunkCompletionUpdateStruct();
        $struct->id_project          = self::ID_PROJECT;
        $struct->id_job              = self::ID_JOB;
        $struct->password            = $password;
        $struct->job_first_segment   = 1;
        $struct->job_last_segment    = 99;
        $struct->source              = 'user';
        $struct->uid                 = 7777;
        $struct->is_review           = $isReview;
        $struct->last_translation_at = '2020-01-01 10:00:00';

        return $struct;
    }

    /**
     * @return array<string,mixed>|false
     */
    private function fetchByUniqueTuple(string $password, bool $isReview): array|false
    {
        $stmt = $this->realSqlDb->getConnection()->prepare(
            "SELECT * FROM chunk_completion_updates
             WHERE id_job = :j AND password = :p AND job_first_segment = 1
               AND job_last_segment = 99 AND is_review = :r"
        );
        $stmt->execute(['j' => self::ID_JOB, 'p' => $password, 'r' => (int)$isReview]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $row;
    }

    private function trackRow(string $password, bool $isReview): int
    {
        $row = $this->fetchByUniqueTuple($password, $isReview);
        $this->assertNotFalse($row, 'expected a chunk_completion_updates row to exist');
        $id = (int)$row['id'];
        $this->trackGeneratedId('chunk_completion_updates', $id);

        return $id;
    }

    // -----------------------------------------------------------------------------------------
    // tests
    // -----------------------------------------------------------------------------------------

    #[Test]
    public function dao_uses_the_injected_connection(): void
    {
        $this->assertInjectedConnection($this->dao);
    }

    #[Test]
    public function create_or_update_inserts_a_new_row(): void
    {
        $pw = 'u0insert1234567890123456789012';

        $this->assertTrue($this->dao->createOrUpdateFromStruct($this->makeUpdateStruct($pw)));
        $this->trackRow($pw, false);

        $row = $this->fetchByUniqueTuple($pw, false);
        $this->assertNotFalse($row);
        $this->assertSame(self::ID_JOB, (int)$row['id_job']);
        $this->assertSame(self::ID_PROJECT, (int)$row['id_project']);
        $this->assertSame('user', $row['source']);
        $this->assertSame(7777, (int)$row['uid']);
        $this->assertSame('2020-01-01 10:00:00', $row['last_translation_at']);
    }

    #[Test]
    public function create_or_update_updates_existing_row_on_duplicate_key(): void
    {
        $pw = 'u0update1234567890123456789012';

        // First call inserts.
        $this->assertTrue($this->dao->createOrUpdateFromStruct($this->makeUpdateStruct($pw)));
        $id = $this->trackRow($pw, false);

        // Second call with the same unique tuple but a changed mutable field hits
        // ON DUPLICATE KEY UPDATE (source/uid/is_review/last_translation_at).
        $changed                      = $this->makeUpdateStruct($pw);
        $changed->uid                 = 8888;
        $changed->last_translation_at = '2021-12-31 23:59:59';
        $this->assertTrue($this->dao->createOrUpdateFromStruct($changed));

        $row = $this->fetchByUniqueTuple($pw, false);
        $this->assertNotFalse($row);
        // Same physical row (id unchanged on update) -> proves UPDATE branch, not a 2nd INSERT.
        $this->assertSame($id, (int)$row['id']);
        $this->assertSame(8888, (int)$row['uid']);
        $this->assertSame('2021-12-31 23:59:59', $row['last_translation_at']);

        // And exactly one row exists for this tuple.
        $stmt = $this->realSqlDb->getConnection()->prepare(
            "SELECT COUNT(*) FROM chunk_completion_updates WHERE id_job = :j AND password = :p"
        );
        $stmt->execute(['j' => self::ID_JOB, 'p' => $pw]);
        $this->assertSame(1, (int)$stmt->fetchColumn());
        $stmt->closeCursor();
    }

    #[Test]
    public function update_password_repoints_matching_rows(): void
    {
        $old = 'u0oldpw12345678901234567890abc';
        $new = 'u0newpw12345678901234567890def';

        $this->assertTrue($this->dao->createOrUpdateFromStruct($this->makeUpdateStruct($old)));
        // Track under BOTH passwords: the row's password column will move from old -> new.
        $id = $this->trackRow($old, false);

        $affected = $this->dao->updatePassword(self::ID_JOB, $new, $old);
        $this->assertSame(1, $affected);

        // Round-trip: old gone, new present, same physical row (M-3: assert data not id-value).
        $this->assertFalse($this->fetchByUniqueTuple($old, false));
        $row = $this->fetchByUniqueTuple($new, false);
        $this->assertNotFalse($row);
        $this->assertSame($id, (int)$row['id']);
        $this->assertSame($new, $row['password']);
    }

    #[Test]
    public function update_password_affects_zero_rows_when_no_match(): void
    {
        $this->assertSame(
            0,
            $this->dao->updatePassword(self::ID_JOB, 'whatever1234567890', 'no-such-pw-1234567890')
        );
    }
}
