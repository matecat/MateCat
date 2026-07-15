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
}
