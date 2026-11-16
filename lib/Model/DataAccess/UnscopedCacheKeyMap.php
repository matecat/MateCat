<?php

declare(strict_types=1);

namespace Model\DataAccess;

use Error;

/**
 * Raised when a cached query would put every call under one Redis key.
 *
 * `AbstractDao::_fetchObjectMap()` names the Redis hash after the calling method and the bound
 * values. A query that writes its values into the SQL and binds nothing gets the same hash on every
 * call, and each call adds one field to it. Every write renews the hash TTL, so the hash never
 * expires. Example: `SegmentNoteDao::getBySegmentIds()` wrote `WHERE id_segment IN ( 10, 11 )`;
 * its hash reached 18.9 GB on production and Redis evicted the login tokens to make room.
 *
 * The fix is at the call site: bind the values as parameters, or read without the cache.
 *
 * Deliberately an `Error`, like `Utils\Session\StatelessSessionViolation`: controllers catch
 * `Exception` and render it as an ordinary failure, and this is a mistake in the code that must
 * surface in the tests instead.
 */
class UnscopedCacheKeyMap extends Error
{
}
