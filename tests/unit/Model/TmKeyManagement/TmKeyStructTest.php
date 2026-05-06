<?php

namespace unit\Model\TmKeyManagement;

use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Utils\TmKeyManagement\TmKeyStruct;

class TmKeyStructTest extends TestCase
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
}
