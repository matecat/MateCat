<?php

namespace Matecat\Core\DAO\TestMemoryKeyDAO;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Utils\TmKeyManagement\TmKeyStruct;

class TestMemoryKeyDao extends MemoryKeyDao
{
    public array $fetchResult = [];

    protected function _fetchObjectMap(
        PDOStatement $stmt,
        string       $fetchClass,
        array        $bindParams,
        ?string      $keyMap = null
    ): array {
        $stmt->execute($bindParams);

        return $this->fetchResult;
    }
}

class MemoryKeyDaoTest extends AbstractTest
{
    private MemoryKeyDao $dao;

    protected function setUp(): void
    {
        parent::setUp();
        $dbStub = $this->createStub(IDatabase::class);
        $this->dao = new MemoryKeyDao($dbStub);
    }

    private function makeDbStub(): IDatabase
    {
        $stmt = $this->createStub(PDOStatement::class);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        return $db;
    }

    private function makeDbStubWithRowCount(int $rowCount): IDatabase
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('rowCount')->willReturn($rowCount);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $db = $this->createStub(IDatabase::class);
        $db->method('getConnection')->willReturn($pdo);

        return $db;
    }

    private function makeValidStruct(): MemoryKeyStruct
    {
        $struct = new MemoryKeyStruct(['uid' => 123]);
        $struct->tm_key = new TmKeyStruct(['key' => 'abc123', 'name' => 'test-key']);

        return $struct;
    }

    // ── sanitize ──

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

    // ── sanitizeArray ──

    #[Test]
    public function testSanitizeArrayReturnsTypedArray(): void
    {
        $s1 = new MemoryKeyStruct(['uid' => 1]);
        $s1->tm_key = new TmKeyStruct(['key' => 'k1']);
        $s2 = new MemoryKeyStruct(['uid' => 2]);
        $s2->tm_key = new TmKeyStruct(['key' => 'k2']);

        $result = $this->dao->sanitizeArray([$s1, $s2]);

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
        $this->dao->sanitizeArray([$valid, $invalid]);
    }

    #[Test]
    public function testSanitizeArrayWithEmptyArrayReturnsEmpty(): void
    {
        $result = $this->dao->sanitizeArray([]);
        $this->assertSame([], $result);
    }

    // ── getKeyringOwnerKeysByUid ──

    #[Test]
    public function testGetKeyringOwnerKeysByUidReturnsArray(): void
    {
        $result = $this->dao->getKeyringOwnerKeysByUid(999);
        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    // ── create ──

    #[Test]
    public function testCreateReturnsStructOnSuccess(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStubWithRowCount(1));
        $struct = $this->makeValidStruct();

        $result = $dao->create($struct);

        $this->assertInstanceOf(MemoryKeyStruct::class, $result);
        $this->assertSame(123, $result->uid);
    }

    #[Test]
    public function testCreateReturnsNullWhenNoRowInserted(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStubWithRowCount(0));
        $struct = $this->makeValidStruct();

        $result = $dao->create($struct);

        $this->assertNull($result);
    }

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

    // ── read ──

    #[Test]
    public function testReadReturnsMemoryKeyStructs(): void
    {
        $db = $this->makeDbStub();
        $dao = new TestMemoryKeyDao($db);
        $dao->fetchResult = [
            new ShapelessConcreteStruct([
                'uid'        => 42,
                'key_value'  => 'mykey',
                'key_name'   => 'My Key',
                'tm'         => 1,
                'glos'       => 0,
                'owners_tot' => 1,
                'owner_uids' => '42',
            ]),
        ];

        $input = new MemoryKeyStruct(['uid' => 42]);
        $result = $dao->read($input);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(MemoryKeyStruct::class, $result[0]);
        $this->assertSame('mykey', $result[0]->tm_key->key);
        $this->assertSame('My Key', $result[0]->tm_key->name);
        $this->assertTrue($result[0]->tm_key->tm);
        $this->assertFalse($result[0]->tm_key->glos);
        $this->assertFalse($result[0]->tm_key->is_shared);
        $this->assertTrue($result[0]->tm_key->owner);
    }

    #[Test]
    public function testReadWithSharedKey(): void
    {
        $db = $this->makeDbStub();
        $dao = new TestMemoryKeyDao($db);
        $dao->fetchResult = [
            new ShapelessConcreteStruct([
                'uid'        => 42,
                'key_value'  => 'shared-key',
                'key_name'   => 'Shared',
                'tm'         => 1,
                'glos'       => 1,
                'owners_tot' => 3,
                'owner_uids' => '42,55,66',
            ]),
        ];

        $input = new MemoryKeyStruct(['uid' => 42]);
        $result = $dao->read($input);

        $this->assertTrue($result[0]->tm_key->is_shared);
        $this->assertTrue($result[0]->tm_key->owner);
    }

    #[Test]
    public function testReadWithKeyFilter(): void
    {
        $db = $this->makeDbStub();
        $dao = new TestMemoryKeyDao($db);
        $dao->fetchResult = [];

        $input = new MemoryKeyStruct(['uid' => 42]);
        $input->tm_key = new TmKeyStruct(['key' => 'specific-key', 'name' => 'test', 'tm' => true, 'glos' => false]);
        $result = $dao->read($input);

        $this->assertSame([], $result);
    }

    #[Test]
    public function testReadReturnsEmptyArrayWhenNoResults(): void
    {
        $db = $this->makeDbStub();
        $dao = new TestMemoryKeyDao($db);
        $dao->fetchResult = [];

        $input = new MemoryKeyStruct(['uid' => 999]);
        $result = $dao->read($input);

        $this->assertSame([], $result);
    }

    // ── atomicUpdate ──

    #[Test]
    public function testAtomicUpdateReturnsStructOnSuccess(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStubWithRowCount(1));
        $struct = $this->makeValidStruct();

        $result = $dao->atomicUpdate($struct);

        $this->assertInstanceOf(MemoryKeyStruct::class, $result);
    }

    #[Test]
    public function testAtomicUpdateReturnsNullWhenNoRowUpdated(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStubWithRowCount(0));
        $struct = $this->makeValidStruct();

        $result = $dao->atomicUpdate($struct);

        $this->assertNull($result);
    }

    #[Test]
    public function testAtomicUpdateThrowsWhenTmKeyIsNull(): void
    {
        $struct = new MemoryKeyStruct(['uid' => 123]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Key value');
        $this->dao->atomicUpdate($struct);
    }

    #[Test]
    public function testAtomicUpdateThrowsWhenNameIsNull(): void
    {
        $struct = new MemoryKeyStruct(['uid' => 123]);
        $struct->tm_key = new TmKeyStruct(['key' => 'abc']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Array given is empty');
        $dao = new MemoryKeyDao($this->makeDbStub());
        $dao->atomicUpdate($struct);
    }

    // ── delete ──

    #[Test]
    public function testDeleteReturnsStructOnSuccess(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStubWithRowCount(1));
        $struct = $this->makeValidStruct();

        $result = $dao->delete($struct);

        $this->assertInstanceOf(MemoryKeyStruct::class, $result);
    }

    #[Test]
    public function testDeleteReturnsNullWhenNoRowDeleted(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStubWithRowCount(0));
        $struct = $this->makeValidStruct();

        $result = $dao->delete($struct);

        $this->assertNull($result);
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

    // ── disable ──

    #[Test]
    public function testDisableReturnsStructOnSuccess(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStubWithRowCount(1));
        $struct = $this->makeValidStruct();

        $result = $dao->disable($struct);

        $this->assertInstanceOf(MemoryKeyStruct::class, $result);
    }

    #[Test]
    public function testDisableReturnsNullWhenNoRowUpdated(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStubWithRowCount(0));
        $struct = $this->makeValidStruct();

        $result = $dao->disable($struct);

        $this->assertNull($result);
    }

    #[Test]
    public function testDisableThrowsWhenUidIsEmpty(): void
    {
        $struct = new MemoryKeyStruct();
        $struct->tm_key = new TmKeyStruct(['key' => 'abc']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Uid');
        $this->dao->disable($struct);
    }

    #[Test]
    public function testDisableThrowsWhenTmKeyIsNull(): void
    {
        $struct = new MemoryKeyStruct(['uid' => 123]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Key value');
        $this->dao->disable($struct);
    }

    // ── enable ──

    #[Test]
    public function testEnableReturnsStructOnSuccess(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStubWithRowCount(1));
        $struct = $this->makeValidStruct();

        $result = $dao->enable($struct);

        $this->assertInstanceOf(MemoryKeyStruct::class, $result);
    }

    #[Test]
    public function testEnableReturnsNullWhenNoRowUpdated(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStubWithRowCount(0));
        $struct = $this->makeValidStruct();

        $result = $dao->enable($struct);

        $this->assertNull($result);
    }

    #[Test]
    public function testEnableThrowsWhenTmKeyIsNull(): void
    {
        $struct = new MemoryKeyStruct(['uid' => 123]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Key value');
        $this->dao->enable($struct);
    }

    // ── createList ──

    #[Test]
    public function testCreateListExecutesWithValidStructs(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStub());

        $s1 = $this->makeValidStruct();
        $s2 = new MemoryKeyStruct(['uid' => 456]);
        $s2->tm_key = new TmKeyStruct(['key' => 'def456', 'name' => 'second-key']);

        $dao->createList([$s1, $s2]);

        $this->assertTrue(true);
    }

    #[Test]
    public function testCreateListThrowsOnInvalidElement(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStub());
        $invalid = new UserStruct();

        $this->expectException(Exception::class);
        $dao->createList([$invalid]);
    }

    #[Test]
    public function testCreateListThrowsWhenTmKeyIsNull(): void
    {
        $dao = new MemoryKeyDao($this->makeDbStub());
        $struct = new MemoryKeyStruct(['uid' => 123]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Key value cannot be null');
        $dao->createList([$struct]);
    }

    // ── validators ──

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

    #[Test]
    public function testValidatePrimaryKeyRejectsEmptyTmKeyKey(): void
    {
        $method = new \ReflectionMethod(MemoryKeyDao::class, '_validatePrimaryKey');
        $struct = new MemoryKeyStruct(['uid' => 123]);
        $struct->tm_key = new TmKeyStruct(['key' => '']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Key value');
        $method->invoke($this->dao, $struct);
    }
}
