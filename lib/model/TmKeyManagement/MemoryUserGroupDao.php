<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/10/14
 * Time: 15.25
 * 
 */

class TmKeyManagement_MemoryUserGroupDao extends DataAccess_AbstractDao {

    const TABLE = "user_groups";

    const STRUCT_TYPE = "TmKeyManagement_MemoryGroupStruct";

    /**
     * Builds an array with a result set according to the data structure it handles.
     *
     * @param $array_result array A result array obtained by a MySql query
     *
     * @return TmKeyManagement_MemoryGroupStruct[] An array containing TmKeyManagement_MemoryGroupStruct objects
     */
    protected function _buildResult( $array_result ) {
        $result = array();

        foreach( $array_result as $item ){

            $build_arr = array(
                    'gid'        => $item[ 'gid' ],
                    'uid'        => $item[ 'uid' ],
                    'group_name' => $item[ 'group_name' ]
            );

            $build_array = new TmKeyManagement_MemoryGroupStruct( $build_arr );

            $result[ ] = $build_array;
        }

        return $result;

    }

    /**
     * Create a unique 53bit Int even properly handled by Javascript if needed
     *
     *
     * @return int
     */
    public static function createNewGID(){

        $myEpoch = 1401573600; //strtotime('1 Jun 2014');
        $myNow   = microtime( true ) - $myEpoch;
        $myNowMS = (int)( $myNow * 1000 );
        $Shifted = $myNowMS << 12;

        usleep(1000); //ensure real uniqueness without a sequence for a single instance, but simultaneous/concurrent requests in the same 1000 micro-seconds generate the same UniqueID
        $UniqueNum = $Shifted + mt_rand( 0, 4095 ); //TODO REMOVE rand, add a sequence for single micro-second ?! ( JAVA )

        return $UniqueNum;

    }

    /**
     * @param TmKeyManagement_MemoryGroupStruct $obj
     *
     * @return TmKeyManagement_MemoryGroupStruct|void
     * @throws Exception
     */
    public function create( TmKeyManagement_MemoryGroupStruct $obj ) {

        $obj = $this->sanitize( $obj );

        $this->_validateNotNullFields( $obj );

        $query = "INSERT INTO " . self::TABLE . " ( gid, uid, group_name ) VALUES ( %u, %u, '%s')";

        $query = sprintf(
                $query,
                $obj->gid,
                $obj->uid,
                $obj->group_name
        );

        $this->con->query( $query );

        $this->_checkForErrors();

        //return the inserted object on success, null otherwise
        if ( $this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;

    }

    /**
     * @param TmKeyManagement_MemoryGroupStruct $obj
     *
     * @return TmKeyManagement_MemoryGroupStruct[]|void
     * @throws Exception
     */
    public function read( TmKeyManagement_MemoryGroupStruct $obj ) {

        $obj = $this->sanitize( $obj );

        $where_conditions = array();
        $query            = "SELECT gid, uid, group_name
                             FROM " . self::TABLE . " WHERE %s";

        if ( $obj->uid !== null ) {
            $where_conditions[ ] = "uid = " . $obj->uid;
        }

        if ( $obj->gid !== null ) {
            $where_conditions[ ] = "gid = " . $obj->gid;
        }

        if ( $obj->group_name !== null ) {
            $where_conditions[ ] = "group_name = " . $obj->group_name;
        }

        if ( count( $where_conditions ) ) {
            $where_string = implode( " and ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );

        $arr_result = $this->con->fetch_array( $query );

        $this->_checkForErrors();

        return $this->_buildResult( $arr_result );

    }

    /**
     * @param TmKeyManagement_MemoryGroupStruct $obj
     *
     * @return TmKeyManagement_MemoryGroupStruct|void
     * @throws Exception
     */
    public function update( TmKeyManagement_MemoryGroupStruct $obj ) {

        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $set_array        = array();
        $where_conditions = array();
        $query            = "UPDATE " . self::TABLE . " SET %s WHERE %s";

        $where_conditions[ ] = "uid = " . $obj->uid;
        $where_conditions[ ] = "gid = " . $obj->gid;

        //tm_key conditions
        if ( $obj->group_name !== null ) {
            $keyName    = "group_name = '%s'";
            $set_array[ ] = sprintf( $keyName, $obj->group_name );
        }

        $set_string   = null;
        $where_string = implode( " AND ", $where_conditions );

        if ( count( $set_array ) ) {
            $set_string = implode( ", ", $set_array );
        } else {
            throw new Exception( "Array given is empty. Please set at least one value." );
        }

        $query = sprintf( $query, $set_string, $where_string );

        $this->con->query( $query );

        $this->_checkForErrors();

        if ( $this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    public function delete( DataAccess_IDaoStruct $obj ) {
        parent::delete( $obj ); // TODO: Change the autogenerated stub
    }

    public function createList( Array $obj_arr ) {
        return true;
    }

    public function updateList( Array $obj_arr ) {
        return true;
    }

    public function deleteList( Array $obj_arr ) {
        return true;
    }

    /**
     * See parent definition
     * @see DataAccess_AbstractDao::sanitize
     *
     * @param TmKeyManagement_MemoryGroupStruct $input
     *
     * @return TmKeyManagement_MemoryGroupStruct
     * @throws Exception
     */
    public static function sanitize( $input ) {
        return parent::_sanitizeInput( $input, self::STRUCT_TYPE );
    }

    /**
     * See parent definition.
     * @see DataAccess_AbstractDao::sanitizeArray
     *
     * @param array $input
     *
     * @return array
     */
    public static function sanitizeArray( $input ) {
        return parent::_sanitizeInputArray( $input, self::STRUCT_TYPE );
    }

    /**
     * See in DataAccess_AbstractDao::validatePrimaryKey
     * @see DataAccess_AbstractDao::_validatePrimaryKey
     *
     * @param TmKeyManagement_MemoryGroupStruct $obj
     *
     * @return void
     * @throws Exception
     */
    protected function _validatePrimaryKey( TmKeyManagement_MemoryGroupStruct $obj ) {

        /**
         * @var $obj TmKeyManagement_MemoryGroupStruct
         */

        if ( is_null( $obj->gid ) || !is_numeric( $obj->gid ) ) {
            throw new Exception( "Invalid Gid" );
        }

        if ( is_null( $obj->uid ) || empty( $obj->uid ) ) {
            throw new Exception( "Invalid Uid" );
        }

    }

    /**
     * See in DataAccess_AbstractDao::validateNotNullFields
     * @see DataAccess_AbstractDao::_validateNotNullFields
     *
     * @param TmKeyManagement_MemoryGroupStruct $obj
     *
     * @return null
     * @throws Exception
     */
    protected function _validateNotNullFields( TmKeyManagement_MemoryGroupStruct $obj ) {
        /**
         * @var $obj TmKeyManagement_MemoryKeyStruct
         */

        if ( is_null( $obj->gid ) || !is_numeric( $obj->gid ) ) {
            throw new Exception( "Invalid Gid" );
        }

        if ( is_null( $obj->uid ) || empty( $obj->uid ) ) {
            throw new Exception( "Uid cannot be null" );
        }

    }

} 