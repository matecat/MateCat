<?php

namespace Matecat\Core\Model\ConnectedServices\Oauth\LinkedIn;

use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\LinkedIn\LinkedinFinal;
use Model\ConnectedServices\Oauth\LinkedIn\LinkedInProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class LinkedInProviderTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$LINKEDIN_OAUTH_CLIENT_ID = 'test-li-id';
        AppConfig::$LINKEDIN_OAUTH_CLIENT_SECRET = 'test-li-secret';
        AppConfig::$LINKEDIN_OAUTH_REDIRECT_URL = 'http://localhost/li/callback';
    }

    #[Test]
    public function providerName(): void
    {
        $this->assertSame('linkedin', LinkedInProvider::PROVIDER_NAME);
    }

    #[Test]
    public function getClientReturnsLinkedinFinalInstance(): void
    {
        $provider = new LinkedInProvider();
        $client = $provider->getClient();

        $this->assertInstanceOf(LinkedinFinal::class, $client);
    }

    #[Test]
    public function getClientUsesCustomRedirectUrl(): void
    {
        $provider = new LinkedInProvider();
        $client = $provider->getClient('http://custom/callback');

        $this->assertInstanceOf(LinkedinFinal::class, $client);
    }

    #[Test]
    public function getAuthorizationUrlReturnsLinkedInUrl(): void
    {
        $provider = new LinkedInProvider('http://localhost/li/callback');
        $url = $provider->getAuthorizationUrl('csrf-token');

        $this->assertStringContainsString('linkedin.com', $url);
        $this->assertStringContainsString('csrf-token', $url);
    }
}
