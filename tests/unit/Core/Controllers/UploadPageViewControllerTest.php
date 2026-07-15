<?php

namespace Matecat\Core\Controllers;

use Controller\Abstracts\Authentication\CookieManager;
use Controller\API\GDrive\GDriveController;
use Controller\Exceptions\RenderTerminatedException;
use Controller\Views\UploadPageController;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use Utils\Constants\Constants;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;

class TestableUploadPageController extends UploadPageController
{
    public function __construct()
    {
    }

    public string $lastTemplate = '';
    /** @var array<string, mixed> */
    public array $lastViewData = [];
    public int $lastViewCode = 200;
    /** @var array<string, mixed> */
    public array $addedParams = [];
    public int $addParamsCallCount = 0;

    public function setView(string $template_name, array $params = [], int $code = 200): void
    {
        $this->lastTemplate = $template_name;
        $this->lastViewData = $params;
        $this->lastViewCode = $code;
    }

    public function addParamsToView(array $params): void
    {
        $this->addParamsCallCount++;
        $this->addedParams = array_merge($this->addedParams, $params);
    }

    public function render(?int $code = null): never
    {
        throw new RenderTerminatedException();
    }

    /** @var list<array{name:string,value:string,options:array<string,mixed>}> */
    public array $cookieWrites = [];

    protected function cookieManager(): CookieManager
    {
        $sink = &$this->cookieWrites;

        return new class($sink) extends CookieManager {
            /** @param list<array{name:string,value:string,options:array<string,mixed>}> $sink */
            public function __construct(private array &$sink)
            {
            }

            protected function writeCookie(string $name, string $value, array $options): bool
            {
                $this->sink[] = ['name' => $name, 'value' => $value, 'options' => $options];

                return true;
            }
        };
    }
}

class UploadPageViewControllerTest extends AbstractTest
{
    private const string INTENTO_CACHE_KEY = 'IntentoProviders';

    private ReflectionClass $reflector;
    private TestableUploadPageController $controller;

    /** @throws ReflectionException */
    protected function setUp(): void
    {
        parent::setUp();

        $this->reflector = new ReflectionClass(TestableUploadPageController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        unset(
            $_COOKIE[GDriveController::GDRIVE_LIST_COOKIE_NAME],
            $_COOKIE['upload_token'],
            $_COOKIE[Constants::COOKIE_SOURCE_LANG],
            $_COOKIE[Constants::COOKIE_TARGET_LANG],
        );
    }

    protected function tearDown(): void
    {
        unset(
            $_COOKIE[GDriveController::GDRIVE_LIST_COOKIE_NAME],
            $_COOKIE['upload_token'],
            $_COOKIE[Constants::COOKIE_SOURCE_LANG],
            $_COOKIE[Constants::COOKIE_TARGET_LANG],
        );

        parent::tearDown();
    }

    /**
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        return $this->reflector->getMethod($method)->invokeArgs($this->controller, $args);
    }

    /**
     * Seeds the Intento provider-list Redis cache so renderView() never performs
     * a real network call to the Intento API; returns a restore callback.
     *
     * @return callable(): void
     */
    private function seedIntentoProvidersCache(): callable
    {
        $conn = (new RedisHandler())->getConnection();
        $previous = $conn->get(self::INTENTO_CACHE_KEY);
        $conn->set(self::INTENTO_CACHE_KEY, json_encode(['test' => ['id' => 'test', 'name' => 'Test']]));

        return static function () use ($conn, $previous): void {
            if ($previous !== null) {
                $conn->set(UploadPageViewControllerTest::INTENTO_CACHE_KEY, $previous);
            } else {
                $conn->del(UploadPageViewControllerTest::INTENTO_CACHE_KEY);
            }
        };
    }

    /** @throws ReflectionException */
    #[Test]
    public function checkDriveFilesOrGetGuidReturnsNullWhenGdriveCookieIsPresent(): void
    {
        $_COOKIE[GDriveController::GDRIVE_LIST_COOKIE_NAME] = '1';

        $result = $this->invokePrivate('checkDriveFilesOrGetGuid');

        $this->assertNull($result);
    }

    /** @throws ReflectionException */
    #[Test]
    public function checkDriveFilesOrGetGuidGeneratesGuidAndDeletesStaleUploadDirWhenTokenValid(): void
    {
        $_COOKIE['upload_token'] = 'deadbeef-dead-beef-dead-beefdeadbeef';

        $result = $this->invokePrivate('checkDriveFilesOrGetGuid');

        $this->assertIsString($result);
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
            $result
        );
    }

