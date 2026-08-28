<?php

namespace Model\Segments;

use Exception;
use PDOException;
use ReflectionException;
use TypeError;

/**
 * Service for managing segment disabled state.
 *
 * Delegates to {@see SegmentMetadataDao} for persistence.
 * The DAO's built-in cache (via _fetchObjectMap, 1-week TTL) handles
 * all caching — no additional cache layer is needed.
 */
class SegmentDisabledService
{
    private SegmentMetadataDao $segmentMetadataDao;

    public function __construct(SegmentMetadataDao $segmentMetadataDao)
    {
        $this->segmentMetadataDao = $segmentMetadataDao;
    }

    /**
     * Check whether a segment is disabled for translation.
     *
     * @param int $id_segment
     *
     * @return bool
     * @throws ReflectionException
     * @throws Exception
     */
    public function isDisabled(int $id_segment): bool
    {
        $metadata = $this->segmentMetadataDao->get(
            $id_segment,
            'translation_disabled'
        );

        return $metadata !== null && $metadata->meta_value === '1';
    }

    /**
     * Disable translation for a segment.
     *
     * Idempotent — safe to call multiple times. If already disabled, returns immediately.
     * Persists the row via save(), which evicts every address it is read at.
     *
     * @param int $id_segment
     *
     * @return void
     * @throws PDOException
     * @throws Exception
     * @throws TypeError
     */
    public function disable(int $id_segment): void
    {
        if ($this->isDisabled($id_segment)) {
            return;
        }

        $metadata = new SegmentMetadataStruct();
        $metadata->id_segment = $id_segment;
        $metadata->meta_key = 'translation_disabled';
        $metadata->meta_value = "1";

        $this->segmentMetadataDao->save($metadata);
    }

    /**
     * Enable translation for a previously disabled segment.
     *
     * Deletes the metadata row, which evicts every address it was read at.
     * Safe to call even if the segment is not currently disabled.
     *
     * @param int $id_segment
     *
     * @return void
     * @throws ReflectionException
     * @throws PDOException
     * @throws TypeError
     * @throws Exception
     */
    public function enable(int $id_segment): void
    {
        $this->segmentMetadataDao->delete($id_segment, 'translation_disabled');
    }
}
