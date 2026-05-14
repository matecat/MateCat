<?php

namespace Model\DataAccess;

use Exception;
use PDO;
use PDOException;
use PDOStatement;
use ReflectionException;
use Throwable;
use Utils\Logger\LoggerFactory;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 29/09/14
 * Time: 17.55
 */
abstract class AbstractDao
{

    use DaoCacheTrait;

    /**
     * The connection object
     * @var IDatabase
     */
    protected IDatabase $database;

    /**
     * @var string This property will be overridden in the subclasses.
     */
    const string STRUCT_TYPE = '';

    /**
     * @var int The maximum number of tuples that can be inserted for a single query
     */
    const int MAX_INSERT_NUMBER = 1;

    /**
     * @var list<string>
     */
    protected static array $primary_keys;

    /**
     * @var list<string>
     */
    protected static array $auto_increment_field = [];

    /**
     * @var string
     */
    const string TABLE = '';

    private const string FIND_BY_ID_SQL = "SELECT * FROM %s WHERE id = :id";
    private const string UPDATE_STRUCT_SQL = " UPDATE %s SET %s WHERE %s ";

    public function __construct(?IDatabase $con = null)
    {
        if ($con == null) {
            $con = Database::obtain();
        }

        $this->database = $con;
        self::$auto_increment_field = [];
    }

