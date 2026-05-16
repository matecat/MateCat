<?php

use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Model\DataAccess\Database;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

#[Group('PersistenceNeeded')]
class GetByIdApikeyTest extends AbstractTest
{
    private ApiKeyDao $apiKeyDao;
    private Database $database;
    private int $apikeyId;

    public function setUp(): void
    {
        parent::setUp();
        $this->database = Database::obtain(AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE);
        $this->apiKeyDao = new ApiKeyDao($this->database);

        $sql = "INSERT INTO " . AppConfig::$DB_DATABASE . ".`api_keys` " .
            " ( uid, api_key, api_secret, create_date, last_update, enabled ) " .
            " VALUES " .
            " ( '1999', 'c4ca4238bar92382fake509a6f758foo', 'api_secret' , '2016-06-16 18:06:29', '2016-06-16 19:06:30', '1') ";

        $this->database->getConnection()->query($sql);
        $this->apikeyId = (int)$this->database->getConnection()->lastInsertId();
    }

    public function tearDown(): void
    {
        $this->database->getConnection()->query(
            "DELETE FROM " . AppConfig::$DB_DATABASE . ".`api_keys` WHERE id = " . $this->apikeyId
        );
        (new Predis\Client(AppConfig::$REDIS_SERVERS))->flushdb();
        parent::tearDown();
    }

    #[Test]
    public function fetchById_returns_struct(): void
    {
        $result = $this->apiKeyDao->fetchById($this->apikeyId, ApiKeyStruct::class);

        $this->assertInstanceOf(ApiKeyStruct::class, $result);
        $this->assertEquals($this->apikeyId, $result->id);
        $this->assertEquals(1999, $result->uid);
        $this->assertEquals('c4ca4238bar92382fake509a6f758foo', $result->api_key);
        $this->assertEquals('api_secret', $result->api_secret);
        $this->assertEquals('2016-06-16 18:06:29', $result->create_date);
        $this->assertEquals('2016-06-16 19:06:30', $result->last_update);
        $this->assertEquals(1, $result->enabled);
    }

    #[Test]
    public function fetchById_returns_null_for_nonexistent_id(): void
    {
        $result = $this->apiKeyDao->fetchById(999999, ApiKeyStruct::class);
        $this->assertNull($result);
    }
}
