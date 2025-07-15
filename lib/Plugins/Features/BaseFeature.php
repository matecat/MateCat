<?php

namespace Plugins\Features;

use Exception;
use Klein\Klein;
use LogicException;
use Model\FeaturesBase\BasicFeatureStruct;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Utils\Registry\AppConfig;


abstract class BaseFeature implements IBaseFeature {

    const FEATURE_CODE = null;

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

    protected static array $dependencies = [];

    protected static array $conflictingDependencies = [];

    /**
     * @return array
     */
    public static function getConflictingDependencies(): array {
        return static::$conflictingDependencies;
    }

    /**
     * @throws Exception
     */
    public static function getConfig() {
        $config_file_path = realpath( self::getPluginBasePath() . '/../config.ini' );
        if ( !file_exists( $config_file_path ) ) {
            throw new Exception( 'Config file not found', 500 );
        }

        return parse_ini_file( $config_file_path, true );
    }

    /**
     * Warning: passing a $projectStructure prevents the possibility to pass
     * a persisted project in the future. TODO: this is likely to be reworked
     * in the future.
     *
     * The ideal solution would be to use a ProjectStruct for both persisted and
     * unpersisted scenarios, so to work with the same input structure every time.
     *
     * @param BasicFeatureStruct $feature
     */
    public function __construct( BasicFeatureStruct $feature ) {
        $fCode = static::FEATURE_CODE;
        if ( empty( $fCode ) ) {
            throw new LogicException( "Plugin code not defined." );
        }
        $this->feature     = $feature;
        $this->logger_name = $this->feature->feature_code . '_plugin';
    }

    public function isAutoActivableOnProject(): bool {
        return $this->autoActivateOnProject;
    }

    public function isForceableOnProject(): bool {
        return $this->forceOnProject;
    }

    public static function getDependencies(): array {
        return static::$dependencies;
    }

    /**
     * gets a feature specific logger
     *
     * @return Logger
     * @throws Exception
     */
    public function getLogger() {
        if ( $this->log == null ) {
            $this->log     = new Logger( $this->logger_name );
            $streamHandler = new StreamHandler( $this->logFilePath(), Logger::INFO );
            $streamHandler->setFormatter( new LineFormatter( "%message%\n", "", true, true ) );
            $this->log->pushHandler( $streamHandler );
        }

        return $this->log;
    }

    protected function logFilePath(): string {
        return AppConfig::$LOG_REPOSITORY . '/' . $this->logger_name . '.log';
    }


    public static function getClassPath(): string {
        $rc = new ReflectionClass( get_called_class() );

        return dirname( $rc->getFileName() ) . '/' . pathinfo( $rc->getFileName(), PATHINFO_FILENAME );
    }

    public static function getPluginBasePath() {
        return realpath( static::getClassPath() . '/../..' );
    }

    public static function getTemplatesPath() {
        return static::getClassPath() . '/View';
    }

    public function getFeatureStruct(): BasicFeatureStruct {
        return $this->feature;
    }

    /**
     * @param Klein $klein
     *
     * @see \Model\FeaturesBase\PluginsLoader::loadRoutes
     */
    public static function loadRoutes( Klein $klein ) {
    }

    /**
     *
     * Return a list of files in build path of a plugin
     * @return array|false
     */
    public function getBuildFiles() {
        $path = realpath( self::getPluginBasePath() . '/../static/build' );
        if ( empty( $path ) ) {
            return false;
        }

        return scandir( $path );
    }

}
