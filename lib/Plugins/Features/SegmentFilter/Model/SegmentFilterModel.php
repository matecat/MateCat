<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/10/16
 * Time: 11:08 AM
 */

namespace Features\SegmentFilter\Model;

use Exception;
use Model\Jobs\JobStruct;
use Model\Translations\SegmentTranslationStruct;

class SegmentFilterModel {

    /**
     * @var JobStruct
     */
    private $chunk;

    /**
     * @var FilterDefinition
     */
    private $filter;

    /**
     * SegmentFilterModel constructor.
     *
     * @param JobStruct        $chunk
     * @param FilterDefinition $filter
     *
     * @throws Exception
     */
    public function __construct( JobStruct $chunk, FilterDefinition $filter ) {
        $this->chunk  = $chunk;
        $this->filter = $filter;
    }

    /**
     * @return null|\Model\Translations\SegmentTranslationStruct[]
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