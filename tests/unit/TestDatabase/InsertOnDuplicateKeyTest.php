<?php

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

/**
 * @group regression
 * @covers \Model\DataAccess\Database::insert
 */
#[Group('PersistenceNeeded')]
class InsertOnDuplicateKeyTest extends AbstractTest
{
    protected const string TABLE_NAME = 'test_insert_on_duplicate';

    public function setUp(): void
    {
        parent::setUp();
        $this->databaseInstance = Database::obtain(
            AppConfig::$DB_SERVER,
            AppConfig::$DB_USER,
            AppConfig::$DB_PASS,
            AppConfig::$DB_DATABASE
        );

        // Create temporary table
        $sql = "CREATE TEMPORARY TABLE " . self::TABLE_NAME . " (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending'
        )";
        $this->databaseInstance->getConnection()->exec($sql);
    }

    public function tearDown(): void
    {
        // Drop temporary table
        $sql = "DROP TEMPORARY TABLE IF EXISTS " . self::TABLE_NAME;
        try {
            $this->databaseInstance->getConnection()->exec($sql);
        } catch (Exception) {
            // Silently ignore if table doesn't exist
        }

        parent::tearDown();
    }

    /**
     * Test upsert with 'value' references in ON DUPLICATE KEY clause.
     * This test SHOULD PASS because all duplicate fields use VALUES() syntax.
     * No bind values are needed in the UPDATE clause.
     *
     * @group regression
     * @covers \Model\DataAccess\Database::insert
     */
    #[Test]
    public function test_upsert_with_values_references()
    {
        // Insert initial row
        $mask = ['id' => 'id', 'name' => 'name', 'status' => 'status'];
        $data = ['id' => 1, 'name' => 'Initial', 'status' => 'pending'];

        $this->databaseInstance->insert(self::TABLE_NAME, $data, $mask);

        // Verify initial insert
        $stmt = $this->databaseInstance->getConnection()->prepare(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE id = ?"
        );
        $stmt->execute([1]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Initial', $row['name']);
        $this->assertEquals('pending', $row['status']);

        // Upsert with 'value' references - all duplicate fields use VALUES()
        $mask = ['id' => 'id', 'name' => 'name', 'status' => 'status'];
        $data = ['id' => 1, 'name' => 'Updated', 'status' => 'active'];
        $onDuplicateKey = ['name' => 'value', 'status' => 'value'];

        $this->databaseInstance->insert(self::TABLE_NAME, $data, $mask, false, false, $onDuplicateKey);

        // Verify the row was updated with the new values
        $stmt = $this->databaseInstance->getConnection()->prepare(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE id = ?"
        );
        $stmt->execute([1]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Updated', $row['name']);
        $this->assertEquals('active', $row['status']);
    }

    /**
     * Test upsert with mixed 'value' references and literal bind values.
     * This tests the previously-buggy path where literal bind values were lost.
     * After the fix, the literal value 'archived' should be properly bound and applied.
     *
     * @group regression
     * @covers \Model\DataAccess\Database::insert
     */
    #[Test]
    public function test_upsert_with_literal_bind_values()
    {
        // Insert initial row
        $mask = ['id' => 'id', 'name' => 'name', 'status' => 'status'];
        $data = ['id' => 2, 'name' => 'Initial', 'status' => 'pending'];

        $this->databaseInstance->insert(self::TABLE_NAME, $data, $mask);

        // Upsert with mixed: 'value' for name, literal 'archived' for status
        $mask = ['id' => 'id', 'name' => 'name', 'status' => 'status'];
        $data = ['id' => 2, 'name' => 'Updated', 'status' => 'active'];
        $onDuplicateKey = ['name' => 'value', 'status' => 'archived'];

        $this->databaseInstance->insert(self::TABLE_NAME, $data, $mask, false, false, $onDuplicateKey);

        // Verify: name should be 'Updated' (from VALUES), status should be 'archived' (literal)
        $stmt = $this->databaseInstance->getConnection()->prepare(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE id = ?"
        );
        $stmt->execute([2]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('Updated', $row['name']);
        $this->assertEquals('archived', $row['status']);
    }

    /**
     * Test upsert with all literal bind values in ON DUPLICATE KEY clause.
     * After the fix, all literal values should be properly bound and applied.
     *
     * @group regression
     * @covers \Model\DataAccess\Database::insert
     */
    #[Test]
    public function test_upsert_with_all_literal_bind_values()
    {
        // Insert initial row
        $mask = ['id' => 'id', 'name' => 'name', 'status' => 'status'];
        $data = ['id' => 3, 'name' => 'Initial', 'status' => 'pending'];

        $this->databaseInstance->insert(self::TABLE_NAME, $data, $mask);

        // Upsert with all literal values
        $mask = ['id' => 'id', 'name' => 'name', 'status' => 'status'];
        $data = ['id' => 3, 'name' => 'UpdatedName', 'status' => 'active'];
        $onDuplicateKey = ['name' => 'LiteralName', 'status' => 'archived'];

        $this->databaseInstance->insert(self::TABLE_NAME, $data, $mask, false, false, $onDuplicateKey);

        // Verify: both should be the literal values, not the INSERT values
        $stmt = $this->databaseInstance->getConnection()->prepare(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE id = ?"
        );
        $stmt->execute([3]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('LiteralName', $row['name']);
        $this->assertEquals('archived', $row['status']);
    }

    /**
     * Test insertStruct with literal ON DUPLICATE KEY value using AbstractDao path.
     * After the fix, the literal value 'overridden' should be properly bound and applied.
     *
     * @group regression
     * @covers \Model\DataAccess\Database::insert
     */
    #[Test]
    public function test_insert_struct_with_literal_on_duplicate_key()
    {
        // Insert initial struct
        $struct1 = new TestUpsertStruct();
        $struct1->id = 10;
        $struct1->name = 'Struct1';
        $struct1->status = 'active';

        TestUpsertDao::insertStruct($struct1, ['no_nulls' => true]);

        // Verify initial insert
        $stmt = $this->databaseInstance->getConnection()->prepare(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE id = ?"
        );
        $stmt->execute([10]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Struct1', $row['name']);
        $this->assertEquals('active', $row['status']);

        // Create new struct with same id, different values
        $struct2 = new TestUpsertStruct();
        $struct2->id = 10;
        $struct2->name = 'Struct2';
        $struct2->status = 'inactive';

        // Upsert with literal 'overridden' for status
        TestUpsertDao::insertStruct(
            $struct2,
            [
                'no_nulls' => true,
                'on_duplicate_update' => [
                    'name' => 'value',
                    'status' => 'overridden'
                ]
            ]
        );

        // Verify: name should be 'Struct2' (from VALUES), status should be 'overridden' (literal)
        $stmt = $this->databaseInstance->getConnection()->prepare(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE id = ?"
        );
        $stmt->execute([10]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertEquals('Struct2', $row['name']);
        $this->assertEquals('overridden', $row['status']);
    }
}

/**
 * Helper struct for insertStruct test.
 * Implements IDaoStruct interface for use with AbstractDao::insertStruct().
 */
class TestUpsertStruct implements IDaoStruct
{
    public ?int $id = null;
    public ?string $name = null;
    public ?string $status = null;

    /**
     * Get array copy - required by IDaoStruct
     */
    public function getArrayCopy(): array
    {
        return $this->toArray();
    }

    /**
     * Count elements - required by IDaoStruct
     */
    public function count(): int
    {
        return count($this->toArray());
    }

    /**
     * Convert to array with optional field mask
     */
    public function toArray(array $mask = null): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status
        ];

        if ($mask !== null) {
            $data = array_intersect_key($data, array_flip($mask));
        }

        return $data;
    }
}

/**
 * Helper DAO class for insertStruct test.
 * Extends AbstractDao for use in testing insertStruct() method.
 */
class TestUpsertDao extends AbstractDao
{
    const string TABLE = 'test_insert_on_duplicate';
    protected static array $primary_keys = ['id'];
}
