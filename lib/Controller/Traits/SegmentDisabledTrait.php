<?php

namespace Controller\Traits;

use Model\DataAccess\DaoCacheTrait;
use ReflectionException;

trait SegmentDisabledTrait
{
    use DaoCacheTrait;

    /**
     * Checks if a specific segment is disabled for a given job.
     *
     * @param string $id_job The unique identifier of the job.
     * @param string $id_segment The unique identifier of the segment.
     * @return bool Returns true if the segment is disabled, false otherwise.
     * @throws ReflectionException
     */
    protected function isSegmentDisabled(string $id_job, string $id_segment): bool
    {
        $cacheKey = 'segment_is_disabled_' . $id_job . '_' . $id_segment;
        $cachedQuery = "__SEGMENT_IS_DISABLED__" . $id_job . "_" . $id_segment . "";
        $cachedValue = $this->_getFromCacheMap($cacheKey, $cachedQuery);

        if(empty($cachedValue)){
            return false;
        }

        return $cachedValue == [1];
    }
}
