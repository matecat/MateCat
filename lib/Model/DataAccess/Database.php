<?php

namespace Model\DataAccess;

use Exception;
use PDO;
use PDOException;
use Throwable;
use Utils\Logger\LoggerFactory;

/**
 * Class which implements a database using PDO
 *
 * The used test script can be found at: https://gist.github.com/reneses/3108444332d4e56c0b73
 */
class Database implements IDatabase
{

    /**
     * Established connection
     * @var ?PDO $connection
     */
    protected ?PDO $connection = null;

    // Connection variables
    protected string $server; //database server
    protected string $user; //database login name
    protected string $password; //database login password
    protected string $database; //database name

    // Affected rows
    protected int $affected_rows;

    /**
     * Work deferred until the current transaction commits.
     *
     * @var list<array{callable(): void, bool}> The callback and whether its failure is critical.
     */
    private array $afterCommitCallbacks = [];

    /**
     * Set when a scope inside the current transaction failed.
     *
     * A nested scope cannot roll back a transaction it does not own, so it condemns it instead and
     * commit() enforces the verdict. Cleared when a transaction really opens and when one is rolled
     * back, and never by a successful commit — a successful commit is unreachable while it is set.
     */
    private bool $rollbackOnly = false;


    const string SEQ_ID_SEGMENT = 'id_segment';
    const string SEQ_ID_PROJECT = 'id_project';

    /** @var list<string> */
    protected static array $SEQUENCES = [
        Database::SEQ_ID_SEGMENT,
        Database::SEQ_ID_PROJECT,
    ];

    /**
     * Instantiate the database (singleton design pattern)
     *
     * @param string $server
     * @param string $user
     * @param string $password
     * @param string $database
     */
    public function __construct(string $server, string $user, string $password, string $database)
    {
        // Set fields
        $this->server = $server;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
    }


    /**
     * Class destructor
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return PDO
     *
     * @throws PDOException
     */
    public function getConnection(): PDO
    {
        if (empty($this->connection)) {
            $this->connection = new PDO(
                "mysql:host=$this->server;dbname=$this->database",
                $this->user,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Raise exceptions on errors
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                ]
            );
            $this->connection->exec("SET names utf8");
        }

