<?php

namespace Matecat\TestHelpers;

use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Utils\Registry\AppConfig;

abstract class AbstractTest extends TestCase
{

    protected ?float $thisTestStartingTime = null;

    protected IDatabase $databaseInstance;
    protected ReflectionMethod $reflectedMethod;

    /**
     * Tracks whether this test replaced the Database singleton with a mock/stub
     * so tearDown() can restore the real connection and avoid leaking the mock
     * into subsequent tests in the same process.
     */
    protected bool $databaseMockApplied = false;

    public static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->thisTestStartingTime = microtime(true);
    }

    protected function tearDown(): void
    {
        if ($this->databaseMockApplied) {
            $this->resetDatabaseMock();
            $this->databaseMockApplied = false;
        } else {
            // Roll back any transaction a test left open on the shared connection.
            // Without this, a DAO that throws inside an open tx (e.g. once the
            // AbstractDao ctor requires a non-null IDatabase) orphans the tx on the
            // singleton -> innodb_lock_wait cascade into the next test's setUp.
            // rollback() is a no-op when no transaction is active; teardown cleanup
            // must never mask the test result, so swallow any error.
            try {
                Database::obtain()->rollback();
            } catch (\Throwable) {
            }
        }

        parent::tearDown();
        $resultTime = microtime(true) - $this->thisTestStartingTime ?? microtime(true);
        echo " " . str_pad(get_class($this) . "::" . $this->name(), 35) . " - Did in " . round($resultTime, 6) . " seconds.\n";
    }

    /**
     * @return array{0: IDatabase, 1: PDO, 2: PDOStatement}
     * @throws Exception
     */
    protected function createDatabaseMock(): array
    {
        $stmtStub = $this->createStub(PDOStatement::class);
        $stmtStub->queryString = '';

        $pdoStub = $this->createStub(PDO::class);
        $pdoStub->method('prepare')->willReturn($stmtStub);

        $dbStub = $this->createStub(IDatabase::class);
        $dbStub->method('getConnection')->willReturn($pdoStub);

        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, $dbStub);
        $this->databaseMockApplied = true;

        return [$dbStub, $pdoStub, $stmtStub];
    }

    protected function resetDatabaseMock(): void
    {
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, null);

        Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE);
    }

    protected function setDatabaseInstance(?IDatabase $db): void
    {
        $ref = new ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setValue(null, $db);
        $this->databaseMockApplied = true;
    }

    /**
     * @param IDatabase $database_instance
     * @return mixed
     */
    protected function getTheLastInsertIdByQuery(IDatabase $database_instance): mixed
    {
        $stmt = $database_instance->getConnection()->query("SELECT LAST_INSERT_ID()");
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    /**
     * @param array $preparedQuery
     *
     * @return string
     */
    protected function getRawQuery(array $preparedQuery): string
    {
        $rawQuery = $preparedQuery[0];
        foreach ($preparedQuery[1] as $key => $value) {
            if (is_string($value)) {
                $value = '\'' . $value . '\'';
            }

            $rawQuery = str_replace(':' . $key, $value, $rawQuery);
        }

        return $rawQuery;
    }
}

