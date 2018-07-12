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
     * @throws ReflectionException
     * @return Predis\Client
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

            $connectionParams = INIT::$REDIS_SERVERS;

            if ( is_string( $connectionParams ) ) {

                $connectionParams = $this->formatDSN( $connectionParams );

            } elseif( is_array( $connectionParams ) ){

                $connectionParams = array_map( 'RedisHandler::formatDSN', $connectionParams );

            }

            $this->redisHandler = new Predis\Client( $connectionParams );

        }

        return $this->redisHandler;

    }

    protected function formatDSN( $dsnString ){

        if ( !is_null( INIT::$INSTANCE_ID ) ) {

            $conf = parse_url( $dsnString );

            if ( isset( $conf[ 'query' ] ) ) {
                $instanceID = "&database=" . (int) INIT::$INSTANCE_ID;
            } else {
                $instanceID = "?database=" . (int) INIT::$INSTANCE_ID;
            }

            return $dsnString . $instanceID;

        }

        return $dsnString;

    }

}