    /**
     * @template T of IDaoStruct
     *
     * @param int $id
     * @param class-string<T> $fetchClass
     * @param int|null $ttl Cache TTL in seconds (0 = no cache)
     *
     * @return T|null
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    public function fetchById(int $id, string $fetchClass, ?int $ttl = null): ?IDaoStruct
    {
        $sql = sprintf(self::FIND_BY_ID_SQL, static::TABLE);
        $stmt = $this->database->getConnection()->prepare($sql);
        $keyMap = static::class . "::fetchById-" . $id;

        if ($ttl !== null) {
            $this->setCacheTTL($ttl);
        }

        return $this->_fetchObjectMap($stmt, $fetchClass, ['id' => $id], $keyMap)[0] ?? null;
    }

    /**
     * @template T of IDaoStruct
     *
     * @param int $id
     * @param class-string<T> $fetchClass
     *
     * @return bool
     * @throws PDOException
     */
    public function destroyFetchByIdCache(int $id, string $fetchClass): bool
    {
        $sql = sprintf(self::FIND_BY_ID_SQL, static::TABLE);
        $stmt = $this->database->getConnection()->prepare($sql);

        return $this->_destroyObjectCache($stmt, $fetchClass, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     * @return int
     * @throws PDOException
     */
    public function updateFields(array $data = [], array $where = []): int
    {
        return $this->database->update(static::TABLE, $data, $where);
    }

    /**
     * Updates the struct. The record is found via the primary
     * key attributes provided by the struct.
     *
     * @param IDaoStruct $struct
     * @param array{fields?: list<string>} $options
     *
     * @return int
     * @throws Exception
     */
    public function updateStruct(IDaoStruct $struct, array $options = []): int
    {
        $attrs = $struct->toArray();

        $fields = [];

        if (isset($options['fields'])) {
            if (!is_array($options['fields'])) {
                throw new Exception('`fields` must be an array');
            }
            $fields = $options['fields'];
        }

        $sql = sprintf(
            self::UPDATE_STRUCT_SQL,
            static::TABLE,
            static::buildUpdateSet($attrs, $fields),
            static::buildPkeyCondition($attrs)
        );

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);

        if (!$struct instanceof AbstractDaoObjectStruct) {
            throw new Exception('Struct must be an instance of AbstractDaoObjectStruct');
        }

        $data = array_merge(
            $struct->toArray($fields),
            static::structKeys($struct)
        );

        LoggerFactory::getLogger('dao')->debug([
            'table' => static::TABLE,
            'sql' => $sql,
            'attr' => $attrs,
            'fields' => $fields,
            'struct' => $struct->toArray($fields),
            'data' => $data
        ]);

        $stmt->execute($data);

        // WARNING
        // When updating a Mysql table with identical values, nothing's really affected so rowCount will return 0.
        // If you need this value use this:
        // https://www.php.net/manual/en/pdostatement.rowcount.php#example-1096
        return $stmt->rowCount();
    }

    /**
     * Inserts a struct into the database.
     *
     * If an `auto_increment_field` is defined for the table, the last inserted is returned.
     * Otherwise, it returns TRUE on success.
     *
     * Returns FALSE on failure.
     *
     * @param IDaoStruct $struct
     * @param array{ignore?: bool, no_nulls?: bool, on_duplicate_fields?: array<string, string>}|null $options
     *
     * @return int|false
     * @throws Exception
     */
    public function insertStruct(IDaoStruct $struct, ?array $options = []): int|false
    {
        $ignore = isset($options['ignore']) && $options['ignore'] == true;
        $no_nulls = isset($options['no_nulls']) && $options['no_nulls'] == true;
        $on_duplicate_fields = (!empty($options['on_duplicate_fields']) ? $options['on_duplicate_fields'] : []);

        // TODO: allow the mask to be passed as option.
        $mask = array_keys($struct->toArray());
        /** @var list<string> $mask */
        $mask = array_values(array_diff($mask, static::$auto_increment_field));

        [$sql, $dupBindValues] = static::buildInsertStatement($struct->toArray(), $mask, $ignore, $no_nulls, $on_duplicate_fields);

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $data = array_merge($struct->toArray($mask), $dupBindValues);

        LoggerFactory::getLogger('dao')->debug(["SQL" => $sql, "values" => $data]);

        $stmt->execute($data);

        if (count(static::$auto_increment_field)) {
            $id = $conn->lastInsertId();

            return $id === false ? false : (int)$id;
        } else {
            return $stmt->rowCount();
        }
    }

    /**
     * @return Database|IDatabase
     */
    public function getDatabaseHandler(): Database|IDatabase
    {
        return $this->database;
    }

    /**
     * @param array<int, IDaoStruct> $obj_arr
     *
     * @return mixed
     * @throws Exception
     */
    public function createList(array $obj_arr)
    {
        throw new Exception("Abstract method " . __METHOD__ . " must be overridden ");
    }

    /**
     * @param array<int, IDaoStruct> $obj_arr
     *
     * @throws Exception
     */
    public function updateList(array $obj_arr): void
    {
        throw new Exception("Abstract method " . __METHOD__ . " must be overridden ");
    }

    /**
     * @param $input IDaoStruct The input object
     *
     * @return IDaoStruct The input object, sanitized.
     * @throws Exception This function throws exception input is not a \DataAccess\IDaoStruct object
     */
    public function sanitize(IDaoStruct $input): IDaoStruct
    {
        throw new Exception("Abstract method " . __METHOD__ . " must be overridden ");
    }

    /**
     * @param array<int, IDaoStruct> $input An array of \DataAccess\IDaoStruct objects
     *
     * @return array<int, IDaoStruct> The input array, sanitized.
     * @throws Exception This function throws exception if input is not:<br/>
     *                  <ul>
     *                      <li>An array of $type objects</li>
     *                      or
     *                      <li>A \DataAccess\IDaoStruct object</li>
     *                  </ul>
     */
    public static function sanitizeArray(array $input): array
    {
        throw new Exception("Abstract method " . __METHOD__ . " must be overridden ");
    }

    /**
     * @param array<int, IDaoStruct> $input The input array
     * @param string $type The expected type
     *
     * @return array<int, IDaoStruct> The input array if sanitize was successful, otherwise this function throws exception
     * @throws Exception This function throws exception if input is not:<br/>
     *                  <ul>
     *                      <li>An array of $type objects</li>
     *                      or
     *                      <li>A $type object</li>
     *                  </ul>.
     */
    protected static function _sanitizeInputArray(array $input, string $type): array
    {
        foreach ($input as $i => $elem) {
            $input[$i] = self::_sanitizeInput($elem, $type);
        }

        return $input;
    }

    /**
     * @param IDaoStruct $input The input to be sanitized
     * @param string $type The expected type
     *
     * @return IDaoStruct The input object if sanitize was successful, otherwise this function throws exception.
     * @throws Exception This function throws exception input is not an object of type $type
     */
    protected static function _sanitizeInput(IDaoStruct $input, string $type): IDaoStruct
    {
        //if something different from $type is passed, throw exception
        if (!($input instanceof $type)) {
            throw new Exception("Invalid input. Expected " . $type, -1);
        }

        return $input;
    }


    /**
     * This method validates the primary key of a single object to be used in persistency.
     *
     * @param $obj IDaoStruct The input object
     *
     * @return void
     */
    protected function _validatePrimaryKey(IDaoStruct $obj): void
    {
        //to be overridden in subclasses
    }

    /**
     * This method validates the fields of a single object that have to be not null.
     *
     * @param $obj IDaoStruct The input object
     *
     * @return void
     */
    protected function _validateNotNullFields(IDaoStruct $obj): void
    {
        //to be overridden in subclasses
    }

    /**
     * Get a statement object by query string
     *
     * @param string $query
     *
     * @return PDOStatement
     * @throws PDOException
     */
    protected function _getStatementForQuery(string $query): PDOStatement
    {
        $conn = Database::obtain()->getConnection();

        return $conn->prepare($query);
    }

    /**
     * @param array<int|string, scalar|null> $bindParams
     */
    protected function _destroyObjectCache(PDOStatement $stmt, string $fetchClass, array $bindParams): bool
    {
        try {
            return $this->_deleteCacheByKey(md5($stmt->queryString . $this->_serializeForCacheKey($bindParams) . $fetchClass));
        } catch (Exception $e) {
            try {
                LoggerFactory::getLogger('query_cache')->error([
                    'destroyObjectCache failed' => $e->getMessage(),
                    'class'                     => static::class,
                ]);
            } catch (Throwable) {
                // Logger failure during cache eviction is non-critical
            }

            return false;
        }
    }

    /**
     * This method facilitates grouping cached queries into a hashset, making it easier to locate and delete the entire group in Redis.
     *
     * Replacement for deprecated `AbstractDao::_fetchObject`
     *
     * @template T of IDaoStruct
     *
     * @param PDOStatement $stmt
     * @param class-string<T> $fetchClass
     * @param array<int|string, scalar|null> $bindParams
     *
     * @param string|null $keyMap
     *
     * @return list<T>
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _fetchObjectMap(PDOStatement $stmt, string $fetchClass, array $bindParams, ?string $keyMap = null): array
    {
        if (empty($keyMap)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $keyMap = ($trace[1]['class'] ?? '') . "::" . ($trace[1]['function'] ?? '') . "-" . implode(":", $bindParams);
        }

        $_cacheResult = $this->_getFromCacheMap($keyMap, $stmt->queryString . $this->_serializeForCacheKey($bindParams) . $fetchClass);

        if (!is_null($_cacheResult)) {
            $typedCachedResult = [];
            foreach ($_cacheResult as $item) {
                if ($item instanceof $fetchClass) {
                    $typedCachedResult[] = $item;
                }
            }

            return $typedCachedResult;
        }

        $stmt->setFetchMode(PDO::FETCH_CLASS, $fetchClass);

        $t0 = microtime(true);
        $stmt->execute($bindParams);
        $result = $stmt->fetchAll();
        $this->_setLastComputeDelta(microtime(true) - $t0);

        $typedResult = [];
        foreach ($result as $item) {
            if ($item instanceof $fetchClass) {
                $typedResult[] = $item;
            }
        }

        $this->_setInCacheMap($keyMap, $stmt->queryString . $this->_serializeForCacheKey($bindParams) . $fetchClass, $typedResult);

        return $typedResult;
    }

    /**
     * @deprecated Use instead PDO::setFetchMode()
     *
     * @param list<mixed> $array_result
     *
     * @return list<mixed>
     */
    protected function _buildResult(array $array_result): array
    {
        return [];
    }

    /**
     * Returns a string suitable for insert of the fields
     * provided by the attributes array.
     *
     * @param array<string, scalar|null> $attrs array of full attributes to update
     * @param array<int|string, mixed> $mask array of attributes to include in the update
     * @param bool $ignore Use INSERT IGNORE query type
     * @param bool $no_nulls Exclude NULL fields when build the sql
     * @param array<string, string> $on_duplicate_fields
     *
     * @return array{0: string, 1: array<string, scalar|null>} [sql, dupBindValues]
     * @throws Exception
     */
     public static function buildInsertStatement(array $attrs, array &$mask = [], bool $ignore = false, bool $no_nulls = false, array $on_duplicate_fields = []): array
    {
        return Database::buildInsertStatement(static::TABLE, $attrs, $mask, $ignore, $no_nulls, $on_duplicate_fields);
    }


    /**
     * Returns a string suitable for updates of the fields
     * provided by the attributes array.
     *
     * @param array<string, scalar|null> $attrs array of full attributes to update
     * @param list<string>|null $mask array of attributes to include in the update
     *
     * @return string
     */

    protected static function buildUpdateSet(array $attrs, ?array $mask = []): string
    {
        $map = [];
        $pks = static::$primary_keys;

        if (empty($mask)) {
            $mask = array_keys($attrs);
        }

        foreach ($attrs as $key => $value) {
            if (!in_array($key, $pks) && in_array($key, $mask)) {
                $map[] = " $key = :$key ";
            }
        }

        return implode(', ', $map);
    }

    /**
     * Returns a string suitable to identify the struct to perform
     * update or delete operations via PDO data binding.
     *
     * WARNING: only AND conditions are supported
     *
     * @param array<string, scalar|null> $attrs array of attributes of the struct
     *
     * @return string
     *
     */

    protected static function buildPkeyCondition(array $attrs): string
    {
        $map = [];

        foreach ($attrs as $key => $value) {
            if (in_array($key, static::$primary_keys)) {
                $map[] = " $key = :$key ";
            }
        }

        return implode(' AND ', $map);
    }

    /**
     * Returns an array of the specified attributes, plus the primary
     * keys specified by the current DAO.
     *
     * @param AbstractDaoObjectStruct $struct
     *
     * @return array<string, scalar|null> the struct's primary keys
     */

    protected static function structKeys(AbstractDaoObjectStruct $struct): array
    {
        $keys = static::$primary_keys;

        return $struct->toArray($keys);
    }

    /**
     * @param array<string, scalar|null> $data
     * @param array<string, scalar|null> $where
     *
     * @throws PDOException
     */
    public static function staticUpdate(array $data = [], array $where = []): int
    {
        return Database::obtain()->update(static::TABLE, $data, $where);
    }

    /**
     * Updates the struct. The record is found via the primary
     * key attributes provided by the struct.
     *
     * @param AbstractDaoObjectStruct $struct
     * @param array{fields?: list<string>} $options
     *
     * @return int
     * @throws Exception
     */
    public static function staticUpdateStruct(IDaoStruct $struct, array $options = []): int
    {
        $attrs = $struct->toArray();

        $fields = [];

        if (isset($options['fields'])) {
            if (!is_array($options['fields'])) {
                throw new Exception('`fields` must be an array');
            }
            $fields = $options['fields'];
        }

        $sql = " UPDATE " . static::TABLE;
        $sql .= " SET " . static::buildUpdateSet($attrs, $fields);
        $sql .= " WHERE " . static::buildPkeyCondition($attrs);

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);

        $data = array_merge(
            $struct->toArray($fields),
            self::structKeys($struct)
        );

        LoggerFactory::getLogger('dao')->debug([
            'table' => static::TABLE,
            'sql' => $sql,
            'attr' => $attrs,
            'fields' => $fields,
            'struct' => $struct->toArray($fields),
            'data' => $data
        ]);

        $stmt->execute($data);

        // WARNING
        // When updating a Mysql table with identical values, nothing's really affected so rowCount will return 0.
        // If you need this value use this:
        // https://www.php.net/manual/en/pdostatement.rowcount.php#example-1096
        return $stmt->rowCount();
    }

