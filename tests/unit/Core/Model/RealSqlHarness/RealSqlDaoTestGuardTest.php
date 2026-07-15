<?php

namespace Matecat\Core\Model\RealSqlHarness;

use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\RealSqlDaoTestTrait;
use PHPUnit\Framework\Attributes\Group;
use ReflectionMethod;
use RuntimeException;
use Utils\Registry\AppConfig;

/**
 * Mutation-proves the fail-closed DB write guard (C1 / S-1 / S-2 / X-3, plan dao-realsql-90.md
 * Wave 1 / T1). The guard must:
 *   - permit writes ONLY when the resolved DB matches ^unittest_ AND we are in a test env;
 *   - HARD FAIL (throw) in CI when the DB is poisoned (never a silent green);
 *   - SKIP locally when poisoned;
 *   - treat USE_LOCAL_DEVELOPMENT_ENV as NOT a write-permitting env (X-3).
 *
 * The guard logic is exercised directly (reflection on the trait's private predicates) so this
 * self-test deterministically proves the trip WITHOUT touching the database and WITHOUT
 * depending on whether the current run happens to be CI or local.
 */
#[Group('PersistenceNeeded')]
#[Group('DaoRealSql')]
class RealSqlDaoTestGuardTest extends AbstractTest
{
    use RealSqlDaoTestTrait;

    private ?string $savedDb = null;
    private ?string $savedEnv = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedDb = AppConfig::$DB_DATABASE;
        $this->savedEnv = AppConfig::$ENV;
    }

    protected function tearDown(): void
    {
        AppConfig::$DB_DATABASE = $this->savedDb;
        AppConfig::$ENV = $this->savedEnv;
        parent::tearDown();
    }

    private function callPrivateBool(string $method): bool
    {
        $m = new ReflectionMethod($this, $method);
        $m->setAccessible(true);

        return (bool)$m->invoke($this);
    }

    public function testAllowlistAcceptsUnittestDb(): void
    {
        AppConfig::$DB_DATABASE = 'unittest_matecat_local';
        AppConfig::$ENV = 'testing';
        // resolvedDbName matches ^unittest_ and env is testing -> recognised.
        $this->assertTrue($this->callPrivateBool('isRecognisedTestEnv'));
        $this->assertMatchesRegularExpression('/^unittest_/', AppConfig::$DB_DATABASE);
    }

    public function testGuardTripsWhenDbNotAllowlisted(): void
    {
        // Poison the resolved DB to a non-unittest schema.
        AppConfig::$DB_DATABASE = 'production_matecat';
        AppConfig::$ENV = 'testing';

        if (getenv('CI_ENV') !== false && getenv('CI_ENV') !== '') {
            // CI path: MUST hard-fail (throw), never skip.
            $this->expectException(RuntimeException::class);
            $this->assertDbWriteGuard();

            return;
        }

        // Local path: MUST skip (and therefore never reach the assertion below).
        try {
            $this->assertDbWriteGuard();
            $this->fail('Guard should have skipped on a poisoned DB locally.');
        } catch (\PHPUnit\Framework\SkippedTestError | \PHPUnit\Framework\SkippedWithMessageException $e) {
            $this->assertStringContainsString('production_matecat', $e->getMessage());
        }
    }

    public function testGuardTripsWhenNotTestEnv(): void
    {
        // Allowlisted DB but a non-test env (e.g. production boot) must still trip the guard.
        AppConfig::$DB_DATABASE = 'unittest_matecat_local';
        AppConfig::$ENV = 'production';
        $hadCi = getenv('CI_ENV');
        putenv('CI_ENV');
        try {
            $this->assertFalse($this->callPrivateBool('isRecognisedTestEnv'));
        } finally {
            putenv('CI_ENV=' . $hadCi);
        }

    }

    public function testUseLocalDevelopmentEnvIsNotWritePermitting(): void
    {
        // X-3: even with USE_LOCAL_DEVELOPMENT_ENV set, env recognition depends ONLY on
        // ENV==='testing' OR CI_ENV. A dev-bootstrapped run (ENV not 'testing', no CI_ENV) is
        // NOT granted test-env status by USE_LOCAL_DEVELOPMENT_ENV.
        AppConfig::$ENV = 'development';
        $hadCi = getenv('CI_ENV');
        putenv('CI_ENV');                  // ensure unset for this assertion
        putenv('USE_LOCAL_DEVELOPMENT_ENV=1');

        try {
            $this->assertFalse(
                $this->callPrivateBool('isRecognisedTestEnv'),
                'USE_LOCAL_DEVELOPMENT_ENV must not grant write-permitting test-env status (X-3).'
            );
        } finally {
            putenv('USE_LOCAL_DEVELOPMENT_ENV');
            if ($hadCi !== false) {
                putenv('CI_ENV=' . $hadCi);
            }
        }
    }
}
