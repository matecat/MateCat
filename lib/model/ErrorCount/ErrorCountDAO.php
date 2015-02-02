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
                                    revision_stats_typing,
                                    revision_stats_translations,
                                    revision_stats_terminology,
                                    revision_stats_language_quality,
                                    revision_stats_style
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

        $this->_checkForErrors();

        return $this->_buildResult( $arr_result );
    }

    public function update( ErrorCount_Struct $obj ) {
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $set_array        = array();
        $where_conditions = array();
        $query            = "UPDATE " . self::TABLE . " SET %s WHERE %s";

        $where_conditions[ ] = "id = " . $obj->getIdJob();
        $where_conditions[ ] = "password = '" . $this->con->escape( $obj->getJobPassword() ) . "'";

        //WARNING: cannot check if object's values are correctly set, because they have a default value
        //the update will be done for all the values
        $condition    = "%s = %s + %d";
        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_typing',
                'revision_stats_typing',
                $obj->getTyping()
        );
        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_translations',
                'revision_stats_translations',
                $obj->getTranslation() );
        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_terminology',
                'revision_stats_terminology',
                $obj->getTerminology() );
        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_language_quality',
                'revision_stats_language_quality',
                $obj->getQuality() );
        $set_array[ ] = sprintf(
                $condition,
                'revision_stats_style',
                'revision_stats_style',
                $obj->getStyle() );

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

    /**
     * @param ErrorCount_Struct $input
     *
     * @return ErrorCount_Struct
     * @throws Exception
     */
    public static function sanitize( $input ) {
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
                    ->setTyping(      $item[ 'revision_stats_typing' ] )
                    ->setTerminology( $item[ 'revision_stats_terminology' ] )
                    ->setTranslation( $item[ 'revision_stats_translations' ] )
                    ->setQuality(     $item[ 'revision_stats_language_quality' ] )
                    ->setStyle(       $item[ 'revision_stats_style' ] );

            $result[ ] = $obj;
        }

        return $result;
    }


} 