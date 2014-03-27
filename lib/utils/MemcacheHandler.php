<?php

/**
 * Created by PhpStorm.
 * User: domenico domenico@translated.net / ostico@gmail.com
 * Date: 24/03/14
 * Time: 19.36
 *
 */
class MemcacheHandler {

    /**
     * @var MemcacheHandler instance of $this
     */
    protected static $_INSTANCE;

    /**
     * Only store a list of Memcache servers, not used for store the resources
     * because of PHP Memcache add these to it's internal pool
     *
     * @var array
     */
    protected static $_MemCachePool;
    protected static $_MemCachePoolWeights;

    /**
     * @var Memcache
     */
    protected static $_Connection = null;

    protected function __construct() {

        if( !class_exists( 'Memcache', false ) ){
            throw new Exception( 'Class Memcache not Found. php5-memcache may not be installed.' );
        }

        self::$_Connection = new Memcache();

        foreach ( self::$_MemCachePool as $_host => $weight ) {
            list( $host, $port ) = explode( ":", $_host );
            self::$_Connection->addServer( $host, $port, true, $weight );
        }

        $results = @self::$_Connection->getExtendedStats();

        foreach( $results as $__host => $_stats){

            if ( empty($_stats) ) {
                list( $host, $port ) = explode( ":", $__host );
                self::$_Connection->setServerParams( $host, $port, 1, -1, false );
                Log::doLog( "Server $host:$port down, not available." );
                unset( self::$_MemCachePool[ $_host ] );
            }

        }

        if( empty( self::$_MemCachePool ) ){
            throw new Exception( 'No Memcached servers available. All down.' );
        }

        register_shutdown_function( 'MemcacheHandler::close' );
    }

    public static function close() {

        //already Closed Resource
        if( !self::$_Connection instanceof Memcache ){
           return;
        }

        self::$_Connection->close();
        foreach ( self::$_MemCachePool as $key => $_host ) {
            unset( self::$_MemCachePool[ $key ] );
        }
        self::$_Connection = null;
        self::$_INSTANCE = null;
    }

    /**
     * Check for Existent and active Connection
     *
     * @return Memcache
     */
    protected function _getConnection() {
        if ( empty( self::$_Connection ) || !is_resource( self::$_Connection->connection ) ) {
            self::getInstance( self::$_MemCachePool );
        }

        return self::$_Connection;
    }

    public static function getInstance( array $memcachedPool = null ) {

        if ( self::$_INSTANCE === null ) {

            if( is_null( $memcachedPool ) && !empty( INIT::$MEMCACHE_SERVERS ) ){
                $memcachedPool = INIT::$MEMCACHE_SERVERS;
            } elseif( is_null( $memcachedPool ) && empty( INIT::$MEMCACHE_SERVERS ) ) {
                throw new LogicException( 'No Memcached server(s) configured.' );
            }

            self::$_MemCachePool = $memcachedPool;

            self::$_INSTANCE = new self();

        }

        return self::$_INSTANCE;
    }

    /**
     * @param $key
     *
     * @return mixed
     */
    public function get( $key ) {
        $key        = md5( $key );
        $connection = $this->_getConnection();

        return $connection->get( $key );
    }

    public function set( $key, $value, $ttl = 2592000 /* 1 Month */ ) {
        $key        = md5( $key );
        $connection = $this->_getConnection();

        return $connection->set( $key, $value, MEMCACHE_COMPRESSED, $ttl );
    }

    public function delete( $key ) {
        $key        = md5( $key );
        $connection = $this->_getConnection();

        return $connection->delete( $key, 0 );
    }

    public function increment( $key, $byValue = 1 ){
        $key        = md5( $key );
        $connection = $this->_getConnection();

        $newItem = $connection->increment( $key, $byValue );

        if( $newItem === false ){
            $connection->add( $key, $byValue, 0 /* no compression because can't increment compressed */, 2592000 /* 1 Month */ );
            $newItem = $byValue;
        }

        return $newItem;
    }

    public function decrement( $key, $byValue = 1 ){
        $key        = md5( $key );
        $connection = $this->_getConnection();

        $newItem = $connection->decrement( $key, $byValue );

        if( $newItem === false ){
            $newItem = 0; //can't decrement a not existent item and no items can be less than zero
        }

        return $newItem;

    }

    public function flush(){
        $connection = $this->_getConnection();
        return $connection->flush();
    }

    public function add( $key, $value, $ttl = 2592000 /* 1 Month */ ){
        $key        = md5( $key );
        $connection = $this->_getConnection();

        return $connection->add( $key, $value, MEMCACHE_COMPRESSED, $ttl );
    }

    public function getCurrentPool(){
        return self::$_MemCachePool;
    }

}
