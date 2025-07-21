<?php

namespace Utils\Redis;

use Exception;
use Predis\Client;
use ReflectionClass;
use ReflectionException;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 09/11/15
 * Time: 18.51
 *
 */
class RedisHandler {

    /**
     * @var ?Client
     */
    private ?Client $redisClient = null;

    private string $instanceHash;
    private string $instanceUUID;

    /**
     * @throws Exception
     */
    public function __construct() {
        $this->instanceHash = spl_object_hash( $this );
        $this->instanceUUID = Utils::uuid4();
    }

    protected function getInstanceIdentifier(): string {
        return $this->instanceHash . ":" . $this->instanceUUID;
    }

    /**
     * Lazy connection
     *
     * Get the connection to Redis server and return it
     *
     * @return Client
     * @throws ReflectionException
     */
    public function getConnection(): Client {

        $resource = null;
        if ( $this->redisClient != null ) {
            $reflectorClass    = new ReflectionClass( $this->redisClient->getConnection() );
            $reflectorProperty = $reflectorClass->getParentClass()->getProperty( 'resource' );
            $reflectorProperty->setAccessible( true );
            $resource = $reflectorProperty->getValue( $this->redisClient->getConnection() );
        }

        if (
                $this->redisClient === null
                || !$this->redisClient->getConnection()->isConnected()
                || !is_resource( $resource )
        ) {

            $this->redisClient = $this->getClient();

        }

        return $this->redisClient;

    }

    /**
     * @return Client
     */
    private function getClient(): Client {
        $connectionParams = AppConfig::$REDIS_SERVERS;

        if ( is_string( $connectionParams ) ) {

            $connectionParams = $this->formatDSN( $connectionParams );

        } elseif ( is_array( $connectionParams ) ) {

            $connectionParams = array_map( 'Utils\Redis\RedisHandler', $connectionParams );

        }

        return new Client( $connectionParams );

    }

    protected function formatDSN( $dsnString ): string {

        if ( !is_null( AppConfig::$INSTANCE_ID ) ) {

            $conf = parse_url( $dsnString );

            if ( isset( $conf[ 'query' ] ) ) {
                $instanceID = "&database=" . AppConfig::$INSTANCE_ID;
            } else {
                $instanceID = "?database=" . AppConfig::$INSTANCE_ID;
            }

            return $dsnString . $instanceID;

        }

        return $dsnString;

    }

    /**
     * @param string $key
     * @param int    $wait_time_seconds
     *
     * @throws Exception
     */
    public function tryLock( string $key, int $wait_time_seconds = 10 ): void {

        $time      = microtime( true );
        $exit_time = $time + $wait_time_seconds;
        $sleep     = 500000; // microseconds

        do {

            // Lock Redis with NX and Expire
            $lock = (bool)$this->redisClient->setnx( "lock:" . $key, $this->getInstanceIdentifier() );

            if ( $lock ) {
                $this->redisClient->expire( "lock:" . $key, $wait_time_seconds );

                return;
            }

            usleep( $sleep );

        } while ( microtime( true ) < $exit_time );

        throw new Exception( "Lock wait timeout reached." );

    }

    /**
     * Unlock the key only if this instance holds the lock
     *
     * @param $key
     *
     * @return void
     */
    public function unlock( $key ): void {
        $lockingInstance = $this->redisClient->get( "lock:" . $key );
        if ( !empty( $lockingInstance ) && $lockingInstance == $this->getInstanceIdentifier() ) {
            $this->redisClient->del( "lock:" . $key );
        }
    }

}