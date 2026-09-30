<?php

namespace Model\DataAccess;

use Exception;
use PDO;
use PDOException;
use Throwable;

interface IDatabase
{


    /**
     * Connect and select database
     *
     * @throws PDOException
     */
    public function connect(): void;

    /**
     * Verify the connection is alive (e.g. SELECT 1).
     *
     * @throws PDOException
     */
    public function ping(): bool;

    /**
     * Close the connection
     */
    public function close(): void;


    /**
     * Switch the DB
     *
     * @param string $name name of the db to connect to
     *
     * @throws PDOException
     */
    public function useDb(string $name): void;


    // begin(), commit() and rollback() are deliberately absent. They are the statements a
    // transaction is built from, not a service a consumer of this interface may reach for:
    // a caller holding an IDatabase can only open a transaction through transaction() below,
    // which cannot leave a window open on a failure path or an early return. The three remain
    // public on Database itself, because the test harness opens a fixture scope in setUp() and
    // rolls it back in tearDown() — a shape a callback cannot express.

    /**
     * Run something once the transaction has committed: a cache eviction, a queue message, a mail.
     *
     * Two reasons not to run it inline. Before the commit the rows are not visible yet, so whoever
     * reads that cache or picks up that message sees the old state — or state a rollback then threw
     * away. And the work does not need the locks the transaction is holding.
     *
     * Use it when your code does not own the transaction. A DAO write cannot tell whether it is the
     * outermost scope or nested five calls deep, so it defers and lets the owner's commit run the
     * callback. When you do own the scope, put the statement after transaction() returns instead:
     * same effect, and you get the exception if it fails.
     *
     * With no transaction open the callback runs straight away, so callers never have to check
     * which case they are in. A rollback discards the queue.
     *
     * @param callable(): void $callback
     * @param bool $critical Re-throw a failure instead of only logging it, after the rest of the
     *                       queue has run. Only for work whose silent failure is a correctness or a
     *                       security problem — revoking a credential, not warming a cache — because
     *                       by then the caller is the only party left that can retry.
     *
     * @throws PDOException
     */
    public function onCommit(callable $callback, bool $critical = false): void;

    /**
     * Run a callback inside a database transaction.
     *
     * The outermost scope owns the transaction: it issues the BEGIN and the single COMMIT. A scope
     * entered while a transaction is already open is a guest — it opens nothing and closes nothing,
     * so it cannot commit its caller's work early.
     *
     * Any throw aborts the entire tree. A guest that fails marks the transaction unable to commit
     * before re-throwing, so swallowing its exception does not rescue the writes: the eventual
     * commit is refused and everything rolls back. That holds even when the outermost transaction
     * was opened by hand rather than through this method.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T The value returned by the callback
     *
     * @throws Throwable Re-throws the original exception after the transaction is aborted
     */
    public function transaction( callable $callback ): mixed;

    /**
     * Mark the open transaction as unable to commit.
     *
     * A nested scope that fails cannot roll back — it does not own the transaction — so it condemns
     * it instead. Whoever eventually calls commit() is refused, including a caller that opened the
     * transaction by hand and knows nothing about this mechanism.
     */
    public function markRollbackOnly(): void;

    /**
     * Execute an update query with an array as argument
     *
     * @param string $table Table to update
     * @param array<string, mixed> $data Data to update, with the form (keyToUpdate => newValue)
     * @param array<int|string, mixed> $where Condition
     *
     * @return int Number of affected rows
     *
     * @throws PDOException
     */
    public function update(string $table, array $data, array $where = ['1' => '0']): int;


    /**
     * Run an insert query with an array as argument
     *
     * @param string $table Table to insert data in
     * @param array<string, mixed> $data Data to insert, with the form (keyToUpdate => newValue)
     *
     * @return string
     */
    public function insert(string $table, array $data): string;


    /**
     * Get the ID of the last inserted row
     *
     * @return false|string Last insert ID
     *
     * @throws PDOException
     */
    public function last_insert(): false|string;


    /**
     * Get the number of rows affected by the last update/insert query
     */
    public function rowCount(): int;

    /**
     * Get the underlying PDO connection
     *
     * @return PDO
     *
     * @throws PDOException
     */
    public function getConnection(): PDO;

    /**
     * Reserve and return a range of sequence IDs
     *
     * @param string $sequence_name
     * @param int $seqIncrement
     *
     * @return list<int>
     *
     * @throws PDOException
     */
    public function nextSequence(string $sequence_name, int $seqIncrement = 1): array;

    /**
     * @param array<string, mixed> $attrs
     * @param array<int|string, mixed> $mask
     * @param array<string, string> $on_duplicate_update
     *
     * @return array{0: string, 1: array<string, scalar|null>}
     * @throws Exception
     */
    public function buildInsertStatement(string $table, array $attrs, array &$mask = [], bool $ignore = false, bool $no_nulls = false, array $on_duplicate_update = []): array;

}