<?php

namespace Model\DataAccess;

use PDO;
use PDOException;
use Throwable;

interface IDatabase
{

    /**
     * Obtain an instance of the database
     *
     * @param string|null $server
     * @param string|null $user
     * @param string|null $password
     * @param string|null $database
     *
     * @return IDatabase
     */
    public static function obtain(?string $server = null, ?string $user = null, ?string $password = null, ?string $database = null): IDatabase;


    /**
     * Connect and select database
     *
     * @throws PDOException
     */
    public function connect(): void;

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

}