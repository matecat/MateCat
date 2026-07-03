<?php

namespace Matecat\Core\Utils\Url;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;
use Utils\Url\CanonicalRoutes;

class CanonicalRoutesExtendedTest extends AbstractTest
{
    private string $originalHost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalHost = AppConfig::$HTTPHOST;
        AppConfig::$HTTPHOST = 'https://example.com';
    }

    protected function tearDown(): void
    {
        AppConfig::$HTTPHOST = $this->originalHost;
        parent::tearDown();
    }

    #[Test]
    public function httpHostReturnsConfiguredHost(): void
    {
        $this->assertSame('https://example.com', CanonicalRoutes::httpHost());
    }

    #[Test]
    public function httpHostUsesOverride(): void
    {
        $this->assertSame('https://custom.com', CanonicalRoutes::httpHost(['http_host' => 'https://custom.com']));
    }

    #[Test]
    public function httpHostThrowsWhenEmpty(): void
    {
        AppConfig::$HTTPHOST = '';
        $this->expectException(\Exception::class);
        CanonicalRoutes::httpHost();
    }

    #[Test]
    public function passwordResetReturnsUrl(): void
    {
        $url = CanonicalRoutes::passwordReset('token123');
        $this->assertSame('https://example.com/api/app/user/password_reset/token123', $url);
    }

    #[Test]
    public function signupConfirmationReturnsUrl(): void
    {
        $url = CanonicalRoutes::signupConfirmation('confirm456');
        $this->assertSame('https://example.com/api/app/user/confirm/confirm456', $url);
    }

    #[Test]
    public function downloadXliffReturnsUrl(): void
    {
        $url = CanonicalRoutes::downloadXliff(1, 'pass');
        $this->assertSame('https://example.com/api/v2/xliff/1/pass/1.zip', $url);
    }

    #[Test]
    public function downloadOriginalReturnsUrl(): void
    {
        $url = CanonicalRoutes::downloadOriginal(1, 'pass');
        $this->assertStringContainsString('/api/v2/original/1/pass', $url);
        $this->assertStringContainsString('download_type=all', $url);
    }

    #[Test]
    public function downloadOriginalWithFilename(): void
    {
        $url = CanonicalRoutes::downloadOriginal(1, 'pass', 'test.xliff');
        $this->assertStringContainsString('filename=test.xliff', $url);
    }

    #[Test]
    public function downloadTranslationReturnsUrl(): void
    {
        $url = CanonicalRoutes::downloadTranslation(1, 'pass');
        $this->assertStringContainsString('/api/v2/translation/1/pass', $url);
    }

    #[Test]
    public function translateReturnsUrl(): void
    {
        $url = CanonicalRoutes::translate('my-project', 1, 'pass', 'en-US', 'it-IT');
        $this->assertSame('https://example.com/translate/my-project/en-US-it-IT/1-pass', $url);
    }

    #[Test]
    public function reviseReturnsUrl(): void
    {
        $url = CanonicalRoutes::revise('my-project', 1, 'pass', 'en-US', 'it-IT');
        $this->assertStringContainsString('/revise/my-project/', $url);
    }

    #[Test]
    public function reviseWithRevisionNumber(): void
    {
        $url = CanonicalRoutes::revise('my-project', 1, 'pass', 'en-US', 'it-IT', ['revision_number' => 2]);
        $this->assertStringContainsString('/revise2/', $url);
    }

    #[Test]
    public function reviseWithSegmentId(): void
    {
        $url = CanonicalRoutes::revise('my-project', 1, 'pass', 'en-US', 'it-IT', ['id_segment' => 42]);
        $this->assertStringEndsWith('#42', $url);
    }

    #[Test]
    public function manageReturnsUrl(): void
    {
        $url = CanonicalRoutes::manage();
        $this->assertSame('https://example.com/manage', $url);
    }

    #[Test]
    public function appRootReturnsUrl(): void
    {
        $url = CanonicalRoutes::appRoot();
        $this->assertStringStartsWith('https://example.com', $url);
    }

    #[Test]
    public function pluginsBaseReturnsUrl(): void
    {
        $url = CanonicalRoutes::pluginsBase();
        $this->assertSame('https://example.com/plugins', $url);
    }

    #[Test]
    public function analyzeReturnsUrl(): void
    {
        $url = CanonicalRoutes::analyze([
            'project_name' => 'My Project',
            'id_project' => 42,
            'password' => 'abc123',
        ]);

        $this->assertStringContainsString('/analyze/', $url);
        $this->assertStringContainsString('42-abc123', $url);
    }

    #[Test]
    public function inviteToTeamConfirmReturnsUrl(): void
    {
        $originalSecret = AppConfig::$AUTHSECRET;
        $originalBuild = AppConfig::$BUILD_NUMBER;
        AppConfig::$AUTHSECRET = 'test-secret-key';
        AppConfig::$BUILD_NUMBER = '1';

        try {
            $url = CanonicalRoutes::inviteToTeamConfirm([
                'invited_by_uid' => 1,
                'email' => 'test@test.com',
                'team_id' => 5,
            ]);

            $this->assertStringContainsString('/api/app/teams/members/invite/', $url);
            $this->assertStringStartsWith('https://example.com', $url);
        } finally {
            AppConfig::$AUTHSECRET = $originalSecret;
            AppConfig::$BUILD_NUMBER = $originalBuild;
        }
    }

    #[Test]
    public function appRootWithQuery(): void
    {
        $url = CanonicalRoutes::appRoot(['query' => ['foo' => 'bar', 'baz' => '1']]);

        $this->assertStringContainsString('foo=bar', $url);
        $this->assertStringContainsString('baz=1', $url);
    }
}
