<?php

namespace Plugins\Features;

use Exception;
use Klein\Klein;
use LogicException;
use Model\FeaturesBase\BasicFeatureStruct;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;


abstract class BaseFeature implements IBaseFeature
{

    const string FEATURE_CODE = '';

    protected BasicFeatureStruct $feature;

    /**
     * @var ?LoggerInterface
     */
    protected ?LoggerInterface $log = null;

    protected string $logger_name;

    /**
     * @var bool This property defines if the feature is automatically active when projects are created,
     *           or if it requires an explicit activation from the user when the project is created.
     *           If this property is true, the feature is added to project's metadata `features` string.
     *           This property is only used to activate features that come from owner_features records.
     */
    protected bool $autoActivateOnProject = true;

    /**
     * @var bool This property defines if the feature is to be included in project features even if
     *           it's not defined in project features. This should be set to `true` when adding features
     *           that should be enabled systemwide, even on older projects.
     */
    protected bool $forceOnProject = false;

    /**
     * @var array<int, string>
     */
    protected static array $dependencies = [];

    /**
     * @var array<int, string>
     */
    protected static array $conflictingDependencies = [];

    /**
     * @return array<int, string>
     */
    public static function getConflictingDependencies(): array
    {
        return static::$conflictingDependencies;
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public static function getConfig(): array
    {
        $config_file_path = realpath(self::getPluginBasePath() . '/../config.ini');
        if ($config_file_path === false || !file_exists($config_file_path)) {
            throw new Exception('Config file not found', 500);
        }

        $config = @parse_ini_file($config_file_path, true);
        if ($config === false) {
            throw new Exception('Unable to parse config file', 500);
        }

        return $config;
    }

    /**
     * Constructor method for the class.
     *
     * @param BasicFeatureStruct $feature An instance of BasicFeatureStruct representing the feature data.
     * @return void
     *
     * @throws LogicException If the plugin code is not defined.
     */
    public function __construct(BasicFeatureStruct $feature)
    {
        $fCode = static::FEATURE_CODE;
        if (empty($fCode)) {
            throw new LogicException("Plugin code not defined.");
        }
        $this->feature = $feature;
        $this->logger_name = $this->feature->feature_code . '_plugin';
    }

    public function isAutoActivableOnProject(): bool
    {
        return $this->autoActivateOnProject;
    }

    public function isForceableOnProject(): bool
    {
        return $this->forceOnProject;
    }

    /**
     * @return array<int, string>
     */
    public static function getDependencies(): array
    {
        return static::$dependencies;
    }

    /**
     * gets a feature-specific logger
     *
     * @return LoggerInterface
     * @throws Exception
     */
    public function getLogger(): LoggerInterface
    {
        if ($this->log == null) {
            $this->log = LoggerFactory::getLogger(self::FEATURE_CODE, $this->logger_name);
        }

        return $this->log;
    }

    protected function logFilePath(): string
    {
        return AppConfig::$LOG_REPOSITORY . '/' . $this->logger_name . '.log';
    }


    /**
     * @throws LogicException
     */
    public static function getClassPath(): string
    {
        $rc = new ReflectionClass(get_called_class());
        $fileName = $rc->getFileName();
        if ($fileName === false) {
            throw new LogicException('Class file path not available');
        }

        return dirname($fileName) . '/' . pathinfo($fileName, PATHINFO_FILENAME);
    }

    /**
     * @throws LogicException
     */
    public static function getPluginBasePath(): false|string
    {
        return realpath(dirname(static::getClassPath(), 2));
    }

    /**
     * @throws LogicException
     */
    public static function getTemplatesPath(): string
    {
        return static::getClassPath() . '/View';
    }

    public function getFeatureStruct(): BasicFeatureStruct
    {
        return $this->feature;
    }

    /**
     * @param Klein $klein
     *
     * @return void
     * @see \Model\FeaturesBase\PluginsLoader::loadRoutes
     */
    public static function loadRoutes(Klein $klein)
    {
    }

    /**
     *
     * Return a list of files in build path of a plugin
     *
     * @return list<string>|null
     * @throws LogicException
     */
    public function getBuildFiles(): ?array
    {
        $path = realpath(self::getPluginBasePath() . '/../static/build');
        if ($path === false) {
            return null;
        }

        $files = scandir($path);
        if ($files === false) {
            return null;
        }

        return $files;
    }

}
