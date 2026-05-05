<?php

namespace unit\Model\DataAccess;

use Exception;
use Model\DataAccess\Database;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

#[CoversClass(Database::class)]
#[Group('PersistenceNeeded')]
class DatabaseTest extends AbstractTest
{
    private Database $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetSingleton();

        $this->db = Database::obtain(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );
    }

    protected function tearDown(): void
    {
        $this->db->close();
        $this->resetSingleton();

        parent::tearDown();
    }

    private function resetSingleton(): void
    {
        $reflector = new ReflectionClass(Database::class);
        $instanceProp = $reflector->getProperty('instance');
        $instanceProp->setValue(null, null);
    }

    // ─── Singleton / obtain() ───────────────────────────────────────────────

    #[Test]
    public function obtainReturnsSameInstance(): void
    {
        $second = Database::obtain();
        $this->assertSame($this->db, $second);
    }

    #[Test]
    public function obtainWithoutParamsReturnsCachedInstance(): void
    {
        $cached = Database::obtain();
        $this->assertSame($this->db, $cached);
    }

    #[Test]
    public function obtainWithNewParamsCreatesNewInstance(): void
    {
        $this->resetSingleton();

        $new = Database::obtain(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );

        $this->assertNotSame($this->db, $new);
    }

    // ─── getConnection() ────────────────────────────────────────────────────

    #[Test]
    public function getConnectionReturnsPDO(): void
    {
        $conn = $this->db->getConnection();
        $this->assertInstanceOf(PDO::class, $conn);
    }

    #[Test]
    public function getConnectionReturnsSameInstanceOnMultipleCalls(): void
    {
        $conn1 = $this->db->getConnection();
        $conn2 = $this->db->getConnection();
        $this->assertSame($conn1, $conn2);
    }

    #[Test]
    public function getConnectionThrowsPDOExceptionOnBadCredentials(): void
    {
        $reflector = new ReflectionClass(Database::class);
        $instanceProp = $reflector->getProperty('instance');
        $instanceProp->setValue(null, null);

        $badDb = Database::obtain('invalid_host_that_does_not_exist', 'bad', 'bad', 'bad');

        $this->expectException(PDOException::class);
        $badDb->getConnection();
    }

    // ─── Transaction management ─────────────────────────────────────────────

    #[Test]
    public function beginReturnsPDOAndStartsTransaction(): void
    {
        $pdo = $this->db->begin();
        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertTrue($pdo->inTransaction());
        $this->db->rollback();
    }

    #[Test]
    public function beginIsIdempotentWhenAlreadyInTransaction(): void
    {
        $pdo1 = $this->db->begin();
        $pdo2 = $this->db->begin();
        $this->assertSame($pdo1, $pdo2);
        $this->assertTrue($pdo2->inTransaction());
        $this->db->rollback();
    }

    #[Test]
    public function commitEndsTransaction(): void
    {
        $this->db->begin();
        $this->db->commit();
        $this->assertFalse($this->db->getConnection()->inTransaction());
    }

    #[Test]
    public function rollbackEndsTransaction(): void
    {
        $this->db->begin();
        $this->db->rollback();
        $this->assertFalse($this->db->getConnection()->inTransaction());
    }

    #[Test]
    public function rollbackIsNoOpWhenNotInTransaction(): void
    {
        $this->db->rollback();
        $this->assertFalse($this->db->getConnection()->inTransaction());
    }

    #[Test]
    public function transactionCommitsOnSuccess(): void
    {
        $result = $this->db->transaction(function () {
            return 42;
        });

        $this->assertSame(42, $result);
        $this->assertFalse($this->db->getConnection()->inTransaction());
    }

    #[Test]
    public function transactionRollsBackOnException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('test failure');

        try {
            $this->db->transaction(function () {
                throw new Exception('test failure');
            });
        } finally {
            $this->assertFalse($this->db->getConnection()->inTransaction());
        }
    }

    // ─── insert() / last_insert() ───────────────────────────────────────────

    #[Test]
    public function insertReturnsLastInsertId(): void
    {
        $data = [
            'name'                      => '__test_db_insert_' . uniqid(),
            'type'                      => 'NONE',
            'class_load'                => 'none',
            'extra_parameters'          => '{}',
            'translate_relative_url'    => '',
            'contribute_relative_url'   => '',
            'delete_relative_url'       => '',
            'others'                    => '{}',
            'active'                    => 0,
        ];

        $mask = array_keys($data);
        $id = $this->db->insert('engines', $data, $mask);

        $this->assertIsString($id);
        $this->assertGreaterThan(0, (int) $id);

        $this->db->getConnection()->exec("DELETE FROM engines WHERE id = " . (int) $id);
    }

    #[Test]
    public function insertWithIgnoreDuplicateDoesNotThrow(): void
    {
        $data = [
            'name'                      => '__test_db_ignore_' . uniqid(),
            'type'                      => 'NONE',
            'class_load'                => 'none',
            'extra_parameters'          => '{}',
            'translate_relative_url'    => '',
            'contribute_relative_url'   => '',
            'delete_relative_url'       => '',
            'others'                    => '{}',
            'active'                    => 0,
        ];

        $mask = array_keys($data);
        $id = $this->db->insert('engines', $data, $mask, true);
        $id2 = $this->db->insert('engines', $data, $mask, true);

        $this->db->getConnection()->exec("DELETE FROM engines WHERE id IN (" . (int) $id . ", " . (int) $id2 . ")");
        $this->assertTrue(true);
    }

    // ─── update() ───────────────────────────────────────────────────────────

    #[Test]
    public function updateReturnsAffectedRows(): void
    {
        $name = '__test_db_update_' . uniqid();
        $data = [
            'name'                      => $name,
            'type'                      => 'NONE',
            'class_load'                => 'none',
            'extra_parameters'          => '{}',
            'translate_relative_url'    => '',
            'contribute_relative_url'   => '',
            'delete_relative_url'       => '',
            'others'                    => '{}',
            'active'                    => 0,
        ];
        $mask = array_keys($data);
        $id = $this->db->insert('engines', $data, $mask);

        $affected = $this->db->update(
            'engines',
            ['active' => 1],
            ['id' => (int) $id]
        );

        $this->assertSame(1, $affected);
        $this->assertSame(1, $this->db->rowCount());

        $this->db->getConnection()->exec("DELETE FROM engines WHERE id = " . (int) $id);
    }

    #[Test]
    public function updateWithNonMatchingWhereReturnsZero(): void
    {
        $affected = $this->db->update(
            'engines',
            ['active' => 1],
            ['id' => 999999999]
        );

        $this->assertSame(0, $affected);
    }

    // ─── buildInsertStatement() ─────────────────────────────────────────────

    #[Test]
    public function buildInsertStatementGeneratesCorrectSQL(): void
    {
        $attrs = ['name' => 'test', 'type' => 'NONE'];
        $mask = ['name', 'type'];

        [$sql, $dupBindValues] = Database::buildInsertStatement('engines', $attrs, $mask);

        $this->assertStringContainsString('INSERT', $sql);
        $this->assertStringContainsString('`name`', $sql);
        $this->assertStringContainsString('`type`', $sql);
        $this->assertStringContainsString(':name', $sql);
        $this->assertStringContainsString(':type', $sql);
        $this->assertEmpty($dupBindValues);
    }

    #[Test]
    public function buildInsertStatementWithIgnore(): void
    {
        $attrs = ['name' => 'test'];
        $mask = ['name'];

        [$sql] = Database::buildInsertStatement('engines', $attrs, $mask, true);

        $this->assertStringContainsString('INSERT  IGNORE ', $sql);
    }

    #[Test]
    public function buildInsertStatementThrowsOnEmptyTable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('TABLE constant is not defined');

        Database::buildInsertStatement('', ['a' => 1]);
    }

    #[Test]
    public function buildInsertStatementThrowsOnIgnoreWithDuplicateKey(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('INSERT IGNORE and ON DUPLICATE KEYS UPDATE are not allowed together');

        $mask = [];
        Database::buildInsertStatement('t', ['a' => 1], $mask, true, false, ['a' => 'override']);
    }

    #[Test]
    public function buildInsertStatementWithOnDuplicateKey(): void
    {
        $attrs = ['name' => 'test', 'type' => 'NONE'];
        $mask = ['name', 'type'];
        $onDup = ['name' => 'value'];

        [$sql, $dupBindValues] = Database::buildInsertStatement('engines', $attrs, $mask, false, false, $onDup);

        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        $this->assertStringContainsString('VALUES( name )', $sql);
        $this->assertEmpty($dupBindValues);
    }

    #[Test]
    public function buildInsertStatementWithOnDuplicateKeyAndExplicitBindValue(): void
    {
        $attrs = ['name' => 'test', 'type' => 'NONE'];
        $mask = ['name', 'type'];
        $onDup = ['name' => 'some_literal_string'];

        [$sql, $dupBindValues] = Database::buildInsertStatement('engines', $attrs, $mask, false, false, $onDup);

        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        $this->assertStringContainsString(':dupUpdate_name', $sql);
        $this->assertArrayHasKey(':dupUpdate_name', $dupBindValues);
        $this->assertSame('some_literal_string', $dupBindValues[':dupUpdate_name']);
    }

    #[Test]
    public function buildInsertStatementNoNullsExcludesNullFields(): void
    {
        $attrs = ['name' => 'test', 'type' => null];
        $mask = ['name', 'type'];

        [$sql] = Database::buildInsertStatement('engines', $attrs, $mask, false, true);

        $this->assertStringContainsString('`name`', $sql);
        $this->assertStringNotContainsString('`type`', $sql);
    }

    // ─── nextSequence() ─────────────────────────────────────────────────────

    #[Test]
    public function nextSequenceReturnsArrayOfIds(): void
    {
        $ids = $this->db->nextSequence(Database::SEQ_ID_SEGMENT, 3);

        $this->assertIsArray($ids);
        $this->assertCount(3, $ids);
        $this->assertSame($ids[0] + 1, $ids[1]);
        $this->assertSame($ids[1] + 1, $ids[2]);
    }

    #[Test]
    public function nextSequenceThrowsOnInvalidSequenceName(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Undefined sequence');

        $this->db->nextSequence('nonexistent_sequence');
    }

    #[Test]
    public function nextSequenceIncrementsByOne(): void
    {
        $first = $this->db->nextSequence(Database::SEQ_ID_SEGMENT, 1);
        $second = $this->db->nextSequence(Database::SEQ_ID_SEGMENT, 1);

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame($first[0] + 1, $second[0]);
    }

    // ─── useDb() ────────────────────────────────────────────────────────────

    #[Test]
    public function useDbSwitchesDatabase(): void
    {
        $this->db->useDb(AppConfig::$DB_DATABASE);
        $this->assertTrue($this->db->ping());
    }

    // ─── close() / ping() ───────────────────────────────────────────────────

    #[Test]
    public function closeResetsConnection(): void
    {
        $this->db->getConnection();
        $this->db->close();

        $reflector = new ReflectionClass($this->db);
        $connProp = $reflector->getProperty('connection');
        $this->assertNull($connProp->getValue($this->db));
    }

    #[Test]
    public function pingReturnsTrue(): void
    {
        $this->assertTrue($this->db->ping());
    }

    #[Test]
    public function getConnectionReconnectsAfterClose(): void
    {
        $this->db->getConnection();
        $this->db->close();

        $conn = $this->db->getConnection();
        $this->assertInstanceOf(PDO::class, $conn);
    }
}
