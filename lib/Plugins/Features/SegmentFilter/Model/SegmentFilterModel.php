<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/10/16
 * Time: 11:08 AM
 */

namespace Features\SegmentFilter\Model;

use DataAccess\ShapelessConcreteStruct;
use Exception;
use Jobs_JobStruct;

class SegmentFilterModel {

    /**
     * @var Jobs_JobStruct
     */
    private Jobs_JobStruct $chunk;

    /**
     * @var FilterDefinition
     */
    private FilterDefinition $filter;

    /**
     * SegmentFilterModel constructor.
     *
     * @param Jobs_JobStruct   $chunk
     * @param FilterDefinition $filter
     *
     * @throws Exception
     */
    public function __construct( Jobs_JobStruct $chunk, FilterDefinition $filter ) {
        $this->chunk  = $chunk;
        $this->filter = $filter;
    }

    /**
     * @return ShapelessConcreteStruct[]
     * @throws Exception
     */
    public function getSegmentList(): array {

        if ( $this->filter->isSampled() ) {
            $result = SegmentFilterDao::findSegmentIdsForSample( $this->chunk, $this->filter );
        } else {
            $result = SegmentFilterDao::findSegmentIdsBySimpleFilter( $this->chunk, $this->filter );
        }

        return $result;
    }

}