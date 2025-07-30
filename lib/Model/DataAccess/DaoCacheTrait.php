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


    protected function _logCache( $type, $key, $value, $sqlQuery ) {
        Log::doJsonLog( [
                "type" => $type,
                "key"  => $key,
                "sql"  => preg_replace( "/ +/", " ", str_replace( "\n", " ", $sqlQuery ) ),
            //"result_set" => $value,
        ], "query_cache.log" );
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
     * This method uses a clean, human-readable key instead of a md5 hash.
     * It also allows grouping multiple queries under a single namespace (`$keyMap`).
     *
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
            self::$cache_con->setex( $key, $this->cacheTTL, $keyMap );
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
     * @param string $reverseKeyMap
     * @param string $keyElementName
     *
     * @return bool|int
     * @throws ReflectionException
     */
    protected function _removeObjectCacheMapElement( string $reverseKeyMap, string $keyElementName ) {
        $this->_cacheSetConnection();
        if ( isset( self::$cache_con ) && !empty( self::$cache_con ) ) {
            $keyMap = self::$cache_con->get( $reverseKeyMap );

            return self::$cache_con->hdel( $keyMap, [ md5( $keyElementName ) ] ); // let the hashset expire by himself instead of calling HLEN and DEL
        }

        return false;

    }

    /**
     * Destroy a key directly when it is known
     *
     * @param string $key
     * @param ?bool  $isReverseKeyMap
     *
     * @return bool
     * @throws ReflectionException
     *
     */
    protected function _deleteCacheByKey( string $key, ?bool $isReverseKeyMap = true ): bool {
        $this->_cacheSetConnection();
        if ( isset( self::$cache_con ) && !empty( self::$cache_con ) ) {

            if ( $isReverseKeyMap ) {
                $keyMap = self::$cache_con->get( $key );
                $res    = self::$cache_con->del( $keyMap );
                self::$cache_con->del( $key );

                return $res;
            }

            return self::$cache_con->del( $key );
        }

        return false;

    }

}