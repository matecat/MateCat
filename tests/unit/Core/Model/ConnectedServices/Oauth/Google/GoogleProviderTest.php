<?php

namespace Matecat\Core\Model\ConnectedServices\Oauth\Google;

use Google_Client;
use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\Google\AccessToken;
use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class TestableGoogleProvider extends GoogleProvider
{
    private ?Google_Client $mockClient = null;

    public function setMockClient(Google_Client $client): void
    {
        $this->mockClient = $client;
    }

    public function getClient(?string $redirectUrl = null): Google_Client
    {
        if ($this->mockClient !== null) {
            return $this->mockClient;
        }
        return parent::getClient($redirectUrl);
    }
}

class GoogleProviderTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$GOOGLE_OAUTH_CLIENT_ID = 'test-client-id';
        AppConfig::$GOOGLE_OAUTH_CLIENT_SECRET = 'test-client-secret';
        AppConfig::$GOOGLE_OAUTH_CLIENT_APP_NAME = 'test-app';
        AppConfig::$GOOGLE_OAUTH_REDIRECT_URL = 'http://localhost/callback';
        AppConfig::$LOG_REPOSITORY = sys_get_temp_dir();
    }

    #[Test]
    public function providerName(): void
    {
        $this->assertSame('google', GoogleProvider::PROVIDER_NAME);
    }

    #[Test]
    public function getClientReturnsGoogleClient(): void
    {
        $provider = new GoogleProvider();
        $client = $provider->getClient();

        $this->assertInstanceOf(Google_Client::class, $client);
    }

    #[Test]
    public function getClientUsesConfigValues(): void
    {
        $provider = new GoogleProvider();
        $client = $provider->getClient();

        $this->assertSame('test-app', $client->getConfig('application_name'));
    }

    #[Test]
    public function getClientUsesCustomRedirectUrl(): void
    {
        $provider = new GoogleProvider();
        $client = $provider->getClient('http://custom/callback');

        $this->assertNotNull($client);
    }

    #[Test]
    public function getAuthorizationUrlReturnsGoogleUrl(): void
    {
        $provider = new GoogleProvider('http://localhost/callback');
        $url = $provider->getAuthorizationUrl('csrf-token-123');

        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('csrf-token-123', $url);
    }

    #[Test]
    public function getClientThrowsWhenAppNameNotConfigured(): void
    {
        AppConfig::$GOOGLE_OAUTH_CLIENT_APP_NAME = null;

        $provider = new GoogleProvider();

        $this->expectException(\RuntimeException::class);
        $provider->getClient();
    }

    #[Test]
    public function getAccessTokenFromAuthCodeReturnsAccessToken(): void
    {
        $mockClient = $this->createStub(Google_Client::class);
        $mockClient->method('fetchAccessTokenWithAuthCode')->willReturn([
            'access_token' => 'ya29.test',
            'expires_in' => 3600,
            'refresh_token' => 'refresh123',
            'scope' => 'email',
            'token_type' => 'Bearer',
            'id_token' => 'id-token',
            'created' => time(),
        ]);

        $provider = new TestableGoogleProvider();
        $provider->setMockClient($mockClient);

        $token = $provider->getAccessTokenFromAuthCode('auth-code-123');

        $this->assertInstanceOf(AccessToken::class, $token);
        $this->assertSame('ya29.test', $token->getToken());
    }
}
