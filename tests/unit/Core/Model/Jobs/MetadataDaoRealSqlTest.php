<?php

namespace Matecat\Core\Model\Jobs;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\MetadataDao;
use Model\Jobs\MetadataStruct;
use PHPUnit\Framework\Attributes\Group;

/**
 * Real-SQL coverage for Model\Jobs\MetadataDao (plan dao-realsql-90.md, Wave 2 / T2).
 *
 * Every public SQL method is invoked DIRECTLY against the live job_metadata table and asserted
 * on real returned data (DoD b). set()/bulkSet() use the transactional trait (openTransaction /
 * commitTransaction) on the SAME singleton connection the trait seeds (C-2), so NO wrapping
 * test transaction is used (C-1). Rows the DAO INSERTs are registered for cleanup via
 * trackExisting() so the whole-table COUNT(*) residue gate over job_metadata returns to
 * baseline (A-1/A-2/AC-1). No assertion is made on absolute generated ids (M-3).
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class MetadataDaoRealSqlTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private const array TABLE_DEPS = ['job_metadata'];

    private MetadataDao $dao;
    private int $idJob;
    private string $password;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertDbWriteGuard();
        $this->startRealSql(self::TABLE_DEPS);

        $this->dao = new MetadataDao($this->realSqlDb());
        $this->assertDaoUsesTestConnection($this->dao);

        // job_metadata has no FK; an arbitrary (id_job,password) pair scopes the rows.
        $this->idJob = $this->fixtures->nextAssignableId();
        $this->password = 'pw_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->finishRealSql();
        parent::tearDown();
    }

    /** Register a (id_job,password,key) row the DAO INSERTed so cleanup returns to baseline. */
    private function track(string $key): void
    {
        $this->fixtures->trackExisting('job_metadata', [
            'id_job'   => $this->idJob,
            'password' => $this->password,
            'key'      => $key,
        ]);
    }

    public function testSetInsertsAndReturnsStruct(): void
    {
        $struct = $this->dao->set($this->idJob, $this->password, 'speed', 'fast');
        $this->track('speed');

        self::assertInstanceOf(MetadataStruct::class, $struct);
        self::assertSame('speed', $struct->key);
        self::assertSame('fast', $struct->value);
        self::assertSame($this->idJob, (int)$struct->id_job);
    }

    public function testSetUpsertsExistingKey(): void
    {
        $this->dao->set($this->idJob, $this->password, 'speed', 'fast');
        $this->track('speed');

        $updated = $this->dao->set($this->idJob, $this->password, 'speed', 'slow');

        self::assertInstanceOf(MetadataStruct::class, $updated);
        self::assertSame('slow', $updated->value);
        // still a single row for this key
        self::assertSame('slow', $this->dao->get($this->idJob, $this->password, 'speed')->value);
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        self::assertNull($this->dao->get($this->idJob, $this->password, 'nope'));
    }

    public function testGetByIdJobReturnsRowsForKey(): void
    {
        $this->dao->set($this->idJob, $this->password, 'colour', 'blue');
        $this->track('colour');

        $rows = $this->dao->getByIdJob($this->idJob, 'colour');

        self::assertCount(1, $rows);
        self::assertSame('blue', $rows[0]->value);
    }

    public function testGetByJobIdAndPasswordReturnsUnmarshalledRows(): void
    {
        $this->dao->set($this->idJob, $this->password, JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value, '25');
        $this->track(JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value);

        $rows = $this->dao->getByJobIdAndPassword($this->idJob, $this->password);

        self::assertCount(1, $rows);
        // PUBLIC_TM_PENALTY is unmarshalled to int by the marshaller.
        self::assertSame(25, $rows[0]->value);
    }

    public function testBulkSetInsertsMultipleKeys(): void
    {
        $this->dao->bulkSet($this->idJob, $this->password, [
            'k1' => 'v1',
            'k2' => 'v2',
        ]);
        $this->track('k1');
        $this->track('k2');

        self::assertSame('v1', $this->dao->get($this->idJob, $this->password, 'k1')->value);
        self::assertSame('v2', $this->dao->get($this->idJob, $this->password, 'k2')->value);
    }

    public function testBulkSetEmptyIsNoOp(): void
    {
        $this->dao->bulkSet($this->idJob, $this->password, []);

        self::assertSame([], $this->dao->getByJobIdAndPassword($this->idJob, $this->password));
    }

    public function testDeleteRemovesRow(): void
    {
        $this->dao->set($this->idJob, $this->password, 'temp', 'x');
        $this->track('temp'); // DELETE in test cleanup is idempotent even if already gone

        $this->dao->delete($this->idJob, $this->password, 'temp');

        self::assertNull($this->dao->get($this->idJob, $this->password, 'temp'));
    }

    public function testDestroyCacheByJobId(): void
    {
        $this->dao->set($this->idJob, $this->password, 'cachekey', 'v');
        $this->track('cachekey');
        $this->dao->getByIdJob($this->idJob, 'cachekey', 3600); // prime cache (ttl>0)

        self::assertTrue($this->dao->destroyCacheByJobId($this->idJob, 'cachekey'));
    }

    public function testDestroyCacheByJobAndPassword(): void
    {
        $this->dao->set($this->idJob, $this->password, 'k', 'v');
        $this->track('k');
        $this->dao->getByJobIdAndPassword($this->idJob, $this->password, 3600); // prime cache (ttl>0)

        self::assertTrue($this->dao->destroyCacheByJobAndPassword($this->idJob, $this->password));
    }

    public function testDestroyCacheByJobAndPasswordAndKey(): void
    {
        $this->dao->set($this->idJob, $this->password, 'k', 'v');
        $this->track('k');
        $this->dao->get($this->idJob, $this->password, 'k', 3600); // prime cache (ttl>0)

        self::assertTrue($this->dao->destroyCacheByJobAndPasswordAndKey($this->idJob, $this->password, 'k'));
    }

    public function testGetSubfilteringCustomHandlersReturnsDecodedJson(): void
    {
        $this->dao->set(
            $this->idJob,
            $this->password,
            JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value,
            json_encode(['handlerA', 'handlerB'])
        );
        $this->track(JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value);

        $handlers = $this->dao->getSubfilteringCustomHandlers($this->idJob, $this->password);

        self::assertSame(['handlerA', 'handlerB'], $handlers);
    }

    public function testGetSubfilteringCustomHandlersDefaultsToEmptyArray(): void
    {
        // no metadata set -> get() returns null -> json_decode('[]') -> []
        self::assertSame([], $this->dao->getSubfilteringCustomHandlers($this->idJob, $this->password));
    }

    // ────────────────────────────────────────────────────────────────
    // Carrying a chunk's metadata across a new password
    // ────────────────────────────────────────────────────────────────

    /** Register a row written under a password other than the test's own. */
    private function trackAt(string $password, string $key): void
    {
        $this->fixtures->trackExisting('job_metadata', [
            'id_job'   => $this->idJob,
            'password' => $password,
            'key'      => $key,
        ]);
    }

    /**
     * The map is what a copy is built from, so its values have to be the text that was stored.
     * getByJobIdAndPassword() cannot serve that: it runs everything through
     * JobsMetadataMarshaller::unMarshall(), which turns '1' into true and a JSON list into an array.
     * Writing either of those back would corrupt the row.
     */
    public function testGetRawMapByJobIdAndPasswordReturnsValuesVerbatim(): void
    {
        $this->dao->set($this->idJob, $this->password, JobsMetadataMarshaller::DIALECT_STRICT->value, '1');
        $this->track(JobsMetadataMarshaller::DIALECT_STRICT->value);
        $this->dao->set($this->idJob, $this->password, JobsMetadataMarshaller::MANDATORY_ISSUES->value, '["r1","r2"]');
        $this->track(JobsMetadataMarshaller::MANDATORY_ISSUES->value);

        $map = $this->dao->getRawMapByJobIdAndPassword($this->idJob, $this->password);

        self::assertSame([
            JobsMetadataMarshaller::DIALECT_STRICT->value   => '1',
            JobsMetadataMarshaller::MANDATORY_ISSUES->value => '["r1","r2"]',
        ], $map);

        // The contrast that makes the point: the same rows through the unmarshalling read.
        $unmarshalled = [];
        foreach ($this->dao->getByJobIdAndPassword($this->idJob, $this->password) as $row) {
            $unmarshalled[(string)$row->key] = $row->value;
        }
        self::assertTrue($unmarshalled[JobsMetadataMarshaller::DIALECT_STRICT->value]);
        self::assertSame(['r1', 'r2'], $unmarshalled[JobsMetadataMarshaller::MANDATORY_ISSUES->value]);
    }

    /**
     * The read binds the password, which is what keeps MMT's MT context out of a copy: it is stored
     * under the empty password and read back by (id_job, key) alone, so it already belongs to every
     * chunk of the job. Duplicating it per chunk would leave getByIdJob() picking from N rows.
     */
    public function testGetRawMapByJobIdAndPasswordIgnoresOtherChunksAndThePasswordLessRow(): void
    {
        $sibling = 'pw_' . bin2hex(random_bytes(4));

        $this->dao->set($this->idJob, $this->password, 'colour', 'blue');
        $this->track('colour');
        $this->dao->set($this->idJob, $sibling, 'colour', 'red');
        $this->trackAt($sibling, 'colour');
        $this->dao->set($this->idJob, '', 'mt_context', 'ctx-1');
        $this->trackAt('', 'mt_context');

        self::assertSame(['colour' => 'blue'], $this->dao->getRawMapByJobIdAndPassword($this->idJob, $this->password));
    }

    public function testDeleteByJobIdAndPasswordRemovesEveryKeyOfThatChunkOnly(): void
    {
        $sibling = 'pw_' . bin2hex(random_bytes(4));

        foreach (['colour', 'speed', JobsMetadataMarshaller::DIALECT_STRICT->value] as $key) {
            $this->dao->set($this->idJob, $this->password, $key, 'v');
            $this->track($key);
        }
        $this->dao->set($this->idJob, $sibling, 'colour', 'survivor');
        $this->trackAt($sibling, 'colour');

        $this->dao->deleteByJobIdAndPassword($this->idJob, $this->password);

        self::assertSame([], $this->dao->getRawMapByJobIdAndPassword($this->idJob, $this->password));
        self::assertSame(['colour' => 'survivor'], $this->dao->getRawMapByJobIdAndPassword($this->idJob, $sibling));
    }

    /**
     * Primed at a real TTL first: with nothing cached this would pass for the wrong reason.
     */
    public function testDeleteByJobIdAndPasswordEvictsEveryAddressItRemoved(): void
    {
        $this->flushDaoCache();
        $this->dao->set($this->idJob, $this->password, 'colour', 'blue');
        $this->track('colour');

        self::assertSame('blue', $this->dao->get($this->idJob, $this->password, 'colour', 3600)->value);
        self::assertSame('blue', $this->dao->getByIdJob($this->idJob, 'colour', 3600)[0]->value);
        self::assertNotSame([], $this->daoCacheRedis()->keys('*'), 'the reads have to be cached for this to prove anything');

        $this->dao->deleteByJobIdAndPassword($this->idJob, $this->password);

        self::assertNull($this->dao->get($this->idJob, $this->password, 'colour', 3600));
        self::assertSame([], $this->dao->getByIdJob($this->idJob, 'colour', 3600));
    }

    /**
     * The regression guard for a rotated credential. JobDao::changePassword() renames the job
     * password in place; without the move the rows stay at a password nothing resolves any more and
     * the job loses every setting it had.
     */
    public function testMovePasswordCarriesEveryKeyOntoTheNewPassword(): void
    {
        $newPassword = 'pw_' . bin2hex(random_bytes(4));

        $stored = [
            JobsMetadataMarshaller::DIALECT_STRICT->value    => '1',
            JobsMetadataMarshaller::MANDATORY_ISSUES->value  => '["r2"]',
            JobsMetadataMarshaller::PUBLIC_TM_PENALTY->value => '25',
        ];

        foreach ($stored as $key => $value) {
            $this->dao->set($this->idJob, $this->password, $key, $value);
            $this->trackAt($newPassword, $key);
        }

        self::assertSame(3, $this->dao->movePassword($this->idJob, $this->password, $newPassword));

        self::assertSame([], $this->dao->getRawMapByJobIdAndPassword($this->idJob, $this->password));
        self::assertSame($stored, $this->dao->getRawMapByJobIdAndPassword($this->idJob, $newPassword));
    }

    public function testMovePasswordLeavesThePasswordLessRowAndOtherChunksAlone(): void
    {
        $newPassword = 'pw_' . bin2hex(random_bytes(4));
        $sibling     = 'pw_' . bin2hex(random_bytes(4));

        $this->dao->set($this->idJob, $this->password, 'colour', 'blue');
        $this->trackAt($newPassword, 'colour');
        $this->dao->set($this->idJob, $sibling, 'colour', 'red');
        $this->trackAt($sibling, 'colour');
        $this->dao->set($this->idJob, '', 'mt_context', 'ctx-1');
        $this->trackAt('', 'mt_context');

        $this->dao->movePassword($this->idJob, $this->password, $newPassword);

        self::assertSame(['colour' => 'blue'], $this->dao->getRawMapByJobIdAndPassword($this->idJob, $newPassword));
        self::assertSame(['colour' => 'red'], $this->dao->getRawMapByJobIdAndPassword($this->idJob, $sibling));
        self::assertSame(['mt_context' => 'ctx-1'], $this->dao->getRawMapByJobIdAndPassword($this->idJob, ''));
    }

    /**
     * Both ends are evicted: the address the rows left, and the one they arrived at, where a lookup
     * made before the rotation may have cached the miss it found there.
     */
    public function testMovePasswordEvictsBothEndsOfTheMove(): void
    {
        $newPassword = 'pw_' . bin2hex(random_bytes(4));

        $this->flushDaoCache();
        $this->dao->set($this->idJob, $this->password, 'colour', 'blue');
        $this->trackAt($newPassword, 'colour');

        self::assertSame('blue', $this->dao->get($this->idJob, $this->password, 'colour', 3600)->value);
        self::assertNull($this->dao->get($this->idJob, $newPassword, 'colour', 3600), 'primes the miss at the destination');
        self::assertNotSame([], $this->daoCacheRedis()->keys('*'), 'the reads have to be cached for this to prove anything');

        $this->dao->movePassword($this->idJob, $this->password, $newPassword);

        self::assertNull($this->dao->get($this->idJob, $this->password, 'colour', 3600));
        self::assertSame('blue', $this->dao->get($this->idJob, $newPassword, 'colour', 3600)->value);
    }

    public function testMovePasswordIsANoOpWhenTheCredentialDidNotChange(): void
    {
        $this->dao->set($this->idJob, $this->password, 'colour', 'blue');
        $this->track('colour');

        self::assertSame(0, $this->dao->movePassword($this->idJob, $this->password, $this->password));
        self::assertSame(['colour' => 'blue'], $this->dao->getRawMapByJobIdAndPassword($this->idJob, $this->password));
    }

    public function testMovePasswordReportsNothingMovedWhenTheChunkHasNoMetadata(): void
    {
        self::assertSame(0, $this->dao->movePassword($this->idJob, $this->password, 'pw_' . bin2hex(random_bytes(4))));
    }

    /**
     * The empty password is not a credential anything rotates: it is where MMT keeps the MT context,
     * shared by every chunk. Moving it would take the context away from the chunks that stayed put.
     */
    public function testMovePasswordRefusesToMoveTheEmptyPasswordAddress(): void
    {
        $this->dao->set($this->idJob, '', 'mt_context', 'ctx-1');
        $this->trackAt('', 'mt_context');

        self::assertSame(0, $this->dao->movePassword($this->idJob, '', $this->password));

        self::assertSame(['mt_context' => 'ctx-1'], $this->dao->getRawMapByJobIdAndPassword($this->idJob, ''));
        self::assertSame([], $this->dao->getRawMapByJobIdAndPassword($this->idJob, $this->password));
    }
}
