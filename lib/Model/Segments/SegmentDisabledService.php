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
     * $ttl overrides the DAO's default (7-day) cache TTL for this read. A concurrent read that
     * started before a disable/enable write commits can still cache a stale result after that
     * write's eviction runs, silently re-poisoning the cache for up to 7 days. Callers where that
     * matters (save-enforcement, idempotency checks guarding a unique-key insert) should pass 0;
     * callers where an occasional stale read is harmless can leave it at the cached default.
     *
     * @param int $id_segment
     * @param int|null $ttl
     *
     * @return bool
     * @throws ReflectionException
     * @throws Exception
     */
    public function isDisabled(int $id_segment, ?int $ttl = null): bool
    {
        $metadata = $ttl === null
            ? $this->segmentMetadataDao->get($id_segment, 'translation_disabled')
            : $this->segmentMetadataDao->get($id_segment, 'translation_disabled', $ttl);

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
        // ttl=0: segment_metadata has a UNIQUE KEY on (id_segment, meta_key) and save() below is
        // a plain INSERT, not an upsert. A stale cached "not disabled" here would let this proceed
        // to save() on an already-disabled segment and crash on the duplicate key instead of
        // returning early as the docblock promises.
        if ($this->isDisabled($id_segment, 0)) {
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
