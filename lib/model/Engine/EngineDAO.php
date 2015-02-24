<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 23/02/15
 * Time: 14.55
 */
class Engine_EngineDAO extends DataAccess_AbstractDao {

    const TABLE = "engines";

    const STRUCT_TYPE = "Engine_EngineStruct";

    /**
     * @param Engine_EngineStruct $obj
     *
     * @return Engine_EngineStruct|null
     * @throws Exception
     */
    public function create( Engine_EngineStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validateNotNullFields( $obj );

        $query = "INSERT INTO " . self::TABLE .
                " ( name, type, description, base_url, translate_relative_url, contribute_relative_url,
                delete_relative_url, others, extra_parameters, google_api_compliant_version, penalty, active, uid)
                    VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ) ON DUPLICATE KEY UPDATE
                        active = VALUES(active),
                        others = VALUES(others),
                        name = VALUES(name)
            ";

        $query = sprintf(
                $query,
                ( $obj->name == null ) ? "NULL" : "'" . $obj->name . "'",
                ( $obj->type == null ) ? "NULL" : "'" . $obj->type . "'",
                ( $obj->description == null ) ? "NULL" : "'" . $obj->description . "'",
                ( $obj->base_url == null ) ? "NULL" : "'" . $obj->base_url . "'",
                ( $obj->translate_relative_url == null ) ? "NULL" : "'" . $obj->translate_relative_url . "'",
                ( $obj->contribute_relative_url == null ) ? "NULL" : "'" . $obj->contribute_relative_url . "'",
                ( $obj->delete_relative_url == null ) ? "NULL" : "'" . $obj->delete_relative_url . "'",
                ( $obj->others == null ) ? "NULL" : "'" . $obj->others . "'",
                ( $obj->extra_parameters == null ) ? "NULL" : "'" . $obj->extra_parameters . "'",
                2,
                //harcoded because we're planning to implement variable penalty
                ( $obj->penalty == null ) ? "14" : $obj->penalty,
                ( $obj->active == null ) ? "NULL" : $obj->active,
                ( $obj->uid == null ) ? "NULL" : $obj->uid
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
     * @param Engine_EngineStruct $obj
     *
     * @return array|void
     * @throws Exception
     */
    public function read( Engine_EngineStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $where_conditions = array();
        $query            = "SELECT *
                             FROM " . self::TABLE . " WHERE %s";

        if ( $obj->id !== null ) {
            $where_conditions[ ] = "id = " . (int)$obj->id;
        }

        if ( $obj->uid !== null ) {
            $where_conditions[ ] = "uid = " . (int)$obj->uid;
        }

        if ( $obj->active !== null ) {
            $where_conditions[ ] = "active = " . (int)$obj->active;
        }


        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );

        $arr_result = $this->fetch_array( $query );

        $this->_checkForErrors();

        return $this->_buildResult( $arr_result );
    }

    public function update( Engine_EngineStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $set_array        = array();
        $where_conditions = array();
        $query            = "UPDATE " . self::TABLE . " SET %s WHERE %s";

        $where_conditions[ ] = "id = " . (int)$obj->id;
        $where_conditions[ ] = "uid = " . (int)$obj->id;

        if ( $obj->active !== null ) {
            $condition    = "active = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->active );
        }

        if ( $obj->others !== null ) {
            $condition    = "others = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->others );
        }

        if ( $obj->name !== null ) {
            $condition    = "name = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->name );
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

    public function delete( Engine_EngineStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $query = "DELETE FROM " . self::TABLE . " WHERE id = %d and uid = %d";

        $query = sprintf(
                $query,
                $obj->id,
                $obj->uid
        );


        $this->con->query( $query );

        $this->_checkForErrors();

        if ( $this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    protected function _buildResult( $array_result ) {
        $result = array();

        foreach ( $array_result as $item ) {

            $build_arr = array(
                    'id'                           => (int)$item[ 'id' ],
                    'name'                         => $item[ 'name' ],
                    'type'                         => $item[ 'type' ],
                    'description'                  => $item[ 'description' ],
                    'base_url'                     => $item[ 'base_url' ],
                    'translate_relative_url'       => $item[ 'translate_relative_url' ],
                    'contribute_relative_url'      => $item[ 'contribute_relative_url' ],
                    'delete_relative_url'          => $item[ 'delete_relative_url' ],
                    'others'                       => json_decode( $item[ 'others' ], true ),
                    'extra_parameters'             => $item[ 'extra_parameters' ],
                    'google_api_compliant_version' => $item[ 'google_api_compliant_version' ],
                    'penalty'                      => $item[ 'penalty' ],
                    'active'                       => $item[ 'active' ],
                    'uid'                          => $item[ 'uid' ]
            );

            $obj = new Engine_EngineStruct( $build_arr );

            $result[ ] = $obj;
        }

        return $result;
    }

    /**
     * @param Engine_EngineStruct $input
     *
     * @return Engine_EngineStruct
     * @throws Exception
     */
    public function sanitize( $input ) {
        $con = Database::obtain();
        parent::_sanitizeInput( $input, self::STRUCT_TYPE );

        if ( is_array( $input->others ) && empty( $input->others ) ) {
            $input->others = "{}";
        }

        $input->name                    = ( $input->name !== null ) ? $con->escape( $input->name ) : null;
        $input->description             = ( $input->description !== null ) ? $con->escape( $input->description ) : null;
        $input->base_url                = ( $input->base_url !== null ) ? $con->escape( $input->base_url ) : null;
        $input->translate_relative_url  = ( $input->translate_relative_url !== null ) ? $con->escape( $input->translate_relative_url ) : null;
        $input->contribute_relative_url = ( $input->contribute_relative_url !== null ) ? $con->escape( $input->contribute_relative_url ) : null;
        $input->delete_relative_url     = ( $input->delete_relative_url !== null ) ? $con->escape( $input->delete_relative_url ) : null;
        $input->others                  = ( $input->others !== null ) ? json_encode( $input->others ) : "{}";
        $input->extra_parameters        = ( $input->extra_parameters !== null ) ? $con->escape( $input->extra_parameters ) : null;
        $input->penalty                 = ( $input->penalty !== null ) ? $input->penalty : null;
        $input->active                  = ( $input->active !== null ) ? $input->active : null;
        $input->uid                     = ( $input->uid !== null ) ? $input->uid : null;

        return $input;
    }

    protected function _validateNotNullFields( Engine_EngineStruct $obj ) {
        /**
         * @var $obj Engine_EngineStruct
         */
        if ( empty( $obj->base_url ) ) {
            throw new Exception( "Base URL cannot be null" );
        }

        $reflect   = new ReflectionClass( 'Constants_Revise' );
        $constants = $reflect->getConstants();

        if ( !empty ( $obj->type ) && !in_array( $obj->type, $constants ) ) {
            throw new Exception( "Type not allowed" );
        }

    }

    protected function _validatePrimaryKey( DataAccess_IDaoStruct $obj ) {
        if ( $obj->id === null ) {
            throw new Exception( "Engine ID required" );
        }

        if ( $obj->uid === null ) {
            throw new Exception( "User's uid required" );
        }
    }


}

