<?php

namespace Matecat\TestHelpers;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\IDatabase;
use PDO;
use Predis\Client;
use RuntimeException;
use Utils\Redis\RedisHandler;

/**
 * Shared harness for real-SQL DAO tests (plan dao-realsql-90.md, Wave 1 / T1).
 *
 * Provides:
 *  - Fail-closed DB write guard (C1 / S-1 / S-2 / X-3): refuses to touch the DB unless the
 *    resolved schema name matches ^unittest_ AND we are in a recognised test env. In CI the
 *    guard HARD FAILS (never silent-skips); locally it skips. USE_LOCAL_DEVELOPMENT_ENV is
 *    deliberately NOT treated as a write-permitting test env.
 *  - One connection per test for BOTH the DAO under test AND the builder/cleanup (C-2). The
 *    connection is built through the obtainTestDatabase() seam (tests/inc/functions.php): the
 *    4-arg form returns a fresh `new Database(...)` bound to the test creds. Database::obtain()
 *    was removed at the composition root on this branch, and DAOs now require an injected
 *    IDatabase, so the DAO under test is constructed with exactly this per-test handle.
 *  - Whole-table COUNT(*) residue snapshots over every declared table dep (A-1/A-2/AC-1):
 *    captured before setUp and after tearDown; the per-DAO DoD asserts they match (minus the
 *    documented permanent-mutation set).
 *  - Seed-safe id DELETE cleanup honouring protected-seed PKs (M-1) with per-table
 *    autoincrement|assignable id strategy via TestFixtureBuilder (M-2).
 *  - flushDaoCache() targeting the SAME Redis index the DAOs write (DB 11 via RedisHandler /
 *    AppConfig::$INSTANCE_ID) plus a reset of the static DAO cache handle (M-4).
 *  - Fixed tearDown chain (M-5): post-snapshot -> DELETE cleanup -> flush -> reset statics ->
 *    parent::tearDown().
 *
 * Used ONLY by opt-in *RealSqlTest classes, never the shared AbstractTest base (S-4), so the
 * 666 classes that extend AbstractTest are unperturbed.
 */
trait RealSqlDaoTestTrait
{
    /** Allowlist regex: the resolved DB name MUST match this before any write. */
    private const string DB_ALLOWLIST_REGEX = '/^unittest_/';

    /**
     * Assignable-id floor mirror (M-2): exposed on the trait so tests that allocate their own
     * above-the-seed-band ids (the realSqlSetUp/realSqlTearDown lifecycle style) can reference
     * the same floor as TestFixtureBuilder without depending on the builder instance.
     */
    public const int ASSIGNABLE_ID_FLOOR = TestFixtureBuilder::ASSIGNABLE_ID_FLOOR;

    /** Single connection used by the DAO under test AND the builder/cleanup for this test. */
    protected ?IDatabase $realSqlConnection = null;

    /**
     * Lifecycle-style alias for the per-test connection (realSqlSetUp/realSqlTearDown style).
     * Identical instance to realSqlDb(); kept as a property so tests written against the
     * lifecycle hooks can read $this->realSqlDb directly. Reconciled into the one canonical
     * trait (no separate forked trait).
     */
    protected ?IDatabase $realSqlDb = null;

    protected ?TestFixtureBuilder $fixtures = null;

    /** table => COUNT(*) captured before setUp completes. */
    private array $residueBaseline = [];

    /**
     * Generated ids tracked by lifecycle-style tests via trackGeneratedId(), deleted in
     * realSqlTearDown() when no explicit cleanup closure is supplied.
     *
     * @var list<array{table:string,id:int}>
     */
    private array $realSqlTrackedIds = [];

    /**
     * Tables whose whole-table COUNT(*) is expected to drift permanently and is excluded
     * from the residue gate (AC-3): the `sequences` row (committed nextSequence UPDATE is an
     * in-place mutation, not a row count change, but listed defensively) plus any DAO-specific
     * permanent-mutation tables a test declares.
     */
    protected function residueExcludedTables(): array
    {
        return ['sequences'];
    }

    /**
     * Resolve the DB name the test harness booted against (AppConfig::$DB_DATABASE).
     */
    private function resolvedDbName(): string
    {
        return (string)\Utils\Registry\AppConfig::$DB_DATABASE;
    }

    private function isCiEnv(): bool
    {
        return getenv('CI_ENV') !== false && getenv('CI_ENV') !== '';
    }

