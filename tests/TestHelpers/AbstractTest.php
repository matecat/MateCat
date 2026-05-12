<?php

namespace TestHelpers;

use Model\DataAccess\IDatabase;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * User: domenico
 * Date: 09/10/13
 * Time: 15.21
 *
 */
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
     * Return the raw query from a prepared query:
     *
     * Example
     * ----------------------------------------
     * Convert this:
     *
     * array(2) {
     *    [0] => string(36) "SELECT * FROM engines WHERE id = :id"
     *    [1] =>
     *    array(1) {
     *       'id' => int(10)
     *    }
     * }
     *
     * into this:
     *
     * SELECT * FROM engines WHERE id = 10
     *
     * @param array $preparedQuery
     *
     * @return string
     *
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

