<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/10/16
 * Time: 11:08 AM
 */

namespace Features\SegmentFilter\Model;

class SegmentFilterModel {

    /**
     * @var \Chunks_ChunkStruct
     */
    private $chunk;
    /**
     * @var array
     */
    private $filter;

    public function __construct( \Chunks_ChunkStruct $chunk, FilterDefinition $filter ) {
        $this->chunk  = $chunk;
        $this->filter = $filter;
        $this->chunk->getProject()->getFeatures()->filter('filterSegmentFilter', $filter, $chunk) ;
    }

    /**
     * @return null|\Translations_SegmentTranslationStruct[]
     * @throws \Exception
     */
    public function getSegmentList() {
        $result = null;

        if ( $this->filter->isSampled() ) {
            $result = SegmentFilterDao::findSegmentIdsForSample( $this->chunk, $this->filter );
        } else {
            $result = SegmentFilterDao::findSegmentIdsBySimpleFilter( $this->chunk, $this->filter );
        }

        return $result;
    }

}