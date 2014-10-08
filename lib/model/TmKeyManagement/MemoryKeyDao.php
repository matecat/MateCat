<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 29/09/14
 * Time: 18.45
 */

/**
 * Class DataAccess_MemoryKeyDao<br/>
 * This class handles the communication with the corresponding table in the database using a CRUD interface
 */
class TmKeyManagement_MemoryKeyDao extends DataAccess_AbstractDao {

    const TABLE = "memory_keys";

    const STRUCT_TYPE = "TmKeyManagement_MemoryKeyStruct";

    /**
     * @param TmKeyManagement_MemoryKeyStruct $obj
     *
     * @return TmKeyManagement_MemoryKeyStruct|null The inserted object on success, null otherwise
     * @throws Exception
     */
    public function create( TmKeyManagement_MemoryKeyStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validateNotNullFields( $obj );

        $query = "INSERT INTO " . self::TABLE .
                " (gid, uid, owner_uid, key_value, key_name, key_tm, key_glos, read_grants, write_grants, creation_date)
                VALUES (%s, %d, %d, '%s', '%s', %s, %s, %d, %d NOW())";

        $query = sprintf(
                $query,
                ( $obj->gid == null ) ? 0 : $obj->gid,
                (int)$obj->uid,
                (int)$obj->owner_uid,
                $obj->tm_key->key,
                ( $obj->tm_key->name == null ) ? '' : $obj->tm_key->name,
                ( $obj->tm_key->tm == null ) ? 1 : $obj->tm_key->tm,
                ( $obj->tm_key->glos == null ) ? 1 : $obj->tm_key->glos,
                ( $obj->r == null ) ? true : $obj->r,
                ( $obj->w == null ) ? true : $obj->w
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
     * @param TmKeyManagement_MemoryKeyStruct $obj
     *
     * @return array|void
     * @throws Exception
     */
    public function read( TmKeyManagement_MemoryKeyStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $where_conditions = array();
        $query            = "SELECT gid, uid, owner_uid,
                                    key_value,
                                    key_name,
                                    key_tm AS tm,
                                    key_glos AS glos,
                                    read_grants AS r,
                                    write_grants AS w
                             FROM " . self::TABLE . " WHERE %s";

        if ( $obj->uid !== null ) {
            $where_conditions[ ] = "uid = " . $obj->uid;
        }

        if ( $obj->gid !== null ) {
            $where_conditions[ ] = "gid = " . $obj->gid;
        }

        if ( $obj->owner_uid !== null ) {
            $where_conditions[ ] = "owner_uid = " . $obj->owner_uid;
        }

        if ( $obj->r !== null ) {
            $condition           = "read_grants = %d";
            $where_conditions[ ] = sprintf( $condition, $obj->r );
        }

        if ( $obj->w !== null ) {
            $condition           = "write_grants = %d";
            $where_conditions[ ] = sprintf( $condition, $obj->w );
        }

        //tm_key conditions
        if ( $obj->tm_key !== null ) {

            if ( $obj->tm_key->key !== null ) {
                $condition           = "key_value = '%s'";
                $where_conditions[ ] = sprintf( $condition, $obj->tm_key->key );
            }

            if ( $obj->tm_key->name !== null ) {
                $condition           = "key_name = '%s'";
                $where_conditions[ ] = sprintf( $condition, $obj->tm_key->name );
            }

            if ( $obj->tm_key->tm !== null ) {
                $condition           = "key_tm = %d";
                $where_conditions[ ] = sprintf( $condition, $obj->tm_key->tm );
            }

            if ( $obj->tm_key->glos !== null ) {
                $condition           = "key_glos = %d";
                $where_conditions[ ] = sprintf( $condition, $obj->tm_key->glos );
            }
        }

        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );

        $arr_result = $this->con->fetch_array( $query );

        $this->_checkForErrors();

        return $this->_buildResult( $arr_result );
    }

    /**
     * @param TmKeyManagement_MemoryKeyStruct $obj
     *
     * @return bool|void
     * @throws Exception
     */
    public function update( TmKeyManagement_MemoryKeyStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $set_array        = array();
        $where_conditions = array();
        $query            = "UPDATE " . self::TABLE . " SET %s WHERE %s";

        $where_conditions[ ] = "uid = " . $obj->uid;
        $where_conditions[ ] = "gid = " . $obj->gid;
        $where_conditions[ ] = "key_value = '" . $obj->tm_key->key . "'";

//        if ( $obj->owner_uid !== null ) {
//            $set_array[ ] = "owner_uid = " . $obj->owner_uid;
//        }

        if ( $obj->r !== null ) {
            $condition    = "read_grants = %d";
            $set_array[ ] = sprintf( $condition, $obj->r );
        }

        if ( $obj->w !== null ) {
            $condition    = "write_grants = %d";
            $set_array[ ] = sprintf( $condition, $obj->w );
        }

        //tm_key conditions
        if ( $obj->tm_key !== null ) {

            if ( $obj->tm_key->name !== null ) {
                $condition    = "key_name = '%s'";
                $set_array[ ] = sprintf( $condition, $obj->tm_key->name );
            }

//            if ( $obj->tm_key->tm !== null ) {
//                $condition    = "key_tm = %d";
//                $set_array[ ] = sprintf( $condition, $obj->tm_key->tm );
//            }
//
//            if ( $obj->tm_key->glos !== null ) {
//                $condition    = "key_glos = %d";
//                $set_array[ ] = sprintf( $condition, $obj->tm_key->glos );
//            }
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

    /**
     * @param $obj_arr TmKeyManagement_MemoryKeyStruct[] An array of TmKeyManagement_MemoryKeyStruct objects
     *
     * @return array|null The input array on success, null otherwise
     * @throws Exception
     */
    public function createList( Array $obj_arr ) {
        $obj_arr = $this->sanitizeArray( $obj_arr );

        $query = "INSERT INTO " . self::TABLE .
                " (gid, uid, owner_uid, key_value, key_name, key_tm, key_glos, read_grants, write_grants, creation_date)
                VALUES %s;";

        $tuple_template = "(%s, %d, %d, '%s', '%s', %s, %s, %d, %d, NOW())";

        $values = array();

        //chunk array using MAX_INSERT_NUMBER
        $objects = array_chunk( $obj_arr, self::MAX_INSERT_NUMBER );

        //begin transaction
        $this->con->begin();

        //create an insert query for each chunk
        foreach ( $objects as $i => $chunk ) {
            foreach ( $chunk as $obj ) {

                //fill values array
                $values[ ] = sprintf(
                        $tuple_template,
                        ( $obj->gid == null ) ? 0 : $obj->gid,
                        (int)$obj->uid,
                        (int)$obj->owner_uid,
                        $obj->tm_key->key,
                        ( $obj->tm_key->name == null ) ? '' : $obj->tm_key->name,
                        ( $obj->tm_key->tm == null ) ? 1 : $obj->tm_key->tm,
                        ( $obj->tm_key->glos == null ) ? 1 : $obj->tm_key->glos,
                        ( $obj->r == null ) ? true : $obj->r,
                        ( $obj->w == null ) ? true : $obj->w
                );
            }

            $insert_query = sprintf(
                    $query,
                    implode( ", ", $values )
            );

            $this->con->query( $insert_query );

            $values = array();
        }

        //commit transaction
        $this->con->commit();

        $this->_checkForErrors();

        if ( $this->con->affected_rows > 0 ) {
            return $obj_arr;
        }

        return null;
    }

    /**
     * Update
     *
     * @param $obj_arr array An array of TmKeyManagement_MemoryKeyStruct objects
     *
     * @return array|null The input array on success, null otherwise
     * @throws Exception
     */
    public function updateList( Array $obj_arr ) {
        $this->sanitizeArray( $obj_arr );

        return $this->updateRange( $obj_arr[ 0 ] );
    }

    /**
     * @param TmKeyManagement_MemoryKeyStruct $obj
     *
     * @return null|TmKeyManagement_MemoryKeyStruct
     * @throws Exception
     */
    private function updateRange( TmKeyManagement_MemoryKeyStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $set_array        = array();
        $where_conditions = array();
        $query            = "UPDATE " . self::TABLE . " SET %s WHERE %s";

        //compose where condition
        if ( $obj->uid !== null ) {
            $where_conditions[ ] = "uid = " . $obj->uid;
        }

        if ( $obj->gid !== null ) {
            $where_conditions[ ] = "gid = " . $obj->gid;
        }

        if ( $obj->tm_key->key !== null ) {
            $where_conditions[ ] = "key_value = '" . $obj->tm_key->key . "'";
        }

        //throw exception if where condition is empty
        if ( !count( $where_conditions ) ) {
            throw new Exception( "You must set at least one field of the following: uid, gid, tm_key->key" );
        }

        if ( $obj->r !== null ) {
            $condition    = "read_grants = %d";
            $set_array[ ] = sprintf( $condition, $obj->r );
        }

        if ( $obj->w !== null ) {
            $condition    = "write_grants = %d";
            $set_array[ ] = sprintf( $condition, $obj->w );
        }

        //tm_key settings
        if ( $obj->tm_key !== null ) {

            if ( $obj->tm_key->name !== null ) {
                $condition    = "key_name = '%s'";
                $set_array[ ] = sprintf( $condition, $obj->tm_key->name );
            }

//            if ( $obj->tm_key->tm !== null ) {
//                $condition    = "key_tm = %d";
//                $set_array[ ] = sprintf( $condition, $obj->tm_key->tm );
//            }
//
//            if ( $obj->tm_key->glos !== null ) {
//                $condition    = "key_glos = %d";
//                $set_array[ ] = sprintf( $condition, $obj->tm_key->glos );
//            }
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

    /**
     * @param $obj_arr array An array of TmKeyManagement_MemoryKeyStruct objects
     *
     * @return array|null The input array on success, null otherwise
     * @throws Exception
     */
    private function __updateList( Array $obj_arr ) {

        $obj_arr = $this->sanitizeArray( $obj_arr );

        $query = "INSERT INTO " . self::TABLE .
                " (gid, uid, owner_uid, key_value, key_name, key_tm, key_glos, read_grants, write_grants, creation_date)
                VALUES %s
                ON DUPLICATE KEY UPDATE
                gid = gid,
                uid = uid,
                owner_uid = owner_uid,
                key_value = key_value,
                key_name = VALUES(key_name),
                key_tm = key_tm,
                key_glos = key_glos,
                read_grants = VALUES(read_grants),
                write_grants = VALUES(write_grants),
                creation_date = NOW();";

        $tuple_template = "(%s, %d, %d, '%s', '%s', %s, %s, %d, %d, NOW())";

        $values = array();

        //chunk array using MAX_INSERT_NUMBER
        $objects = array_chunk( $obj_arr, self::MAX_INSERT_NUMBER );

        //begin transaction
        $this->con->begin();

        //create an insert query for each chunk
        foreach ( $objects as $i => $chunk ) {
            foreach ( $chunk as $obj ) {

                //fill values array
                $values[ ] = sprintf(
                        $tuple_template,
                        ( $obj->gid == null ) ? 0 : $obj->gid,
                        (int)$obj->uid,
                        (int)$obj->owner_uid,
                        $obj->tm_key->key,
                        ( $obj->tm_key->name == null ) ? '' : $obj->tm_key->name,
                        ( $obj->tm_key->tm == null ) ? 1 : $obj->tm_key->tm,
                        ( $obj->tm_key->glos == null ) ? 1 : $obj->tm_key->glos,
                        ( $obj->r == null ) ? true : $obj->r,
                        ( $obj->w == null ) ? true : $obj->w
                );
            }

            $insert_query = sprintf(
                    $query,
                    implode( ", ", $values )
            );

            $this->con->query( $insert_query );

            $values = array();
        }

        //commit transaction
        $this->con->commit();

        $this->_checkForErrors();

        if ( $this->con->affected_rows > 0 ) {
            return $obj_arr;
        }

        return null;

    }

    /**
     * See parent definition
     * @see DataAccess_AbstractDao::sanitize
     *
     * @param TmKeyManagement_MemoryKeyStruct $input
     *
     * @return TmKeyManagement_MemoryKeyStruct
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
    public static function sanitizeArray( Array $input ) {
        return parent::_sanitizeInputArray( $input, self::STRUCT_TYPE );
    }

    /**
     * See in DataAccess_AbstractDao::validatePrimaryKey
     * @see DataAccess_AbstractDao::_validatePrimaryKey
     *
     * @param TmKeyManagement_MemoryKeyStruct $obj
     *
     * @return void
     * @throws Exception
     */
    protected function _validatePrimaryKey( TmKeyManagement_MemoryKeyStruct $obj ) {

        /**
         * @var $obj TmKeyManagement_MemoryKeyStruct
         */

        if ( is_null( $obj->gid ) || !is_numeric( $obj->gid ) ) {
            throw new Exception( "Invalid Gid" );
        }

        if ( is_null( $obj->uid ) || empty( $obj->uid ) ) {
            throw new Exception( "Invalid Uid" );
        }

        if ( is_null( $obj->tm_key->key ) ) {
            throw new Exception( "Invalid Key value" );
        }

    }

    /**
     * See in DataAccess_AbstractDao::validateNotNullFields
     * @see DataAccess_AbstractDao::_validateNotNullFields
     *
     * @param TmKeyManagement_MemoryKeyStruct $obj
     *
     * @return null
     * @throws Exception
     */
    protected function _validateNotNullFields( TmKeyManagement_MemoryKeyStruct $obj ) {
        /**
         * @var $obj TmKeyManagement_MemoryKeyStruct
         */

        if ( is_null( $obj->gid ) || !is_numeric( $obj->gid ) ) {
            throw new Exception( "Gid cannot be null" );
        }

        if ( is_null( $obj->uid ) || empty( $obj->uid ) ) {
            throw new Exception( "Uid cannot be null" );
        }

        if ( is_null( $obj->tm_key->key ) ) {
            throw new Exception( "Key value cannot be null" );
        }

        if ( is_null( $obj->owner_uid ) || empty( $obj->uid ) ) {
            throw new Exception( "Owner uid cannot be null" );
        }

        if ( is_null( $obj->r ) ) {
            throw new Exception( "Group grants cannot be null" );
        }

        if ( is_null( $obj->w ) ) {
            throw new Exception( "Group grants cannot be null" );
        }

    }

    /**
     * Builds an array with a result set according to the data structure it handles.
     *
     * @param $array_result array A result array obtained by a MySql query
     *
     * @return TmKeyManagement_MemoryKeyStruct[] An array containing TmKeyManagement_MemoryKeyStruct objects
     */
    protected function _buildResult( $array_result ) {
        $result = array();

        foreach ( $array_result as $item ) {

            $build_arr = array(
                    'uid'       => $item[ 'uid' ],
                    'gid'       => $item[ 'gid' ],
                    'owner_uid' => $item[ 'owner_uid' ],
                    'r'         => (bool)$item[ 'r' ],
                    'w'         => (bool)$item[ 'w' ],
                    'tm_key'    => new TmKeyManagement_TmKeyStruct( array(
                                    'key'  => (string)$item[ 'key_value' ],
                                    'name' => (string)$item[ 'key_name' ],
                                    'tm'   => (bool)$item[ 'tm' ],
                                    'glos' => (bool)$item[ 'glos' ],
                                    'r'    => (bool)$item[ 'r' ],
                                    'w'    => (bool)$item[ 'w' ],
                            )
                    )
            );

            $obj = new TmKeyManagement_MemoryKeyStruct( $build_arr );

            $result[ ] = $obj;
        }

        return $result;
    }


}