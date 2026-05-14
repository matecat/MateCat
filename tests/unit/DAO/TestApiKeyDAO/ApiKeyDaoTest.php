<?php

namespace unit\DAO\TestApiKeyDAO;

use Model\ApiKeys\ApiKeyDao;
use Model\ApiKeys\ApiKeyStruct;
use Model\DataAccess\IDatabase;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class TestApiKeyDaoStub extends ApiKeyDao
{
    private ?ApiKeyStruct $stubbedGetByUidResult = null;

    public function setGetByUidResult(?ApiKeyStruct $result): void
    {
        $this->stubbedGetByUidResult = $result;
    }

    public function getByUid(int $uid): ?ApiKeyStruct
    {
        return $this->stubbedGetByUidResult;
    }
}

class ApiKeyDaoTest extends AbstractTest
{
    #[Test]
    public function getByUid_returns_struct_when_found(): void
    {
        $expected          = new ApiKeyStruct();
        $expected->id      = 1;
        $expected->uid     = 42;
        $expected->api_key    = 'test_api_key';
        $expected->api_secret = 'test_secret';
        $expected->create_date = '2024-01-01 00:00:00';
        $expected->last_update = '2024-01-01 00:00:00';
        $expected->enabled    = true;

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($expected);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $dao    = new ApiKeyDao($db);
        $result = $dao->getByUid(42);

        $this->assertInstanceOf(ApiKeyStruct::class, $result);
        $this->assertSame(42, $result->uid);
    }

    #[Test]
    public function getByUid_returns_null_when_not_found(): void
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $dao    = new ApiKeyDao($db);
        $result = $dao->getByUid(99999);

        $this->assertNull($result);
    }

    #[Test]
    public function deleteByUid_returns_zero_when_key_not_found(): void
    {
        $db = $this->createStub(IDatabase::class);

        $dao = new TestApiKeyDaoStub($db);
        $dao->setGetByUidResult(null);

        $result = $dao->deleteByUid(999);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function deleteByUid_deletes_record_and_returns_row_count(): void
    {
        $apiKey               = new ApiKeyStruct();
        $apiKey->id           = 5;
        $apiKey->uid          = 1;
        $apiKey->api_key      = 'some_key';
        $apiKey->api_secret   = 'some_secret';
        $apiKey->create_date  = '2024-01-01 00:00:00';
        $apiKey->last_update  = '2024-01-01 00:00:00';
        $apiKey->enabled      = true;

        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(1);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        $dao = new TestApiKeyDaoStub($db);
        $dao->setGetByUidResult($apiKey);

        $result = $dao->deleteByUid(1);

        $this->assertSame(1, $result);
    }
}
