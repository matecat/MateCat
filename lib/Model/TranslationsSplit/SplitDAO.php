<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 24/03/15
 * Time: 13.21
 */
class TranslationsSplit_SplitDAO extends DataAccess_AbstractDao {

    const TABLE = "segment_translations_splits";

    const STRUCT_TYPE = "TranslationsSplit_SplitStruct";

    /**
     * @param TranslationsSplit_SplitStruct $obj
     *
     * @return TranslationsSplit_SplitStruct|TranslationsSplit_SplitStruct[]|void
     * @throws Exception
     */
    public function read( TranslationsSplit_SplitStruct $obj ) {

        $obj = $this->sanitize( $obj );

        $where_conditions = array();
        $query            = "SELECT id_segment,
                                    id_job,
                                    source_chunk_lengths,
                                    target_chunk_lengths
                             FROM " . self::TABLE . " WHERE %s";

        if ( $obj->id_segment !== null ) {
            $where_conditions[ ] = "id_segment = " . $obj->id_segment;
        }

        if ( $obj->id_job !== null ) {
            $where_conditions[ ] = "id_job = " . $obj->id_job;
        }

        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        }
        else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );

        $arr_result = $this->con->fetch_array( $query );

        return $this->_buildResult( $arr_result );

    }

    /**
     * @param TranslationsSplit_SplitStruct $obj
     *
     * @return null|TranslationsSplit_SplitStruct|void
     * @throws Exception
     */
    public function update( TranslationsSplit_SplitStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $values_array         = array();
        $inserting_keys_array = array( 'id_segment', 'id_job' );
        $query                = "INSERT INTO " . self::TABLE . " ( %s ) VALUES ( '%s' ) ON DUPLICATE KEY UPDATE %s ";

        $values_array[ ] = (int)$obj->id_segment;
        $values_array[ ] = (int)$obj->id_job;

        if ( $obj->source_chunk_lengths !== null ) {
            $inserting_keys_array[ ] = 'source_chunk_lengths';
            $values_array[ ]         = $obj->source_chunk_lengths;
        }

        if ( $obj->target_chunk_lengths !== null ) {
            $inserting_keys_array[ ] = 'target_chunk_lengths';
            $values_array[ ]         = $obj->target_chunk_lengths;
        }

        $values_string = null;

        if ( count( $inserting_keys_array ) == count( $values_array ) && count( $values_array ) ) {
            $inserting_keys_string = implode( ", ", $inserting_keys_array );
            $values_string         = implode( "', '", $values_array );

            $update_string_array = array();
            foreach( $inserting_keys_array as $position => $key ){
                $update_string_array[] = $key . ' = VALUES( ' . $key . ' )';
            }

            $update_string = implode( ', ', $update_string_array );

        } else {
            throw new Exception( "Array given is empty. Please set at least one value." );
        }

        $query = sprintf( $query, $inserting_keys_string, $values_string, $update_string );

        $this->con->query( $query );

        if ( $this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    /**
     * @param TranslationsSplit_SplitStruct $input
     *
     * @return TranslationsSplit_SplitStruct
     * @throws Exception
     */
    public function sanitize( $input ) {
        $con = Database::obtain();
        parent::_sanitizeInput( $input, self::STRUCT_TYPE );

        $input->id_segment          = ( $input->id_segment !== null ) ? $input->id_segment : null;
        $input->id_job              = ( $input->id_job !== null ) ? $input->id_job : null;
        $input->source_chunk_lengths = ( $input->source_chunk_lengths !== null ) ? $con->escape( json_encode( $input->source_chunk_lengths ) ) : null;
        $input->target_chunk_lengths = ( $input->target_chunk_lengths !== null ) ? $con->escape( json_encode( $input->target_chunk_lengths ) ) : null;

        return $input;
    }


    /**
     * @param TranslationsSplit_SplitStruct $obj
     *
     * @return bool|void
     * @throws Exception
     */
    protected function _validatePrimaryKey( TranslationsSplit_SplitStruct $obj ) {
        if ( $obj->id_segment === null ) {
            throw new Exception( "ID segment required" );
        }

        if ( $obj->id_job === null ) {
            throw new Exception( "ID job required" );
        }
    }


    /**
     * @param $array_result array
     *
     * @return TranslationsSplit_SplitStruct|TranslationsSplit_SplitStruct[]
     */
    protected function _buildResult( $array_result ) {
        $result = array();

        foreach ( $array_result as $item ) {

            $build_arr = array(
                    'id_segment'          => (int)$item[ 'id_segment' ],
                    'id_job'              => $item[ 'id_job' ],
                    'source_chunk_lengths' => json_decode( $item[ 'source_chunk_lengths' ], true ),
                    'target_chunk_lengths' => json_decode( $item[ 'target_chunk_lengths' ], true ),
            );

            $obj = new TranslationsSplit_SplitStruct( $build_arr );

            $result[ ] = $obj;
        }

        return $result;
    }


} 