    /**
     * Recognised test env for write permission (X-3): ENV === 'testing' OR CI_ENV set.
     * USE_LOCAL_DEVELOPMENT_ENV is deliberately excluded.
     */
    private function isRecognisedTestEnv(): bool
    {
        return \Utils\Registry\AppConfig::$ENV === 'testing' || $this->isCiEnv();
    }

    /**
     * Fail-closed guard. Returns true when writes are permitted. When NOT permitted:
     *  - CI: throws RuntimeException -> non-zero exit (never a silent green).
     *  - local: marks the test skipped naming the resolved DB.
     */
    protected function assertDbWriteGuard(): bool
    {
        $db = $this->resolvedDbName();
        $allowed = (bool)preg_match(self::DB_ALLOWLIST_REGEX, $db) && $this->isRecognisedTestEnv();

        if ($allowed) {
            return true;
        }

        $reason = sprintf(
            'RealSql DB write guard tripped: resolved DB "%s" is not allowlisted (^unittest_) or not a test env (ENV=%s, CI_ENV=%s).',
            $db,
            (string)\Utils\Registry\AppConfig::$ENV,
            $this->isCiEnv() ? '1' : '0'
        );

        if ($this->isCiEnv()) {
            // Hard fail in CI: a silent all-skip would green CI with ~0% real-SQL coverage.
            throw new RuntimeException($reason);
        }

        $this->markTestSkipped($reason);
    }

    /**
     * The single per-test connection used by the DAO under test AND the builder/cleanup (C-2).
     *
     * Database::obtain() was removed at the composition root on this branch; tests build the
     * connection through the obtainTestDatabase() seam (tests/inc/functions.php). The 4-arg
     * form returns a fresh `new Database(...)` bound to the test creds, giving this test its
     * own isolated handle that the DAO is then injected with — so DAO + builder + cleanup all
     * share one PDO handle without leaking into other tests' connections.
     */
    protected function realSqlDb(): IDatabase
    {
        if ($this->realSqlConnection === null) {
            $this->realSqlConnection = obtainTestDatabase(
                \Utils\Registry\AppConfig::$DB_SERVER,
                \Utils\Registry\AppConfig::$DB_USER,
                \Utils\Registry\AppConfig::$DB_PASS,
                \Utils\Registry\AppConfig::$DB_DATABASE
            );
            $this->realSqlConnection->getConnection(); // force connect
        }

        return $this->realSqlConnection;
    }

    /**
     * Assert the DAO under test holds the exact test connection (C-2), never a stray
     * no-arg singleton handle.
     *
     * @param AbstractDao $dao
     */
    protected function assertDaoUsesTestConnection(AbstractDao $dao): void
    {
        $this->assertSame(
            $this->realSqlDb(),
            $dao->getDatabaseHandler(),
            'DAO under test must use the single per-test connection (C-2), not the no-arg singleton.'
        );
    }

    /**
     * Redis client pointing at the SAME index the DAO cache writes (DB 11 via INSTANCE_ID).
     */
    private function daoCacheRedis(): Client
    {
        return (new RedisHandler())->getConnection();
    }

    /**
     * The Redis DB index the DAO cache actually uses (INSTANCE_ID, DB 11). Exposed so the
     * flush-index self-test can assert flushed-index == DAO-conn index (C3/S-5).
     */
    protected function daoCacheRedisIndex(): int
    {
        $params = $this->daoCacheRedis()->getConnection()->getParameters();

        return (int)($params->database ?? 0);
    }

    /**
     * Flush the DAO cache on BOTH the DAO index (DB 11 via INSTANCE_ID) AND index 0 (S-5).
     *
     * The DAO index is the one new tests and DAOs write. Index 0 is flushed too because 33
     * legacy tests call raw flushdb() on DB 0; flushing both here keeps legacy + new callers
     * covered without editing those files (the v3 explicit exception to the ADD-only rule).
     * Also resets the static cache handle (M-4) is done separately in tearDown.
     */
    protected function flushDaoCache(): void
    {
        // DAO index (DB 11).
        $this->daoCacheRedis()->flushdb();

        // Index 0 (legacy callers). Reuse the same server; select DB 0 explicitly.
        $servers = (string)\Utils\Registry\AppConfig::$REDIS_SERVERS;
        $dsn = $servers . (str_contains($servers, '?') ? '&' : '?') . 'database=0';
        (new Client($dsn))->flushdb();
    }

