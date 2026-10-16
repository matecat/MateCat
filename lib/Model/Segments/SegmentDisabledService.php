<?php

namespace Model\Segments;

use Exception;
use PDOException;
use ReflectionException;
use TypeError;

/**
 * Service for managing segment disabled state.
 *
 * Delegates to {@see SegmentMetadataDao} for persistence and caching. Reads are
 * cached by the DAO (1-week TTL), and every write through the DAO evicts them,
 * so callers read through the cache and never override the TTL.
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
        $metadata = $this->segmentMetadataDao->get($id_segment, SegmentMetadataMarshaller::TRANSLATION_DISABLED->value);

        return $metadata !== null && $metadata->meta_value === '1';
    }

    /**
     * Disable translation for a segment.
     *
     * Idempotent — safe to call multiple times. The row is upserted, so disabling a segment that is
     * already disabled cannot fail on the unique key, and the write evicts every address it is read at.
     *
     * @param int $id_segment
     * @param int $id_project
     *
     * @return void
     * @throws ReflectionException
     * @throws PDOException
     * @throws TypeError
     * @throws Exception
     */
    public function disable(int $id_segment, int $id_project): void
    {
        $this->segmentMetadataDao->upsert($id_segment, SegmentMetadataMarshaller::TRANSLATION_DISABLED->value, '1', $id_project);
    }

    /**
     * Enable translation for a previously disabled segment.
     *
     * Deletes the metadata row, which evicts every address it was read at.
     * Safe to call even if the segment is not currently disabled.
     *
     * @param int $id_segment
     * @param int $id_project
     *
     * @return void
     * @throws ReflectionException
     * @throws PDOException
     * @throws TypeError
     * @throws Exception
     */
    public function enable(int $id_segment, int $id_project): void
    {
        $this->segmentMetadataDao->delete($id_segment, SegmentMetadataMarshaller::TRANSLATION_DISABLED->value, $id_project);
    }
}
