<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/10/16
 * Time: 11:08 AM
 */

namespace Features\SegmentFilter\Model;

use Chunks_ChunkStruct;
use Exception;
use Translations_SegmentTranslationStruct;

class SegmentFilterModel {

    /**
     * @var Chunks_ChunkStruct
     */
    private $chunk;

    /**
     * @var FilterDefinition
     */
    private $filter;

    /**
     * SegmentFilterModel constructor.
     *
     * @param Chunks_ChunkStruct $chunk
     * @param FilterDefinition    $filter
     *
     * @throws Exception
     */
    public function __construct( Chunks_ChunkStruct $chunk, FilterDefinition $filter ) {
        $this->chunk  = $chunk;
        $this->filter = $filter;
    }

    /**
     * @return null|Translations_SegmentTranslationStruct[]
     * @throws Exception
     */
    public function getSegmentList() {

        if ( $this->filter->isSampled() ) {
            $result = SegmentFilterDao::findSegmentIdsForSample( $this->chunk, $this->filter );
        } else {
            $result = SegmentFilterDao::findSegmentIdsBySimpleFilter( $this->chunk, $this->filter );
        }

        return $result;
    }

}