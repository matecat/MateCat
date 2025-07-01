<?php

namespace Model\TranslationsSplit;

use Database;
use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\IDaoStruct;


/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 24/03/15
 * Time: 13.21
 */
class SplitDAO extends AbstractDao {

    const TABLE = "segment_translations_splits";

    const STRUCT_TYPE = SegmentSplitStruct::class;

    /**
     * @param SegmentSplitStruct $obj
     *
     * @return SegmentSplitStruct[]
     * @throws Exception
     */
    public function read( SegmentSplitStruct $obj ): array {

        $where_conditions = [];
        $values           = [];

        $query = "SELECT id_segment,
                                    id_job,
                                    source_chunk_lengths,
                                    target_chunk_lengths
                             FROM " . self::TABLE . " WHERE ";

        if ( $obj->id_segment !== null ) {
            $where_conditions[]     = "id_segment = :id_segment";
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

        $result = $this->_fetchObjectMap( $stmt, SegmentSplitStruct::class, $values );

        return $this->_buildResult( $result );

    }

    /**
     * @param SegmentSplitStruct $obj
     *
     * @return null|SegmentSplitStruct
     * @throws Exception
     */
    public function atomicUpdate( SegmentSplitStruct $obj ): ?SegmentSplitStruct {

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
     * @param SegmentSplitStruct $input
     *
     * @return SegmentSplitStruct
     * @throws Exception
     */
    public function sanitize( IDaoStruct $input ) {

        parent::_sanitizeInput( $input, self::STRUCT_TYPE );

        $input->id_segment           = ( $input->id_segment !== null ) ? $input->id_segment : null;
        $input->id_job               = ( $input->id_job !== null ) ? $input->id_job : null;
        $input->source_chunk_lengths = ( $input->source_chunk_lengths !== null ) ? json_encode( $input->source_chunk_lengths ) : null;
        $input->target_chunk_lengths = ( $input->target_chunk_lengths !== null ) ? json_encode( $input->target_chunk_lengths ) : null;

        return $input;
    }


    /**
     * @param SegmentSplitStruct $obj
     *
     * @return void
     * @throws Exception
     */
    protected function _validatePrimaryKey( IDaoStruct $obj ): void {

        /**
         * @var $obj SegmentSplitStruct
         */
        if ( $obj->id_segment === null ) {
            throw new Exception( "ID segment required" );
        }

        if ( $obj->id_job === null ) {
            throw new Exception( "ID job required" );
        }
    }


    /**
     * @param $array_result SegmentSplitStruct[]
     *
     * @return SegmentSplitStruct[]
     */
    protected function _buildResult( array $array_result ): array {
        foreach ( $array_result as $item ) {
            $item->source_chunk_lengths = json_decode( $item->source_chunk_lengths, true );
            $item->target_chunk_lengths = json_decode( $item->target_chunk_lengths, true );
        }

        return $array_result;
    }


} 