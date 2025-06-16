<?php

namespace DataAccess;

use Database;
use Exception;
use Exceptions\ValidationError;
use IDatabase;
use Log;
use PDO;
use PDOStatement;
use ReflectionException;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 29/09/14
 * Time: 17.55
 */
abstract class AbstractDao {

    use DaoCacheTrait;

    /**
     * The connection object
     * @var Database
     */
    protected IDatabase $database;

    /**
     * @var string This property will be overridden in the subclasses.
     */
    const STRUCT_TYPE = '';

    /**
     * @var int The maximum number of tuples that can be inserted for a single query
     */
    const MAX_INSERT_NUMBER = 1;

    /**
     * @var array
     */
    protected static array $primary_keys;

    /**
     * @var array
     */
    protected static array $auto_increment_field = [];

    /**
     * @var string
     */
    const TABLE = null;

    public function __construct( IDatabase $con = null ) {
        /**
         * @var $con IDatabase
         */

        if ( $con == null ) {
            $con = Database::obtain();
        }

        $this->database             = $con;
        self::$auto_increment_field = [];
    }

    /**
     * @return Database|IDatabase
     */
    public function getDatabaseHandler() {
        return $this->database;
    }

    /**
     * @throws Exception
     */
    public function createList( array $obj_arr ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    /**
     * @throws Exception
     */
    public function updateList( array $obj_arr ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    /**
     * @throws Exception
     */
    public function deleteList( array $obj_arr ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    /**
     * @param $input IDaoStruct The input object
     *
     * @return IDaoStruct The input object, sanitized.
     * @throws Exception This function throws exception input is not a \DataAccess\IDaoStruct object
     */
    public function sanitize( IDaoStruct $input ) {
        throw new Exception( "Abstract method " . __METHOD__ . " must be overridden " );
    }

    /**
     * @param $input array An array of \DataAccess\IDaoStruct objects
     *
     * @return array The input array, sanitized.
     * @throws Exception This function throws exception if input is not:<br/>
     *                  <ul>
     *                      <li>An array of $type objects</li>
     *                      or
     *                      <li>A \DataAccess\IDaoStruct object</li>
     *                  </ul>
     */
    public static function sanitizeArray( array $input ): array {
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
    protected static function _sanitizeInputArray( array $input, string $type ): array {

        foreach ( $input as $i => $elem ) {
            $input[ $i ] = self::_sanitizeInput( $elem, $type );
        }

        return $input;
    }

    /**
     * @param IDaoStruct $input The input to be sanitized
     * @param string     $type  The expected type
     *
     * @return IDaoStruct The input object if sanitize was successful, otherwise this function throws exception.
     * @throws Exception This function throws exception input is not an object of type $type
     */
    protected static function _sanitizeInput( IDaoStruct $input, string $type ): IDaoStruct {

        //if something different from $type is passed, throw exception
        if ( !( $input instanceof $type ) ) {
            throw new Exception( "Invalid input. Expected " . $type, -1 );
        }

        return $input;
    }


    /**
     * This method validates the primary key of a single object to be used in persistency.
     *
     * @param $obj IDaoStruct The input object
     *
     * @return void
     */
    protected function _validatePrimaryKey( IDaoStruct $obj ): void {
        //to be overridden in subclasses
    }

    /**
     * This method validates the fields of a single object that have to be not null.
     *
     * @param $obj IDaoStruct The input object
     *
     * @return void
     */
    protected function _validateNotNullFields( IDaoStruct $obj ): void {
        //to be overridden in subclasses
    }

    /**
     * Get a statement object by query string
     *
     * @param $query
     *
     * @return PDOStatement
     */
    protected function _getStatementForQuery( $query ): PDOStatement {

        $conn = Database::obtain()->getConnection();

        return $conn->prepare( $query );
    }

    /**
     * @param PDOStatement $stmt
     * @param IDaoStruct   $fetchClass
     * @param array        $bindParams
     *
     * @return IDaoStruct[]
     * @throws ReflectionException
     * @deprecated We should use the new cache system `AbstractDao::_fetchObjectMap`
     *
     */
    protected function _fetchObject( PDOStatement $stmt, IDaoStruct $fetchClass, array $bindParams ): array {

        $trace = debug_backtrace( !DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 2 );

        $keyMap = $trace[ 1 ][ 'class' ] . "::" . $trace[ 1 ][ 'function' ] . "-" . implode( ":", $bindParams );

        return $this->_fetchObjectMap( $stmt, get_class( $fetchClass ), $bindParams, $keyMap );
    }

    /**
     * @throws ReflectionException
     */
    protected function _destroyObjectCache( PDOStatement $stmt, string $fetchClass, array $bindParams ): bool {
        return $this->_deleteCacheByKey( md5( $stmt->queryString . $this->_serializeForCacheKey( $bindParams ) . $fetchClass ) );
    }

    /**
     * * This method facilitates grouping cached queries into a hashset, making it easier to locate and delete the entire group in Redis.
     *
     *  Replacement for deprecated `AbstractDao::_fetchObject`
     *
     * @param PDOStatement $stmt
     * @param string       $fetchClass
     * @param array        $bindParams
     *
     * @param string|null  $keyMap
     *
     * @return IDaoStruct[]
     * @throws ReflectionException
     */
    protected function _fetchObjectMap( PDOStatement $stmt, string $fetchClass, array $bindParams, string $keyMap = null ): array {

        if ( empty( $keyMap ) ) {
            $trace  = debug_backtrace( !DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
            $keyMap = $trace[ 1 ][ 'class' ] . "::" . $trace[ 1 ][ 'function' ] . "-" . implode( ":", $bindParams );
        }

        $_cacheResult = $this->_getFromCacheMap( $keyMap, $stmt->queryString . $this->_serializeForCacheKey( $bindParams ) . $fetchClass );

        if ( !empty( $_cacheResult ) ) {
            return $_cacheResult;
        }

        $stmt->setFetchMode( PDO::FETCH_CLASS, $fetchClass );
        $stmt->execute( $bindParams );
        $result = $stmt->fetchAll();

        $this->_setInCacheMap( $keyMap, $stmt->queryString . $this->_serializeForCacheKey( $bindParams ) . $fetchClass, $result );

        return $result;

    }

    /**
     * @param $array_result array
     *
     * @deprecated Use instead PDO::setFetchMode()
     */
    protected function _buildResult( array $array_result ) {
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
    public static function buildInsertStatement( array $attrs, array &$mask = [], bool $ignore = false, bool $no_nulls = false, array $on_duplicate_fields = [] ): string {
        return Database::buildInsertStatement( static::TABLE, $attrs, $mask, $ignore, $no_nulls, $on_duplicate_fields );
    }


    /**
     * Returns a string suitable for updates of the fields
     * provided by the attributes array.
     *
     * @param            $attrs array of full attributes to update
     * @param array|null $mask  array of attributes to include in the update
     *
     * @return string
     */

    protected static function buildUpdateSet( array $attrs, ?array $mask = [] ): string {
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

    protected static function buildPkeyCondition( array $attrs ): string {
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
     * @param AbstractDaoObjectStruct $struct
     *
     * @throws ValidationError
     */

    protected static function ensurePrimaryKeyValues( AbstractDaoObjectStruct $struct ) {
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
     * @param AbstractDaoObjectStruct $struct
     *
     * @return array the struct's primary keys
     */

    protected static function structKeys( AbstractDaoObjectStruct $struct ): array {
        $keys = static::$primary_keys;

        return $struct->toArray( $keys );
    }

    public static function updateFields( array $data = [], array $where = [] ): int {
        return Database::obtain()->update( static::TABLE, $data, $where );
    }

    /**
     * Updates the struct. The record is found via the primary
     * key attributes provided by the struct.
     *
     * @param AbstractDaoObjectStruct|IDaoStruct $struct
     * @param array                              $options
     *
     * @return int
     * @throws Exception
     */
    public static function updateStruct( IDaoStruct $struct, array $options = [] ): int {

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
     * Inserts a struct into the database.
     *
     * If an `auto_increment_field` is defined for the table, the last inserted is returned.
     * Otherwise, it returns TRUE on success.
     *
     * Returns FALSE on failure.
     *
     * @param IDaoStruct $struct
     * @param array|null $options
     *
     * @return bool|string
     * @throws Exception
     */
    public static function insertStruct( IDaoStruct $struct, ?array $options = [] ) {

        $ignore              = isset( $options[ 'ignore' ] ) && $options[ 'ignore' ] == true;
        $no_nulls            = isset( $options[ 'no_nulls' ] ) && $options[ 'no_nulls' ] == true;
        $on_duplicate_fields = ( !empty( $options[ 'on_duplicate_update' ] ) ? $options[ 'on_duplicate_update' ] : [] );

        // TODO: allow the mask to be passed as option.
        $mask = array_keys( $struct->toArray() );
        $mask = array_diff( $mask, static::$auto_increment_field );

        $sql = self::buildInsertStatement( $struct->toArray(), $mask, $ignore, $no_nulls, $on_duplicate_fields );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $data = $struct->toArray( $mask );

        Log::doJsonLog( [ "SQL" => $sql, "values" => $data ] );

        $stmt->execute( $data );

        if ( count( static::$auto_increment_field ) ) {
            return $conn->lastInsertId();
        } else {
            return $stmt->rowCount();
        }

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
