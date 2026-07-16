<?php

namespace Matecat\Core\Model\ConnectedServices;

use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\ConnectedServiceStruct;
use Model\ConnectedServices\Oauth\OauthTokenEncryption;
use PHPUnit\Framework\Attributes\Test;

class ConnectedServiceStructTest extends AbstractTest
{
    private static string $keyFilePath = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$keyFilePath = sys_get_temp_dir() . '/oauth_test_' . uniqid() . '.key';
        $instance = new OauthTokenEncryption(self::$keyFilePath);
        $ref = new \ReflectionProperty(OauthTokenEncryption::class, 'instance');
        $ref->setValue(null, $instance);
    }

    public static function tearDownAfterClass(): void
    {
        $ref = new \ReflectionProperty(OauthTokenEncryption::class, 'instance');
        $ref->setValue(null, null);
        if (file_exists(self::$keyFilePath)) {
            unlink(self::$keyFilePath);
        }
        parent::tearDownAfterClass();
    }

    #[Test]
    public function getDecryptedOauthAccessTokenReturnsNullWhenTokenIsNull(): void
    {
        $struct = new ConnectedServiceStruct();
        $struct->oauth_access_token = null;

        $this->assertNull($struct->getDecryptedOauthAccessToken());
    }

    #[Test]
    public function getDecodedOauthAccessTokenReturnsNullWhenTokenIsNull(): void
    {
        $struct = new ConnectedServiceStruct();
        $struct->oauth_access_token = null;

        $this->assertNull($struct->getDecodedOauthAccessToken());
    }

    #[Test]
    public function getDecodedOauthAccessTokenReturnsNullWhenFieldRequestedButTokenIsNull(): void
    {
        $struct = new ConnectedServiceStruct();
        $struct->oauth_access_token = null;

        $this->assertNull($struct->getDecodedOauthAccessToken('some_field'));
    }

    #[Test]
    public function structHasExpectedPublicProperties(): void
    {
        $struct = new ConnectedServiceStruct([
            'id'         => 1,
            'uid'        => 100,
            'service'    => 'gdrive',
            'email'      => 'user@example.com',
            'name'       => 'Test User',
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $this->assertSame(1, $struct->id);
        $this->assertSame(100, $struct->uid);
        $this->assertSame('gdrive', $struct->service);
        $this->assertSame('user@example.com', $struct->email);
        $this->assertSame('Test User', $struct->name);
        $this->assertNull($struct->disabled_at);
        $this->assertSame(1, $struct->is_default);
    }

    #[Test]
    public function setEncryptedAccessTokenEncryptsValue(): void
    {
        $struct = new ConnectedServiceStruct();
        $struct->setEncryptedAccessToken('plain-token-value');

        $this->assertNotNull($struct->oauth_access_token);
        $this->assertNotSame('plain-token-value', $struct->oauth_access_token);
    }

    #[Test]
    public function getDecryptedOauthAccessTokenRoundTrip(): void
    {
        $struct = new ConnectedServiceStruct();
        $struct->setEncryptedAccessToken('my-secret-token');

        $decrypted = $struct->getDecryptedOauthAccessToken();

        $this->assertSame('my-secret-token', $decrypted);
    }

    #[Test]
    public function getDecodedOauthAccessTokenReturnsDecodedJson(): void
    {
        $tokenData = ['access_token' => 'ya29.test', 'expires_in' => 3600, 'scope' => 'email'];
        $struct = new ConnectedServiceStruct();
        $struct->setEncryptedAccessToken(json_encode($tokenData));

        $decoded = $struct->getDecodedOauthAccessToken();

        $this->assertIsArray($decoded);
        $this->assertSame('ya29.test', $decoded['access_token']);
        $this->assertSame(3600, $decoded['expires_in']);
    }

    #[Test]
    public function getDecodedOauthAccessTokenWithFieldReturnsFieldValue(): void
    {
        $tokenData = ['access_token' => 'ya29.test', 'nested' => ['key' => 'val']];
        $struct = new ConnectedServiceStruct();
        $struct->setEncryptedAccessToken(json_encode($tokenData));

        $result = $struct->getDecodedOauthAccessToken('nested');

        $this->assertIsArray($result);
        $this->assertSame('val', $result['key']);
    }

    #[Test]
    public function getDecodedOauthAccessTokenWithMissingFieldThrows(): void
    {
        $tokenData = ['access_token' => 'ya29.test'];
        $struct = new ConnectedServiceStruct();
        $struct->setEncryptedAccessToken(json_encode($tokenData));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('key not found on token');

        $struct->getDecodedOauthAccessToken('nonexistent_field');
    }
}
