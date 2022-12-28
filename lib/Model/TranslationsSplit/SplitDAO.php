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
     * @return DataAccess_IDaoStruct[]|TranslationsSplit_SplitStruct[]
     * @throws Exception
     */
    public function read( TranslationsSplit_SplitStruct $obj ) {

        $where_conditions = [];
        $values           = [];

        $query            = "SELECT id_segment,
                                    id_job,
                                    source_chunk_lengths,
                                    target_chunk_lengths
                             FROM " . self::TABLE . " WHERE ";

        if ( $obj->id_segment !== null ) {
            $where_conditions[] = "id_segment = :id_segment";
            $values[ 'id_segment' ] = $obj->id_segment;
        }

        if ( $obj->id_job !== null ) {
            $where_conditions[] = "id_job = :id_job";
            $values[ 'id_job' ] = $obj->id_job;
        }

        if ( count( $where_conditions ) ) {
            $query .= implode( " AND ", $where_conditions );
        } else {
            throw new Exception( "Where condition needed." );
        }


        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $query );

        $result = $this->_fetchObject( $stmt, $obj, $values );

        return $this->_buildResult( $result );

    }

    /**
     * @param TranslationsSplit_SplitStruct $obj
     *
     * @return null|TranslationsSplit_SplitStruct
     * @throws Exception
     */
    public function atomicUpdate( TranslationsSplit_SplitStruct $obj ) {

        $obj = $this->sanitize( $obj );

        $this->_validatePrimaryKey( $obj );

        $res = self::insertStruct( $obj, [
                'no_nulls'            => true,
                'on_duplicate_update' => [
                        'id_segment'           => 'value',
                        'id_job'               => 'value',
                        'source_chunk_lengths' => 'value',
                        'target_chunk_lengths' => 'value'
                ]
        ] );

        if ( $res > 0 ) {
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
    public function sanitize( DataAccess_IDaoStruct $input ) {

        parent::_sanitizeInput( $input, self::STRUCT_TYPE );

        $input->id_segment           = ( $input->id_segment !== null ) ? $input->id_segment : null;
        $input->id_job               = ( $input->id_job !== null ) ? $input->id_job : null;
        $input->source_chunk_lengths = ( $input->source_chunk_lengths !== null ) ? json_encode( $input->source_chunk_lengths ) : null;
        $input->target_chunk_lengths = ( $input->target_chunk_lengths !== null ) ? json_encode( $input->target_chunk_lengths ) : null;

        return $input;
    }


    /**
     * @param TranslationsSplit_SplitStruct $obj
     *
     * @return bool|void
     * @throws Exception
     */
    protected function _validatePrimaryKey( DataAccess_IDaoStruct $obj ) {

        /**
         * @var $obj TranslationsSplit_SplitStruct
         */
        if ( $obj->id_segment === null ) {
            throw new Exception( "ID segment required" );
        }

        if ( $obj->id_job === null ) {
            throw new Exception( "ID job required" );
        }
    }


    /**
     * @param $array_result DataAccess_IDaoStruct[]|TranslationsSplit_SplitStruct[]
     *
     * @return DataAccess_IDaoStruct[]|TranslationsSplit_SplitStruct[]
     */
    protected function _buildResult( $array_result ) {
        foreach ( $array_result as $item ) {
            $item->source_chunk_lengths = json_decode( $item->source_chunk_lengths, true );
            $item->target_chunk_lengths = json_decode( $item->target_chunk_lengths, true );
        }
        return $array_result;
    }


} 