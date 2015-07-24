<?php

/**
 * Created by PhpStorm.
 * User: roberto <roberto@translated.net>
 * Date: 19/01/15
 * Time: 17.44
 */
class Revise_ReviseDAO extends DataAccess_AbstractDao {

    const TABLE = "segment_revisions";

    const STRUCT_TYPE = "Revise_ReviseStruct";

    public function create( DataAccess_IDaoStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validateNotNullFields( $obj );

        $query = "INSERT INTO " . self::TABLE .
                " (id_job, id_segment, err_typing, err_translation, err_terminology, err_language, err_style, original_translation)
                    VALUES ( %d, %d, '%s', '%s', '%s', '%s', '%s', '%s' ) ON DUPLICATE KEY UPDATE
                        err_typing = VALUES(err_typing),
                        err_translation = VALUES(err_translation),
                        err_terminology = VALUES(err_terminology),
                        err_language = VALUES(err_language),
                        err_style = VALUES(err_style),
                        original_translation = original_translation
            ";

        $query = sprintf(
                $query,
                (int)$obj->id_job,
                (int)$obj->id_segment,
                $obj->err_typing,
                $obj->err_translation,
                $obj->err_terminology,
                $obj->err_language,
                $obj->err_style,
                $this->con->escape( $obj->original_translation )
        );

        $this->con->query( $query );

        //return the inserted object on success, null otherwise
        if ( $this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    public function read( Revise_ReviseStruct $obj ) {

        $obj = $this->sanitize( $obj );

        $where_conditions = array();
        $query            = "SELECT id_job,
                                    id_segment,
                                    err_typing,
                                    err_translation,
                                    err_terminology,
                                    err_language,
                                    err_style,
                                    original_translation
                             FROM " . self::TABLE . " WHERE %s";

        if ( $obj->id_job !== null ) {
            $where_conditions[ ] = "id_job = " . (int)$obj->id_job;
        }

        if ( $obj->id_segment !== null ) {
            $where_conditions[ ] = "id_segment = " . (int)$obj->id_segment;
        }


        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );

        $arr_result = $this->con->fetch_array( $query );

        return $this->_buildResult( $arr_result );
    }

    public function update( Revise_ReviseStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $set_array        = array();
        $where_conditions = array();
        $query            = "UPDATE " . self::TABLE . " SET %s WHERE %s";

        $where_conditions[ ] = "id_job = " . (int)$obj->id_job;
        $where_conditions[ ] = "id_segment = " . (int)$obj->id_segment;

        if ( $obj->err_typing !== null ) {
            $condition    = "err_typing = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->err_typing );
        }

        if ( $obj->err_typing !== null ) {
            $condition    = "err_translation = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->err_translation );
        }

        if ( $obj->err_typing !== null ) {
            $condition    = "err_terminology = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->err_terminology );
        }

        if ( $obj->err_typing !== null ) {
            $condition    = "err_language = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->err_language );
        }

        if ( $obj->err_typing !== null ) {
            $condition    = "err_style = '%s'";
            $set_array[ ] = sprintf( $condition, $obj->err_style );
        }

//        if($obj->original_translation !== null){
//            $condition = "origin_translation = '%s'";
//            $set_array[ ] = sprintf($condition, $this->con->escape( $obj->original_translation ) );
//        }

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
     * @param Revise_ReviseStruct $input
     *
     * @return Revise_ReviseStruct
     * @throws Exception
     */
    public function sanitize( Revise_ReviseStruct $input ) {
        return parent::_sanitizeInput( $input, self::STRUCT_TYPE );
    }

    protected function _validateNotNullFields( Revise_ReviseStruct $obj ) {
        /**
         * @var $obj Revise_ReviseStruct
         */
        if ( empty( $obj->id_job ) ) {
            throw new Exception( "Job id cannot be null" );
        }

        if ( empty( $obj->id_segment ) ) {
            throw new Exception( "Segment id cannot be null" );
        }

    }


    protected function _buildResult( $array_result ) {
        $result = array();

        foreach ( $array_result as $item ) {

            $build_arr = array(
                    'id_job'               => $item[ 'id_job' ],
                    'id_segment'           => $item[ 'id_segment' ],
                    'err_typing'           => $item[ 'err_typing' ],
                    'err_translation'      => $item[ 'err_translation' ],
                    'err_terminology'      => $item[ 'err_terminology' ],
                    'err_language'         => $item[ 'err_language' ],
                    'err_style'            => $item[ 'err_style' ],
                    'original_translation' => $item[ 'original_translation' ]
            );

            $obj = new Revise_ReviseStruct( $build_arr );

            $result[ ] = $obj;
        }

        return $result;
    }

    /**
     * @param Revise_ReviseStruct $obj
     *
     * @return bool|void
     * @throws Exception
     */
    protected function _validatePrimaryKey( Revise_ReviseStruct $obj ) {

        /**
         * @var $obj Revise_ReviseStruct
         */
        if ( is_null( $obj->id_job ) || empty( $obj->id_segment ) ) {
            throw new Exception( "Invalid id job" );
        }

        if ( is_null( $obj->tm_key->key ) ) {
            throw new Exception( "Invalid id segment" );
        }

    }


} 