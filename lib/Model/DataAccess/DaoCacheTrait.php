<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 08/08/24
 * Time: 14:35
 *
 */

namespace DataAccess;

use Exception;
use INIT;
use Log;
use PDO;
use PDOStatement;
use Predis\Client;
use RedisHandler;
use ReflectionException;

trait DaoCacheTrait {

    /**
     * The cache connection object
     * @var ?Client
     */
    protected static ?Client $cache_con;

    /**
     * @var int Cache expiry time, expressed in seconds
     */
    protected int $cacheTTL = 0;

    /**
     * Cache Initialization
     *
     * @return void
     * @throws ReflectionException
     */
    protected function _cacheSetConnection() {
        if ( !isset( self::$cache_con ) || empty( self::$cache_con ) ) {

            try {
                self::$cache_con = ( new RedisHandler() )->getConnection();
                self::$cache_con->get( 1 );
            } catch ( Exception $e ) {
                self::$cache_con = null;
                Log::doJsonLog( $e->getMessage() );
                Log::doJsonLog( "No Redis server(s) configured." );
                throw $e;
            }

        }
    }


    /**
     * @param string $query A query
     *
     * @return mixed
     * @throws ReflectionException
     */
    protected function _getFromCache( string $query ): ?array {
        if ( INIT::$SKIP_SQL_CACHE || $this->cacheTTL == 0 ) {
            return null;
        }

        $this->_cacheSetConnection();

        $value = null;
        if ( isset( self::$cache_con ) && !empty( self::$cache_con ) ) {
            $key   = md5( $query );
            $value = unserialize( self::$cache_con->get( $key ) );
            $this->_logCache( "GET", $key, $value, $query );
        }

        return $value ?: null;

    }

    protected function _logCache( $type, $key, $value, $sqlQuery ) {
        Log::doJsonLog( [
                "type" => $type,
                "key"  => $key,
                "sql"  => preg_replace( "/ +/", " ", str_replace( "\n", " ", $sqlQuery ) ),
            //"result_set" => $value,
        ], "query_cache.log" );
    }

    /**
     * @param $query string
     * @param $value array
     *
     * @return void|null
     */
    protected function _setInCache( string $query, array $value ) {
        if ( $this->cacheTTL == 0 ) {
            return null;
        }

        if ( isset( self::$cache_con ) && !empty( self::$cache_con ) ) {
            $key = md5( $query );
            self::$cache_con->setex( $key, $this->cacheTTL, serialize( $value ) );
            $this->_logCache( "SET", $key, $value, $query );
        }
    }

    /**
     * @param string $keyMap
     * @param string $query A query
     *
     * @return ?object
     * @throws ReflectionException
     */
    protected function _getFromCacheMap( string $keyMap, string $query ): ?array {
        if ( INIT::$SKIP_SQL_CACHE || $this->cacheTTL == 0 ) {
            return null;
        }

        $this->_cacheSetConnection();

        $value = null;
        if ( isset( self::$cache_con ) && !empty( self::$cache_con ) ) {
            $key   = md5( $query );
            $value = unserialize( self::$cache_con->hget( $keyMap, $key ) );
            $this->_logCache( "GETMAP: " . $keyMap, $key, $value, $query );
        }

        return $value ?: null;
    }

    /**
     * @param string $keyMap
     * @param        $query string
     * @param        $value array
     *
     * @return void|null
     */
    protected function _setInCacheMap( string $keyMap, string $query, array $value ) {
        if ( $this->cacheTTL == 0 ) {
            return null;
        }

        if ( isset( self::$cache_con ) && !empty( self::$cache_con ) ) {
            $key = md5( $query );
            self::$cache_con->hset( $keyMap, $key, serialize( $value ) );
            self::$cache_con->expire( $keyMap, $this->cacheTTL );
            $this->_logCache( "SETMAP: " . $keyMap, $key, $value, $query );
        }
    }

    /**
     * @param ?int $cacheSecondsTTL
     *
     * @return self
     */
    public function setCacheTTL( ?int $cacheSecondsTTL ): self {
        if ( !INIT::$SKIP_SQL_CACHE ) {
            $this->cacheTTL = $cacheSecondsTTL ?? 0;
        }

        return $this;
    }

    /**
     * Serialize params, ensuring values are always treated as strings.
     *
     * @param array $params
     *
     * @return string
     */
    protected function _serializeForCacheKey( array $params ): string {
        foreach ( $params as $key => $value ) {
            $params[ $key ] = (string)$value;
        }

        return serialize( $params );
    }

    /**
     * Destroy a single element in the hash set
     *
     * @param              $keyMap
     * @param PDOStatement $stmt
     * @param array        $bindParams
     *
     * @return bool|int
     * @throws ReflectionException
     */
    protected function _destroyObjectCacheMapElement( $keyMap, PDOStatement $stmt, array $bindParams ) {
        $this->_cacheSetConnection();
        if ( isset( self::$cache_con ) && !empty( self::$cache_con ) ) {
            return self::$cache_con->hdel( $keyMap, [ md5( $stmt->queryString . $this->_serializeForCacheKey( $bindParams ) ) ] );
        }

        return false;

    }

    /**
     * @param PDOStatement $stmt
     * @param array        $bindParams
     *
     * @return bool|int
     * @throws ReflectionException
     */
    protected function _destroyObjectCache( PDOStatement $stmt, array $bindParams ) {
        return $this->_destroyCache( $stmt->queryString . $this->_serializeForCacheKey( $bindParams ) );
    }

    /**
     * @param string $key
     * @param ?bool  $makeHash
     *
     * @return bool
     * @throws ReflectionException
     */
    protected function _destroyCache( string $key, ?bool $makeHash = true ): bool {
        $this->_cacheSetConnection();
        if ( isset( self::$cache_con ) && !empty( self::$cache_con ) ) {
            return self::$cache_con->del( $makeHash ? md5( $key ) : $key );
        }

        return false;

    }

}