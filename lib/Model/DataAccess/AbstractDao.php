<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 29/09/14
 * Time: 17.55
 */
abstract class DataAccess_AbstractDao {

    /**
     * The connection object
     * @var Database
     */
    protected $con;

    /**
     * The cache connection object
     * @var Predis\Client
     */
    protected $cache_con;

    /**
     * @var string This property will be overridden in the sub-classes.
     *             This means that const assignment cannot be done. We don't have PHP>5.3
     */
    const STRUCT_TYPE = '';

    /**
     * @var int The maximum number of tuples that can be inserted for a single query
     */
    const MAX_INSERT_NUMBER = 1;

    /**
     * @var int Cache expiry time, expressed in seconds
     */
    protected $cacheTTL = 0;

    public function __construct( $con = null ) {
        /**
         * @var $con Database
         */

        if ( $con == null ) {
            $con = Database::obtain();
        }

        $this->con = $con;
    }

    public function create( DataAccess_IDaoStruct $obj ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    public function read( DataAccess_IDaoStruct $obj ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    public function update( DataAccess_IDaoStruct $obj ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    public function delete( DataAccess_IDaoStruct $obj ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    public function createList( Array $obj_arr ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    public function updateList( Array $obj_arr ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    public function deleteList( Array $obj_arr ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    /**
     * @param $input DataAccess_IDaoStruct The input object
     *
     * @return DataAccess_IDaoStruct The input object, sanitized.
     * @throws Exception This function throws exception input is not a DataAccess_IDaoStruct object
     */
    public function sanitize( $input ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    /**
     * @param $input array An array of DataAccess_IDaoStruct objects
     *
     * @return array The input array, sanitized.
     * @throws Exception This function throws exception if input is not:<br/>
     *                  <ul>
     *                      <li>An array of $type objects</li>
     *                      or
     *                      <li>A DataAccess_IDaoStruct object</li>
     *                  </ul>
     */
    public static function sanitizeArray( $input ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    /**
     * @param array  $input The input array
     * @param string $type  The expected type
     *
     * @return array The input array if sanitize was successful, otherwise this function throws exception
     * @throws Exception This function throws exception if input is not:<br/>
     *                  <ul>
     *                      <li>An array of $type objects</li>
     *                      or
     *                      <li>A $type object</li>
     *                  </ul>.
     */
    protected static function _sanitizeInputArray( Array $input, $type ) {

        foreach ( $input as $i => $elem ) {
            $input[ $i ] = self::_sanitizeInput( $elem, $type );
        }

        return $input;
    }

    /**
     * @param DataAccess_IDaoStruct $input The input to be sanitized
     * @param string                $type  The expected type
     *
     * @return DataAccess_IDaoStruct The input object if sanitize was successful, otherwise this function throws exception.
     * @throws Exception This function throws exception input is not an object of type $type
     */
    protected static function _sanitizeInput( $input, $type ) {

        //if something different from $type is passed, throw exception
        if ( !( $input instanceof $type ) ) {
            throw new Exception( "Invalid input. Expected " . $type, -1 );
        }

        return $input;
    }


    /**
     * This method validates the primary key of a single object to be used in persistency.
     *
     * @param $obj DataAccess_IDaoStruct The input object
     *
     * @return bool True if object is valid, false otherwise
     */
    protected function _validatePrimaryKey( DataAccess_IDaoStruct $obj ) {
        //to be overridden in sub-classes
        return true;
    }

    /**
     * This method validates the fields of a single object that have to be not null.
     *
     * @param $obj DataAccess_IDaoStruct The input object
     *
     * @return bool True if object is valid, false otherwise
     */
    protected function _validateNotNullFields( DataAccess_IDaoStruct $obj ){
        //to be overridden in sub-classes
        return true;
    }

    /**
     * Get a statement object by query string
     *
     * @param $query
     *
     * @return PDOStatement
     */
    protected function _getStatementForCache( $query ) {

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $query );

        return $stmt;
    }

    /**
     * Cache Initialization
     *
     * @return $this
     */
    protected function _cacheSetConnection(){
        if ( !isset( $this->cache_con ) || empty( $this->cache_con ) ) {

            require_once 'Predis/autoload.php';
            try {
                $this->cache_con = new Predis\Client( INIT::$REDIS_SERVERS );
                $this->cache_con->get(1);
            } catch ( Exception $e ){
                $this->cache_con = null;
                Log::doLog( $e->getMessage() );
                Log::doLog( "No Redis server(s) configured." );
            }

        }
    }

    /**
     * @param $query string A query
     *
     * @return mixed
     */
    protected function _getFromCache($query){
        if($this->cacheTTL == 0 ) return null;

        $this->_cacheSetConnection();

        $_existingResult = null;
        if ( isset( $this->cache_con ) && !empty( $this->cache_con ) ) {
            $cacheQuery = md5( $query );
            Log::doLog( "Fetching from cache $cacheQuery - query: \"" . $query . "\"" );
            $_existingResult = unserialize( $this->cache_con->get( $cacheQuery ) );
        }

        return $_existingResult;
    }

    /**
     * @param $query string
     * @param $value array
     *
     * @return void|null
     */
    protected function _setInCache( $query, $value ){
        if($this->cacheTTL == 0 ) return null;

        if ( isset( $this->cache_con ) && !empty( $this->cache_con ) ) {
            $this->cache_con->setex( md5( $query ), $this->cacheTTL, serialize( $value ) );
        }
    }

    /**
     * @param int $cacheSecondsTTL
     *
     * @return $this
     */
    public function setCacheTTL( $cacheSecondsTTL ) {
        $this->cacheTTL = $cacheSecondsTTL;
        return $this;
    }

    /**
     * @param $query string A query
     *
     * @return array|mixed
     */
    protected function _fetch_array( $query ) {
        $_cacheResult = $this->_getFromCache( $query );

        if ( $_cacheResult !== false && $_cacheResult !== null ) {
            return $_cacheResult;
        }

        $result = $this->con->fetch_array( $query );

        $this->_setInCache( $query, $result );

        return $result;
    }

    /**
     * @param $query
     *
     * @return bool
     */
    protected function _destroyCache( $query ){
        $this->_cacheSetConnection();
        if ( isset( $this->cache_con ) && !empty( $this->cache_con ) ) {
            return $this->cache_con->del( md5 ($query ));
        }

        return false;

    }

    /**
     * @param PDOStatement          $stmt
     * @param DataAccess_IDaoStruct $fetchClass
     * @param array                 $bindParams
     *
     * @return DataAccess_IDaoStruct|DataAccess_IDaoStruct[]
     */
    protected function _fetchObject( PDOStatement $stmt, DataAccess_IDaoStruct $fetchClass, Array $bindParams ){

        $_cacheResult = $this->_getFromCache( $stmt->queryString . serialize( $bindParams ) );

        if ( $_cacheResult !== false && $_cacheResult !== null ) {
            return $_cacheResult;
        }

        /** @noinspection PhpMethodParametersCountMismatchInspection */
        $stmt->setFetchMode( PDO::FETCH_CLASS, get_class( $fetchClass ) );
        $stmt->execute( $bindParams );
        $result = $stmt->fetchAll();
        
        $this->_setInCache( $stmt->queryString . serialize( $bindParams ), $result );

        return $result;

    }

    /**
     * @param PDOStatement          $stmt
     * @param array                 $bindParams
     *
     * @return bool|int
     */
    protected function _destroyObjectCache( PDOStatement $stmt, Array $bindParams ){
        $this->_cacheSetConnection();
        if ( isset( $this->cache_con ) && !empty( $this->cache_con ) ) {
            return $this->cache_con->del( md5 ( $stmt->queryString . serialize( $bindParams ) ) );
        }

        return false;

    }

    /**
     * @param $array_result array
     * @deprecated Use instead PDO::setFetchMode()
     * @return DataAccess_IDaoStruct|DataAccess_IDaoStruct[]
     */
    protected abstract function _buildResult( $array_result );

    /**
     * Returns a string suitable for insert of the fields
     * provided by the attributes array.
     *
     * @param $attrs array of full attributes to update
     * @param $mask array of attributes to include in the update
     * @return string
     */

    protected static function buildInsertStatement( $attrs, $mask ) {
        $first = array()  ;
        $second = array() ;
        $pks = static::$primary_keys;

        if ( empty($mask) ) {
            $mask = array_keys( $attrs );
        }

        foreach( $attrs as $key => $value ) {
            if ( !in_array( $key, $pks ) && in_array($key, $mask) ) {
                $first[] =  $key;
                $second[] = ":$key" ;
            }
        }

        $sql = "INSERT INTO " . static::$TABLE . "(" .
                implode(', ', $first ) . ") VALUES (" .
                implode(', ', $second) . ");" ;

        return $sql ;
    }


    /**
     * Returns a string suitable for updates of the fields
     * provided by the attributes array.
     *
     * @param $attrs array of full attributes to update
     * @param $mask array of attributes to include in the update
     * @return string
     */

    protected static function buildUpdateSet( $attrs, $mask ) {
        $map = array();
        $pks = static::$primary_keys;

        if ( empty($mask) ) {
            $mask = array_keys( $attrs );
        }

        foreach( $attrs as $key => $value ) {
            if ( !in_array( $key, $pks ) && in_array($key, $mask) ) {
                $map[] =  " $key = :$key " ;
            }
        }

        return implode(', ', $map);
    }

    /**
     * Returns a string suitable to identify the struct to perform
     * update or delete operations via PDO data binding.
     *
     * @return string
     * @param $attrs array of attributes of the struct
     */

    protected static function buildPkeyCondition( $attrs ) {
        $map = array();

        foreach( $attrs as $key => $value ) {
            if ( in_array( $key, static::$primary_keys )) {
                $map[] =  " $key = :$key " ;
            }

        }

        return implode(' AND ', $map);
    }

    /**
     * Ensures the primary keys are populated on the struct.
     *
     * @throw \Exceptions\ValidationError
     */

    protected static function ensurePrimaryKeyValues( $struct ) {
        $attrs = self::structKeys( $struct );

        foreach ( $attrs as $k => $v) {
            if ( $v == null ) {
                throw new \Exceptions\ValidationError("pkey '$k' is null");
            }
        }
    }

    /**
     * Returns an array of the specified attributes, plus the primary
     * keys specified by the current DAO.
     *
     * @return array the struct's primary keys
     */

    protected static function structKeys( $struct ) {
        $keys = static::$primary_keys  ;
        return $struct->attributes( $keys );
    }

    /**
     * Updates the struct. The record is found via the primary
     * key attributes provided by the struct.
     *
     * @param DataAccess_AbstractDaoObjectStruct $struct
     * @param array $options
     *
     * @return bool
     */
    public static function updateStruct( $struct, $options=array() ) {
        $struct->ensureValid();

        $attrs = $struct->attributes();

        $sql = " UPDATE " . static::TABLE ;
        $sql .= " SET " . self::buildUpdateSet( $attrs, $options['fields'] );
        $sql .= " WHERE " . self::buildPkeyCondition( $attrs );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $data = array_merge(
            $struct->attributes( $options['fields'] ),
            self::structKeys( $struct )
        );

        \Log::doLog("SQL", $sql);
        \Log::doLog("data", $data);

        return $stmt->execute( $data );
    }

}
