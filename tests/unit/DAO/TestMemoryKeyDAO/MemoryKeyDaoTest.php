<?php

namespace unit\DAO\TestMemoryKeyDAO;

use Exception;
use Model\DataAccess\IDatabase;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\TmKeyManagement\TmKeyStruct;

class MemoryKeyDaoTest extends AbstractTest
{
    private MemoryKeyDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $dbStub = $this->createStub(IDatabase::class);
        $this->dao = new MemoryKeyDao($dbStub);
    }

    #[Test]
    public function testSanitizeReturnsMemoryKeyStruct(): void
    {
        $input = new MemoryKeyStruct(['uid' => 42]);
        $input->tm_key = new TmKeyStruct(['key' => 'abc123']);

        $result = $this->dao->sanitize($input);

        $this->assertInstanceOf(MemoryKeyStruct::class, $result);
        $this->assertSame(42, $result->uid);
    }

    #[Test]
    public function testSanitizeThrowsOnInvalidStructType(): void
    {
        $wrongStruct = new UserStruct();

        $this->expectException(Exception::class);
        $this->dao->sanitize($wrongStruct);
    }

    #[Test]
    public function testSanitizeArrayReturnsTypedArray(): void
    {
        $s1 = new MemoryKeyStruct(['uid' => 1]);
        $s1->tm_key = new TmKeyStruct(['key' => 'k1']);
        $s2 = new MemoryKeyStruct(['uid' => 2]);
        $s2->tm_key = new TmKeyStruct(['key' => 'k2']);

        $result = MemoryKeyDao::sanitizeArray([$s1, $s2]);

        $this->assertCount(2, $result);
        $this->assertInstanceOf(MemoryKeyStruct::class, $result[0]);
        $this->assertInstanceOf(MemoryKeyStruct::class, $result[1]);
    }

    #[Test]
    public function testSanitizeArrayThrowsOnInvalidElement(): void
    {
        $valid = new MemoryKeyStruct(['uid' => 1]);
        $invalid = new UserStruct();

        $this->expectException(Exception::class);
        MemoryKeyDao::sanitizeArray([$valid, $invalid]);
    }

    /**
     * Regression: current code accesses $obj->tm_key->key without
     * null-checking $obj->tm_key, causing TypeError instead of Exception.
     */
    #[Test]
    public function testCreateThrowsCleanExceptionWhenTmKeyIsNull(): void
    {
        $struct = new MemoryKeyStruct(['uid' => 123]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Key value cannot be null');
        $this->dao->create($struct);
    }

    #[Test]
    public function testCreateThrowsWhenUidIsEmpty(): void
    {
        $struct = new MemoryKeyStruct();
        $struct->tm_key = new TmKeyStruct(['key' => 'abc']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Uid cannot be null');
        $this->dao->create($struct);
    }

    #[Test]
    public function testCreateThrowsWhenTmKeyKeyIsNull(): void
    {
        $struct = new MemoryKeyStruct(['uid' => 123]);
        $struct->tm_key = new TmKeyStruct();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Key value cannot be null');
        $this->dao->create($struct);
    }

    #[Test]
    public function testDeleteThrowsWhenUidIsEmpty(): void
    {
        $struct = new MemoryKeyStruct();
        $struct->tm_key = new TmKeyStruct(['key' => 'abc']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Uid');
        $this->dao->delete($struct);
    }

    #[Test]
    public function testDeleteThrowsWhenTmKeyIsNull(): void
    {
        $struct = new MemoryKeyStruct(['uid' => 123]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Key value');
        $this->dao->delete($struct);
    }

    #[Test]
    public function testDeleteThrowsWhenTmKeyKeyIsNull(): void
    {
        $struct = new MemoryKeyStruct(['uid' => 123]);
        $struct->tm_key = new TmKeyStruct();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Key value');
        $this->dao->delete($struct);
    }

    #[Test]
    public function testValidateNotNullFieldsRejectsWrongStructType(): void
    {
        $method = new \ReflectionMethod(MemoryKeyDao::class, '_validateNotNullFields');
        $wrongStruct = new UserStruct();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Expected MemoryKeyStruct');
        $method->invoke($this->dao, $wrongStruct);
    }

    #[Test]
    public function testValidatePrimaryKeyRejectsWrongStructType(): void
    {
        $method = new \ReflectionMethod(MemoryKeyDao::class, '_validatePrimaryKey');
        $wrongStruct = new UserStruct();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Expected MemoryKeyStruct');
        $method->invoke($this->dao, $wrongStruct);
    }
}
