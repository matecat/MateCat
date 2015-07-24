<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 29/01/15
 * Time: 11.31
 */
class ErrorCount_ErrorCountDAO extends DataAccess_AbstractDao {

    const TABLE = "jobs";

    const STRUCT_TYPE = "ErrorCount_Struct";

    public function read( ErrorCount_Struct $obj ) {
        $obj = $this->sanitize( $obj );

        $where_conditions = array();
        $query            = "SELECT id, password,
                                revision_stats_typing_min,
                                revision_stats_translations_min,
                                revision_stats_terminology_min,
                                revision_stats_language_quality_min,
                                revision_stats_style_min,
                                revision_stats_typing_maj,
                                revision_stats_translations_maj,
                                revision_stats_terminology_maj,
                                revision_stats_language_quality_maj,
                                revision_stats_style_maj
                             FROM " . self::TABLE . " WHERE %s";

        if ( $obj->getIdJob() !== null ) {
            $where_conditions[ ] = "id = " . $obj->getIdJob();
        }

        if ( $obj->getJobPassword() !== null ) {
            $where_conditions[ ] = "password = '" . $this->con->escape($obj->getJobPassword())."'";
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

    /**
     * @param ErrorCount_Struct $obj
     *
     * @return ErrorCount_Struct|null
     * @throws Exception
     */
    public function update( ErrorCount_Struct $obj ) {

        /**
         * @var $obj ErrorCount_Struct
         */
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $set_array        = array();
        $where_conditions = array();
        $query            = "UPDATE " . self::TABLE . " SET %s WHERE %s";

        $where_conditions[ ] = "id = " . (int)$obj->getIdJob();
        $where_conditions[ ] = "password = '" . $this->con->escape( $obj->getJobPassword() ) . "'";

        //WARNING: cannot check if object's values are correctly set, because they have a default value
        //the update will be done for all the values
        $condition    = "%s = %s + %d";
        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_typing_min',
                'revision_stats_typing_min',
                $obj->getTypingMin()
        );
        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_typing_maj',
                'revision_stats_typing_maj',
                $obj->getTypingMaj()
        );

        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_translations_min',
                'revision_stats_translations_min',
                $obj->getTranslationMin() );
        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_translations_maj',
                'revision_stats_translations_maj',
                $obj->getTranslationMaj() );

        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_terminology_min',
                'revision_stats_terminology_min',
                $obj->getTerminologyMin() );
        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_terminology_maj',
                'revision_stats_terminology_maj',
                $obj->getTerminologyMaj() );

        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_language_quality_min',
                'revision_stats_language_quality_min',
                $obj->getLanguageMin() );
        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_language_quality_maj',
                'revision_stats_language_quality_maj',
                $obj->getLanguageMaj() );

        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_style_min',
                'revision_stats_style_min',
                $obj->getStyleMin() );
        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_style_maj',
                'revision_stats_style_maj',
                $obj->getStyleMaj() );

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

    /**
     * @param ErrorCount_Struct $input
     *
     * @return ErrorCount_Struct
     * @throws Exception
     */
    public function sanitize( $input ) {
        return parent::_sanitizeInput( $input, self::STRUCT_TYPE );
    }

    protected function _validatePrimaryKey( ErrorCount_Struct $obj ) {
        $id   = $obj->getIdJob();
        $pass = $obj->getJobPassword();

        return !empty( $id ) && !empty( $pass );
    }


    protected function _buildResult( $array_result ) {
        $result = array();

        foreach ( $array_result as $item ) {

            $obj = new ErrorCount_Struct();
            $obj->setIdJob( $item[ 'id' ] )
                    ->setJobPassword( $item[ 'password' ] )
                    ->setTypingMin(      $item[ 'revision_stats_typing_min' ] )
                    ->setTerminologyMin( $item[ 'revision_stats_terminology_min' ] )
                    ->setTranslationMin( $item[ 'revision_stats_translations_min' ] )
                    ->setLanguageMin(    $item[ 'revision_stats_language_quality_min' ] )
                    ->setStyleMin(       $item[ 'revision_stats_style_min' ] )

                    ->setTypingMaj(      $item[ 'revision_stats_typing_maj' ] )
                    ->setTerminologyMaj( $item[ 'revision_stats_terminology_maj' ] )
                    ->setTranslationMaj( $item[ 'revision_stats_translations_maj' ] )
                    ->setLanguageMaj(    $item[ 'revision_stats_language_quality_maj' ] )
                    ->setStyleMaj(       $item[ 'revision_stats_style_maj' ] );

            $result[ ] = $obj;
        }

        return $result;
    }


} 