<?php

namespace Matecat\Core\Model\ConnectedServices\Oauth;

use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\Facebook\FacebookProvider;
use Model\ConnectedServices\Oauth\Github\GithubProvider;
use Model\ConnectedServices\Oauth\Google\GoogleProvider;
use Model\ConnectedServices\Oauth\LinkedIn\LinkedInProvider;
use Model\ConnectedServices\Oauth\Microsoft\MicrosoftProvider;
use Model\ConnectedServices\Oauth\OauthClient;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class OauthClientTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $ref = new \ReflectionProperty(OauthClient::class, 'instance');
        $ref->setValue(null, null);

        AppConfig::$GOOGLE_OAUTH_CLIENT_ID = 'test-id';
        AppConfig::$GOOGLE_OAUTH_CLIENT_SECRET = 'test-secret';
        AppConfig::$GOOGLE_OAUTH_CLIENT_APP_NAME = 'test-app';
        AppConfig::$GOOGLE_OAUTH_REDIRECT_URL = 'http://localhost/callback';
        AppConfig::$LOG_REPOSITORY = sys_get_temp_dir();
        AppConfig::$GITHUB_OAUTH_CLIENT_ID = 'test-id';
        AppConfig::$GITHUB_OAUTH_CLIENT_SECRET = 'test-secret';
        AppConfig::$GITHUB_OAUTH_REDIRECT_URL = 'http://localhost/callback';
        AppConfig::$FACEBOOK_OAUTH_CLIENT_ID = 'test-id';
        AppConfig::$FACEBOOK_OAUTH_CLIENT_SECRET = 'test-secret';
        AppConfig::$FACEBOOK_OAUTH_REDIRECT_URL = 'http://localhost/callback';
        AppConfig::$LINKEDIN_OAUTH_CLIENT_ID = 'test-id';
        AppConfig::$LINKEDIN_OAUTH_CLIENT_SECRET = 'test-secret';
        AppConfig::$LINKEDIN_OAUTH_REDIRECT_URL = 'http://localhost/callback';
        AppConfig::$MICROSOFT_OAUTH_CLIENT_ID = 'test-id';
        AppConfig::$MICROSOFT_OAUTH_CLIENT_SECRET = 'test-secret';
        AppConfig::$MICROSOFT_OAUTH_REDIRECT_URL = 'http://localhost/callback';
        AppConfig::$XSRF_TOKEN = 'xsrf-key';
    }

    protected function tearDown(): void
    {
        $ref = new \ReflectionProperty(OauthClient::class, 'instance');
        $ref->setValue(null, null);
        parent::tearDown();
    }

    #[Test]
    public function getInstanceReturnsOauthClient(): void
    {
        $client = OauthClient::getInstance('google');

        $this->assertInstanceOf(OauthClient::class, $client);
    }

    #[Test]
    public function getInstanceWithGoogleReturnsGoogleProvider(): void
    {
        $client = OauthClient::getInstance('google');

        $this->assertInstanceOf(GoogleProvider::class, $client->getProvider());
    }

    #[Test]
    public function getInstanceWithGithubReturnsGithubProvider(): void
    {
        $client = OauthClient::getInstance('github');

        $this->assertInstanceOf(GithubProvider::class, $client->getProvider());
    }

    #[Test]
    public function getInstanceWithFacebookReturnsFacebookProvider(): void
    {
        $client = OauthClient::getInstance('facebook');

        $this->assertInstanceOf(FacebookProvider::class, $client->getProvider());
    }

    #[Test]
    public function getInstanceWithLinkedinReturnsLinkedInProvider(): void
    {
        $client = OauthClient::getInstance('linkedin');

        $this->assertInstanceOf(LinkedInProvider::class, $client->getProvider());
    }

    #[Test]
    public function getInstanceWithMicrosoftReturnsMicrosoftProvider(): void
    {
        $client = OauthClient::getInstance('microsoft');

        $this->assertInstanceOf(MicrosoftProvider::class, $client->getProvider());
    }

    #[Test]
    public function getInstanceCachesSameProvider(): void
    {
        $first = OauthClient::getInstance('google');
        $second = OauthClient::getInstance('google');

        $this->assertSame($first, $second);
    }

    #[Test]
    public function getInstanceCreatesNewInstanceWhenProviderChanges(): void
    {
        $google = OauthClient::getInstance('google');
        $github = OauthClient::getInstance('github');

        $this->assertNotSame($google, $github);
        $this->assertInstanceOf(GithubProvider::class, $github->getProvider());
    }

    #[Test]
    public function getInstanceWithNullDefaultsToGoogle(): void
    {
        $client = OauthClient::getInstance(null);

        $this->assertInstanceOf(GoogleProvider::class, $client->getProvider());
    }

    #[Test]
    public function getAuthorizationUrlGeneratesUrl(): void
    {
        $client = OauthClient::getInstance('github');
        $session = [];

        $url = $client->getAuthorizationUrl($session);

        $this->assertNotEmpty($url);
        $this->assertStringContainsString('github.com', $url);
    }

    #[Test]
    public function getAuthorizationUrlStoresXsrfTokenInSession(): void
    {
        $client = OauthClient::getInstance('github');
        $session = [];

        $client->getAuthorizationUrl($session);

        $this->assertNotEmpty($session);
        $keys = array_keys($session);
        $this->assertStringContainsString('xsrf-key', $keys[0]);
    }

    #[Test]
    public function getAuthorizationUrlReusesExistingXsrfToken(): void
    {
        $client = OauthClient::getInstance('github');
        $session = [];

        $client->getAuthorizationUrl($session);
        $tokenAfterFirst = array_values($session)[0];

        $client->getAuthorizationUrl($session);
        $tokenAfterSecond = array_values($session)[0];

        $this->assertSame($tokenAfterFirst, $tokenAfterSecond);
    }
}
