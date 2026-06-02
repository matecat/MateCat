<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins\Features;

use Exception;
use Klein\Klein;
use LogicException;
use Model\FeaturesBase\BasicFeatureStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Plugins\Features\BaseFeature;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use ReflectionProperty;
use Utils\Registry\AppConfig;

class TestFeature extends BaseFeature
{
    public const string FEATURE_CODE = 'test_feature';
}

class InvalidFeatureCodeTestFeature extends BaseFeature
{
}

#[Group('unit')]
class BaseFeatureTest extends AbstractTest
{
    private TestFeature $feature;

    protected function setUp(): void
    {
        parent::setUp();

        if (AppConfig::$LOG_REPOSITORY === null) {
            AppConfig::$LOG_REPOSITORY = sys_get_temp_dir();
        }

        $this->feature = new TestFeature($this->createFeatureStruct());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $configPath = $this->configPath();
        if ($configPath !== '' && file_exists($configPath)) {
            @unlink($configPath);
        }

        $buildDir = $this->buildDirPath();
        if ($buildDir !== '' && is_dir($buildDir)) {
            $files = scandir($buildDir);
            if (is_array($files)) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    @unlink($buildDir . '/' . $file);
                }
            }
            @rmdir($buildDir);
        }

        $staticDir = dirname($buildDir);
        if (is_dir($staticDir)) {
            @rmdir($staticDir);
        }

        self::setStaticProperty(TestFeature::class, 'dependencies', []);
        self::setStaticProperty(TestFeature::class, 'conflictingDependencies', []);
    }

    #[Test]
    public function constructorThrowsWhenFeatureCodeIsNotDefined(): void
    {
        $feature = new BasicFeatureStruct(['feature_code' => 'any_feature']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Plugin code not defined.');

        new InvalidFeatureCodeTestFeature($feature);
    }

    #[Test]
    public function constructorSetsFeatureStructAndFlags(): void
    {
        self::assertSame($this->createFeatureStruct()->feature_code, $this->feature->getFeatureStruct()->feature_code);
        self::assertTrue($this->feature->isAutoActivableOnProject());
        self::assertFalse($this->feature->isForceableOnProject());
    }

    #[Test]
    public function getLoggerReturnsLoggerAndCachesInstance(): void
    {
        $logger1 = $this->feature->getLogger();
        $logger2 = $this->feature->getLogger();

        self::assertInstanceOf(LoggerInterface::class, $logger1);
        self::assertSame($logger1, $logger2);
    }

    #[Test]
    public function logFilePathIncludesLoggerNameAndLogExtension(): void
    {
        $path = $this->invokeProtectedMethod($this->feature, 'logFilePath');

        self::assertStringEndsWith('/test_feature_plugin.log', $path);
    }

    #[Test]
    public function classAndPluginPathsAreResolvedFromConcreteSubclass(): void
    {
        $classPath = $this->feature->getClassPath();
        $pluginBasePath = $this->feature->getPluginBasePath();
        $templatesPath = $this->feature->getTemplatesPath();

        self::assertStringEndsWith('/tests/unit/Plugins/Features/BaseFeatureTest', $classPath);
        self::assertSame(realpath(dirname($classPath, 2)), $pluginBasePath);
        self::assertSame($classPath . '/View', $templatesPath);
    }

    #[Test]
    public function dependenciesAndConflictingDependenciesAreReturnedFromStaticProperties(): void
    {
        self::setStaticProperty(TestFeature::class, 'dependencies', ['a' => ['enabled' => true]]);
        self::setStaticProperty(TestFeature::class, 'conflictingDependencies', ['b' => ['enabled' => false]]);

        self::assertSame(['a' => ['enabled' => true]], TestFeature::getDependencies());
        self::assertSame(['b' => ['enabled' => false]], TestFeature::getConflictingDependencies());
    }

    #[Test]
    public function getConfigThrowsWhenConfigFileDoesNotExist(): void
    {
        $configPath = $this->configPath();
        if ($configPath !== '' && file_exists($configPath)) {
            @unlink($configPath);
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Config file not found');

        $this->feature->getConfig();
    }

    #[Test]
    public function getConfigThrowsWhenIniCannotBeParsed(): void
    {
        $configPath = $this->configPath();
        self::assertNotSame('', $configPath);
        file_put_contents($configPath, "[broken_section\nkey=value\n");

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to parse config file');

        $this->feature->getConfig();
    }

    #[Test]
    public function getConfigReturnsParsedIniArray(): void
    {
        $configPath = $this->configPath();
        self::assertNotSame('', $configPath);
        file_put_contents($configPath, "[feature]\nname=test\nvalue=1\n");

        $config = $this->feature->getConfig();

        self::assertSame(['feature' => ['name' => 'test', 'value' => '1']], $config);
    }

    #[Test]
    public function getConfigReturnsInjectedConfigWhenProvided(): void
    {
        $injectedConfig = ['section' => ['key' => 'injected_value']];
        $feature = new TestFeature($this->createFeatureStruct(), $injectedConfig);

        self::assertSame($injectedConfig, $feature->getConfig());
    }

    #[Test]
    public function getBuildFilesReturnsNullWhenBuildDirectoryDoesNotExist(): void
    {
        self::assertNull($this->feature->getBuildFiles());
    }

    #[Test]
    public function getBuildFilesReturnsDirectoryListingWhenBuildDirectoryExists(): void
    {
        $buildDir = $this->buildDirPath();
        self::assertNotSame('', $buildDir);

        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0777, true);
        }

        file_put_contents($buildDir . '/asset.js', 'console.log(1);');

        $files = $this->feature->getBuildFiles();

        self::assertIsArray($files);
        self::assertContains('asset.js', $files);
    }

    #[Test]
    public function loadRoutesIsCallableAndReturnsVoid(): void
    {
        $result = TestFeature::loadRoutes(new Klein());

        self::assertNull($result);
    }

    private function createFeatureStruct(): BasicFeatureStruct
    {
        return new BasicFeatureStruct(['feature_code' => TestFeature::FEATURE_CODE]);
    }

    private function configPath(): string
    {
        $basePath = $this->feature->getPluginBasePath();
        if (!is_string($basePath)) {
            return '';
        }

        return $basePath . '/../config.ini';
    }

    private function buildDirPath(): string
    {
        $basePath = $this->feature->getPluginBasePath();
        if (!is_string($basePath)) {
            return '';
        }

        return $basePath . '/../static/build';
    }

    private function invokeProtectedMethod(BaseFeature $feature, string $methodName): string
    {
        $method = new ReflectionMethod($feature, $methodName);
        $method->setAccessible(true);

        $result = $method->invoke($feature);
        self::assertIsString($result);

        return $result;
    }

    /**
     * @param array<string, mixed> $value
     */
    private static function setStaticProperty(string $class, string $property, array $value): void
    {
        $reflection = new ReflectionProperty($class, $property);
        $reflection->setValue(null, $value);
    }
}