    /** @throws ReflectionException */
    #[Test]
    public function checkDriveFilesOrGetGuidGeneratesGuidWhenNoUploadTokenCookie(): void
    {
        $result = $this->invokePrivate('checkDriveFilesOrGetGuid');

        $this->assertIsString($result);
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
            $result
        );
    }

    /** @throws ReflectionException */
    #[Test]
    public function initUploadDirCreatesDirectoryWhenMissing(): void
    {
        $guid = 'upload-page-controller-test-dir-missing';
        $target = AppConfig::$UPLOAD_REPOSITORY . '/' . $guid . '/';
        if (is_dir($target)) {
            rmdir($target);
        }

        try {
            $this->invokePrivate('initUploadDir', [$guid]);
            $this->assertDirectoryExists($target);
        } finally {
            if (is_dir($target)) {
                rmdir($target);
            }
        }
    }

    /** @throws ReflectionException */
    #[Test]
    public function initUploadDirDoesNothingWhenDirectoryAlreadyExists(): void
    {
        $guid = 'upload-page-controller-test-dir-existing';
        $target = AppConfig::$UPLOAD_REPOSITORY . '/' . $guid . '/';
        mkdir($target, 0775, true);

        try {
            $this->invokePrivate('initUploadDir', [$guid]);
            $this->assertDirectoryExists($target);
        } finally {
            rmdir($target);
        }
    }

    /** @throws ReflectionException */
    #[Test]
    public function countSupportedFileTypesSumsAllSupportedExtensions(): void
    {
        $expected = 0;
        foreach (AppConfig::$SUPPORTED_FILE_TYPES as $value) {
            $expected += count($value);
        }

        $result = $this->invokePrivate('countSupportedFileTypes');

        $this->assertSame($expected, $result);
    }

    /** @throws ReflectionException */
    #[Test]
    public function renderViewSkipsUploadDirAndLxqParamsWhenNoGuidAndNoLicense(): void
    {
        $_COOKIE[GDriveController::GDRIVE_LIST_COOKIE_NAME] = '1';

        $previousLicense = AppConfig::$LXQ_LICENSE;
        AppConfig::$LXQ_LICENSE = null;

        $restoreCache = $this->seedIntentoProvidersCache();

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('upload.html', $this->controller->lastTemplate);
            $this->assertArrayHasKey('formats_number', $this->controller->lastViewData);
            $this->assertArrayHasKey('subjects', $this->controller->lastViewData);
            $this->assertSame(200, $this->controller->lastViewCode);
            $this->assertSame(0, $this->controller->addParamsCallCount);
        } finally {
            AppConfig::$LXQ_LICENSE = $previousLicense;
            $restoreCache();
        }
    }

    /** @throws ReflectionException */
    #[Test]
    public function renderViewCreatesUploadDirAndAddsLxqParamsWhenGuidAndLicensePresent(): void
    {
        $previousLicense = AppConfig::$LXQ_LICENSE;
        $previousPartnerId = AppConfig::$LXQ_PARTNERID;
        $previousServer = AppConfig::$LXQ_SERVER;

        AppConfig::$LXQ_LICENSE = 'test-license';
        AppConfig::$LXQ_PARTNERID = 'test-partner';
        AppConfig::$LXQ_SERVER = 'https://example.test';

        $restoreCache = $this->seedIntentoProvidersCache();

        $before = scandir(AppConfig::$UPLOAD_REPOSITORY);

        try {
            $this->controller->renderView();
            $this->fail('Expected RenderTerminatedException');
        } catch (RenderTerminatedException) {
            $this->assertSame('upload.html', $this->controller->lastTemplate);
            $this->assertSame(1, $this->controller->addParamsCallCount);
            $this->assertSame('test-license', $this->controller->addedParams['lxq_license']);
            $this->assertSame('test-partner', $this->controller->addedParams['lxq_partnerid']);
            $this->assertSame('https://example.test', $this->controller->addedParams['lexiqaServer']);
        } finally {
            AppConfig::$LXQ_LICENSE = $previousLicense;
            AppConfig::$LXQ_PARTNERID = $previousPartnerId;
            AppConfig::$LXQ_SERVER = $previousServer;
            $restoreCache();

            $after = scandir(AppConfig::$UPLOAD_REPOSITORY);
            foreach (array_diff($after, $before) as $newEntry) {
                $newPath = AppConfig::$UPLOAD_REPOSITORY . '/' . $newEntry;
                if (is_dir($newPath)) {
                    rmdir($newPath);
                }
            }
        }
    }

    /** @throws ReflectionException */
    #[Test]
    public function initLanguagePreferenceCookiesSeedsBothCookiesWhenAbsent(): void
    {
        $this->invokePrivate('initLanguagePreferenceCookies');

        $writes = $this->controller->cookieWrites;
        $this->assertCount(2, $writes);

        $this->assertSame(Constants::COOKIE_SOURCE_LANG, $writes[0]['name']);
        $this->assertSame(Constants::COOKIE_TARGET_LANG, $writes[1]['name']);
        $this->assertSame(Constants::EMPTY_VAL, $writes[0]['value']);
        $this->assertSame('Strict', $writes[0]['options']['samesite']);
    }

    /** @throws ReflectionException */
    #[Test]
    public function initLanguagePreferenceCookiesSkipsCookiesAlreadyPresent(): void
    {
        $_COOKIE[Constants::COOKIE_SOURCE_LANG] = 'en-US';
        $_COOKIE[Constants::COOKIE_TARGET_LANG] = 'it-IT';

        $this->invokePrivate('initLanguagePreferenceCookies');

        $this->assertCount(0, $this->controller->cookieWrites);
    }
}
