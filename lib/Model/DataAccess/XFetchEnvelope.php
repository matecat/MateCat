<?php

namespace Model\DataAccess;

/**
 * Value object wrapping a cached entry with XFetch metadata.
 *
 * When XFetch is active, _setInCacheMap stores serialized instances of this
 * class instead of raw arrays. On read, _getFromCacheMap detects the envelope
 * via instanceof and applies the probabilistic early expiration algorithm.
 *
 * @see DaoCacheTrait::_setInCacheMap()
 * @see DaoCacheTrait::_getFromCacheMap()
 */
final readonly class XFetchEnvelope
{
    /**
     * @param list<mixed> $value    The actual cached data (array of IDaoStruct objects)
     * @param float $storedAt Timestamp (microtime) when the entry was cached
     * @param float $delta    Measured recomputation time in seconds
     */
    public function __construct(
        public array $value,
        public float $storedAt,
        public float $delta,
    ) {
    }
}
