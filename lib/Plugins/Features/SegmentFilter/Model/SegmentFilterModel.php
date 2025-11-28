<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/10/16
 * Time: 11:08 AM
 */

namespace Plugins\Features\SegmentFilter\Model;

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

    /**
     * SegmentFilterModel constructor.
     *
     * @param JobStruct        $chunk
     * @param FilterDefinition $filter
     *
     * @throws Exception
     */
    public function __construct(JobStruct $chunk, FilterDefinition $filter)
    {
        $this->chunk  = $chunk;
        $this->filter = $filter;
    }

    /**
     * @return ShapelessConcreteStruct[]
     * @throws ReflectionException
     * @throws Exception
     */
    public function getSegmentList(): array
    {
        if ($this->filter->isSampled()) {
            $result = SegmentFilterDao::findSegmentIdsForSample($this->chunk, $this->filter);
        } else {
            $result = SegmentFilterDao::findSegmentIdsBySimpleFilter($this->chunk, $this->filter);
        }

        return $result;
    }

}