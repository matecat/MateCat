<?php

namespace Features ;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class BaseFeature {

    protected $feature;

    private $log ;

    private $logger_name ;

    /**
     * Warning: passing a $projectStructure prevents the possibility to pass
     * a persisted project in the future. TODO: this is likely to be reworked
     * in the future.
     *
     * The ideal solution would be to use a ProjectStruct for both persisted and
     * unpersisted scenarios, so to work with the same input structure every time.
     *
     */
    public function __construct( \BasicFeatureStruct $feature ) {
        $this->feature = $feature ;
        $this->logger_name = $this->feature->feature_code . '_plugin' ;
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
       return \INIT::$LOG_REPOSITORY . '/' . $this->logger_name . '.log';
    }
}
