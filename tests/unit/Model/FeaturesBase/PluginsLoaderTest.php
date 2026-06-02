<?php

use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureCodes;
use Model\FeaturesBase\PluginsLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Plugins\Features\ProjectCompletion;
use Plugins\Features\UnknownFeature;
use Utils\Registry\AppConfig;

#[Group('unit')]
class PluginsLoaderTest extends AbstractTest
{
    private string $originalIncludePath;
    private string $originalAppConfigRoot;

    /** @var array<int, string> */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalIncludePath = get_include_path();
        $this->originalAppConfigRoot = AppConfig::$ROOT;
    }

    protected function tearDown(): void
    {
        AppConfig::$ROOT = $this->originalAppConfigRoot;

        set_include_path($this->originalIncludePath);

        foreach ($this->tempDirs as $tempDir) {
            $this->deleteDirectory($tempDir);
        }

        parent::tearDown();
    }

    #[Test]
    public function getInstanceLoadsPluginManifestAndReturnsSingletonInstance(): void
    {
        $tmpRoot = sys_get_temp_dir() . '/plugins-loader-test-' . uniqid('', true);
        $this->tempDirs[] = $tmpRoot;

        $pluginDir = $tmpRoot . '/plugins/UnitPlugin';
        mkdir($pluginDir . '/lib', 0777, true);
        file_put_contents(
            $pluginDir . '/manifest.php',
            "<?php\nreturn [\n    'FEATURE_CODE' => 'unit_test_feature',\n    'PLUGIN_CLASS' => '\\\\Plugins\\\\Features\\\\ProjectCompletion',\n];\n"
        );

        AppConfig::$ROOT = $tmpRoot;

        $first = $this->invokeProtectedGetInstance();
        $second = $this->invokeProtectedGetInstance();

        self::assertSame($first, $second);
        self::assertContains(FeatureCodes::PROJECT_COMPLETION, PluginsLoader::getValidCodes());
    }

    #[Test]
    public function getValidCodesReturnsDefaultFeatureCodesOnFreshInstance(): void
    {
        $this->resetSingletonWithFreshInstance();

        $codes = PluginsLoader::getValidCodes();

        self::assertContains(FeatureCodes::PROJECT_COMPLETION, $codes);
        self::assertContains(FeatureCodes::TRANSLATION_VERSIONS, $codes);
        self::assertContains(FeatureCodes::REVIEW_EXTENDED, $codes);
        self::assertContains(FeatureCodes::SECOND_PASS_REVIEW, $codes);
    }

    #[Test]
    public function populateVarsStoresPluginPathsCodesAndClasses(): void
    {
        $this->resetSingletonWithFreshInstance();

        PluginsLoader::populateVars(
            [
                'FEATURE_CODE' => 'custom_feature',
                'PLUGIN_CLASS' => ProjectCompletion::class,
            ],
            '/tmp/custom-plugin'
        );

        $instance = $this->invokeProtectedGetInstance();
        $reflection = new \ReflectionClass($instance);
        $pluginPaths = $reflection->getProperty('PLUGIN_PATHS');
        $pluginPaths->setAccessible(true);
        $pluginClasses = $reflection->getProperty('PLUGIN_CLASSES');
        $pluginClasses->setAccessible(true);

        /** @var array<string, string> $paths */
        $paths = $pluginPaths->getValue($instance);
        /** @var array<string, string> $classes */
        $classes = $pluginClasses->getValue($instance);

        self::assertArrayHasKey('custom_feature', $paths);
        self::assertSame('/tmp/custom-plugin/lib', $paths['custom_feature']);
        self::assertContains('custom_feature', PluginsLoader::getValidCodes());
        self::assertSame(ProjectCompletion::class, $classes['custom_feature']);
    }

    #[Test]
    public function getPluginClassReturnsConfiguredPluginClassWhenItExists(): void
    {
        $this->resetSingletonWithFreshInstance();

        PluginsLoader::populateVars(
            [
                'FEATURE_CODE' => 'configured_feature',
                'PLUGIN_CLASS' => ProjectCompletion::class,
            ],
            '/tmp/configured-feature'
        );

        self::assertSame(ProjectCompletion::class, PluginsLoader::getPluginClass('configured_feature'));
    }

    #[Test]
    public function getPluginClassFallsBackToInternalPluginClassWhenNotConfigured(): void
    {
        $this->resetSingletonWithFreshInstance();

        self::assertSame('\\Plugins\\Features\\ProjectCompletion', PluginsLoader::getPluginClass(FeatureCodes::PROJECT_COMPLETION));
    }

    #[Test]
    public function getPluginClassReturnsUnknownFeatureWhenConfiguredClassDoesNotExist(): void
    {
        $this->resetSingletonWithFreshInstance();

        PluginsLoader::populateVars(
            [
                'FEATURE_CODE' => 'broken_feature',
                'PLUGIN_CLASS' => '\\Plugins\\Features\\DefinitelyMissingClass',
            ],
            '/tmp/broken-feature'
        );

        self::assertSame(UnknownFeature::class, PluginsLoader::getPluginClass('broken_feature'));
    }

    #[Test]
    public function getPluginClassReturnsUnknownFeatureWhenInternalPluginClassDoesNotExist(): void
    {
        $this->resetSingletonWithFreshInstance();

        self::assertSame(UnknownFeature::class, PluginsLoader::getPluginClass('totally_unknown_feature_code'));
    }

    #[Test]
    public function getFeatureClassDecoratorReturnsDecoratorClassForInternalFeature(): void
    {
        $this->resetSingletonWithFreshInstance();

        $feature = new BasicFeatureStruct(['feature_code' => FeatureCodes::PROJECT_COMPLETION, 'options' => null]);

        $decorator = PluginsLoader::getFeatureClassDecorator($feature, 'CatDecorator');

        self::assertSame('\\Plugins\\Features\\ProjectCompletion\\Decorator\\CatDecorator', $decorator);
    }

    #[Test]
    public function getFeatureClassDecoratorReturnsDecoratorClassForConfiguredFeature(): void
    {
        $this->resetSingletonWithFreshInstance();

        PluginsLoader::populateVars(
            [
                'FEATURE_CODE' => 'configured_decorator_feature',
                'PLUGIN_CLASS' => ProjectCompletion::class,
            ],
            '/tmp/configured-decorator'
        );

        $feature = new BasicFeatureStruct(['feature_code' => 'configured_decorator_feature', 'options' => null]);

        $decorator = PluginsLoader::getFeatureClassDecorator($feature, 'CatDecorator');

        self::assertSame('Plugins\\Features\\ProjectCompletion\\Decorator\\CatDecorator', $decorator);
    }

    #[Test]
    public function getFeatureClassDecoratorReturnsFalseWhenDecoratorDoesNotExist(): void
    {
        $this->resetSingletonWithFreshInstance();

        $feature = new BasicFeatureStruct(['feature_code' => FeatureCodes::PROJECT_COMPLETION, 'options' => null]);

        self::assertFalse(PluginsLoader::getFeatureClassDecorator($feature, 'MissingDecorator'));
    }

    #[Test]
    public function getPluginDirectoryNameReturnsDirectoryNameFromPluginPath(): void
    {
        $this->resetSingletonWithFreshInstance();

        PluginsLoader::populateVars(
            [
                'FEATURE_CODE' => 'directory_feature',
                'PLUGIN_CLASS' => ProjectCompletion::class,
            ],
            '/tmp/MyPluginDirectory'
        );

        self::assertSame('MyPluginDirectory', PluginsLoader::getPluginDirectoryName('directory_feature'));
    }

    #[Test]
    public function setIncludePathAppendsConfiguredPluginLibDirectories(): void
    {
        $this->resetSingletonWithFreshInstance();

        PluginsLoader::populateVars(
            [
                'FEATURE_CODE' => 'include_path_feature',
                'PLUGIN_CLASS' => ProjectCompletion::class,
            ],
            '/tmp/include-path-plugin'
        );

        PluginsLoader::setIncludePath();
        $includePathParts = explode(PATH_SEPARATOR, get_include_path());

        self::assertContains('/tmp/include-path-plugin/lib', $includePathParts);
    }

    #[Test]
    public function loadRoutesRegistersPluginPrefixWhenRequestContainsAValidFeatureCode(): void
    {
        $this->resetSingletonWithFreshInstance();

        $_SERVER['REQUEST_URI'] = '/plugins/project_completion/anything';

        $klein = $this->createMock(\Klein\Klein::class);
        $klein->expects(self::once())
            ->method('with')
            ->with('/plugins/project_completion', self::isCallable());

        PluginsLoader::loadRoutes($klein);
    }

    #[Test]
    public function loadRoutesSkipsRouteRegistrationWhenRequestContainsAnInvalidFeatureCode(): void
    {
        $this->resetSingletonWithFreshInstance();

        $_SERVER['REQUEST_URI'] = '/plugins/not_valid/anything';

        $klein = $this->createMock(\Klein\Klein::class);
        $klein->expects(self::never())->method('with');

        PluginsLoader::loadRoutes($klein);
    }

    private function invokeProtectedGetInstance(): PluginsLoader
    {
        $reflection = new \ReflectionClass(PluginsLoader::class);
        $method = $reflection->getMethod('getInstance');
        $method->setAccessible(true);

        /** @var PluginsLoader $instance */
        $instance = $method->invoke(null);

        return $instance;
    }

    private function resetSingletonWithFreshInstance(): void
    {
        $reflection = new \ReflectionClass(PluginsLoader::class);
        $instanceProperty = $reflection->getProperty('_INSTANCE');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, new PluginsLoader());
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);
                continue;
            }

            @unlink($itemPath);
        }

        @rmdir($path);
    }
}
