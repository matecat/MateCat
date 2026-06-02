<?php

namespace unit\Utils\Registry;

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

class AppConfigTest extends AbstractTest
{
    private ?string $origGoogleClientId;
    private ?string $origGoogleBrowserApiKey;
    private string $origRoot;
    private string $origEnv;
    private string $origBuildNumber;
    private string $origCliHttpHost;
    private string $origCookieDomain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->origGoogleClientId = AppConfig::$GOOGLE_OAUTH_CLIENT_ID;
        $this->origGoogleBrowserApiKey = AppConfig::$GOOGLE_OAUTH_BROWSER_API_KEY;
        $this->origRoot = AppConfig::$ROOT;
        $this->origEnv = AppConfig::$ENV ?? 'test';
        $this->origBuildNumber = AppConfig::$BUILD_NUMBER;
        $this->origCliHttpHost = AppConfig::$CLI_HTTP_HOST;
        $this->origCookieDomain = AppConfig::$COOKIE_DOMAIN;
    }

    protected function tearDown(): void
    {
        AppConfig::$GOOGLE_OAUTH_CLIENT_ID = $this->origGoogleClientId;
        AppConfig::$GOOGLE_OAUTH_BROWSER_API_KEY = $this->origGoogleBrowserApiKey;

        AppConfig::resetSingleton();
        AppConfig::init(
            $this->origRoot,
            $this->origEnv,
            $this->origBuildNumber,
            ['CLI_HTTP_HOST' => $this->origCliHttpHost, 'COOKIE_DOMAIN' => $this->origCookieDomain],
            [],
        );
        parent::tearDown();
    }

    #[Test]
    public function areMandatoryKeysPresentReturnsTrueWhenAllSet(): void
    {
        $allSet = true;
        foreach (AppConfig::$MANDATORY_KEYS as $key) {
            if (!property_exists(AppConfig::class, $key) || AppConfig::$$key === null) {
                $allSet = false;
                break;
            }
        }

        $this->assertSame($allSet, AppConfig::areMandatoryKeysPresent());
    }

    #[Test]
    public function areMandatoryKeysPresentReturnsFalseWhenKeyIsNull(): void
    {
        $firstKey = AppConfig::$MANDATORY_KEYS[0];
        $original = AppConfig::$$firstKey;

        AppConfig::$$firstKey = null;
        $this->assertFalse(AppConfig::areMandatoryKeysPresent());

        AppConfig::$$firstKey = $original;
    }

    #[Test]
    public function isGDriveConfiguredReturnsFalseWhenClientIdIsNull(): void
    {
        AppConfig::$GOOGLE_OAUTH_CLIENT_ID = null;
        AppConfig::$GOOGLE_OAUTH_BROWSER_API_KEY = 'some-key';

        $this->assertFalse(AppConfig::isGDriveConfigured());
    }

    #[Test]
    public function isGDriveConfiguredReturnsFalseWhenBrowserApiKeyIsNull(): void
    {
        AppConfig::$GOOGLE_OAUTH_CLIENT_ID = 'some-id';
        AppConfig::$GOOGLE_OAUTH_BROWSER_API_KEY = null;

        $this->assertFalse(AppConfig::isGDriveConfigured());
    }

    #[Test]
    public function isGDriveConfiguredReturnsFalseWhenBothEmpty(): void
    {
        AppConfig::$GOOGLE_OAUTH_CLIENT_ID = '';
        AppConfig::$GOOGLE_OAUTH_BROWSER_API_KEY = '';

        $this->assertFalse(AppConfig::isGDriveConfigured());
    }

    #[Test]
    public function isGDriveConfiguredReturnsTrueWhenBothSet(): void
    {
        AppConfig::$GOOGLE_OAUTH_CLIENT_ID = 'client-id';
        AppConfig::$GOOGLE_OAUTH_BROWSER_API_KEY = 'browser-key';

        $this->assertTrue(AppConfig::isGDriveConfigured());
    }

    #[Test]
    public function resetSingletonAllowsReinitialization(): void
    {
        AppConfig::resetSingleton();
        AppConfig::init(
            $this->origRoot,
            'test-reset',
            $this->origBuildNumber,
            ['CLI_HTTP_HOST' => 'test.local', 'COOKIE_DOMAIN' => '.test.local'],
            [],
        );

        $this->assertSame('test-reset', AppConfig::$ENV);
    }

    #[Test]
    public function initWithConfigurationOverridesDefaults(): void
    {
        AppConfig::resetSingleton();
        AppConfig::init(
            $this->origRoot,
            $this->origEnv,
            $this->origBuildNumber,
            [
                'CLI_HTTP_HOST' => $this->origCliHttpHost,
                'COOKIE_DOMAIN' => $this->origCookieDomain,
                'DEBUG'         => true,
            ],
            [],
        );

        $this->assertTrue(AppConfig::$DEBUG);
    }

    #[Test]
    public function initIgnoresUnknownConfigurationKeys(): void
    {
        AppConfig::resetSingleton();
        AppConfig::init(
            $this->origRoot,
            $this->origEnv,
            $this->origBuildNumber,
            [
                'CLI_HTTP_HOST'        => $this->origCliHttpHost,
                'COOKIE_DOMAIN'        => $this->origCookieDomain,
                'TOTALLY_UNKNOWN_KEY'  => 'should-be-ignored',
            ],
            [],
        );

        $this->assertFalse(property_exists(AppConfig::class, 'TOTALLY_UNKNOWN_KEY'));
    }

    #[Test]
    public function initSetsStoragePaths(): void
    {
        AppConfig::resetSingleton();
        AppConfig::init(
            $this->origRoot,
            $this->origEnv,
            $this->origBuildNumber,
            [
                'CLI_HTTP_HOST' => $this->origCliHttpHost,
                'COOKIE_DOMAIN' => $this->origCookieDomain,
                'STORAGE_DIR'   => '/tmp/matecat_test_storage',
            ],
            [],
        );

        $this->assertSame('/tmp/matecat_test_storage', AppConfig::$STORAGE_DIR);
        $this->assertStringStartsWith('/tmp/matecat_test_storage/', AppConfig::$UPLOAD_REPOSITORY);
        $this->assertStringStartsWith('/tmp/matecat_test_storage/', AppConfig::$LOG_REPOSITORY);
        $this->assertStringStartsWith('/tmp/matecat_test_storage/', AppConfig::$FILES_REPOSITORY);
    }

    #[Test]
    public function mandatoryKeysListIsNotEmpty(): void
    {
        $this->assertNotEmpty(AppConfig::$MANDATORY_KEYS);
        $this->assertContains('DB_SERVER', AppConfig::$MANDATORY_KEYS);
        $this->assertContains('REDIS_SERVERS', AppConfig::$MANDATORY_KEYS);
    }

    #[Test]
    public function initLoadsOAuthConfigFromIniFile(): void
    {
        $tmpRoot = sys_get_temp_dir() . '/matecat_appconfig_test_' . uniqid();
        mkdir($tmpRoot . '/inc', 0755, true);
        mkdir($tmpRoot . '/lib/View/templates', 0755, true);

        $ini = <<<'INI'
[GOOGLE_OAUTH_CONFIG]
GOOGLE_OAUTH_CLIENT_ID = "test-google-id"
GOOGLE_OAUTH_BROWSER_API_KEY = "test-browser-key"

[GITHUB_OAUTH_CONFIG]
GITHUB_OAUTH_CLIENT_ID = "test-github-id"
INI;
        file_put_contents($tmpRoot . '/inc/oauth_config.ini', $ini);
        file_put_contents($tmpRoot . '/inc/login_secret.dat', 'test-secret-value');

        AppConfig::resetSingleton();
        AppConfig::init($tmpRoot, 'test', '1.0.0', [
            'CLI_HTTP_HOST' => 'test.local',
            'COOKIE_DOMAIN' => '.test.local',
        ], []);

        $this->assertSame('test-google-id', AppConfig::$GOOGLE_OAUTH_CLIENT_ID);
        $this->assertSame('test-browser-key', AppConfig::$GOOGLE_OAUTH_BROWSER_API_KEY);
        $this->assertSame('test-github-id', AppConfig::$GITHUB_OAUTH_CLIENT_ID);
        $this->assertSame('test-secret-value', AppConfig::$AUTHSECRET);
        $this->assertTrue(AppConfig::isGDriveConfigured());

        @unlink($tmpRoot . '/inc/oauth_config.ini');
        @unlink($tmpRoot . '/inc/login_secret.dat');
        @rmdir($tmpRoot . '/lib/View/templates');
        @rmdir($tmpRoot . '/lib/View');
        @rmdir($tmpRoot . '/lib');
        @rmdir($tmpRoot . '/inc');
        @rmdir($tmpRoot);
    }

    #[Test]
    public function initGeneratesAuthSecretWhenFileDoesNotExist(): void
    {
        $tmpRoot = sys_get_temp_dir() . '/matecat_appconfig_test_' . uniqid();
        mkdir($tmpRoot . '/inc', 0755, true);
        mkdir($tmpRoot . '/lib/View/templates', 0755, true);

        AppConfig::resetSingleton();
        AppConfig::init($tmpRoot, 'test', '1.0.0', [
            'CLI_HTTP_HOST' => 'test.local',
            'COOKIE_DOMAIN' => '.test.local',
        ], []);

        $this->assertNotEmpty(AppConfig::$AUTHSECRET);
        $this->assertSame(512, strlen(AppConfig::$AUTHSECRET));
        $this->assertFileExists($tmpRoot . '/inc/login_secret.dat');

        @unlink($tmpRoot . '/inc/login_secret.dat');
        @rmdir($tmpRoot . '/lib/View/templates');
        @rmdir($tmpRoot . '/lib/View');
        @rmdir($tmpRoot . '/lib');
        @rmdir($tmpRoot . '/inc');
        @rmdir($tmpRoot);
    }

    #[Test]
    public function initWithMonologHandlersPopulatesConfig(): void
    {
        AppConfig::resetSingleton();
        AppConfig::init(
            $this->origRoot,
            $this->origEnv,
            $this->origBuildNumber,
            [
                'CLI_HTTP_HOST'    => $this->origCliHttpHost,
                'COOKIE_DOMAIN'    => $this->origCookieDomain,
                'MONOLOG_HANDLERS' => ['syslog'],
                'syslog'           => ['level' => 'debug'],
            ],
            [],
        );

        $this->assertArrayHasKey('syslog', AppConfig::$MONOLOG_HANDLERS);
        $this->assertSame(['level' => 'debug'], AppConfig::$MONOLOG_HANDLERS['syslog']);
    }
}
