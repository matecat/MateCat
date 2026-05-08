<?php

namespace Model\Segments;

use Exception;
use PDOException;
use ReflectionException;

/**
 * Service for managing segment disabled state.
 *
 * Delegates to {@see SegmentMetadataDao} for persistence.
 * The DAO's built-in cache (via _fetchObjectMap, 1-week TTL) handles
 * all caching — no additional cache layer is needed.
 */
class SegmentDisabledService
{
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
        $metadata = SegmentMetadataDao::get(
            $id_segment,
            SegmentMetadataMarshaller::TRANSLATION_DISABLED->value
        );

        return $metadata !== null && $metadata->meta_value === '1';
    }

    /**
     * Disable translation for a segment.
     *
     * Internally calls {@see SegmentMetadataDao::setTranslationDisabled()},
     * which persists the row and busts all related DAO caches via save().
     *
     * @param int $id_segment
     *
     * @return void
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    public function disable(int $id_segment): void
    {
        SegmentMetadataDao::setTranslationDisabled($id_segment);
    }

    /**
     * Enable translation for a previously disabled segment.
     *
     * Deletes the metadata row and busts all related DAO caches.
     * Safe to call even if the segment is not currently disabled.
     *
     * @param int $id_segment
     *
     * @return void
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    public function enable(int $id_segment): void
    {
        $key = SegmentMetadataMarshaller::TRANSLATION_DISABLED->value;
        SegmentMetadataDao::delete($id_segment, $key);
        SegmentMetadataDao::destroyGetCache($id_segment, $key);
        SegmentMetadataDao::destroyGetAllCache($id_segment);
        SegmentMetadataDao::destroyGetBySegmentIdsCache($key);
    }
}
