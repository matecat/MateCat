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
                " (uid, key_value, key_name, key_tm, key_glos, creation_date)
                VALUES ( %d, '%s', '%s', '%s', %s, NOW())";

        $query = sprintf(
                $query,
                (int)$obj->uid,
                $this->con->escape( $obj->tm_key->key ),
                ( $obj->tm_key->name == null ) ? '' : $this->con->escape( $obj->tm_key->name ),
                ( $obj->tm_key->tm == null ) ? 1 : $this->con->escape( $obj->tm_key->tm ),
                ( $obj->tm_key->glos == null ) ? 1 : $this->con->escape( $obj->tm_key->glos )
        );

        $this->con->query( $query );

        //return the inserted object on success, null otherwise
        if ($this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    /**
     * @param TmKeyManagement_MemoryKeyStruct $obj
     * @param bool                            $traverse
     *
     * @return array|void
     * @throws Exception
     */
    public function read( TmKeyManagement_MemoryKeyStruct $obj, $traverse = false ) {
        $obj = $this->sanitize( $obj );

        $where_conditions = array();
        $query            = "SELECT  m1.uid, 
                                     m1.key_value, 
                                     m1.key_name, 
                                     m1.key_tm AS tm, 
                                     m1.key_glos AS glos, 
                                     sum(1) AS owners_tot, 
                                     group_concat( DISTINCT m2.uid ) AS owner_uids
                             FROM " . self::TABLE . " m1
                             LEFT JOIN " . self::TABLE . " AS m2 ON m1.key_value = m2.key_value AND m2.deleted = 0
                             WHERE %s and m1.deleted = 0
                             GROUP BY m1.key_value
			                 ORDER BY m1.creation_date desc";

        if ( $obj->uid !== null ) {
            $where_conditions[ ] = "m1.uid = " . $obj->uid;
        }

        //tm_key conditions
        if ( $obj->tm_key !== null ) {

            if ( $obj->tm_key->key !== null ) {
                $condition           = "m1.key_value = '%s'";
                $where_conditions[ ] = sprintf( $condition, $this->con->escape( $obj->tm_key->key ) );
            }

            if ( $obj->tm_key->name !== null ) {
                $condition           = "m1.key_name = '%s'";
                $where_conditions[ ] = sprintf( $condition, $this->con->escape( $obj->tm_key->name ) );
            }

            if ( $obj->tm_key->tm !== null ) {
                $condition           = "m1.key_tm = %d";
                $where_conditions[ ] = sprintf( $condition, $this->con->escape( $obj->tm_key->tm ) );
            }

            if ( $obj->tm_key->glos !== null ) {
                $condition           = "m1.key_glos = %d";
                $where_conditions[ ] = sprintf( $condition, $this->con->escape( $obj->tm_key->glos ) );
            }
        }

        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );

        $arr_result = $this->con->fetch_array( $query );

        if( $traverse ){

            $userDao = new Users_UserDao( Database::obtain() );

            foreach( $arr_result as $k => $row ){
                $users = $userDao->getByUids( explode( ",", $row[ 'owner_uids' ] ) );
                $arr_result[ $k ][ 'in_users' ] = $users;
            }

        } else {

            foreach( $arr_result as $k => $row ){
                $arr_result[ $k ][ 'in_users' ] = $row[ 'owner_uids' ];
            }

        }

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
        $where_conditions[ ] = "key_value = '" . $this->con->escape( $obj->tm_key->key ) . "'";

        //tm_key conditions
        if ( $obj->tm_key !== null ) {

            if ( $obj->tm_key->name !== null ) {
                $condition    = "key_name = '%s'";
                $set_array[ ] = sprintf( $condition, $this->con->escape( $obj->tm_key->name ) );
            }

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

        if ($this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    public function delete( TmKeyManagement_MemoryKeyStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $query = "DELETE FROM " . self::TABLE . " WHERE uid = %d and key_value = '%s'";

        $query = sprintf(
                $query,
                $obj->uid,
                $obj->tm_key->key
                );

        $this->con->query( $query );

        if ($this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    public function disable( TmKeyManagement_MemoryKeyStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $query = "UPDATE " . self::TABLE . " set deleted = 1 WHERE uid = %d and key_value = '%s'";

        $query = sprintf(
                $query,
                $obj->uid,
                $obj->tm_key->key
        );

        $this->con->query( $query );

        if ( $this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    public function enable( TmKeyManagement_MemoryKeyStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $query = "UPDATE " . self::TABLE . " set deleted = 0 WHERE uid = %d and key_value = '%s'";

        $query = sprintf(
                $query,
                $obj->uid,
                $obj->tm_key->key
        );

        $this->con->query( $query );

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
                " ( uid, key_value, key_name, key_tm, key_glos, creation_date)
                VALUES %s;";

        $tuple_template = "(%d, '%s', '%s', %d, %d, NOW())";

        $values = array();

        //chunk array using MAX_INSERT_NUMBER
        $objects = array_chunk( $obj_arr, self::MAX_INSERT_NUMBER );

        //create an insert query for each chunk
        foreach ( $objects as $i => $chunk ) {
            foreach ( $chunk as $obj ) {

                //fill values array
                $values[ ] = sprintf(
                        $tuple_template,
                        (int)$obj->uid,
                        $this->con->escape( $obj->tm_key->key ),
                        ( $obj->tm_key->name == null ) ? '' : $this->con->escape( $obj->tm_key->name ),
                        ( $obj->tm_key->tm == null ) ? 1 : $this->con->escape( $obj->tm_key->tm ),
                        ( $obj->tm_key->glos == null ) ? 1 : $this->con->escape( $obj->tm_key->glos )
                );
            }

            $insert_query = sprintf(
                    $query,
                    implode( ", ", $values )
            );

            $this->con->query( $insert_query );

            $values = array();
        }

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
            $where_conditions[ ] = "uid = " . (int)$obj->uid;
        }

        if ( $obj->tm_key->key !== null ) {
            $where_conditions[ ] = "key_value = '" . $this->con->escape( $obj->tm_key->key ) . "'";
        }

        //throw exception if where condition is empty
        if ( !count( $where_conditions ) ) {
            throw new Exception( "You must set at least one field of the following: uid, gid, tm_key->key" );
        }

        //tm_key settings
        if ( $obj->tm_key !== null ) {

            if ( $obj->tm_key->name !== null ) {
                $condition    = "key_name = '%s'";
                $set_array[ ] = sprintf( $condition, $this->con->escape( $obj->tm_key->name ) );
            }

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
                " (uid, key_value, key_name, key_tm, key_glos, read_grants, write_grants, creation_date)
                VALUES %s
                ON DUPLICATE KEY UPDATE
                uid = uid,
                key_value = key_value,
                key_name = VALUES(key_name),
                key_tm = key_tm,
                key_glos = key_glos,
                read_grants = VALUES(read_grants),
                write_grants = VALUES(write_grants),
                creation_date = NOW();";

        $tuple_template = "(%d, '%s', '%s', %s, %s, %d, %d, NOW())";

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
                        (int)$obj->uid,
                        $this->con->escape( $obj->tm_key->key ),
                        ( $obj->tm_key->name == null ) ? '' : $this->con->escape( $obj->tm_key->name ),
                        ( $obj->tm_key->tm == null ) ? 1 : $this->con->escape( $obj->tm_key->tm ),
                        ( $obj->tm_key->glos == null ) ? 1 : $this->con->escape( $obj->tm_key->glos ),
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
    public function sanitize( $input ) {
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
        if ( is_null( $obj->uid ) || empty( $obj->uid ) ) {
            throw new Exception( "Invalid Uid" );
        }

        if ( is_null( $obj->tm_key ) || is_null( $obj->tm_key->key ) ) {
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
        if ( is_null( $obj->uid ) || empty( $obj->uid ) ) {
            throw new Exception( "Uid cannot be null" );
        }

        if ( is_null( $obj->tm_key->key ) ) {
            throw new Exception( "Key value cannot be null" );
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
                    'uid'    => $item[ 'uid' ],
                    'tm_key' => new TmKeyManagement_TmKeyStruct( array(
                                    'key'       => (string)$item[ 'key_value' ],
                                    'name'      => (string)$item[ 'key_name' ],
                                    'tm'        => (bool)$item[ 'tm' ],
                                    'glos'      => (bool)$item[ 'glos' ],
                                    'is_shared' => ( $item[ 'owners_tot' ] > 1 ),
                                    'in_users'  => $item[ 'in_users' ]
                            )
                    )
            );

            $obj = new TmKeyManagement_MemoryKeyStruct( $build_arr );

            $result[ ] = $obj;
        }

        return $result;
    }


}