    /**
     * Capture whole-table COUNT(*) over every dep, excluding permanent-mutation tables.
     *
     * @param list<string> $tableDeps
     * @return array<string,int>
     */
    protected function snapshotTableCounts(array $tableDeps): array
    {
        $conn = $this->realSqlDb()->getConnection();
        $counts = [];
        $excluded = $this->residueExcludedTables();
        foreach ($tableDeps as $table) {
            if (in_array($table, $excluded, true)) {
                continue;
            }
            $stmt = $conn->query("SELECT COUNT(*) AS c FROM `$table`");
            $counts[$table] = (int)$stmt->fetch(PDO::FETCH_OBJ)->c;
        }

        return $counts;
    }

    /**
     * Initialise harness state for a real-SQL test. Call from the test's setUp AFTER the
     * guard passes. Declares the tables whose residue is gated.
     *
     * @param list<string> $tableDeps
     */
    protected function startRealSql(array $tableDeps): void
    {
        $this->fixtures = new TestFixtureBuilder($this->realSqlDb());
        // Clean DAO cache before the test so stale reads from a previous run cannot leak in.
        $this->flushDaoCache();
        AbstractDao::setCacheConnection(null);
        $this->residueBaseline = $this->snapshotTableCounts($tableDeps);
    }

    /**
     * Fixed tearDown chain (M-5):
     *   (1) post-snapshot + residue assertion
     *   (2) tracked-id / seed-safe DELETE cleanup
     *   (3) flushDaoCache
     *   (4) reset static DAO cache handle + TestDatabaseProvider override
     *   (5) parent::tearDown()  (caller delegates)
     *
     * Returns nothing; the caller's tearDown() must call parent::tearDown() itself AFTER
     * invoking this, to preserve AbstractTest base behaviour on the right connection.
     */
    protected function finishRealSql(): void
    {
        if ($this->fixtures === null) {
            // setUp skipped (guard) - nothing to clean.
            return;
        }

        // (2) cleanup must happen before the post-snapshot so the snapshot reflects baseline.
        $this->fixtures->cleanup();

        // (1) residue gate: whole-table COUNT(*) delta == 0 over every declared dep.
        $post = $this->snapshotTableCounts(array_keys($this->residueBaseline));
        foreach ($this->residueBaseline as $table => $before) {
            $this->assertSame(
                $before,
                $post[$table] ?? -1,
                sprintf('Residue gate: table `%s` row count changed (%d -> %d) after cleanup.', $table, $before, $post[$table] ?? -1)
            );
        }

        // (3) flush DAO cache on the real index.
        $this->flushDaoCache();

        // (4) reset static handles (processIsolation=false leak guard, M-4).
        AbstractDao::setCacheConnection(null);
        if (class_exists('\\TestHelpers\\TestDatabaseProvider') && property_exists('\\TestHelpers\\TestDatabaseProvider', 'override')) {
            TestDatabaseProvider::$override = null;
        }

        $this->fixtures = null;
        $this->realSqlConnection = null;
        $this->realSqlDb = null;
    }

    // ===========================================================================================
    // Lifecycle-hook compatibility layer (realSqlSetUp / realSqlTearDown style).
    //
    // A subset of the real-SQL DAO tests were authored against a sibling trait variant that
    // drove setUp/tearDown through realSqlSetUp() / realSqlTearDown(callable) + an overridable
    // realSqlTableDeps(), managing their own fixtures + cleanup closure instead of the
    // TestFixtureBuilder. To collapse the two forked traits into ONE canonical trait WITHOUT
    // rewriting those tests, the hooks below are implemented in terms of the same canonical
    // machinery (realSqlDb(), snapshotTableCounts(), flushDaoCache(), residue gate, static
    // resets). The contract is identical: ^unittest_ guard, one connection per test, no
    // wrapping transaction, whole-table residue gate, fixed teardown chain.
    // ===========================================================================================

    /**
     * Table deps for the lifecycle-style tests. Default empty; lifecycle tests override it to
     * declare the tables whose whole-table COUNT(*) residue is gated. Tests using the
     * startRealSql()/finishRealSql() style never call this (they pass deps explicitly).
     *
     * @return list<string>
     */
    protected function realSqlTableDeps(): array
    {
        return [];
    }

