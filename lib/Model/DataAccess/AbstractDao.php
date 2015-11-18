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
            $_existingResult = unserialize( $this->cache_con->get( md5( $query ) ) );
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
            return $this->cache_con->delete( $query );
        }

        return false;

    }

    /**
     * @param $array_result array
     *
     * @return DataAccess_IDaoStruct|DataAccess_IDaoStruct[]
     */
    protected abstract function _buildResult( $array_result );

}
