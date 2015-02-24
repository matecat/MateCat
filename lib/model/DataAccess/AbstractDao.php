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
     * @var MemcacheHandler
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

    public function __construct( $con ) {
        /**
         * @var $con Database
         */
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
     * Check for MySql errors and eventually throw exception
     * @throws Exception
     */
    protected function _checkForErrors() {
        $err = $this->con->get_error();

        if ( $err[ 'error_code' ] != 0 ) {
            throw new Exception( __METHOD__ . " -> " . $err[ 'error_code' ] . ": " . $err[ 'error_description' ] );
        }
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
     * @param $query string A query
     *
     * @return mixed
     */
    protected function getFromCache($query){
        if($this->cacheTTL == 0 ) return null;

        $_existingResult = null;
        if ( !isset( $this->cache_con ) || empty( $this->cache_con ) ) {
            try {
                $this->cache_con = MemcacheHandler::getInstance();
                $_existingResult = $this->cache_con->get( $query );
            } catch ( Exception $e ) {
                Log::doLog( $e->getMessage() );
                Log::doLog( "No Memcache server(s) configured." );
            }
        }
        return $_existingResult;
    }

    /**
     * @param $query string
     * @param $value DataAccess_IDaoStruct
     *
     * @return void|null
     */
    protected function setInCache($query, $value){
        if($this->cacheTTL == 0 ) return null;

        if ( isset( $this->cache_con ) && !empty( $this->cache_con ) ) {
            $this->cache_con->set( $query, $value, $this->cacheTTL );
        }
    }

    /**
     * @param int $cacheTTL
     *
     * @return $this
     */
    public function setCacheTTL( $cacheTTL ) {
        $this->cacheTTL = $cacheTTL;
        return $this;
    }

    /**
     * @param $query string A query
     *
     * @return array|mixed
     */
    protected function fetch_array($query){
        $_cacheResult = $this->getFromCache($query);

        if($_cacheResult !== null)
            return $_cacheResult;

        $result = $this->con->fetch_array($query);

        $this->setInCache($query, $result);

        return $result;
    }

    protected abstract function _buildResult( $array_result );

}