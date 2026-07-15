<?php

namespace Matecat\Core\Model\RealSqlHarness;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use PHPUnit\Framework\Attributes\Group;
use Utils\Registry\AppConfig;

/**
 * Self-test for the RealSqlDaoTestTrait harness (plan dao-realsql-90.md Wave 1 / T1):
 * flush-index identity (C3/S-5) — flushDaoCache targets the SAME Redis index the DAO cache
 * writes (DB 11 via INSTANCE_ID), not the inert DB 0.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class RealSqlFlushIndexTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    public function testFlushIndexMatchesDaoCacheConnectionIndex(): void
    {
        // The DAO cache index resolved by the trait must equal INSTANCE_ID (DB 11 in the test
        // config) — the index the DAOs actually write, NOT DB 0 (which would be inert).
        $resolved = $this->daoCacheRedisIndex();

        $this->assertSame((int)AppConfig::$INSTANCE_ID, $resolved);
        $this->assertSame(11, $resolved, 'Test config pins the DAO cache to Redis DB 11 (INSTANCE_ID).');
    }
}
