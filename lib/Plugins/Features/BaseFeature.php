<?php

namespace Features ;
use BasicFeatureStruct;
use Exception;
use INIT;
use Klein\Klein;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


use ReflectionClass ;
use LogicException;

abstract class BaseFeature implements IBaseFeature {

    const FEATURE_CODE = null;

    protected $feature;

    private $log ;

    private $logger_name ;

    protected $autoActivateOnProject = true ;

    protected static $dependencies = [];

    protected static $conflictingDependencies = [];

    /**
     * @return array
     */
    public static function getConflictingDependencies() {
        return static::$conflictingDependencies;
    }

    public static function getConfig() {
        $config_file_path = self::getPluginBasePath() . '/../config.ini' ;
        if ( ! file_exists( $config_file_path ) ) {
            throw new Exception('Config file not found', 500 );
        }
        return parse_ini_file( $config_file_path ) ;
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
        if( empty( $fCode ) ) throw new LogicException( "Plugin code not defined." );
        $this->feature = $feature ;
        $this->logger_name = $this->feature->feature_code . '_plugin' ;
    }

    public function autoActivateOnProject() {
        return $this->autoActivateOnProject ;
    }

    public static function getDependencies() {
        return static::$dependencies ;
    }

    // gets a feature specific logger
    public function getLogger() {
        if ( $this->log == null ) {
            $this->log = new Logger($this->logger_name);
            $this->log->pushHandler(new StreamHandler($this->logFilePath(),  Logger::INFO));
        }
        return $this->log;
    }

    private function logFilePath() {
       return INIT::$LOG_REPOSITORY . '/' . $this->logger_name . '.log';
    }


    public static function getClassPath() {
        $rc = new ReflectionClass(get_called_class());
        return dirname( $rc->getFileName() ) . '/' . pathinfo($rc->getFileName(), PATHINFO_FILENAME ) ;
    }

    public static function getPluginBasePath() {
        return realpath(  static::getClassPath() . '/../..' ) ;
    }

    public static function getTemplatesPath() {
        return static::getClassPath() . '/View' ;
    }

    public function getFeatureStruct() {
        return $this->feature ;
    }

    /**
     * @param Klein $klein
     *
     * @see \Features::loadRoutes
     */
    public static function loadRoutes( Klein $klein ){}

}
