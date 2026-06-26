<?php

namespace Model\DataAccess;

use Exception;
use PDO;
use PDOException;
use Throwable;

interface IDatabase
{

    /**
     * Obtain the process-wide database singleton.
     *
     * @param string|null $server
     * @param string|null $user
     * @param string|null $password
     * @param string|null $database
     *
     * @return IDatabase
     * @deprecated Do NOT call this anywhere except the application composition root. Everything
     *             beyond the root — domain code AND entry points alike — must receive an
     *             {@see IDatabase} by injection (see "The only legitimate caller" below).
     *
     * Why this is discouraged
     * -----------------------
     * 1. Hidden global state. It is a static singleton: every caller silently couples to one
     *    process-wide connection instead of declaring an {@see IDatabase} dependency. The
     *    dependency becomes invisible at the call site and impossible to vary.
     * 2. Untestable SQL. A caller that reaches for the singleton cannot be given a test double,
     *    so its queries are either left untested or "covered" by mocks that never execute SQL.
     *    Real dialect breakage (e.g. MySQL 5.7 -> 8) then surfaces in production, not in CI.
     *    Injecting the connection lets integration tests run the real SQL against the CI DB.
     * 3. Cross-request leakage. Under PHP-FPM the singleton lives for the whole worker. Per-request
     *    connection state (open transactions, schema/useDb toggles) leaks into the next request
     *    served by the same worker. Injected, per-scope instances keep that state isolated.
     * 4. Unclear ownership. With a global accessor there is no single owner of connect / begin /
     *    commit / reconnect; lifecycle decisions get scattered across the codebase.
     *
     * Best practice
     * -------------
     * - Require an {@see IDatabase} in the constructor and store it. {@see AbstractDao} now
     *   REQUIRES an injected connection — its `?? Database::obtain()` fallback was removed.
     * - Thread the SAME instance down the call chain: DAOs, models, services, and factories
     *   (e.g. EnginesFactory::getInstance($id, $database)) all take it explicitly.
     * - Reach for the connection the way your layer already provides it: controllers expose
     *   getDatabase(); workers hold $this->database; features receive it via setDatabase() from
     *   the FeatureSet framework. Never re-fetch the singleton from inside those layers.
     *
     * The only legitimate caller
     * --------------------------
     * The application composition root: {@see \Bootstrap::start()} (lib/Bootstrap.php), which
     * builds the connection from config exactly once and exposes it via
     * {@see \Bootstrap::getDatabase()}. NOTHING else may call obtain().
     *
     * Everything lives BEYOND the root and must be injected — not only domain code (controllers,
     * DAOs, models, services, workers, the task runner, features) but the entry points and
     * submodules too. An entry point (web front controller, daemon, CLI script) boots by calling
     * Bootstrap::start(), then reads Bootstrap::getDatabase() to seed injection downward; it must
     * not reach for the singleton itself. Any remaining direct obtain() call past the Bootstrap
     * (e.g. in a daemon/worker/script) is residual debt to be removed, not a pattern to copy.
     *
     */
    public static function obtain(?string $server = null, ?string $user = null, ?string $password = null, ?string $database = null): IDatabase;


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