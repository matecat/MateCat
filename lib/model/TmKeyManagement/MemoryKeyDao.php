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
class DataAccess_MemoryKeyDao extends DataAccess_AbstractDao {
    const TABLE = "memory_keys";

    const STRUCT_TYPE = "TmKeyManagement_MemoryKeyStruct";

    /**
     * @param DataAccess_IDaoStruct $obj
     *
     * @return TmKeyManagement_MemoryKeyStruct|null The inserted object on success, null otherwise
     * @throws Exception
     */
    public function create( DataAccess_IDaoStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->validateNotNullFields( $obj );

        $query = "INSERT INTO " . self::TABLE .
                " (gid, uid, owner_uid, key_value, key_name, key_tm, key_glos, group_grants, creation_date)
                VALUES (%s, %d, %d, '%s', '%s', %s, %s, '%s', NOW())";

        $query = sprintf(
                $query,
                ( $obj->gid == null ) ? 0 : $obj->gid,
                (int)$obj->uid,
                (int)$obj->owner_uid,
                $obj->tm_key->key,
                ( $obj->tm_key->name == null ) ? '' : $obj->tm_key->name,
                ( $obj->tm_key->tm == null ) ? 1 : $obj->tm_key->tm,
                ( $obj->tm_key->glos == null ) ? 1 : $obj->tm_key->glos,
                ( $obj->grants == null ) ? "rw" : $obj->grants
        );

        $this->con->query( $query );

        $this->checkForErrors();

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
    public function read( DataAccess_IDaoStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $where_conditions = array();
        $query            = "SELECT gid, uid, owner_uid,
                                    key_value,
                                    key_name,
                                    key_tm AS tm,
                                    key_glos AS glos,
                                    group_grants
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

        if ( $obj->grants !== null ) {
            $condition           = "group_grants = '%s'";
            $where_conditions[ ] = sprintf( $condition, $obj->grants );
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
            $where_string = implode( " and ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );

        $arr_result = $this->con->fetch_array( $query );

        $this->checkForErrors();

        return $this->buildResult( $arr_result );
    }

    /**
     * @param DataAccess_IDaoStruct $obj
     *
     * @return bool|void
     * @throws Exception
     */
    public function update( DataAccess_IDaoStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->validatePrimaryKey( $obj );

        $set_array        = array();
        $where_conditions = array();
        $query            = "update " . self::TABLE . " set %s where %s";

        $where_conditions[ ] = "uid = " . $obj->uid;
        $where_conditions[ ] = "gid = " . $obj->gid;
        $where_conditions[ ] = "key_value = '" . $obj->tm_key->key . "'";

//        if ( $obj->owner_uid !== null ) {
//            $set_array[ ] = "owner_uid = " . $obj->owner_uid;
//        }

        if ( $obj->grants !== null ) {
            $condition    = "group_grants = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->grants );
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
        $where_string = implode( " and ", $where_conditions );

        if ( count( $set_array ) ) {
            $set_string = implode( ", ", $set_array );
        } else {
            throw new Exception( "Array given is empty. Please set at least one value." );
        }

        $query = sprintf( $query, $set_string, $where_string );

        $this->con->query( $query );

        $this->checkForErrors();

        if ( $this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    /**
     * @param $obj_arr An array of TmKeyManagement_MemoryKeyStruct objects
     *
     * @return array|null The input array on success, null otherwise
     * @throws Exception
     */
    public function createList( Array $obj_arr ) {
        $obj_arr = $this->sanitizeArray( $obj_arr );

        $query = "INSERT INTO " . self::TABLE .
                " (gid, uid, owner_uid, key_value, key_name, key_tm, key_glos, group_grants, creation_date)
                VALUES %s;";

        $tuple_template = "(%s, %d, %d, '%s', '%s', %s, %s, '%s', NOW())";

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
                        ( $obj->grants == null ) ? "rw" : $obj->grants
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

        $this->checkForErrors();

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
        $query            = "update " . self::TABLE . " set %s where %s";

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
        if( !count($where_conditions) ) {
            throw new Exception("You must set at least one field of the following: uid, gid, tm_key->key");
        }

        if ( $obj->grants !== null ) {
            $condition    = "group_grants = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->grants );
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
        $where_string = implode( " and ", $where_conditions );

        if ( count( $set_array ) ) {
            $set_string = implode( ", ", $set_array );
        } else {
            throw new Exception( "Array given is empty. Please set at least one value." );
        }

        $query = sprintf( $query, $set_string, $where_string );

        $this->con->query( $query );

        $this->checkForErrors();

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
                " (gid, uid, owner_uid, key_value, key_name, key_tm, key_glos, group_grants, creation_date)
                VALUES %s
                ON DUPLICATE KEY UPDATE
                gid = gid,
                uid = uid,
                owner_uid = owner_uid,
                key_value = key_value,
                key_name = VALUES(key_name),
                key_tm = key_tm,
                key_glos = key_glos,
                group_grants = VALUES(group_grants),
                creation_date = NOW();";

        $tuple_template = "(%s, %d, %d, '%s', '%s', %s, %s, '%s', NOW())";

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
                        ( $obj->grants == null ) ? "rw" : $obj->grants
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

        $this->checkForErrors();

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
        return parent::sanitizeInput( $input, self::STRUCT_TYPE );
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
        return parent::sanitizeArray( $input, self::STRUCT_TYPE ); // TODO: Change the autogenerated stub
    }

    /**
     * See in DataAccess_AbstractDao::validatePrimaryKey
     * @see DataAccess_AbstractDao::validatePrimaryKey
     *
     * @param TmKeyManagement_MemoryKeyStruct $obj
     *
     * @throws Exception
     */
    protected function validatePrimaryKey( DataAccess_IDaoStruct $obj ) {

        /**
         * @var $obj TmKeyManagement_MemoryKeyStruct
         */

        if ( is_null( $obj->gid ) ) {
            throw new Exception( "Gid cannot be null" );
        }

        if ( is_null( $obj->uid ) ) {
            throw new Exception( "Uid cannot be null" );
        }

        if ( is_null( $obj->tm_key->key ) ) {
            throw new Exception( "Key value cannot be null" );
        }

    }

    /**
     * See in DataAccess_AbstractDao::validateNotNullFields
     * @see DataAccess_AbstractDao::validateNotNullFields
     *
     * @param TmKeyManagement_MemoryKeyStruct $obj
     *
     * @throws Exception
     */
    protected function validateNotNullFields( DataAccess_IDaoStruct $obj ) {
        /**
         * @var $obj TmKeyManagement_MemoryKeyStruct
         */

        if ( is_null( $obj->gid ) ) {
            throw new Exception( "Gid cannot be null" );
        }

        if ( is_null( $obj->uid ) ) {
            throw new Exception( "Uid cannot be null" );
        }

        if ( is_null( $obj->tm_key->key ) ) {
            throw new Exception( "Key value cannot be null" );
        }

        if ( is_null( $obj->owner_uid ) ) {
            throw new Exception( "Owner uid cannot be null" );
        }

        if ( is_null( $obj->grants ) ) {
            throw new Exception( "Group grants cannot be null" );
        }
    }

    /**
     * Builds an array with a resultset according to the data structure it handles.
     *
     * @param $array_result array A result array obtained by a MySql query
     *
     * @return array An array containing TmKeyManagement_MemoryKeyStruct objects
     */
    protected function buildResult( $array_result ) {
        $result = array();

        foreach ( $array_result as $item ) {

            $build_arr = array(
                    'uid'       => $item[ 'uid' ],
                    'gid'       => $item[ 'gid' ],
                    'owner_uid' => $item[ 'owner_uid' ],
                    'grants'    => (string)$item[ 'group_grants' ],
                    'tm_key'    => array(
                            'key'  => (string)$item[ 'key_value' ],
                            'name' => (string)$item[ 'key_name' ],
                            'tm'   => (bool)$item[ 'tm' ],
                            'glos' => (bool)$item[ 'glos' ]
                    )
            );

            $obj = new TmKeyManagement_MemoryKeyStruct( $build_arr );

            $result[ ] = $obj;
        }

        return $result;
    }


}