    /**
     * Lifecycle setUp: trip the fail-closed guard, open the single per-test connection
     * (mirrored onto $this->realSqlDb), flush stale DAO cache, and snapshot the residue
     * baseline from realSqlTableDeps(). Call from the test's setUp() AFTER parent::setUp().
     */
    protected function realSqlSetUp(): void
    {
        $this->assertDbWriteGuard();
        $this->realSqlDb = $this->realSqlDb();
        $this->flushDaoCache();
        AbstractDao::setCacheConnection(null);
        $this->residueBaseline = $this->snapshotTableCounts($this->normaliseTableDeps($this->realSqlTableDeps()));
    }

    /**
     * Normalise a table-deps declaration into a flat list of table names. The lifecycle-style
     * tests may declare deps as an associative `table => id-strategy` map (the drift-variant
     * convention); the canonical residue gate only needs the table names, so collapse a map to
     * its keys while leaving a plain list untouched.
     *
     * @param array<int|string,string> $deps
     * @return list<string>
     */
    private function normaliseTableDeps(array $deps): array
    {
        return array_is_list($deps) ? $deps : array_keys($deps);
    }

    /**
     * Lifecycle tearDown: run the test-provided cleanup closure (its own seed-safe DELETEs)
     * FIRST, then the fixed canonical chain — residue gate over the declared deps, DAO cache
     * flush, static-handle resets. The caller's tearDown() must still call parent::tearDown()
     * itself afterwards (to preserve AbstractTest base behaviour on the right connection).
     *
     * @param callable|null $cleanup test-supplied seed-safe row cleanup on the per-test
     *                               connection. When null, rows registered via
     *                               trackGeneratedId() are deleted instead.
     */
    protected function realSqlTearDown(?callable $cleanup = null): void
    {
        if ($this->realSqlDb === null) {
            // setUp skipped (guard) — nothing to clean.
            return;
        }

        // (2) test-owned cleanup before the post-snapshot so the snapshot reflects baseline.
        if ($cleanup !== null) {
            $cleanup();
        } else {
            $this->deleteTrackedGeneratedIds();
        }

        // (1) residue gate: whole-table COUNT(*) delta == 0 over every declared dep.
        $post = $this->snapshotTableCounts(array_keys($this->residueBaseline));
        foreach ($this->residueBaseline as $table => $before) {
            $this->assertSame(
                $before,
                $post[$table] ?? -1,
                sprintf('Residue gate: table `%s` row count changed (%d -> %d) after cleanup.', $table, $before, $post[$table] ?? -1)
            );
        }

        // (3) flush DAO cache on the real index.
        $this->flushDaoCache();

        // (4) reset static handles (processIsolation=false leak guard, M-4).
        AbstractDao::setCacheConnection(null);
        if (class_exists('\\TestHelpers\\TestDatabaseProvider') && property_exists('\\TestHelpers\\TestDatabaseProvider', 'override')) {
            TestDatabaseProvider::$override = null;
        }

        $this->fixtures = null;
        $this->realSqlConnection = null;
        $this->realSqlDb = null;
        $this->realSqlTrackedIds = [];
    }

    /**
     * Register an AUTO_INCREMENT id the test (or the DAO under test) generated, so the no-arg
     * realSqlTearDown() can delete it for the residue gate (C-1/M-1). NEVER pass a seeded PK.
     */
    protected function trackGeneratedId(string $table, int $id): void
    {
        $this->realSqlTrackedIds[] = ['table' => $table, 'id' => $id];
    }

    /**
     * Seed-safe DELETE of every id registered via trackGeneratedId(), in reverse order on the
     * per-test connection (no wrapping transaction). Assumes a single-column `id` PK, which
     * holds for the AUTO_INCREMENT tables the lifecycle-style tests target.
     */
    private function deleteTrackedGeneratedIds(): void
    {
        $conn = $this->realSqlDb()->getConnection();
        foreach (array_reverse($this->realSqlTrackedIds) as $row) {
            $stmt = $conn->prepare(sprintf('DELETE FROM `%s` WHERE `id` = :id', $row['table']));
            $stmt->execute(['id' => $row['id']]);
        }
        $this->realSqlTrackedIds = [];
    }

    /**
     * Lifecycle-style alias for assertDaoUsesTestConnection(): the DAO under test must hold the
     * exact per-test connection (C-2), never a stray no-arg handle.
     */
    protected function assertInjectedConnection(AbstractDao $dao): void
    {
        $this->assertDaoUsesTestConnection($dao);
    }
}
