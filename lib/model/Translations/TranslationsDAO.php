<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 24/03/15
 * Time: 13.21
 */
class Translations_TranslationsDAO extends DataAccess_AbstractDao {

    const TABLE = "segment_translations_splits";

    const STRUCT_TYPE = "Translations_TranslationStruct";

    /**
     * @param Translations_TranslationStruct $obj
     *
     * @return Translations_TranslationStruct|Translations_TranslationStruct[]|void
     * @throws Exception
     */
    public function read( Translations_TranslationStruct $obj ) {

        $obj = $this->sanitize( $obj );

        $where_conditions = array();
        $query            = "SELECT id_segment,
                                    id_job,
                                    split_points_source,
                                    split_points_target
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

        $this->_checkForErrors();

        return $this->_buildResult( $arr_result );

    }

    /**
     * @param Translations_TranslationStruct $obj
     *
     * @return null|Translations_TranslationStruct|void
     * @throws Exception
     */
    public function update( Translations_TranslationStruct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $values_array         = array();
        $inserting_keys_array = array( 'id_segment', 'id_job' );
        $query                = "INSERT INTO " . self::TABLE . " ( %s ) VALUES ( '%s' ) ON DUPLICATE KEY UPDATE %s ";

        $values_array[ ] = (int)$obj->id_segment;
        $values_array[ ] = (int)$obj->id_job;

        if ( $obj->split_points_source !== null ) {
            $inserting_keys_array[ ] = 'split_points_source';
            $values_array[ ]         = $this->con->escape( $obj->split_points_source );
        }

        if ( $obj->split_points_target !== null ) {
            $inserting_keys_array[ ] = 'split_points_source';
            $values_array[ ]         = $this->con->escape( $obj->split_points_target );
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

        $this->_checkForErrors();

        if ( $this->con->affected_rows > 0 ) {
            return $obj;
        }

        return null;
    }

    /**
     * @param Translations_TranslationStruct $input
     *
     * @return Translations_TranslationStruct
     * @throws Exception
     */
    public function sanitize( $input ) {
        $con = Database::obtain();
        parent::_sanitizeInput( $input, self::STRUCT_TYPE );

        $input->id_segment          = ( $input->id_segment !== null ) ? $input->id_segment : null;
        $input->id_job              = ( $input->id_job !== null ) ? $input->id_job : null;
        $input->split_points_source = ( $input->split_points_source !== null ) ? $con->escape( json_encode( $input->split_points_source ) ) : null;
        $input->split_points_target = ( $input->split_points_target !== null ) ? $con->escape( json_encode( $input->split_points_target ) ) : null;

        return $input;
    }


    /**
     * @param Translations_TranslationStruct $obj
     *
     * @return bool|void
     * @throws Exception
     */
    protected function _validatePrimaryKey( Translations_TranslationStruct $obj ) {
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
     * @return Translations_TranslationStruct|Translations_TranslationStruct[]
     */
    protected function _buildResult( $array_result ) {
        $result = array();

        foreach ( $array_result as $item ) {

            $build_arr = array(
                    'id_segment'          => (int)$item[ 'id_segment' ],
                    'id_job'              => $item[ 'id_job' ],
                    'split_points_source' => json_decode( $item[ 'split_points_source' ], true ),
                    'split_points_target' => json_decode( $item[ 'split_points_target' ], true ),
            );

            $obj = new Translations_TranslationStruct( $build_arr );

            $result[ ] = $obj;
        }

        return $result;
    }


} 