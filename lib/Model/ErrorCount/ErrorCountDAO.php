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

    /**
     * @param DataAccess_IDaoStruct $obj
     *
     * @return array
     * @throws Exception
     */
    public function getReadQuery( DataAccess_IDaoStruct $obj ) {
        /**
         * @var $obj ErrorCount_Struct
         */
        $obj = $this->sanitize( $obj );

        $where_conditions = [];
        $bind_values      = [];

        $query = "SELECT id, password,
                                revision_stats_typing_min as typing_min,
                                revision_stats_translations_min as translation_min,
                                revision_stats_terminology_min as terminology_min,
                                revision_stats_language_quality_min as language_min,
                                revision_stats_style_min as style_min,
                                revision_stats_typing_maj as typing_maj,
                                revision_stats_translations_maj as translation_maj,
                                revision_stats_terminology_maj as terminology_maj,
                                revision_stats_language_quality_maj as language_maj,
                                revision_stats_style_maj as style_maj
                             FROM " . self::TABLE . " WHERE %s ";

        if ( $obj->getIdJob() !== null ) {
            $where_conditions[]  = "id = :id";
            $bind_values[ 'id' ] = $obj->getIdJob();
        }

        if ( $obj->getJobPassword() !== null ) {
            $where_conditions[]        = "password = :password";
            $bind_values[ 'password' ] = $obj->getJobPassword();
        }

        if ( count( $where_conditions ) ) {
            $where_string = implode( " AND ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }

        $query = sprintf( $query, $where_string );

        return [ $query, $bind_values ];

    }

    /**
     * @param DataAccess_IDaoStruct $obj
     *
     * @return DataAccess_IDaoStruct[]|ErrorCount_Struct[]
     * @throws Exception
     */
    public function read( DataAccess_IDaoStruct $obj ) {
        list( $query, $bind_values ) = $this->getReadQuery( $obj );
        $stmt = $this->database->getConnection()->prepare( $query );

        return $this->_fetchObject( $stmt, new ErrorCount_Struct(), $bind_values );
    }

    /**
     * @param DataAccess_IDaoStruct $obj
     *
     * @throws Exception
     */
    public function cleanErrorCache( DataAccess_IDaoStruct $obj ) {
        list( $query, $bind_values ) = $this->getReadQuery( $obj );
        $stmt = $this->database->getConnection()->prepare( $query );
        $this->_destroyObjectCache( $stmt, $bind_values );
    }

    /**
     * @param DataAccess_IDaoStruct $obj
     *
     * @return ErrorCount_Struct|null
     * @throws Exception
     */
    public function atomicUpdate( DataAccess_IDaoStruct $obj ) {

        /**
         * @var $obj ErrorCount_Struct
         */
        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $query = "UPDATE " . self::TABLE . " SET 
                revision_stats_typing_min = revision_stats_typing_min + ?, 
                revision_stats_typing_maj = revision_stats_typing_maj + ?, 
                revision_stats_translations_min = revision_stats_translations_min + ?, 
                revision_stats_translations_maj = revision_stats_translations_maj + ?, 
                revision_stats_terminology_min = revision_stats_terminology_min + ?, 
                revision_stats_terminology_maj = revision_stats_terminology_maj + ?, 
                revision_stats_language_quality_min = revision_stats_language_quality_min + ?, 
                revision_stats_language_quality_maj = revision_stats_language_quality_maj + ?, 
                revision_stats_style_min = revision_stats_style_min + ?, 
                revision_stats_style_maj = revision_stats_style_maj + ?
         WHERE id = ? AND password = ?
         ";

        $bind_values   = [];
        $bind_values[] = $obj->getTypingMin();
        $bind_values[] = $obj->getTypingMaj();
        $bind_values[] = $obj->getTranslationMin();
        $bind_values[] = $obj->getTranslationMaj();
        $bind_values[] = $obj->getTerminologyMin();
        $bind_values[] = $obj->getTerminologyMaj();
        $bind_values[] = $obj->getLanguageMin();
        $bind_values[] = $obj->getLanguageMaj();
        $bind_values[] = $obj->getStyleMin();
        $bind_values[] = $obj->getStyleMaj();
        $bind_values[] = (int)$obj->getIdJob();
        $bind_values[] = $obj->getJobPassword();

        $stmt = $this->database->getConnection()->prepare( $query );
        $stmt->execute( $bind_values );

        if ( $stmt->rowCount() > 0 ) {
            return $obj;
        }

        return null;
    }

    /**
     * @param ErrorCount_Struct $input
     *
     * @return ErrorCount_Struct|DataAccess_IDaoStruct
     * @throws Exception
     */
    public function sanitize( DataAccess_IDaoStruct $input ) {
        return parent::_sanitizeInput( $input, self::STRUCT_TYPE );
    }

    protected function _validatePrimaryKey( DataAccess_IDaoStruct $obj ) {
        /**
         * @var $obj ErrorCount_Struct
         */
        $id   = $obj->getIdJob();
        $pass = $obj->getJobPassword();

        return !empty( $id ) && !empty( $pass );
    }


    protected function _buildResult( $array_result ) {
        $result = [];

        foreach ( $array_result as $item ) {

            $obj = new ErrorCount_Struct();
            $obj->setIdJob( $item[ 'id' ] )
                    ->setJobPassword( $item[ 'password' ] )
                    ->setTypingMin( $item[ 'revision_stats_typing_min' ] )
                    ->setTerminologyMin( $item[ 'revision_stats_terminology_min' ] )
                    ->setTranslationMin( $item[ 'revision_stats_translations_min' ] )
                    ->setLanguageMin( $item[ 'revision_stats_language_quality_min' ] )
                    ->setStyleMin( $item[ 'revision_stats_style_min' ] )
                    ->setTypingMaj( $item[ 'revision_stats_typing_maj' ] )
                    ->setTerminologyMaj( $item[ 'revision_stats_terminology_maj' ] )
                    ->setTranslationMaj( $item[ 'revision_stats_translations_maj' ] )
                    ->setLanguageMaj( $item[ 'revision_stats_language_quality_maj' ] )
                    ->setStyleMaj( $item[ 'revision_stats_style_maj' ] );

            $result[] = $obj;
        }

        return $result;
    }


} 