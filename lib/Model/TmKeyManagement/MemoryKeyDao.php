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

    const MAX_INSERT_NUMBER = 10;

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
                VALUES ( :uid, :key_value, :key_name, :key_tm, :key_glos, NOW())";

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute(
                [
                        "uid"       => $obj->uid,
                        "key_value" => trim($obj->tm_key->key),
                        "key_name"  => ( $obj->tm_key->name == null ) ? '' : trim($obj->tm_key->name),
                        "key_tm"    => ( $obj->tm_key->tm == null ) ? 1 : $obj->tm_key->tm,
                        "key_glos"  => ( $obj->tm_key->glos == null ) ? 1 : $obj->tm_key->glos
                ]
        );

        //return the inserted object on success, null otherwise
        if ( $stmt->rowCount() > 0 ) {
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

        $where_params = [];
        $condition    = [];

        $query = "SELECT  m1.uid, 
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
            $condition[]           = "m1.uid = :uid";
            $where_params[ 'uid' ] = $obj->uid;
        }

        //tm_key conditions
        if ( $obj->tm_key !== null ) {

            if ( $obj->tm_key->key !== null ) {
                $condition[]                 = "m1.key_value = :key_value";
                $where_params[ 'key_value' ] = $obj->tm_key->key;
            }

            if ( $obj->tm_key->name !== null ) {
                $condition[]                = "m1.key_name = :key_name";
                $where_params[ 'key_name' ] = $obj->tm_key->name;
            }

            if ( $obj->tm_key->tm !== null ) {
                $condition[]              = "m1.key_tm = :key_tm";
                $where_params[ 'key_tm' ] = $obj->tm_key->tm;
            }

            if ( $obj->tm_key->glos !== null ) {
                $condition[]                = "m1.key_glos = :key_glos";
                $where_params[ 'key_glos' ] = $obj->tm_key->glos;
            }
        }

        if ( count( $condition ) ) {
            $where_string = implode( " AND ", $condition );
        } else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( $where_params );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );

        $arr_result = $stmt->fetchAll();

        if ( $traverse ) {

            $userDao = new Users_UserDao( Database::obtain() );

            foreach ( $arr_result as $k => $row ) {
                $users                          = $userDao->getByUids( explode( ",", $row[ 'owner_uids' ] ) );
                $arr_result[ $k ][ 'in_users' ] = $users;
            }

        } else {

            foreach ( $arr_result as $k => $row ) {
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
    public function atomicUpdate( TmKeyManagement_MemoryKeyStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );
        $this->_validateNotNullFields( $obj );

        $set_array        = [];
        $where_conditions = [];
        $bind_params      = [];

        $query = "UPDATE " . self::TABLE . " SET %s WHERE %s";

        $where_conditions[]   = "uid = :uid";
        $bind_params[ 'uid' ] = $obj->uid;

        $where_conditions[]         = "key_value = :key_value";
        $bind_params[ 'key_value' ] = $obj->tm_key->key;

        //tm_key conditions
        if ( $obj->tm_key !== null ) {

            if ( $obj->tm_key->name !== null ) {
                $set_array[]               = "key_name = :key_name";
                $bind_params[ 'key_name' ] = $obj->tm_key->name;
            }

        }

        $where_string = implode( " AND ", $where_conditions );

        $set_string = null;
        if ( count( $set_array ) ) {
            $set_string = implode( ", ", $set_array );
        } else {
            throw new Exception( "Array given is empty. Please set at least one value." );
        }

        $query = sprintf( $query, $set_string, $where_string );

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( $bind_params );

        if ( $stmt->rowCount() ) {
            return $obj;
        }

        return null;
    }

    public function delete( TmKeyManagement_MemoryKeyStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );
        $this->_validateNotNullFields( $obj );

        $query = "DELETE FROM " . self::TABLE . " WHERE uid = :uid and key_value = :key_value";

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( [
                'uid'       => $obj->uid,
                'key_value' => $obj->tm_key->key
        ] );

        if ( $stmt->rowCount() > 0 ) {
            return $obj;
        }

        return null;
    }

    public function disable( TmKeyManagement_MemoryKeyStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );
        $this->_validateNotNullFields( $obj );

        $query = "UPDATE " . self::TABLE . " set deleted = 1 WHERE uid = :uid and key_value = :key_value";

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( [
                'uid'       => $obj->uid,
                'key_value' => $obj->tm_key->key
        ] );

        if ( $stmt->rowCount() > 0 ) {
            return $obj;
        }

        return null;
    }

    public function enable( TmKeyManagement_MemoryKeyStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );
        $this->_validateNotNullFields( $obj );

        $query = "UPDATE " . self::TABLE . " set deleted = 0 WHERE uid = :uid and key_value = :key_value";

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( [
                'uid'       => $obj->uid,
                'key_value' => $obj->tm_key->key
        ] );

        if ( $stmt->rowCount() > 0 ) {
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
                VALUES ";

        $values = [];

        //chunk array using MAX_INSERT_NUMBER
        $objects = array_chunk( $obj_arr, static::MAX_INSERT_NUMBER );

        //create an insert query for each chunk
        foreach ( $objects as $i => $chunk ) {

            $insert_query = $query;
            /**
             * @var $chunk TmKeyManagement_MemoryKeyStruct[]
             */
            foreach ( $chunk as $obj ) {
                $insert_query .= "( ?, ?, ?, ?, ?, NOW() ),";

                //fill values array
                $values[] = (int)$obj->uid;
                $values[] = $obj->tm_key->key;
                $values[] = ( $obj->tm_key->name == null ) ? '' : $obj->tm_key->name;
                $values[] = ( $obj->tm_key->tm == null ) ? 1 : $obj->tm_key->tm;
                $values[] = ( $obj->tm_key->glos == null ) ? 1 : $obj->tm_key->glos;
            }

            $insert_query = rtrim( $insert_query, "," );

            $stmt = $this->database->getConnection()->prepare( $insert_query );
            $stmt->execute( $values );
            $values = [];

        }

        if ( $this->database->affected_rows > 0 ) {
            return $obj_arr;
        }

        return null;
    }

    /**
     * See parent definition
     *
     * @param TmKeyManagement_MemoryKeyStruct $input
     *
     * @return DataAccess_IDaoStruct|TmKeyManagement_MemoryKeyStruct
     * @throws Exception
     * @see DataAccess_AbstractDao::sanitize
     *
     */
    public function sanitize( DataAccess_IDaoStruct $input ) {
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
    public static function sanitizeArray( array $input ) {
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
    protected function _validatePrimaryKey( DataAccess_IDaoStruct $obj ) {

        /**
         * @var $obj TmKeyManagement_MemoryKeyStruct
         */
        if ( empty( $obj->uid ) ) {
            throw new Exception( "Invalid Uid" );
        }

        if ( is_null( $obj->tm_key ) || is_null( $obj->tm_key->key ) ) {
            throw new Exception( "Invalid Key value" );
        }

    }

    /**
     * See in DataAccess_AbstractDao::validateNotNullFields
     *
     * @param DataAccess_IDaoStruct $obj
     *
     * @return null
     * @throws Exception
     * @see DataAccess_AbstractDao::_validateNotNullFields
     *
     */
    protected function _validateNotNullFields( DataAccess_IDaoStruct $obj ) {
        /**
         * @var $obj TmKeyManagement_MemoryKeyStruct
         */
        if ( empty( $obj->uid ) ) {
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
        $result = [];

        foreach ( $array_result as $item ) {

            $build_arr = [
                    'uid'    => $item[ 'uid' ],
                    'tm_key' => new TmKeyManagement_TmKeyStruct( [
                                    'key'       => (string)$item[ 'key_value' ],
                                    'name'      => (string)$item[ 'key_name' ],
                                    'tm'        => (bool)$item[ 'tm' ],
                                    'glos'      => (bool)$item[ 'glos' ],
                                    'is_shared' => ( $item[ 'owners_tot' ] > 1 ),
                                    'in_users'  => $item[ 'in_users' ]
                            ]
                    )
            ];

            $obj = new TmKeyManagement_MemoryKeyStruct( $build_arr );

            $result[] = $obj;
        }

        return $result;
    }


}
