<?php

namespace Matecat\Core\Model\TmKeyManagement;

use DomainException;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use Utils\TmKeyManagement\TmKeyStruct;

class TmKeyStructTest extends AbstractTest
{
    #[Test]
    public function getCryptReturnsEmptyStringWhenKeyIsNull(): void
    {
        $struct = new TmKeyStruct();
        $struct->key = null;

        $this->assertSame('', $struct->getCrypt());
    }

    #[Test]
    public function getCryptReturnsObfuscatedKeyWhenKeyIsSet(): void
    {
        $struct = new TmKeyStruct(['key' => '1234abcd1a2b']);

        $result = $struct->getCrypt();

        $this->assertSame('*******d1a2b', $result);
    }

    #[Test]
    public function isEncryptedKeyReturnsFalseWhenKeyIsNull(): void
    {
        $struct = new TmKeyStruct();
        $struct->key = null;

        $this->assertFalse($struct->isEncryptedKey());
    }

    #[Test]
    public function isEncryptedKeyReturnsTrueWhenKeyContainsAsterisk(): void
    {
        $struct = new TmKeyStruct(['key' => '*******d1a2b']);

        $this->assertTrue($struct->isEncryptedKey());
    }

    #[Test]
    public function isEncryptedKeyReturnsFalseWhenKeyHasNoAsterisk(): void
    {
        $struct = new TmKeyStruct(['key' => '1234abcd1a2b']);

        $this->assertFalse($struct->isEncryptedKey());
    }

    #[Test]
    public function constructorSetsPropertiesFromArray(): void
    {
        $struct = new TmKeyStruct([
            'key'  => 'mykey123',
            'name' => 'My Memory',
            'tm'   => true,
            'glos' => false,
        ]);

        $this->assertSame('mykey123', $struct->key);
        $this->assertSame('My Memory', $struct->name);
        $this->assertTrue($struct->tm);
        $this->assertFalse($struct->glos);
    }

    #[Test]
    public function constructorSetsPropertiesFromTmKeyStruct(): void
    {
        $source = new TmKeyStruct(['key' => 'abc123', 'name' => 'Source Key']);
        $copy = new TmKeyStruct($source);

        $this->assertSame('abc123', $copy->key);
        $this->assertSame('Source Key', $copy->name);
    }

    #[Test]
    public function constructorWithNullDoesNothing(): void
    {
        $struct = new TmKeyStruct(null);

        $this->assertNull($struct->key);
        $this->assertNull($struct->name);
        $this->assertTrue($struct->tm);
    }

    #[Test]
    public function setThrowsDomainExceptionForUnknownProperty(): void
    {
        $this->expectException(DomainException::class);

        $struct = new TmKeyStruct();
        $struct->nonExistentProperty = 'value';
    }

    #[Test]
    public function penaltyDefaultsToZero(): void
    {
        $struct = new TmKeyStruct();

        $this->assertSame(0, $struct->penalty);
    }

    #[Test]
    public function jsonSerializeDoesNotUseNullCoalesceOnPenalty(): void
    {
        $struct = new TmKeyStruct(['key' => 'test', 'penalty' => 50]);

        $json = $struct->jsonSerialize();

        $this->assertSame(50, $json['penalty']);
    }

    #[Test]
    public function getInUsersDefaultsToEmptyArray(): void
    {
        $struct = new TmKeyStruct();

        $this->assertSame([], $struct->getInUsers());
    }

    #[Test]
    public function getInUsersIdDefaultsToEmptyArray(): void
    {
        $struct = new TmKeyStruct();

        $this->assertSame([], $struct->getInUsersId());
    }

    #[Test]
    public function getInUsersReturnsUserStructsSetViaConstructor(): void
    {
        $userA = new UserStruct(['uid' => 11, 'email' => 'a@example.com']);
        $userB = new UserStruct(['uid' => 22, 'email' => 'b@example.com']);

        $struct = new TmKeyStruct([
            'key'      => 'sharedkey0001',
            'is_shared' => true,
            'in_users' => [$userA, $userB],
        ]);

        $this->assertSame([$userA, $userB], $struct->getInUsers());
    }

    #[Test]
    public function getInUsersIdReturnsIdsSetViaConstructor(): void
    {
        $struct = new TmKeyStruct([
            'key'          => 'sharedkey0002',
            'in_users_id'  => [11, 22, 33],
        ]);

        $this->assertSame([11, 22, 33], $struct->getInUsersId());
    }
}
