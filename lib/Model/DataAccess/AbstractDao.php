<?php

use Exceptions\ValidationError;

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
    protected $database;

    /**
     * The cache connection object
     * @var Predis\Client
     */
    protected static $cache_con;

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

    /**
     * @var array
     */
    protected static $primary_keys = [];

    /**
     * @var array
     */
    protected static $auto_increment_field = [];

    /**
     * @var string
     */
    const TABLE = null;

    public function __construct( $con = null ) {
        /**
         * @var $con Database
         */

        if ( $con == null ) {
            $con = Database::obtain();
        }

        $this->database = $con;
    }

    /**
     * @return \Database|IDatabase
     */
    public function getDatabaseHandler() {
        return $this->database;
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
    public function sanitize( DataAccess_IDaoStruct $input ) {
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
    public static function sanitizeArray( array $input ) {
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
    protected function _validateNotNullFields( DataAccess_IDaoStruct $obj ) {
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
     * @param $query string A query
     *
     * @return mixed
     */
    protected function _getFromCache( $query ) {
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

        return $value;
    }

    protected function _logCache( $type, $key, $value, $sqlQuery ) {
        Log::doJsonLog( [
                "type" => $type,
                "key"  => $key,
                "sql"  => preg_replace( "/[ ]+/", " ", str_replace( "\n", " ", $sqlQuery ) ),
            //"result_set" => $value,
        ], "query_cache.log" );
    }

    /**
     * @param $query string
     * @param $value array
     *
     * @return void|null
     */
    protected function _setInCache( $query, $value ) {
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
     * @param int $cacheSecondsTTL
     *
     * @return $this
     */
    public function setCacheTTL( $cacheSecondsTTL ) {
        if ( !INIT::$SKIP_SQL_CACHE ) {
            $this->cacheTTL = $cacheSecondsTTL;
        }

        return $this;
    }

    /**
     * @param $query
     *
     * @return bool
     */
    protected function _destroyCache( $query ) {
        $this->_cacheSetConnection();
        if ( isset( self::$cache_con ) && !empty( self::$cache_con ) ) {
            return self::$cache_con->del( md5( $query ) );
        }

        return false;

    }

    /**
     * Serialize params, ensuring values are always treated as strings.
     *
     * @param array $params
     *
     * @return string
     */
    protected function _serializeForCacheKey( Array $params ) {
        foreach ( $params as $key => $value ) {
            $params[ $key ] = (string)$value;
        }

        return serialize( $params );
    }

    /**
     * @param PDOStatement          $stmt
     * @param DataAccess_IDaoStruct $fetchClass
     * @param array                 $bindParams
     *
     * @return DataAccess_IDaoStruct[]
     */
    protected function _fetchObject( PDOStatement $stmt, DataAccess_IDaoStruct $fetchClass, Array $bindParams ) {

        $_cacheResult = $this->_getFromCache( $stmt->queryString . $this->_serializeForCacheKey( $bindParams ) );

        if ( $_cacheResult !== false && $_cacheResult !== null ) {
            return $_cacheResult;
        }

        $stmt->setFetchMode( PDO::FETCH_CLASS, get_class( $fetchClass ) );
        $stmt->execute( $bindParams );
        $result = $stmt->fetchAll();

        $this->_setInCache( $stmt->queryString . $this->_serializeForCacheKey( $bindParams ), $result );

        return $result;

    }

    /**
     * @param PDOStatement $stmt
     * @param array        $bindParams
     *
     * @return bool|int
     */
    protected function _destroyObjectCache( PDOStatement $stmt, Array $bindParams ) {
        $this->_cacheSetConnection();
        if ( isset( self::$cache_con ) && !empty( self::$cache_con ) ) {
            return self::$cache_con->del( md5( $stmt->queryString . $this->_serializeForCacheKey( $bindParams ) ) );
        }

        return false;

    }

    /**
     * @param $array_result array
     *
     * @return DataAccess_IDaoStruct|DataAccess_IDaoStruct[]
     * @deprecated Use instead PDO::setFetchMode()
     */
    protected function _buildResult( $array_result ) {
    }

    /**
     * Returns a string suitable for insert of the fields
     * provided by the attributes array.
     *
     * @param       $attrs    array of full attributes to update
     * @param       $mask     array of attributes to include in the update
     * @param bool  $ignore   Use INSERT IGNORE query type
     * @param bool  $no_nulls Exclude NULL fields when build the sql
     *
     * @param array $on_duplicate_fields
     *
     * @return string
     * @throws Exception
     * @internal param array $options of options for the SQL statement
     */
    public static function buildInsertStatement( array $attrs, array &$mask = [], $ignore = false, $no_nulls = false, array $on_duplicate_fields = [] ) {
        return Database::buildInsertStatement( static::TABLE, $attrs, $mask, $ignore, $no_nulls, $on_duplicate_fields );
    }


    /**
     * Returns a string suitable for updates of the fields
     * provided by the attributes array.
     *
     * @param $attrs array of full attributes to update
     * @param $mask  array of attributes to include in the update
     *
     * @return string
     */

    protected static function buildUpdateSet( $attrs, $mask ) {
        $map = [];
        $pks = static::$primary_keys;

        if ( empty( $mask ) ) {
            $mask = array_keys( $attrs );
        }

        foreach ( $attrs as $key => $value ) {
            if ( !in_array( $key, $pks ) && in_array( $key, $mask ) ) {
                $map[] = " $key = :$key ";
            }
        }

        return implode( ', ', $map );
    }

    /**
     * Returns a string suitable to identify the struct to perform
     * update or delete operations via PDO data binding.
     *
     * WARNING: only AND conditions are supported
     *
     * @param $attrs array of attributes of the struct
     *
     * @return string
     *
     */

    protected static function buildPkeyCondition( $attrs ) {
        $map = [];

        foreach ( $attrs as $key => $value ) {
            if ( in_array( $key, static::$primary_keys ) ) {
                $map[] = " $key = :$key ";
            }
        }

        return implode( ' AND ', $map );
    }

    /**
     * Ensures the primary keys are populated on the struct.
     *
     * @throw \Exceptions\ValidationError
     * @param DataAccess_AbstractDaoObjectStruct $struct
     *
     * @throws ValidationError
     */

    protected static function ensurePrimaryKeyValues( DataAccess_AbstractDaoObjectStruct $struct ) {
        $attrs = self::structKeys( $struct );

        foreach ( $attrs as $k => $v ) {
            if ( $v == null ) {
                throw new ValidationError( "pkey '$k' is null" );
            }
        }
    }

    /**
     * Returns an array of the specified attributes, plus the primary
     * keys specified by the current DAO.
     *
     * @param DataAccess_AbstractDaoObjectStruct $struct
     *
     * @return array the struct's primary keys
     * @throws ReflectionException
     */

    protected static function structKeys( DataAccess_AbstractDaoObjectStruct $struct ) {
        $keys = static::$primary_keys;

        return $struct->toArray( $keys );
    }

    public static function updateFields( array $data = [], array $where = [] ) {
        return Database::obtain()->update( static::TABLE, $data, $where );
    }

    /**
     * Updates the struct. The record is found via the primary
     * key attributes provided by the struct.
     *
     * @param DataAccess_AbstractDaoObjectStruct|DataAccess_IDaoStruct $struct
     * @param array                                                    $options
     *
     * @return int
     * @throws Exception
     */
    public static function updateStruct( DataAccess_IDaoStruct $struct, $options = [] ) {

        $attrs = $struct->toArray();

        $fields = [];

        if ( isset( $options[ 'fields' ] ) ) {
            if ( !is_array( $options[ 'fields' ] ) ) {
                throw new Exception( '`fields` must be an array' );
            }
            $fields = $options[ 'fields' ];
        }

        $sql = " UPDATE " . static::TABLE;
        $sql .= " SET " . static::buildUpdateSet( $attrs, $fields );
        $sql .= " WHERE " . static::buildPkeyCondition( $attrs );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $data = array_merge(
                $struct->toArray( $fields ),
                self::structKeys( $struct )
        );

        Log::doJsonLog( [
                'table'  => static::TABLE,
                'sql'    => $sql,
                'attr'   => $attrs,
                'fields' => $fields,
                'struct' => $struct->toArray( $fields ),
                'data'   => $data
        ] );

        $stmt->execute( $data );

        // WARNING
        // When updating a Mysql table with identical values, nothing's really affected so rowCount will return 0.
        // If you need this value use this:
        // https://www.php.net/manual/en/pdostatement.rowcount.php#example-1096
        return $stmt->rowCount();
    }

    /**
     * Inserts a struct into database.
     *
     * If an `auto_increment_field` is defined for the table, the last inserted is returned.
     * Otherwise it returns TRUE on success.
     *
     * Returns FALSE on failure.
     *
     * @param DataAccess_IDaoStruct $struct
     * @param array                 $options
     *
     * @return bool|string
     * @throws Exception
     */
    public static function insertStruct( DataAccess_IDaoStruct $struct, $options = [] ) {

        $ignore              = isset( $options[ 'ignore' ] ) && $options[ 'ignore' ] == true;
        $no_nulls            = isset( $options[ 'no_nulls' ] ) && $options[ 'no_nulls' ] == true;
        $on_duplicate_fields = ( isset( $options[ 'on_duplicate_update' ] ) && !empty( $options[ 'on_duplicate_update' ] ) ? $options[ 'on_duplicate_update' ] : [] );

        // TODO: allow the mask to be passed as option.
        $mask = array_keys( $struct->toArray() );
        $mask = array_diff( $mask, static::$auto_increment_field );

        $sql = self::buildInsertStatement( $struct->toArray(), $mask, $ignore, $no_nulls, $on_duplicate_fields );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $data = $struct->toArray( $mask );

        Log::doJsonLog( [ "SQL" => $sql, "values" => $data ] );

        if ( $stmt->execute( $data ) ) {
            if ( count( static::$auto_increment_field ) ) {
                return $conn->lastInsertId();
            } else {
                return $stmt->rowCount();
            }
        } else {

            if ( $options[ 'raise' ] ) {
                throw new Exception( $stmt->errorInfo() );
            }

            return false;
        }
    }

    /**
     * Normally, insertStruct strips any field_defined as auto_increment because it relies on MySQL
     * AUTO_INCREMENT. This method allows for auto_increment fields (e.g. `id` field) to be treated as
     * any other field in the struct.
     *
     * Use this method when you want to pass the id field, for instance when it comes from a generated sequence.
     *
     * @param       $struct
     * @param array $options
     *
     * @return bool|string
     * @throws Exception
     */
    public static function insertStructWithAutoIncrements( $struct, $options = [] ) {
        $auto_increment_fields        = static::$auto_increment_field;
        static::$auto_increment_field = [];
        $id                           = self::insertStruct( $struct, $options );
        static::$auto_increment_field = $auto_increment_fields;

        return $id;
    }

    /**
     *  Use this function whenever you want to make an empty result
     * returned as null instead of PDO's default FALSE.
     *
     * @return mixed|null
     *
     */
    public static function resultOrNull( $result ) {
        if ( $result ) {
            return $result;
        } else {
            return null;
        }
    }

}
