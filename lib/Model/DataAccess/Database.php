<?php

namespace Model\DataAccess;

use Exception;
use PDO;
use PDOException;

/**
 * Class which implements a database using PDO
 *
 * The used test script can be found at: https://gist.github.com/reneses/3108444332d4e56c0b73
 */
class Database implements IDatabase
{

    /**
     * Unique instance of the class (singleton design pattern)
     * @var ?IDatabase $instance
     */
    protected static ?IDatabase $instance = null;

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


    const string SEQ_ID_SEGMENT = 'id_segment';
    const string SEQ_ID_PROJECT = 'id_project';

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
    protected function __construct(string $server, string $user, string $password, string $database)
    {
        // Set fields
        $this->server = $server;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public static function obtain(string $server = null, string $user = null, string $password = null, string $database = null): IDatabase
    {
        if (!self::$instance || $server != null && $user != null && $password != null && $database != null) {
            self::$instance = new Database($server, $user, $password, $database);
        }

        return self::$instance;
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
     * @Override
     * {@inheritdoc}
     */
    public function begin(): PDO
    {
        if (!$this->getConnection()->inTransaction()) {
            $this->getConnection()->beginTransaction();
        }

        return $this->getConnection();
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function commit(): void
    {
        $this->getConnection()->commit();
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function rollback(): void
    {
        $this->getConnection()->rollBack();
    }

    /**
     * @Warning This method does not support all the SQL syntax features. Only AND key/value pair is supported, OR in WHERE condition is not supported, nesting "AND ( .. OR .. ) AND ( .. )" is not supported
     * @Override
     * {@inheritdoc}
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
     * @throws Exception
     */
    public function insert(string $table, array $data, array &$mask = [], $ignore = false, $no_nulls = false, array $onDuplicateKey = []): string
    {
        $query = static::buildInsertStatement($table, $data, $mask, $ignore, $no_nulls, $onDuplicateKey);

        $preparedStatement = $this->getConnection()->prepare($query);

        $valuesToBind = array_filter($data, function ($key) use ($mask) {
            return isset($mask[$key]);
        }, ARRAY_FILTER_USE_KEY);


        // Execute it
        $preparedStatement->execute($valuesToBind);
        $this->affected_rows = $preparedStatement->rowCount();

        return $this->last_insert();
    }

    /**
     * Returns a string suitable for insert of the fields
     * provided by the attribute array.
     *
     * @param string $table the table on which perform the insert
     * @param array $attrs array of full attributes to update
     * @param array $mask array of attributes to include in the update
     * @param bool $ignore Use INSERT IGNORE query type
     * @param bool $no_nulls Exclude NULL fields when build the sql
     *
     * @param array $on_duplicate_fields
     *
     * @return string
     * @throws Exception
     * @internal param array $options of options for the SQL statement
     */
    public static function buildInsertStatement(string $table, array $attrs, array &$mask = [], bool $ignore = false, bool $no_nulls = false, array $on_duplicate_fields = []): string
    {
        if (empty($table)) {
            throw new Exception('TABLE constant is not defined');
        }

        if ($ignore && !empty($on_duplicate_fields)) {
            throw new Exception('INSERT IGNORE and ON DUPLICATE KEYS UPDATE are not allowed together.');
        }

        $first = [];
        $second = [];

        $sql_ignore = $ignore ? " IGNORE " : "";

        $duplicate_statement = "";
        if (!empty($on_duplicate_fields)) {
            $duplicate_statement = " ON DUPLICATE KEY UPDATE ";
            foreach ($on_duplicate_fields as $key => $value) {
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
                    $valuesToBind[":dupUpdate_" . $key] = $value; //TODO this is a bug: bind values are not returned and not inserted in the mask
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

        return "INSERT $sql_ignore INTO " . $table .
            " (" .
            implode(', ', $first) .
            ") VALUES (" .
            implode(', ', $second) .
            ")
                $duplicate_statement ;
        ";
    }

    /**
     * @Override
     * {@inheritdoc}
     */
    public function last_insert(): false|string
    {
        return $this->getConnection()->lastInsertId();
    }


    /**
     * TODO this trim should be removed and ALL codebase migrated from $db->escape() to prepared Statements
     * @deprecated
     * @Override
     * {@inheritdoc}
     */
    public function escape(string $string): string
    {
        return substr($this->getConnection()->quote($string), 1, -1);
    }

    /**
     * @param string $sequence_name
     * @param int $seqIncrement
     *
     * @return array
     */
    public function nextSequence(string $sequence_name, int $seqIncrement = 1): array
    {
        if (!in_array($sequence_name, static::$SEQUENCES)) {
            throw new PDOException("Undefined sequence " . $sequence_name);
        }

        $this->getConnection()->beginTransaction();

        $statement = $this->getConnection()->prepare("SELECT " . $sequence_name . " FROM sequences FOR UPDATE;");
        $statement->execute();
        $first_id = $statement->fetch(PDO::FETCH_OBJ);

        $statement = $this->getConnection()->prepare("UPDATE sequences SET " . $sequence_name . " = " . $sequence_name . " + :seqIncrement where 1 limit 1;");
        $statement->bindValue(':seqIncrement', $seqIncrement, PDO::PARAM_INT);
        $statement->execute();

        $this->getConnection()->commit();

        return range($first_id->{$sequence_name}, $first_id->{$sequence_name} + $seqIncrement - 1);
    }

}
