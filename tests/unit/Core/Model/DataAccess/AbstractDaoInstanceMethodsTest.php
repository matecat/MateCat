<?php

declare(strict_types=1);

namespace Matecat\Core\Model\DataAccess;

use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use Model\DataAccess\IDatabase;
use Model\DataAccess\UnscopedCacheKeyMap;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client;
use Utils\Registry\AppConfig;

class TestStruct implements IDaoStruct
{
    public ?int $id = null;
    public string $name = '';

    /**
     * @param array{id?: int, name?: string} $data
     */
    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? null;
        $this->name = $data['name'] ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getArrayCopy(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    public function count(): int
    {
        return count($this->getArrayCopy());
    }

    /**
     * @param list<string>|null $mask
     * @return array<string, mixed>
     */
    public function toArray(?array $mask = null): array
    {
        $data = $this->getArrayCopy();
        if ($mask === null) {
            return $data;
        }

        $filtered = [];
        foreach ($mask as $key) {
            if (array_key_exists($key, $data)) {
                $filtered[$key] = $data[$key];
            }
        }

        return $filtered;
    }
}

class DaoObjectTestStruct extends AbstractDaoObjectStruct
{
    public ?int $id = null;
    public string $name = '';
}

class TestableDao extends AbstractDao
{
    protected static array $primary_keys = ['id'];
    const string TABLE = 'test_table';

    public function exposeCacheTransactionScope(): ?IDatabase
    {
        return $this->_cacheTransactionScope();
    }

    /**
     * @param array<int|string, scalar|null> $bindParams
     *
     * @return list<TestStruct>
     */
    public function fetchMap(PDOStatement $stmt, array $bindParams, int $ttl, ?string $keyMap = null): array
    {
        return $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, TestStruct::class, $bindParams, $keyMap);
    }
}

class AnotherTestableDao extends AbstractDao
{
    protected static array $primary_keys = ['id'];
    const string TABLE = 'test_table';
}

class AutoIncrementTestableDao extends AbstractDao
{
    protected static array $primary_keys = ['id'];
    const string TABLE = 'test_table';

    public function __construct(?IDatabase $con = null)
    {
        parent::__construct($con);
        static::$auto_increment_field = ['id'];
    }
}

class FakeRedisClientForAbstractDaoTest extends Client
{
    /** @var array<string, array<string, string>> */
    private array $hashes = [];
    /** @var array<string, string> */
    private array $strings = [];

    public function __construct()
    {
    }

    public function __call($commandID, $arguments)
    {
        return match (strtolower($commandID)) {
            'hget' => $this->hashes[$arguments[0]][$arguments[1]] ?? null,
            'hset' => $this->doHset($arguments[0], $arguments[1], $arguments[2]),
            'hdel' => $this->doHdel($arguments[0], $arguments[1]),
            'expire' => true,
            'setex' => $this->doSetex($arguments[0], $arguments[2]),
            'get' => $this->strings[$arguments[0]] ?? null,
            'del' => $this->doDel($arguments[0]),
            default => null,
        };
    }

    /** @return list<string> The names of the hashes written so far. */
    public function hashNames(): array
    {
        return array_keys($this->hashes);
    }

    private function doHset(string $key, string $field, string $value): int
    {
        $this->hashes[$key][$field] = $value;

        return 1;
    }

    /**
     * @param array<int, string> $fields
     */
    private function doHdel(string $key, array $fields): int
    {
        $count = 0;
        foreach ($fields as $field) {
            if (isset($this->hashes[$key][$field])) {
                unset($this->hashes[$key][$field]);
                $count++;
            }
        }

        return $count;
    }

    private function doSetex(string $key, string $value): void
    {
        $this->strings[$key] = $value;
    }

    private function doDel(string $key): int
    {
        $existed = isset($this->hashes[$key]) || isset($this->strings[$key]);
        unset($this->hashes[$key], $this->strings[$key]);

        return $existed ? 1 : 0;
    }
}

class AbstractDaoInstanceMethodsTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = false;
        TestableDao::setCacheConnection(null);
    }

    protected function tearDown(): void
    {
        TestableDao::setCacheConnection(null);
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    #[Test]
    public function test_findById_returns_typed_struct_for_existing_id(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->queryString = 'SELECT * FROM test_table WHERE id = :id';
        $stmt->expects($this->once())->method('execute')->with(['id' => 42]);
        $stmt->expects($this->once())->method('fetchAll')->willReturn([
            new TestStruct(['id' => 42, 'name' => 'alpha']),
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($pdo);

        $dao = new TestableDao($database);

        $result = $dao->fetchById(42, TestStruct::class);

        $this->assertInstanceOf(TestStruct::class, $result);
        $this->assertSame(42, $result?->id);
        $this->assertSame('alpha', $result?->name);
    }

    #[Test]
    public function test_findById_returns_null_for_nonexistent_id(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->queryString = 'SELECT * FROM test_table WHERE id = :id';
        $stmt->expects($this->once())->method('execute')->with(['id' => 42]);
        $stmt->expects($this->once())->method('fetchAll')->willReturn([]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($pdo);

        $dao = new TestableDao($database);

        $result = $dao->fetchById(42, TestStruct::class);

        $this->assertNull($result);
    }

    #[Test]
    public function test_findById_cache_hit_skips_db_query(): void
    {
        TestableDao::setCacheConnection(new FakeRedisClientForAbstractDaoTest());

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->queryString = 'SELECT * FROM test_table WHERE id = :id';
        $stmt->expects($this->once())->method('execute')->with(['id' => 42]);
        $stmt->expects($this->once())->method('fetchAll')->willReturn([
            new TestStruct(['id' => 42, 'name' => 'cached']),
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($pdo);

        $dao = new TestableDao($database);

        $first = $dao->fetchById(42, TestStruct::class, 60);
        $second = $dao->fetchById(42, TestStruct::class, 60);

        $this->assertInstanceOf(TestStruct::class, $first);
        $this->assertInstanceOf(TestStruct::class, $second);
        $this->assertSame('cached', $second?->name);
    }

    #[Test]
    public function test_destroyFindByIdCache_evicts_cached_entry(): void
    {
        TestableDao::setCacheConnection(new FakeRedisClientForAbstractDaoTest());

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->queryString = 'SELECT * FROM test_table WHERE id = :id';
        $stmt->expects($this->exactly(2))->method('execute')->with(['id' => 42]);
        $stmt->expects($this->exactly(2))->method('fetchAll')->willReturn([
            new TestStruct(['id' => 42, 'name' => 'value']),
        ]);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($pdo);

        $dao = new TestableDao($database);

        $dao->fetchById(42, TestStruct::class, 60);
        $dao->destroyFetchByIdCache(42, TestStruct::class);
        $dao->fetchById(42, TestStruct::class, 60);

        $this->assertTrue(true);
    }

    #[Test]
    public function test_findById_cache_key_uses_static_class_preventing_cross_dao_collision(): void
    {
        TestableDao::setCacheConnection(new FakeRedisClientForAbstractDaoTest());

        $stmtDaoOne = $this->createMock(PDOStatement::class);
        $stmtDaoOne->queryString = 'SELECT * FROM test_table WHERE id = :id';
        $stmtDaoOne->expects($this->once())->method('execute')->with(['id' => 10]);
        $stmtDaoOne->expects($this->once())->method('fetchAll')->willReturn([
            new TestStruct(['id' => 10, 'name' => 'from-dao-one']),
        ]);

        $pdoDaoOne = $this->createStub(PDO::class);
        $pdoDaoOne->method('prepare')->willReturn($stmtDaoOne);

        $databaseDaoOne = $this->createStub(IDatabase::class);
        $databaseDaoOne->method('getConnection')->willReturn($pdoDaoOne);

        $daoOne = new TestableDao($databaseDaoOne);

        $stmtDaoTwo = $this->createMock(PDOStatement::class);
        $stmtDaoTwo->queryString = 'SELECT * FROM test_table WHERE id = :id';
        $stmtDaoTwo->expects($this->once())->method('execute')->with(['id' => 10]);
        $stmtDaoTwo->expects($this->once())->method('fetchAll')->willReturn([
            new TestStruct(['id' => 10, 'name' => 'from-dao-two']),
        ]);

        $pdoDaoTwo = $this->createStub(PDO::class);
        $pdoDaoTwo->method('prepare')->willReturn($stmtDaoTwo);

        $databaseDaoTwo = $this->createStub(IDatabase::class);
        $databaseDaoTwo->method('getConnection')->willReturn($pdoDaoTwo);

        $daoTwo = new AnotherTestableDao($databaseDaoTwo);

        $first = $daoOne->fetchById(10, TestStruct::class, 60);
        $second = $daoTwo->fetchById(10, TestStruct::class, 60);

        $this->assertSame('from-dao-one', $first?->name);
        $this->assertSame('from-dao-two', $second?->name);
    }

    /**
     * The production defect: an IN list written into the SQL with nothing bound put every call in
     * one Redis hash, `SegmentNoteDao::getBySegmentIds-`, which reached 18.9 GB.
     */
    #[Test]
    public function test_fetchObjectMap_refuses_a_cached_in_list_written_into_the_sql(): void
    {
        TestableDao::setCacheConnection(new FakeRedisClientForAbstractDaoTest());

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->queryString = 'SELECT * FROM test_table WHERE id IN ( 1, 2 )';
        $stmt->expects($this->never())->method('execute');

        $this->expectException(UnscopedCacheKeyMap::class);

        (new TestableDao($this->databaseReturning($stmt)))->fetchMap($stmt, [], 60);
    }

    #[Test]
    public function test_fetchObjectMap_runs_an_in_list_written_into_the_sql_when_the_cache_is_off(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->queryString = 'SELECT * FROM test_table WHERE id IN ( 1, 2 )';
        $stmt->expects($this->once())->method('execute')->with([]);
        $stmt->method('fetchAll')->willReturn([new TestStruct(['id' => 1])]);

        $result = (new TestableDao($this->databaseReturning($stmt)))->fetchMap($stmt, [], 0);

        $this->assertCount(1, $result);
    }

    #[Test]
    public function test_fetchObjectMap_names_the_hash_after_the_bound_in_list(): void
    {
        $redis = new FakeRedisClientForAbstractDaoTest();
        TestableDao::setCacheConnection($redis);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->queryString = 'SELECT * FROM test_table WHERE id IN ( ?,? )';
        $stmt->expects($this->once())->method('execute')->with([1, 2]);
        $stmt->method('fetchAll')->willReturn([new TestStruct(['id' => 1])]);

        (new TestableDao($this->databaseReturning($stmt)))->fetchMap($stmt, [1, 2], 60);

        $this->assertSame([TestableDao::class . '::fetchMap-1:2'], $redis->hashNames());
    }

    /** An explicit key map is the caller's own choice of scope, so the guard leaves it alone. */
    #[Test]
    public function test_fetchObjectMap_accepts_an_in_list_written_into_the_sql_under_an_explicit_key_map(): void
    {
        $redis = new FakeRedisClientForAbstractDaoTest();
        TestableDao::setCacheConnection($redis);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->queryString = 'SELECT * FROM test_table WHERE id IN ( 1, 2 )';
        $stmt->expects($this->once())->method('execute')->with([]);
        $stmt->method('fetchAll')->willReturn([]);

        (new TestableDao($this->databaseReturning($stmt)))->fetchMap($stmt, [], 60, 'test_table:ids-1:2');

        $this->assertSame(['test_table:ids-1:2'], $redis->hashNames());
    }

    private function databaseReturning(PDOStatement $stmt): IDatabase
    {
        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($pdo);

        return $database;
    }

    #[Test]
    public function test_updateFields_delegates_to_injected_database_update(): void
    {
        $database = $this->createMock(IDatabase::class);
        $database
            ->expects($this->once())
            ->method('update')
            ->with('test_table', ['status' => 'done'], ['id' => 1])
            ->willReturn(1);

        $dao = new TestableDao($database);

        $result = $dao->updateFields(['status' => 'done'], ['id' => 1]);

        $this->assertSame(1, $result);
    }

    #[Test]
    public function test_updateStruct_builds_correct_sql_and_executes(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $data): bool {
                return $data['id'] === 5 && $data['name'] === 'updated';
            }));
        $stmt->expects($this->once())->method('rowCount')->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql): bool {
                return str_contains($sql, 'UPDATE test_table')
                    && str_contains($sql, 'SET')
                    && str_contains($sql, 'name = :name')
                    && str_contains($sql, 'WHERE')
                    && str_contains($sql, 'id = :id');
            }))
            ->willReturn($stmt);

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($pdo);

        $dao = new TestableDao($database);
        $struct = new DaoObjectTestStruct(['id' => 5, 'name' => 'updated']);

        $result = $dao->updateStruct($struct);

        $this->assertSame(1, $result);
    }

    #[Test]
    public function test_updateStruct_with_fields_option_limits_set_clause(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $data): bool {
                return $data['id'] === 5 && $data['name'] === 'updated' && count($data) === 2;
            }));
        $stmt->expects($this->once())->method('rowCount')->willReturn(1);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql): bool {
                return str_contains($sql, 'UPDATE test_table')
                    && str_contains($sql, 'SET')
                    && str_contains($sql, 'name = :name')
                    && !str_contains($sql, 'id = :id ,')
                    && str_contains($sql, 'WHERE')
                    && str_contains($sql, 'id = :id');
            }))
            ->willReturn($stmt);

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($pdo);

        $dao = new TestableDao($database);
        $struct = new DaoObjectTestStruct(['id' => 5, 'name' => 'updated']);

        $result = $dao->updateStruct($struct, ['fields' => ['name']]);

        $this->assertSame(1, $result);
    }

    #[Test]
    public function test_insertStruct_prepares_insert_and_returns_last_insert_id(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $data): bool {
                return array_key_exists('name', $data) && $data['name'] === 'created';
            }));

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql): bool {
                return str_contains($sql, 'INSERT')
                    && str_contains($sql, 'INTO test_table');
            }))
            ->willReturn($stmt);
        $pdo->expects($this->once())->method('lastInsertId')->willReturn('99');

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($pdo);
        $database->method('buildInsertStatement')->willReturnCallback(
            fn(string $table, array $attrs, array &$mask = [], bool $ignore = false, bool $no_nulls = false, array $on_duplicate_update = []) => obtainTestDatabase()->buildInsertStatement($table, $attrs, $mask, $ignore, $no_nulls, $on_duplicate_update)
        );

        $dao = new AutoIncrementTestableDao($database);
        $struct = new DaoObjectTestStruct(['name' => 'created']);

        $result = $dao->insertStruct($struct);

        $this->assertSame(99, $result);
    }

    #[Test]
    public function test_insertStruct_with_ignore_option(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())->method('execute');

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql): bool {
                return str_contains($sql, 'INSERT')
                    && str_contains($sql, 'IGNORE')
                    && str_contains($sql, 'INTO test_table');
            }))
            ->willReturn($stmt);
        $pdo->expects($this->once())->method('lastInsertId')->willReturn('101');

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($pdo);
        $database->method('buildInsertStatement')->willReturnCallback(
            fn(string $table, array $attrs, array &$mask = [], bool $ignore = false, bool $no_nulls = false, array $on_duplicate_update = []) => obtainTestDatabase()->buildInsertStatement($table, $attrs, $mask, $ignore, $no_nulls, $on_duplicate_update)
        );

        $dao = new AutoIncrementTestableDao($database);
        $struct = new DaoObjectTestStruct(['name' => 'created']);

        $result = $dao->insertStruct($struct, ['ignore' => true]);

        $this->assertSame(101, $result);
    }

    #[Test]
    public function test_insertStruct_with_on_duplicate_update(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $data): bool {
                return array_key_exists('name', $data)
                    && $data['name'] === 'created';
            }));

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql): bool {
                return str_contains($sql, 'ON DUPLICATE KEY UPDATE');
            }))
            ->willReturn($stmt);
        $pdo->expects($this->once())->method('lastInsertId')->willReturn('102');

        $database = $this->createStub(IDatabase::class);
        $database->method('getConnection')->willReturn($pdo);
        $database->method('buildInsertStatement')->willReturnCallback(
            fn(string $table, array $attrs, array &$mask = [], bool $ignore = false, bool $no_nulls = false, array $on_duplicate_update = []) => obtainTestDatabase()->buildInsertStatement($table, $attrs, $mask, $ignore, $no_nulls, $on_duplicate_update)
        );

        $dao = new AutoIncrementTestableDao($database);
        $struct = new DaoObjectTestStruct(['name' => 'created']);

        $result = $dao->insertStruct($struct, ['on_duplicate_update' => ['name' => 'VALUES(name)']]);

        $this->assertSame(102, $result);
    }

    /**
     * DaoCacheTrait defaults the scope to null, which would make every DAO's cache writes ignore
     * the transaction they run in. AbstractDao is what binds them to the injected handle.
     */
    #[Test]
    public function test_cache_transaction_scope_is_the_injected_database(): void
    {
        $database = $this->createStub(IDatabase::class);

        $this->assertSame($database, (new TestableDao($database))->exposeCacheTransactionScope());
    }
}
