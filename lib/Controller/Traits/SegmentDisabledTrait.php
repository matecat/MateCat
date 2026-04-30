<?php

namespace Controller\Traits;

use Model\DataAccess\DaoCacheTrait;
use Model\Segments\SegmentMetadataDao;
use Exception;
use PDOException;
use ReflectionException;

trait SegmentDisabledTrait
{
    use DaoCacheTrait;

    const CACHE_TTL = 3600;

    /**
     * Removes the "translation_disabled" metadata for a given segment and clears the related cache.
     *
     * @param int $id_segment The unique identifier of the segment to clear metadata and cache for.
     * @return void
     * @throws ReflectionException
     * @throws PDOException
     */
    protected function destroySegmentDisabledCache(int $id_job, int $id_segment): void
    {
        SegmentMetadataDao::delete($id_segment, 'translation_disabled');
        SegmentMetadataDao::destroyCache($id_segment, 'translation_disabled');
        SegmentMetadataDao::destroyGetAllCache($id_segment);

        $cache = $this->cacheKeyAndQuery($id_job, $id_segment);
        $this->cacheInit();
        $this->_deleteCacheByKey($cache['key'], false);
    }

    /**
     * Checks if a specific segment is disabled for a given job.
     *
     * @param int $id_job The unique identifier of the job.
     * @param int $id_segment The unique identifier of the segment.
     * @return bool Returns true if the segment is disabled, false otherwise.
     * @throws ReflectionException
     * @throws Exception
     */
    protected function isSegmentDisabled(int $id_job, int $id_segment): bool
    {
        $cache = $this->cacheKeyAndQuery($id_job, $id_segment);
        $this->cacheInit();
        $cachedValue = $this->_getFromCacheMap($cache['key'], $cache['query']);

        // retrieve from cache fails, we need to check the database and update the cache accordingly
        if (empty($cachedValue)) {
            $metadataList = SegmentMetadataDao::get($id_segment, 'translation_disabled', 0);
            $metadata = $metadataList[0] ?? null;
            $isDisabled = (($metadata->meta_value ?? '0') === '1');
            $this->_setInCacheMap($cache['key'], $cache['query'], [$isDisabled ? 1 : 0]);

            return $isDisabled;
        }

        return $cachedValue == [1];
    }

    /**
     * Saves a segment's disabled state in the cache with a specific key and value.
     *
     * @param int $id_job The identifier for the job associated with the segment.
     * @param int $id_segment The identifier for the segment to be marked as disabled in the cache.
     *
     * @return void
     * @throws ReflectionException
     */
    protected function saveSegmentDisabledInCache(int $id_job, int $id_segment): void
    {
        $cache = $this->cacheKeyAndQuery($id_job, $id_segment);
        $this->cacheInit();
        $this->_setInCacheMap($cache['key'], $cache['query'], [1]);
    }

    /**
     * Generates a cache key and query string for a specific segment.
     *
     * @return array{key: string, query: string}
     */
    private function cacheKeyAndQuery(int $id_job, int $id_segment): array
    {
        $cacheKey = 'segment_is_disabled_' . $id_job . '_' . $id_segment;
        $cachedQuery = "__SEGMENT_IS_DISABLED__" . $id_job . "_" . $id_segment;

        return [
            'key' => $cacheKey,
            'query' => $cachedQuery,
        ];
    }

    /**
     * Initializes the cache system by setting the time-to-live (TTL) and establishing the cache connection.
     *
     * @return void
     * @throws ReflectionException
     */
    private function cacheInit(): void
    {
        $this->setCacheTTL(self::CACHE_TTL);
        $this->_cacheSetConnection();
    }
}
