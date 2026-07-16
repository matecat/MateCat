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


    /**
     * Begin a transaction for InnoDB tables
     *
     * @throws PDOException
     */
    public function begin(): PDO;


    /**
     * Commit a transaction for InnoDB tables
     *
     * @throws PDOException
     */
    public function commit(): void;


    /**
     * Roll back a transaction for InnoDB tables
     *
     * @throws PDOException
     */
    public function rollback(): void;

    /**
     * Execute a callback within a database transaction.
     *
     * Begins a transaction, executes the callback, and commits.
     * On any exception, rolls back (if still in transaction) and re-throws.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T The value returned by the callback
     *
     * @throws Throwable Re-throws the original exception after rollback
     */
    public function transaction( callable $callback ): mixed;

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