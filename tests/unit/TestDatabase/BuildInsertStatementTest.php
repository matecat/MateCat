<?php

use Model\DataAccess\Database;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @group regression
 * @covers \Model\DataAccess\Database::buildInsertStatement
 */
#[Group('PersistenceNeeded')]
class BuildInsertStatementTest extends TestCase
{
    /**
     * ON DUPLICATE KEY with all 'value' references should return empty bind array.
     * All duplicate fields reference VALUES(key), which don't need PDO binding.
     *
     * @group regression
     * @covers \Model\DataAccess\Database::buildInsertStatement
     */
    #[Test]
    public function test_on_duplicate_key_with_values_references_returns_empty_bind_array()
    {
        $table = 'users';
        $attrs = ['id' => 1, 'name' => 'John', 'status' => 'active'];
        $mask = [];
        $onDuplicateFields = ['name' => 'value', 'status' => 'value'];

        [$sql, $dupBindValues] = Database::buildInsertStatement($table, $attrs, $mask, false, false, $onDuplicateFields);

        $this->assertIsString($sql);
        $this->assertIsArray($dupBindValues);
        $this->assertEmpty($dupBindValues);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        $this->assertStringContainsString('VALUES( name )', $sql);
        $this->assertStringContainsString('VALUES( status )', $sql);
    }

    /**
     * ON DUPLICATE KEY with mixed 'value' references and literal values.
     * One field references VALUES(key), another is a literal requiring binding.
     *
     * @group regression
     * @covers \Model\DataAccess\Database::buildInsertStatement
     */
    #[Test]
    public function test_on_duplicate_key_with_literal_values_returns_bind_array()
    {
        $table = 'users';
        $attrs = ['id' => 1, 'name' => 'John', 'status' => 'active'];
        $mask = [];
        $onDuplicateFields = ['name' => 'value', 'status' => 'inactive'];

        [$sql, $dupBindValues] = Database::buildInsertStatement($table, $attrs, $mask, false, false, $onDuplicateFields);

        $this->assertIsString($sql);
        $this->assertIsArray($dupBindValues);
        $this->assertCount(1, $dupBindValues);
        $this->assertArrayHasKey(':dupUpdate_status', $dupBindValues);
        $this->assertEquals('inactive', $dupBindValues[':dupUpdate_status']);
        $this->assertStringContainsString('VALUES( name )', $sql);
        $this->assertStringContainsString(':dupUpdate_status', $sql);
    }

    /**
     * ON DUPLICATE KEY with all literal values (no 'value' references).
     * All duplicate fields are literals requiring PDO binding.
     *
     * @group regression
     * @covers \Model\DataAccess\Database::buildInsertStatement
     */
    #[Test]
    public function test_on_duplicate_key_all_literals_returns_all_bind_values()
    {
        $table = 'users';
        $attrs = ['id' => 1, 'name' => 'John', 'status' => 'active'];
        $mask = [];
        $onDuplicateFields = ['name' => 'Jane', 'status' => 'inactive'];

        [$sql, $dupBindValues] = Database::buildInsertStatement($table, $attrs, $mask, false, false, $onDuplicateFields);

        $this->assertIsString($sql);
        $this->assertIsArray($dupBindValues);
        $this->assertCount(2, $dupBindValues);
        $this->assertArrayHasKey(':dupUpdate_name', $dupBindValues);
        $this->assertArrayHasKey(':dupUpdate_status', $dupBindValues);
        $this->assertEquals('Jane', $dupBindValues[':dupUpdate_name']);
        $this->assertEquals('inactive', $dupBindValues[':dupUpdate_status']);
        $this->assertStringNotContainsString('VALUES(', $sql);
        $this->assertStringContainsString(':dupUpdate_name', $sql);
        $this->assertStringContainsString(':dupUpdate_status', $sql);
    }

    /**
     * Simple insert without ON DUPLICATE KEY clause.
     * Should return empty bind array.
     *
     * @group regression
     * @covers \Model\DataAccess\Database::buildInsertStatement
     */
    #[Test]
    public function test_simple_insert_returns_array_with_empty_bind_values()
    {
        $table = 'users';
        $attrs = ['id' => 1, 'name' => 'John', 'status' => 'active'];
        $mask = [];

        [$sql, $dupBindValues] = Database::buildInsertStatement($table, $attrs, $mask, false, false, []);

        $this->assertIsString($sql);
        $this->assertIsArray($dupBindValues);
        $this->assertEmpty($dupBindValues);
        $this->assertStringContainsString('INSERT', $sql);
        $this->assertStringContainsString('INTO', $sql);
        $this->assertStringNotContainsString('ON DUPLICATE KEY', $sql);
    }

    /**
     * ON DUPLICATE KEY with no_nulls=true should skip null fields.
     * Null attributes should not appear in ON DUPLICATE KEY UPDATE clause.
     *
     * @group regression
     * @covers \Model\DataAccess\Database::buildInsertStatement
     */
    #[Test]
    public function test_no_nulls_skips_null_fields_in_on_duplicate_key()
    {
        $table = 'users';
        $attrs = ['id' => 1, 'name' => null, 'status' => 'active'];
        $mask = [];
        $onDuplicateFields = ['name' => 'value', 'status' => 'value'];

        [$sql, $dupBindValues] = Database::buildInsertStatement($table, $attrs, $mask, false, true, $onDuplicateFields);

        $this->assertIsString($sql);
        $this->assertIsArray($dupBindValues);
        $this->assertEmpty($dupBindValues);
        // name is null and no_nulls is true → name skipped in ON DUPLICATE KEY
        $this->assertStringNotContainsString('VALUES( name )', $sql);
        // status is not null → should appear
        $this->assertStringContainsString('VALUES( status )', $sql);
    }

    /**
     * INSERT IGNORE with ON DUPLICATE KEY should throw Exception.
     * These two clauses are mutually exclusive.
     *
     * @group regression
     * @covers \Model\DataAccess\Database::buildInsertStatement
     */
    #[Test]
    public function test_insert_ignore_with_on_duplicate_key_throws_exception()
    {
        $table = 'users';
        $attrs = ['id' => 1, 'name' => 'John', 'status' => 'active'];
        $mask = [];
        $onDuplicateFields = ['name' => 'value'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('INSERT IGNORE and ON DUPLICATE KEYS UPDATE are not allowed together.');

        Database::buildInsertStatement($table, $attrs, $mask, true, false, $onDuplicateFields);
    }
}