        return $this->connection;
    }

    /**
     * @throws PDOException
     */
    public function connect(): void
    {
        $this->getConnection();
    }

    /**
     * @return bool
     * @throws PDOException
     */
    public function ping(): bool
    {
        $this->getConnection()->query("SELECT 1 FROM DUAL");

        return true;
    }

    /**
     * @Override
     * {@inheritdoc}
     */
    public function close(): void
    {
        $this->connection = null;
    }

    public function rowCount(): int
    {
        return $this->affected_rows;
    }


    /**
     * @Override
     * {@inheritdoc}
     *
     * @throws PDOException
     */
    public function useDb(string $name): void
    {
        $stmt = $this->getConnection()->prepare("USE " . $name); // Table and Column names cannot be replaced by parameters in PDO
        $stmt->execute();
        $stmt->closeCursor();
        unset($stmt);
        $this->database = $name;
    }

    /**
     * Begin a transaction for InnoDB tables.
     *
     * @throws PDOException
     * @internal Deliberately not part of IDatabase: a consumer opens a transaction through
     *           transaction(), which cannot leave a window open on a failure path or an early
     *           return. This stays public because the test harness opens a fixture scope in
     *           setUp() and rolls it back in tearDown(), which a callback cannot express.
     *
     */
    public function begin(): PDO
    {
        if (!$this->getConnection()->inTransaction()) {
            // A fresh transaction starts with an empty deferral queue and no verdict against it.
            // Anything still queued, or still condemned, belongs to a transaction that never reached
            // commit() or rollback() — an aborted request on a connection that outlived it — and must
            // not fire on, or veto, someone else's commit.
            $this->afterCommitCallbacks = [];
            $this->rollbackOnly = false;
            $this->getConnection()->beginTransaction();
        }

        return $this->getConnection();
    }


    /**
     * Commit the open transaction and drain the work deferred with onCommit().
     *
     * @throws PDOException
     * @throws TransactionAbortedException when a scope inside this transaction failed
     * @throws Throwable
     * @internal Deliberately not part of IDatabase — see begin().
     *
     */
    public function commit(): void
    {
        if ($this->rollbackOnly) {
            $this->rollback();

            throw new TransactionAbortedException(
                'commit refused: a scope inside this transaction failed, so the whole transaction was rolled back'
            );
        }

        try {
            $this->getConnection()->commit();
        } catch (Throwable $e) {
            // The writes these callbacks were queued against did not land. Nothing may fire, and the
            // queue must not survive to be drained by whoever commits next on this connection.
            $this->afterCommitCallbacks = [];

            throw $e;
        }

        $this->runAfterCommitCallbacks();
    }

    /**
     * @Override
     * {@inheritdoc}
     */
    public function markRollbackOnly(): void
    {
        $this->rollbackOnly = true;
    }

    /**
     * @Override
     * {@inheritdoc}
     *
     * @throws PDOException
     */
    public function onCommit(callable $callback, bool $critical = false): void
    {
        if (!$this->getConnection()->inTransaction()) {
            $callback();

            return;
        }

        $this->afterCommitCallbacks[] = [$callback, $critical];
    }

    /**
     * Drains the deferral queue after a successful commit.
     *
     * Cleared before running, so a callback that itself calls onCommit() queues for the next
     * transaction instead of extending this drain. Failures are logged and swallowed: the data is
     * already committed, and a caller that has been told its write succeeded must not then receive an
     * exception because a cache invalidation or a message enqueue failed.
     *
     * A callback queued as critical is the exception. Its failure is still logged, the rest of the
     * queue still runs, and only then is it re-thrown — because for that kind of work, a revoked
     * credential still answering out of the cache, the caller is the only party left that can retry.
     *
     * @throws Throwable The first critical callback's failure.
     */
    private function runAfterCommitCallbacks(): void
    {
        $callbacks = $this->afterCommitCallbacks;
        $this->afterCommitCallbacks = [];

        $criticalFailure = null;

        foreach ($callbacks as [$callback, $critical]) {
            try {
                $callback();
            } catch (Throwable $e) {
                LoggerFactory::doJsonLog([
                    'message' => 'after-commit callback failed',
                    'critical' => $critical,
                    'error' => $e->getMessage(),
                ]);

                // Held rather than thrown here: the rest of the queue is unrelated work that has
                // already been paid for, and abandoning it would trade one silent failure for
                // several. The first critical failure is the one reported; later ones are logged.
                if ($critical && $criticalFailure === null) {
                    $criticalFailure = $e;
                }
            }
        }

        if ($criticalFailure !== null) {
            throw $criticalFailure;
        }
    }


    /**
     * Roll back the open transaction and discard the work deferred with onCommit().
     *
     * @throws PDOException
     * @internal Deliberately not part of IDatabase — see begin().
     *
     */
    public function rollback(): void
    {
        $connection = $this->getConnection();

        // Deferred work is discarded: it was queued on the strength of writes that are about to
        // disappear. The verdict goes with it — it condemned this transaction, which is now gone.
        $this->afterCommitCallbacks = [];
        $this->rollbackOnly = false;

        // Check if a transaction is currently active
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }

    /**
     * @Override
     * {@inheritdoc}
     *
     * @throws Throwable Re-throws the original exception after rollback
     */
    public function transaction(callable $callback): mixed
    {
        // Whether this scope is the outermost one, and therefore the one that opens the transaction
        // and issues its single commit. begin() joins an already open transaction silently, so
        // having called it is no evidence of having opened anything: the connection has to be asked.
        $isOutermostScope = !$this->getConnection()->inTransaction();

        if ($isOutermostScope) {
            $this->begin();
        } elseif ($this->rollbackOnly) {
            // Every statement this scope would run is already destined for the rollback.
            throw new TransactionAbortedException(
                'refusing to enter a transaction scope: an enclosing scope has already failed'
            );
        }

        try {
            $result = $callback();
        } catch (Throwable $e) {
            // Condemn it first. A guest cannot roll back, and the owner may be a hand-rolled commit()
            // that has never heard of this class.
            $this->markRollbackOnly();

            if ($isOutermostScope) {
                try {
                    $this->rollback();
                } catch (Throwable $rollbackFailure) {
                    // MySQL kills the transaction itself on deadlock (1213) and on lock wait timeout
                    // (1205), so the rollback can fail for reasons that say nothing about the cause.
                    // Record it and let the original exception travel.
                    LoggerFactory::doJsonLog([
                        'message' => 'rollback failed while aborting a transaction scope',
                        'error' => $rollbackFailure->getMessage(),
                        'cause' => $e->getMessage(),
                    ]);
                }
            }

            throw $e;
        }

        if (!$isOutermostScope) {
            // A guest opened nothing and closes nothing.
            return $result;
        }

        $this->commit();

        return $result;
    }

    /**
     * @Warning This method does not support all the SQL syntax features. Only AND key/value pair is supported, OR in WHERE condition is not supported, nesting "AND ( .. OR .. ) AND ( .. )" is not supported
     * @Override
     * {@inheritdoc}
     *
     * @param string $table
     * @param array<string, mixed> $data
     * @param array<int|string, mixed> $where
     *
     * @return int
     *
     * @throws PDOException
     */
    public function update(string $table, array $data, array $where = ['1' => '0']): int
    {
        // Prepare the statement
        $valuesToBind = [];
        $query = "UPDATE $table SET ";
        $currentIndex = 0;

        foreach ($data as $key => $value) {
            $query .= "$key = :value$currentIndex, ";

            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            $valuesToBind[":value$currentIndex"] = $value;
            ++$currentIndex;
        }

        $query = rtrim($query, ', ');
        $query .= " WHERE ";

        foreach ($where as $k => $v) {
            if ($v !== null) {
                $query .= $k . " = :" . $k . " AND ";
            } else {
                $query .= $k . " IS :" . $k . " AND ";
            }

            $valuesToBind[$k] = $v;
        }

        $query = substr($query, 0, -5);

        $stmt = $this->getConnection()->prepare($query);

        // Execute it
        $stmt->execute($valuesToBind);

        $affected = $stmt->rowCount();
        $this->affected_rows = $affected;

        return $affected;
    }

    /**
     * @Override
     * {@inheritdoc}
     *
     * @param string $table
     * @param array<string, mixed> $data
     * @param array<string> $mask
     * @param bool $ignore
     * @param bool $no_nulls
     * @param array<string, string> $onDuplicateKey
     *
     * @return string
     *
     * @throws Exception
     * @throws PDOException
     */
    public function insert(string $table, array $data, array &$mask = [], bool $ignore = false, bool $no_nulls = false, array $onDuplicateKey = []): string
    {
        [$query, $dupBindValues] = $this->buildInsertStatement($table, $data, $mask, $ignore, $no_nulls, $onDuplicateKey);

        $preparedStatement = $this->getConnection()->prepare($query);

        $valuesToBind = array_filter($data, function ($key) use ($mask) {
            return isset($mask[$key]);
        }, ARRAY_FILTER_USE_KEY);

        $valuesToBind = array_merge($valuesToBind, $dupBindValues);

        // Execute it
        $preparedStatement->execute($valuesToBind);
        $this->affected_rows = $preparedStatement->rowCount();

        return $this->last_insert() ?: '0';
    }

    /**
     * Returns a string suitable for insert of the fields
     * provided by the attribute array.
     *
     * @param string $table the table on which perform the insert
     * @param array<string, mixed> $attrs array of full attributes to update
     * @param array<string> $mask array of attributes to include in the update
     * @param bool $ignore Use INSERT IGNORE query type
     * @param bool $no_nulls Exclude NULL fields when build the sql
     * @param array<string, string> $on_duplicate_update
     *
     * @return array{0: string, 1: array<string, mixed>}
     *
     * @throws Exception
     *
     * @internal param array $options of options for the SQL statement
     */
    public function buildInsertStatement(string $table, array $attrs, array &$mask = [], bool $ignore = false, bool $no_nulls = false, array $on_duplicate_update = []): array
    {
        if (empty($table)) {
            throw new Exception('TABLE constant is not defined');
        }

        if ($ignore && !empty($on_duplicate_update)) {
            throw new Exception('INSERT IGNORE and ON DUPLICATE KEYS UPDATE are not allowed together.');
        }

        $first = [];
        $second = [];

        $sql_ignore = $ignore ? " IGNORE " : "";

        $valuesToBind = [];
        $duplicate_statement = "";
        if (!empty($on_duplicate_update)) {
            $duplicate_statement = " ON DUPLICATE KEY UPDATE ";
            foreach ($on_duplicate_update as $key => $value) {
                if ($no_nulls && is_null($attrs[$key])) {
                    /*
                     *
                     * if NO NULLS flag is set and there is an ON DUPLICATE entry "value"
                     * for such field we do not want override the database value with null
                     * (because it will not be inserted in the value fields, and it will be null by definition)
                     *
                     * Ex:
                     *
                     * INSERT  INTO table (`field_A`, `field_C`)
                     * VALUES (:field_A, :field_C)
                     * ON DUPLICATE KEY UPDATE
                     *     field_A = VALUES( field_A ),
                     *     field_B = VALUES( field_B ),  -- <<<<<<< THIS WILL ERASE THE EXISTING DATABASE VALUE
                     *     field_C = VALUES( field_C );
                     *
                     */
                    continue;
                }

                //set the update keys
                $duplicate_statement .= " $key = ";
                if (stripos($value, "value") !== false) {
                    //if the string contains VALUES( .. ) , it is not needed to bind to PDO
                    $duplicate_statement .= "VALUES( $key )";
                } else {
                    //bind to PDO
                    $duplicate_statement .= ":dupUpdate_" . $key;
                    $valuesToBind[":dupUpdate_" . $key] = $value;
                }
                $duplicate_statement .= ", ";
            }
        }

        $duplicate_statement = rtrim($duplicate_statement, ", ");

        if (empty($mask)) {
            $mask = array_keys($attrs);
        }
        $mask = array_combine($mask, $mask);

        foreach ($attrs as $key => $value) {
            if (array_key_exists($key, $mask)) {
                if ($no_nulls && is_null($value)) {
                    unset($mask[$key]);
                    continue;
                }
                $first[] = "`$key`";
                $second[] = ":$key";
            }
        }

        $sql = "INSERT $sql_ignore INTO " . $table .
            " (" .
            implode(', ', $first) .
            ") VALUES (" .
            implode(', ', $second) .
            ")
                $duplicate_statement ;
        ";

        return [$sql, $valuesToBind];
    }

    /**
     * @Override
     * {@inheritdoc}
     *
     * @throws PDOException
     */
    public function last_insert(): false|string
    {
        return $this->getConnection()->lastInsertId();
    }

    /**
     * @param string $sequence_name
     * @param int $seqIncrement
     *
     * @return list<int>
     *
     * @throws PDOException
     * @throws SequenceAllocationInTransaction if a transaction is already open on this connection
     * @throws Throwable
     */
    public function nextSequence(string $sequence_name, int $seqIncrement = 1): array
    {
        if (!in_array($sequence_name, static::$SEQUENCES)) {
            throw new PDOException("Undefined sequence " . $sequence_name);
        }

        // The allocation owns its transaction and commits it before returning, because the ids it
        // hands out have to survive whatever the caller does next. Joining a caller's transaction
        // instead would tie them to that transaction's fate, and a rollback would put ids that were
        // already handed out back on the counter. Refuse rather than allocate unsafely: the caller
        // allocates before it opens the transaction that consumes the ids.
        if ($this->getConnection()->inTransaction()) {
            throw new SequenceAllocationInTransaction(
                'refusing to allocate from the `' . $sequence_name . '` sequence: a transaction is already open on this ' .
                'connection, and an allocation that rolls back with it would hand the same ids out twice'
            );
        }

        return $this->transaction(function () use ($sequence_name, $seqIncrement): array {
            $statement = $this->getConnection()->prepare("SELECT " . $sequence_name . " FROM sequences FOR UPDATE;");
            $statement->execute();
            $first_id = $statement->fetch(PDO::FETCH_OBJ);

            $statement = $this->getConnection()->prepare("UPDATE sequences SET " . $sequence_name . " = " . $sequence_name . " + :seqIncrement where 1 limit 1;");
            $statement->bindValue(':seqIncrement', $seqIncrement, PDO::PARAM_INT);
            $statement->execute();

            return range($first_id->{$sequence_name}, $first_id->{$sequence_name} + $seqIncrement - 1);
        });
    }

}
