<?php

namespace Model\DataAccess;

use RuntimeException;

/**
 * Raised when a sequence block is requested from inside a transaction the allocator does not own.
 *
 * A sequence allocation has to be durable the moment it returns: the ids it hands out are used to
 * build rows, and a caller whose transaction rolls back must not give them back. Enclosed in someone
 * else's transaction it would be rolled back with them, and the next allocation would hand out ids
 * that had already been handed out once. Gaps in a sequence are harmless; reuse is not.
 *
 * Since a second transaction cannot be opened on a connection that already has one, the only
 * coherent answer is to refuse. Allocate before opening the transaction that consumes the ids.
 *
 * This is a programming error, not a runtime condition — there is nothing for a caller to catch and
 * recover from, and every call site is a place where the allocation boundary was drawn wrongly.
 */
class SequenceAllocationInTransaction extends RuntimeException
{
}
