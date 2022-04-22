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
                    VALUES ( ?, ?, ?, ?, ?, ?, ?, ? ) ON DUPLICATE KEY UPDATE
                        err_typing = VALUES(err_typing),
                        err_translation = VALUES(err_translation),
                        err_terminology = VALUES(err_terminology),
                        err_language = VALUES(err_language),
                        err_style = VALUES(err_style),
                        original_translation = original_translation
            ";

        $bind_values = [
                (int)$obj->id_job,
                (int)$obj->id_segment,
                $obj->err_typing,
                $obj->err_translation,
                $obj->err_terminology,
                $obj->err_language,
                $obj->err_style,
                $obj->original_translation
        ];

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( $bind_values );

        //return the inserted object on success, null otherwise
        if ( $stmt->rowCount() > 0 ) {
            return $obj;
        }

        return null;
    }

    public function read( Revise_ReviseStruct $obj ) {

        $obj = $this->sanitize( $obj );

        $where_conditions = [];
        $bind_values      = [];

        $query = "SELECT id_job,
                                    id_segment,
                                    err_typing,
                                    err_translation,
                                    err_terminology,
                                    err_language,
                                    err_style,
                                    original_translation
                             FROM " . self::TABLE . " WHERE %s";

        if ( $obj->id_job !== null ) {
            $bind_values[ 'id_job' ] = $obj->id_job;
            $where_conditions[]      = "id_job = :id_job";
        }

        if ( $obj->id_segment !== null ) {
            $bind_values[ 'id_segment' ] = $obj->id_segment;
            $where_conditions[]          = "id_segment = :id_segment";
        }


        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );
        $stmt  = $this->database->getConnection()->prepare( $query );

        return $this->_fetchObject( $stmt, new Revise_ReviseStruct(), $bind_values );

    }

    public function readBySegments( $segments_id, $job_id ) {

        $prepare_str_segments_id = str_repeat( 'UNION SELECT ? ', count( $segments_id ) - 1 );

        $query = "SELECT id_job,
                                id_segment,
                                err_typing,
                                err_translation,
                                err_terminology,
                                err_language,
                                err_style,
                                original_translation
                         FROM " . self::TABLE . " JOIN (
                            SELECT ? as id_segment " . $prepare_str_segments_id . "
                         ) AS SLIST USING( id_segment )
                         WHERE id_job = ?";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $query );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, '\DataAccess\ShapelessConcreteStruct' );

        $stmt->execute( array_merge( $segments_id, [ $job_id ] ) );

        return $stmt->fetchAll();
    }

    public function atomicUpdate( Revise_ReviseStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $set_array   = [];
        $bind_values = [];
        $query       = "UPDATE " . self::TABLE . " SET %s WHERE id_job = ? AND id_segment = ?";

        $bind_values[] = $obj->id_job;
        $bind_values[] = $obj->id_segment;

        if ( $obj->err_typing !== null ) {
            $set_array[] = "err_typing = ?";
            $bind_values[] = $obj->err_typing;
        }

        if ( $obj->err_typing !== null ) {
            $set_array   = "err_translation = ?";
            $bind_values[] = $obj->err_translation;
        }

        if ( $obj->err_typing !== null ) {
            $set_array [] = "err_terminology = ?";
            $bind_values[]  = $obj->err_terminology;
        }

        if ( $obj->err_typing !== null ) {
            $set_array [] = "err_language = ?";
            $bind_values[]  = $obj->err_language;
        }

        if ( $obj->err_typing !== null ) {
            $set_array [] = "err_style = ?";
            $bind_values[]  = $obj->err_style;
        }

        if ( count( $set_array ) ) {
            $set_string = implode( ", ", $set_array );
        } else {
            throw new Exception( "Array given is empty. Please set at least one value." );
        }

        $query = sprintf( $query, $set_string );
        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( $bind_values );

        if ( $stmt->rowCount() > 0 ) {
            return $obj;
        }

        return null;
    }

    /**
     * @param Revise_ReviseStruct $input
     *
     * @return DataAccess_IDaoStruct|Revise_ReviseStruct
     * @throws Exception
     */
    public function sanitize( DataAccess_IDaoStruct $input ) {
        return parent::_sanitizeInput( $input, self::STRUCT_TYPE );
    }

    protected function _validateNotNullFields( DataAccess_IDaoStruct $obj ) {
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

    /**
     * @param DataAccess_IDaoStruct $obj
     *
     * @return bool|void
     * @throws Exception
     */
    protected function _validatePrimaryKey( DataAccess_IDaoStruct $obj ) {

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