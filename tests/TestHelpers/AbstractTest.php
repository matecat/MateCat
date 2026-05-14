<?php

namespace TestHelpers;

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

    protected static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->thisTestStartingTime = microtime(true);
    }

    protected function tearDown(): void
    {
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

