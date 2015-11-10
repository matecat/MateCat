<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 09/11/15
 * Time: 18.51
 *
 */
class RedisHandler {

    /**
     * @var Predis\Client
     */
    protected $redisHandler;

    /**
     * Lazy connection
     *
     * Get the connection to Redis server and return it
     *
     * @throws \Predis\Connection\ConnectionException
     */
    public function getConnection( ){

        $resource = null;
        if( $this->redisHandler != null ){
            $reflectorClass = new ReflectionClass( $this->redisHandler->getConnection() );
            $reflectorProperty = $reflectorClass->getParentClass()->getProperty( 'resource' );
            $reflectorProperty->setAccessible( true );
            $resource = $reflectorProperty->getValue( $this->redisHandler->getConnection() );
        }

        if(
                $this->redisHandler === null
                || !$this->redisHandler->getConnection()->isConnected()
                || !is_resource( $resource )
        ){
            $this->redisHandler = new Predis\Client( INIT::$REDIS_SERVERS );
        }

        return $this->redisHandler;

    }

}