    /**
     * Inserts a struct into the database.
     *
     * If an `auto_increment_field` is defined for the table, the last inserted is returned.
     * Otherwise, it returns TRUE on success.
     *
     * Returns FALSE on failure.
     *
     * @param IDaoStruct $struct
     * @param array{ignore?: bool, no_nulls?: bool, on_duplicate_update?: array<string, string>}|null $options
     *
     * @return int|false
     * @throws Exception
     */
    public static function staticInsertStruct(IDaoStruct $struct, ?array $options = []): int|false
    {
        $ignore = isset($options['ignore']) && $options['ignore'] == true;
        $no_nulls = isset($options['no_nulls']) && $options['no_nulls'] == true;
        $on_duplicate_fields = (!empty($options['on_duplicate_update']) ? $options['on_duplicate_update'] : []);

        // TODO: allow the mask to be passed as option.
        $mask = array_keys($struct->toArray());
        /** @var list<string> $mask */
        $mask = array_values(array_diff($mask, static::$auto_increment_field));

        [$sql, $dupBindValues] = self::buildInsertStatement($struct->toArray(), $mask, $ignore, $no_nulls, $on_duplicate_fields);

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $data = array_merge($struct->toArray($mask), $dupBindValues);

        LoggerFactory::getLogger('dao')->debug(["SQL" => $sql, "values" => $data]);

        $stmt->execute($data);

        if (count(static::$auto_increment_field)) {
            $id = $conn->lastInsertId();

            return $id === false ? false : (int)$id;
        } else {
            return $stmt->rowCount();
        }
    }

}
