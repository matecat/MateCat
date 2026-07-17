<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/10/16
 * Time: 11:08 AM
 */

namespace Plugins\Features\SegmentFilter\Model;

use DivisionByZeroError;
use Exception;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use ReflectionException;

class SegmentFilterModel
{

    /**
     * @var JobStruct
     */
    private JobStruct $chunk;

    /**
     * @var FilterDefinition
     */
    private FilterDefinition $filter;
    private SegmentFilterDao $segmentFilterDao;

    /**
     * SegmentFilterModel constructor.
     *
     * @param JobStruct $chunk
     * @param FilterDefinition $filter
     * @param SegmentFilterDao $segmentFilterDao
     */
    public function __construct(JobStruct $chunk, FilterDefinition $filter, SegmentFilterDao $segmentFilterDao)
    {
        $this->chunk = $chunk;
        $this->filter = $filter;
        $this->segmentFilterDao = $segmentFilterDao;
    }

    /**
     * @return ShapelessConcreteStruct[]
     * @throws ReflectionException
     * @throws Exception
     * @throws DivisionByZeroError
     */
    public function getSegmentList(): array
    {
        if ($this->filter->isSampled()) {
            return $this->segmentFilterDao->findSegmentIdsForSample($this->chunk, $this->filter);
        }

        return $this->segmentFilterDao->findSegmentIdsBySimpleFilter($this->chunk, $this->filter);
    }

}