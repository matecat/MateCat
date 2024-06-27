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

    protected static $auto_increment_field = [ 'id' ];
    protected static $primary_keys         = [ 'id' ];

    /**
     * Build the query,
     * needed for get the exact query when invalidating cache
     *
     * @param EnginesModel_EngineStruct $obj
     *
     * @return array
     * @throws Exception
     */
    protected function _buildQueryForEngine( EnginesModel_EngineStruct $obj ) {

        $where_conditions = [];
        $query            = "SELECT * FROM " . self::TABLE . " WHERE %s";

        $bind_values = [];

        if ( $obj->id !== null ) {
            $bind_values[ 'id' ] = (int)$obj->id;
            $where_conditions[]  = "id = :id";
        }

        if ( $obj->uid !== null ) {

            if ( $obj->uid == 'NULL' ) {
                $where_conditions[] = "uid IS NULL";
            } elseif ( empty( $obj->uid ) || $obj->uid <= 0 ) {
                throw new DomainException( "Anonymous User." ); //do not perform any query on anonymous user requests
            } elseif ( is_numeric( $obj->uid ) ) {
                $bind_values[ 'uid' ] = (int)$obj->uid;
                $where_conditions[]   = "uid = :uid";
            }

        }

        if ( $obj->active !== null ) {
            $bind_values[ 'active' ] = (int)$obj->active;
            $where_conditions[]      = "active = :active";
        }

        if ( $obj->type !== null ) {
            $bind_values[ 'type' ] = $obj->type;
            $where_conditions[]    = "type = :type";
        }

        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

//        Log::doJsonLog( sprintf( $query, $where_string ) );

        return [ sprintf( $query, $where_string ), $bind_values ];

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
                " ( name, type, description, base_url, translate_relative_url, contribute_relative_url, update_relative_url,
                delete_relative_url, others, extra_parameters, class_load, google_api_compliant_version, penalty, active, uid)
                    VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )
            ";

        $bind_values   = [];
        $bind_values[] = $obj->name;
        $bind_values[] = $obj->type;
        $bind_values[] = $obj->description;
        $bind_values[] = $obj->base_url;
        $bind_values[] = $obj->translate_relative_url;
        $bind_values[] = $obj->contribute_relative_url;
        $bind_values[] = $obj->update_relative_url;
        $bind_values[] = $obj->delete_relative_url;
        $bind_values[] = $obj->others;
        $bind_values[] = $obj->extra_parameters;

        //This parameter MUST be set from Engine, Needed to load the right Engine
        $bind_values[] = $obj->class_load;

        $bind_values[] = 2;
        $bind_values[] = ( $obj->penalty == null ) ? "14" : $obj->penalty;
        $bind_values[] = intval( $obj->active );
        $bind_values[] = $obj->uid;

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( $bind_values );

        //return the inserted object on success
        $obj->id = $this->database->last_insert();

        return $obj;

    }

    /**
     * @param EnginesModel_EngineStruct $obj
     *
     * @return array
     * @throws Exception
     */
    public function read( EnginesModel_EngineStruct $obj ) {

        $obj = $this->sanitize( $obj );

        /*
         * build the query
         */
        try {
            $query_and_bindValues = $this->_buildQueryForEngine( $obj );
        } catch ( DomainException $e ) {
            return []; //anonymous use request, he can not have any associated engine, do not perform queries
        }

        list( $query, $bind_values ) = $query_and_bindValues;


        $stmt      = $this->database->getConnection()->prepare( $query );
        $resultSet = $this->_fetchObject( $stmt, new EnginesModel_EngineStruct(), $bind_values );

        return $this->_buildResult( $resultSet, Constants_Engines::getAvailableEnginesList() );

    }

    /**
     * Destroy a cached object
     *
     * @param EnginesModel_EngineStruct $obj
     *
     * @return bool
     * @throws Exception
     */
    public function destroyCache( EnginesModel_EngineStruct $obj ) {

        $obj = $this->sanitize( $obj );

        /*
        * build the query
        */
        try {
            $query_and_bindValues = $this->_buildQueryForEngine( $obj );
        } catch ( DomainException $e ) {
            return true; //anonymous use request, he can not have any associated engine, do not perform queries
        }

        list( $query, $bind_values ) = $query_and_bindValues;
        $stmt = $this->database->getConnection()->prepare( $query );

        return $this->_destroyObjectCache( $stmt, $bind_values );

    }

    /**
     * @throws Exception
     */
    public function updateByStruct( EnginesModel_EngineStruct $obj ) {
        $obj = $this->sanitize( clone $obj );

        $this->_validatePrimaryKey( $obj );

        $fieldsToUpdate = [];

        if ( $obj->active !== null ) {
            $fieldsToUpdate[] = 'active';
        }

        if ( $obj->others !== null ) {
            $fieldsToUpdate[] = 'others';
        }

        if ( $obj->extra_parameters !== null ) {
            $fieldsToUpdate[] = 'extra_parameters';
        }

        if ( $obj->name !== null ) {
            $fieldsToUpdate[] = 'name';
        }

        if ( !count( $fieldsToUpdate ) ) {
            throw new Exception( "Array given is empty. Please set at least one value." );
        }

        $res = static::updateStruct( $obj, [ 'fields' => $fieldsToUpdate ] );

        if ( $res ) {
            return $obj;
        }

        return null;
    }

    public function delete( EnginesModel_EngineStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $query = "DELETE FROM " . self::TABLE . " WHERE id = :id and uid = :uid";

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( [
                'id'  => $obj->id,
                'uid' => $obj->uid
        ] );


        if ( $stmt->rowCount() > 0 ) {
            return $obj;
        }

        return null;
    }

    public function disable( EnginesModel_EngineStruct $obj ) {

        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $query = "UPDATE " . self::TABLE . " SET active = 0 WHERE id = :id and uid = :uid";

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( [
                'id'  => $obj->id,
                'uid' => $obj->uid
        ] );

        if ( $stmt->rowCount() > 0 ) {
            $tmpEng         = $this->setCacheTTL( 60 * 60 * 5 )->read( $obj )[ 0 ];
            $tmpEng->active = 0; // avoid slave replication delay

            return $tmpEng;
        }

        return null;
    }

    public function enable( EnginesModel_EngineStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $query = "UPDATE " . self::TABLE . " SET active = 1 WHERE id = :id and uid = :uid";

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( [
                $obj->id,
                $obj->uid
        ] );

        if ( $stmt->rowCount() > 0 ) {
            return $obj;
        }

        return null;
    }

    /**
     * Needed to decode json fields
     *
     * @param array $array_result
     *
     * @return array|EnginesModel_EngineStruct|EnginesModel_EngineStruct[]
     */
    protected function _buildResult( $array_result ) {
        $result = [];

        foreach ( $array_result as $item ) {

            if ( func_num_args() > 1 ) { // check if $availableEngines is provided as second argument
                $availableEngines = func_get_arg( 1 );

                if ( !array_key_exists( $item[ 'class_load' ], $availableEngines ) ) {
                    $result[] = new EnginesModel_NONEStruct();
                    continue;
                }
            }

            $build_arr = [
                    'id'                           => (int)$item[ 'id' ],
                    'name'                         => $item[ 'name' ],
                    'type'                         => $item[ 'type' ],
                    'description'                  => $item[ 'description' ],
                    'base_url'                     => $item[ 'base_url' ],
                    'translate_relative_url'       => $item[ 'translate_relative_url' ],
                    'contribute_relative_url'      => $item[ 'contribute_relative_url' ],
                    'update_relative_url'          => $item[ 'update_relative_url' ],
                    'delete_relative_url'          => $item[ 'delete_relative_url' ],
                    'others'                       => json_decode( $item[ 'others' ], true ),
                    'extra_parameters'             => json_decode( $item[ 'extra_parameters' ], true ),
                    'class_load'                   => $item[ 'class_load' ],
                    'google_api_compliant_version' => $item[ 'google_api_compliant_version' ],
                    'penalty'                      => $item[ 'penalty' ],
                    'active'                       => $item[ 'active' ],
                    'uid'                          => $item[ 'uid' ]
            ];

            $obj = new EnginesModel_EngineStruct( $build_arr );

            $result[] = $obj;
        }

        return $result;
    }

    /**
     * @param EnginesModel_EngineStruct $input
     *
     * @return EnginesModel_EngineStruct
     * @throws Exception
     */
    public function sanitize( DataAccess_IDaoStruct $input ) {
        parent::_sanitizeInput( $input, self::STRUCT_TYPE );

        $input->name                    = ( $input->name !== null ) ?  $input->name  : null;
        $input->description             = ( $input->description !== null ) ?  $input->description  : null;
        $input->base_url                = ( $input->base_url !== null ) ?  $input->base_url  : null;
        $input->translate_relative_url  = ( $input->translate_relative_url !== null ) ?  $input->translate_relative_url  : null;
        $input->contribute_relative_url = ( $input->contribute_relative_url !== null ) ?  $input->contribute_relative_url  : null;
        $input->update_relative_url     = ( $input->update_relative_url !== null ) ?  $input->update_relative_url  : null;
        $input->delete_relative_url     = ( $input->delete_relative_url !== null ) ?  $input->delete_relative_url  : null;
        $input->others                  = ( $input->others !== null and $input->others !== '{}' ) ?  json_encode( $input->others )  : '{}';
        $input->class_load              = ( $input->class_load !== null ) ?  $input->class_load  : null;
        $input->extra_parameters        = ( $input->extra_parameters !== null and $input->extra_parameters !== '{}' ) ?  json_encode( $input->extra_parameters ) : '{}';
        $input->penalty                 = ( $input->penalty !== null ) ? $input->penalty : null;
        $input->active                  = ( $input->active !== null ) ? $input->active : null;
        $input->uid                     = ( $input->uid !== null ) ? $input->uid : null;

        return $input;
    }

    /**
     * @param DataAccess_IDaoStruct $obj
     *
     * @return bool|void
     * @throws Exception
     */
    protected function _validateNotNullFields( DataAccess_IDaoStruct $obj ) {
        /**
         * @var $obj EnginesModel_EngineStruct
         */
        if ( empty( $obj->base_url ) ) {
            throw new Exception( "Base URL cannot be null" );
        }

        if ( !empty ( $obj->type ) && !in_array( $obj->type, [ Constants_Engines::TM, Constants_Engines::MT, Constants_Engines::NONE ], true ) ) {
            throw new Exception( "Type not allowed" );
        }

    }

    /**
     * @param EnginesModel_EngineStruct|DataAccess_IDaoStruct $obj
     *
     * @return void
     * @throws Exception
     */
    protected function _validatePrimaryKey( DataAccess_IDaoStruct $obj ) {
        if ( $obj->id === null ) {
            throw new Exception( "Engine ID required" );
        }

        if ( $obj->uid === null ) {
            throw new Exception( "User's uid required" );
        }
    }

    public function validateForUser( EnginesModel_EngineStruct $obj )
    {
        $query = "SELECT * FROM " . self::TABLE . " WHERE `name` = :engine_name and uid = :uid and active = :active";

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( [
            'engine_name'  => $obj->name,
            'uid' => $obj->uid,
            'active' => 1
        ] );

        if ( $stmt->rowCount() > 0 ) {
            throw new Exception("A user can have only one $obj->name engine");
        }
    }
}

