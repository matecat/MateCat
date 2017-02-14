<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 14/02/2017
 * Time: 13:21
 */

class Logger
{

    /**
     * @var Monolog\Logger
     */
    protected static $sqlcache_log ;
    protected static $default_log ;

    /**
     * @return \Monolog\Logger
     */
    public static function getDefault() {
        if ( self::$default_log == null ) {
            self::$default_log = new Monolog\Logger('matecat') ;

            self::$default_log->pushHandler(new \Monolog\Handler\StreamHandler(
                INIT::$LOG_REPOSITORY . "/matecat.log",
                Monolog\Logger::INFO
            )) ;
        }
        return self::$default_log ;
    }

    /**
     * @return Monolog\Logger
     */
    public static function sqlCache() {
        if ( self::$sqlcache_log == null ) {
            self::$sqlcache_log = new Monolog\Logger('sqlcache') ;

            self::$sqlcache_log->pushHandler(new \Monolog\Handler\StreamHandler(
                INIT::$LOG_REPOSITORY . "/matecat.log",
                Monolog\Logger::INFO
            )) ;
        }
        return self::$sqlcache_log ;
    }

}