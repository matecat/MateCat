<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 23/02/15
 * Time: 14.55
 */
class EnginesModel_EngineDAO extends DataAccess_AbstractDao {

    const TABLE = "engines";

    const STRUCT_TYPE = "EnginesModel_EngineStruct";


    /**
     * Build the query,
     * needed for get the exact query when invalidating cache
     *
     * @param EnginesModel_EngineStruct $obj
     *
     * @return string
     * @throws Exception
     */
    protected function _buildQueryForEngine( EnginesModel_EngineStruct $obj  ){

        $where_conditions = array();
        $query            = "SELECT * FROM " . self::TABLE . " WHERE %s";

        if ( $obj->id !== null ) {
            $where_conditions[ ] = "id = " . (int)$obj->id;
        }

        if ( $obj->uid !== null ) {

            if( $obj->uid == 'NULL' ){
                $where_conditions[ ] = "uid IS " . $obj->uid;
            } elseif( is_numeric( $obj->uid ) ){
                $where_conditions[ ] = "uid = " . (int)$obj->uid;
            }

        }

        if ( $obj->active !== null ) {
            $where_conditions[ ] = "active = " . (int)$obj->active;
        }

        if ( $obj->type !== null ) {
            $where_conditions[ ] = "type = '" . $this->con->escape( $obj->type ) . "'";
        }

        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

//        Log::doLog( sprintf( $query, $where_string ) );

        return sprintf( $query, $where_string );

    }

    /**
     * @param EnginesModel_EngineStruct $obj
     *
     * @return EnginesModel_EngineStruct|null
     * @throws Exception
     */
    public function create( EnginesModel_EngineStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validateNotNullFields( $obj );

        $query = "INSERT INTO " . self::TABLE .
                " ( name, type, description, base_url, translate_relative_url, contribute_relative_url,
                delete_relative_url, others, extra_parameters, class_load, google_api_compliant_version, penalty, active, uid)
                    VALUES ( %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s ) ON DUPLICATE KEY UPDATE
                        active = VALUES(active),
                        others = VALUES(others),
                        extra_parameters = VALUES(extra_parameters),
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

                //This parameter MUST be set from Engine, Needed to load the right Engine
                ( $obj->class_load == null ) ? "NULL" : "'" . $obj->class_load . "'",

                2,
                //harcoded because we're planning to implement variable penalty
                ( $obj->penalty == null ) ? "14" : $obj->penalty,
                ( $obj->active == null ) ? "1" : $obj->active, //TODO BUG This is every time 1!!!
                ( $obj->uid == null ) ? "NULL" : $obj->uid
        );

        $this->con->query( $query );

        //return the inserted object on success, null otherwise
        if ( $this->con->affected_rows > 0 ) {
            $obj->id = $this->con->last_insert( self::TABLE );
            return $obj;
        }

        return null;
    }

    /**
     * @param EnginesModel_EngineStruct $obj
     *
     * @return array|void
     * @throws Exception
     */
    public function read( EnginesModel_EngineStruct $obj ) {

        $obj = $this->sanitize( $obj );

        /*
         * build the query
         */
        $query = $this->_buildQueryForEngine( $obj );
        $arr_result = $this->_fetch_array( $query );
        return $this->_buildResult( $arr_result );

    }

    /**
     * Destroy a cached object
     *
     * @param EnginesModel_EngineStruct $obj
     *
     * @return bool
     * @throws Exception
     */
    public function destroyCache( EnginesModel_EngineStruct $obj ){

        $obj = $this->sanitize( $obj );

        /*
        * build the query
        */
        $query = $this->_buildQueryForEngine( $obj );
        return $this->_destroyCache( $query );

    }

    public function update( EnginesModel_EngineStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $set_array        = array();
        $where_conditions = array();
        $query            = "UPDATE " . self::TABLE . " SET %s WHERE %s";

        $where_conditions[ ] = "id = " . (int)$obj->id;
        $where_conditions[ ] = "uid = " . (int)$obj->uid;

        if ( $obj->active !== null ) {
            $condition    = "active = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->active );
        }

        if ( $obj->others !== null ) {
            $condition    = "others = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->others );
        }

        if ( $obj->extra_parameters !== null ) {
            $condition    = "extra_parameters = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->extra_parameters );
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

        if ( $this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    public function delete( EnginesModel_EngineStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $query = "DELETE FROM " . self::TABLE . " WHERE id = %d and uid = %d";

        $query = sprintf(
                $query,
                $obj->id,
                $obj->uid
        );


        $this->con->query( $query );

        if ( $this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    public function disable( EnginesModel_EngineStruct $obj ){
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $query = "UPDATE " . self::TABLE . " SET active = 0 WHERE id = %d and uid = %d";

        $query = sprintf(
                $query,
                $obj->id,
                $obj->uid
        );

        $this->con->query( $query );

        if ( $this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    /**
     * @param array $array_result
     *
     * @return array|EnginesModel_EngineStruct|EnginesModel_EngineStruct[]
     */
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
                    'extra_parameters'             => json_decode( $item[ 'extra_parameters' ], true ),
                    'class_load'                   => $item[ 'class_load' ],
                    'google_api_compliant_version' => $item[ 'google_api_compliant_version' ],
                    'penalty'                      => $item[ 'penalty' ],
                    'active'                       => $item[ 'active' ],
                    'uid'                          => $item[ 'uid' ]
            );

            $obj = new EnginesModel_EngineStruct( $build_arr );

            $result[ ] = $obj;
        }

        return $result;
    }

    /**
     * @param EnginesModel_EngineStruct $input
     *
     * @return EnginesModel_EngineStruct
     * @throws Exception
     */
    public function sanitize( $input ) {
        $con = Database::obtain();
        parent::_sanitizeInput( $input, self::STRUCT_TYPE );

        $input->name                    = ( $input->name !== null ) ? $con->escape( $input->name ) : null;
        $input->description             = ( $input->description !== null ) ? $con->escape( $input->description ) : null;
        $input->base_url                = ( $input->base_url !== null ) ? $con->escape( $input->base_url ) : null;
        $input->translate_relative_url  = ( $input->translate_relative_url !== null ) ? $con->escape( $input->translate_relative_url ) : null;
        $input->contribute_relative_url = ( $input->contribute_relative_url !== null ) ? $con->escape( $input->contribute_relative_url ) : null;
        $input->delete_relative_url     = ( $input->delete_relative_url !== null ) ? $con->escape( $input->delete_relative_url ) : null;
        $input->others                  = ( $input->others !== null ) ? $con->escape( json_encode( $input->others ) ) : "{}";
        $input->class_load              = ( $input->class_load !== null ) ? $con->escape( $input->class_load ) : null;
        $input->extra_parameters        = ( $input->extra_parameters !== null ) ? $con->escape( json_encode( $input->extra_parameters ) ) : '{}';
        $input->penalty                 = ( $input->penalty !== null ) ? $input->penalty : null;
        $input->active                  = ( $input->active !== null ) ? $input->active : null;
        $input->uid                     = ( $input->uid !== null ) ? $input->uid : null;

        return $input;
    }

    protected function _validateNotNullFields( EnginesModel_EngineStruct $obj ) {
        /**
         * @var $obj EnginesModel_EngineStruct
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

