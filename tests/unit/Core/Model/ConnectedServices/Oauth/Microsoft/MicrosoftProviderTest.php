<?php

namespace Matecat\Core\Model\ConnectedServices\Oauth\Microsoft;

use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\Microsoft\MicrosoftProvider;
use PHPUnit\Framework\Attributes\Test;
use Unt\OAuth2\Client\Provider\MicrosoftProvider as MicProvider;
use Utils\Registry\AppConfig;

class MicrosoftProviderTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$MICROSOFT_OAUTH_CLIENT_ID = 'test-ms-id';
        AppConfig::$MICROSOFT_OAUTH_CLIENT_SECRET = 'test-ms-secret';
        AppConfig::$MICROSOFT_OAUTH_REDIRECT_URL = 'http://localhost/ms/callback';
    }

    #[Test]
    public function providerName(): void
    {
        $this->assertSame('microsoft', MicrosoftProvider::PROVIDER_NAME);
    }

    #[Test]
    public function getClientReturnsMicProviderInstance(): void
    {
        $provider = new MicrosoftProvider();
        $client = $provider->getClient();

        $this->assertInstanceOf(MicProvider::class, $client);
    }

    #[Test]
    public function getClientUsesCustomRedirectUrl(): void
    {
        $provider = new MicrosoftProvider();
        $client = $provider->getClient('http://custom/callback');

        $this->assertInstanceOf(MicProvider::class, $client);
    }

    #[Test]
    public function getClientThrowsWhenClientIdNotConfigured(): void
    {
        AppConfig::$MICROSOFT_OAUTH_CLIENT_ID = null;

        $provider = new MicrosoftProvider();

        $this->expectException(\InvalidArgumentException::class);
        $provider->getClient();
    }

    #[Test]
    public function getAuthorizationUrlReturnsMicrosoftUrl(): void
    {
        $provider = new MicrosoftProvider('http://localhost/ms/callback');
        $url = $provider->getAuthorizationUrl('csrf-token');

        $this->assertStringContainsString('microsoft', $url);
        $this->assertStringContainsString('csrf-token', $url);
    }
}
