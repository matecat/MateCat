<?php

namespace Model\DataAccess;

use RuntimeException;

/**
 * Raised when a commit is refused because a scope inside the transaction failed.
 *
 * This is not a condition to recover from. By the time it is thrown the transaction has already been
 * rolled back and the writes are gone; it exists so that the failure is loud at the point where the
 * caller believed its work was durable, instead of silent.
 */
class TransactionAbortedException extends RuntimeException
{
}
