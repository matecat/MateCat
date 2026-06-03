<?php

namespace Matecat\Core\Model\ConnectedServices\Oauth;

use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\DefuseEncryption;
use PHPUnit\Framework\Attributes\Test;

class DefuseEncryptionTest extends AbstractTest
{
    private string $keyFilePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->keyFilePath = sys_get_temp_dir() . '/defuse_test_' . uniqid() . '.key';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->keyFilePath)) {
            unlink($this->keyFilePath);
        }
        parent::tearDown();
    }

    #[Test]
    public function constructorCreatesKeyFileWhenNotExists(): void
    {
        $this->assertFileDoesNotExist($this->keyFilePath);

        new DefuseEncryption($this->keyFilePath);

        $this->assertFileExists($this->keyFilePath);
        $this->assertGreaterThan(0, filesize($this->keyFilePath));
    }

    #[Test]
    public function constructorLoadsExistingKeyFile(): void
    {
        $first = new DefuseEncryption($this->keyFilePath);
        $encrypted = $first->encrypt('hello');

        $second = new DefuseEncryption($this->keyFilePath);
        $decrypted = $second->decrypt($encrypted);

        $this->assertSame('hello', $decrypted);
    }

    #[Test]
    public function loadEncryptionKeyReturnsFalseForNonExistentFile(): void
    {
        $encryption = new DefuseEncryption($this->keyFilePath);

        unlink($this->keyFilePath);

        $this->assertFalse($encryption->loadEncryptionKey());
    }

    #[Test]
    public function loadEncryptionKeyReturnsTrueForValidFile(): void
    {
        $encryption = new DefuseEncryption($this->keyFilePath);

        $this->assertTrue($encryption->loadEncryptionKey());
    }

    #[Test]
    public function encryptDecryptRoundTrip(): void
    {
        $encryption = new DefuseEncryption($this->keyFilePath);

        $plaintext = 'sensitive data 12345';
        $encrypted = $encryption->encrypt($plaintext);

        $this->assertNotSame($plaintext, $encrypted);
        $this->assertSame($plaintext, $encryption->decrypt($encrypted));
    }

    #[Test]
    public function decryptReturnsNullForInvalidCiphertext(): void
    {
        $encryption = new DefuseEncryption($this->keyFilePath);

        $this->assertNull($encryption->decrypt('not-valid-ciphertext'));
    }

    #[Test]
    public function encryptThrowsTypeErrorWhenKeyIsNull(): void
    {
        $encryption = new DefuseEncryption($this->keyFilePath);

        $ref = new \ReflectionProperty($encryption, 'key');
        $ref->setValue($encryption, null);

        $this->expectException(\TypeError::class);
        $encryption->encrypt('test');
    }

    #[Test]
    public function decryptThrowsTypeErrorWhenKeyIsNull(): void
    {
        $encryption = new DefuseEncryption($this->keyFilePath);

        $ref = new \ReflectionProperty($encryption, 'key');
        $ref->setValue($encryption, null);

        $this->expectException(\TypeError::class);
        $encryption->decrypt('test');
    }

    #[Test]
    public function generateKeyReturnsNonEmptyString(): void
    {
        $encryption = new DefuseEncryption($this->keyFilePath);

        $key = $encryption->generateKey();

        $this->assertNotEmpty($key);
        $this->assertIsString($key);
    }

    #[Test]
    public function loadEncryptionKeyReturnsFalseForEmptyFile(): void
    {
        file_put_contents($this->keyFilePath, '');
        $encryption = new DefuseEncryption($this->keyFilePath);

        unlink($this->keyFilePath);
        file_put_contents($this->keyFilePath, '');

        $this->assertFalse($encryption->loadEncryptionKey());
    }
}
