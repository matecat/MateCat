<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 29/09/14
 * Time: 18.45
 */

namespace Model\TmKeyManagement;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Users\UserDao;
use ReflectionException;
use Utils\Logger\LoggerFactory;
use Utils\TmKeyManagement\TmKeyStruct;

/**
 * Class DataAccess_MemoryKeyDao<br/>
 * This class handles the communication with the corresponding table in the database using a CRUD interface
 */
class MemoryKeyDao extends AbstractDao
{

    const string TABLE = "memory_keys";

    const string STRUCT_TYPE = MemoryKeyStruct::class;

    const int MAX_INSERT_NUMBER = 10;

    /**
     * @param MemoryKeyStruct $obj
     *
     * @return MemoryKeyStruct|null The inserted object on success, null otherwise
     * @throws Exception
     */
    public function create(MemoryKeyStruct $obj): ?MemoryKeyStruct
    {
        $obj = $this->sanitize($obj);

        $this->_validateNotNullFields($obj);

        $tmKey = $obj->tm_key ?? throw new Exception("Key value cannot be null");

        $query = "INSERT INTO " . self::TABLE .
            " (uid, key_value, key_name, key_tm, key_glos, creation_date)
                VALUES ( :uid, :key_value, :key_name, :key_tm, :key_glos, NOW())";

        $stmt = $this->database->getConnection()->prepare($query);
        $stmt->execute(
            [
                "uid" => $obj->uid,
                "key_value" => trim($tmKey->key ?? throw new Exception("Key value cannot be null")),
                "key_name" => ($tmKey->name === null) ? '' : trim($tmKey->name),
                "key_tm" => $tmKey->tm ?? true,
                "key_glos" => $tmKey->glos ?? true
            ]
        );

        if ($stmt->rowCount() > 0) {
            return $obj;
        }

        return null;
    }

    /**
     * @param int $uid
     *
     * @return MemoryKeyStruct[]
     */
    public static function getKeyringOwnerKeysByUid(int $uid): array
    {
        /*
         * Take the keys of the user
         */
        $keyList = [];

        try {
            $_keyDao = new MemoryKeyDao(Database::obtain());
            $dh = new MemoryKeyStruct(['uid' => $uid]);
            $keyList = $_keyDao->read($dh);
        } catch (Exception $e) {
            LoggerFactory::getLogger('dao')->error($e->getMessage());
        }

        return $keyList;
    }

    /**
     * @param MemoryKeyStruct $obj
     * @param bool $traverse
     * @param int $ttl
     *
     * @return MemoryKeyStruct[]
     * @throws ReflectionException
     * @throws Exception
     */
    public function read(MemoryKeyStruct $obj, bool $traverse = false, int $ttl = 0): array
    {
        $obj = $this->sanitize($obj);

        $where_params = [];
        $condition = [];

        $query = "SELECT  m1.uid, 
                                     m1.key_value, 
                                     m1.key_name, 
                                     m1.key_tm AS tm, 
                                     m1.key_glos AS glos, 
                                     sum(1) AS owners_tot, 
                                     group_concat( DISTINCT m2.uid ) AS owner_uids
                             FROM " . self::TABLE . " m1
                             LEFT JOIN " . self::TABLE . " AS m2 ON m1.key_value = m2.key_value AND m2.deleted = 0
                             WHERE %s and m1.deleted = 0
                             GROUP BY m1.key_value
			                 ORDER BY m1.creation_date desc";


        $condition[] = "m1.uid = :uid";
        $where_params['uid'] = $obj->uid;

        //tm_key conditions
        if ($obj->tm_key !== null) {
            if ($obj->tm_key->key !== null) {
                $condition[] = "m1.key_value = :key_value";
                $where_params['key_value'] = $obj->tm_key->key;
            }

            if ($obj->tm_key->name !== null) {
                $condition[] = "m1.key_name = :key_name";
                $where_params['key_name'] = $obj->tm_key->name;
            }

            if ($obj->tm_key->tm !== null) {
                $condition[] = "m1.key_tm = :key_tm";
                $where_params['key_tm'] = $obj->tm_key->tm;
            }

            if ($obj->tm_key->glos !== null) {
                $condition[] = "m1.key_glos = :key_glos";
                $where_params['key_glos'] = $obj->tm_key->glos;
            }
        }

        if (count($condition)) {
            $where_string = implode(" AND ", $condition);
        } else {
            throw new Exception("Where condition needed.");
        }

        $query = sprintf($query, $where_string);

        $stmt = $this->database->getConnection()->prepare($query);

        /**
         * @var list<ShapelessConcreteStruct> $arr_result
         */
        $arr_result = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, $where_params);

        if ($traverse) {
            $userDao = new UserDao(Database::obtain());

            foreach ($arr_result as $k => $row) {
                $users = $userDao->getByUids(explode(",", $row['owner_uids']));
                $arr_result[$k]['in_users'] = $users;
            }
        } else {
            foreach ($arr_result as $k => $row) {
                $arr_result[$k]['in_users'] = $row['owner_uids'];
            }
        }

        /** @var list<ShapelessConcreteStruct> $arr_result */
        return $this->_buildResult($arr_result);
    }

    /**
     * @param MemoryKeyStruct $obj
     *
     * @return MemoryKeyStruct|null
     * @throws Exception
     */
    public function atomicUpdate(MemoryKeyStruct $obj): ?MemoryKeyStruct
    {
        $obj = $this->sanitize($obj);

        $this->_validatePrimaryKey($obj);
        $this->_validateNotNullFields($obj);

        $tmKey = $obj->tm_key ?? throw new Exception("Invalid Key value");

        $set_array = [];
        $where_conditions = [];
        $bind_params = [];

        $query = "UPDATE " . self::TABLE . " SET %s WHERE %s";

        $where_conditions[] = "uid = :uid";
        $bind_params['uid'] = $obj->uid;

        $where_conditions[] = "key_value = :key_value";
        $bind_params['key_value'] = $tmKey->key;

        if ($tmKey->name !== null) {
            $set_array[] = "key_name = :key_name";
            $bind_params['key_name'] = $tmKey->name;
        }

        $where_string = implode(" AND ", $where_conditions);

        if (count($set_array)) {
            $set_string = implode(", ", $set_array);
        } else {
            throw new Exception("Array given is empty. Please set at least one value.");
        }

        $query = sprintf($query, $set_string, $where_string);

        $stmt = $this->database->getConnection()->prepare($query);
        $stmt->execute($bind_params);

        if ($stmt->rowCount()) {
            return $obj;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function delete(MemoryKeyStruct $obj): ?MemoryKeyStruct
    {
        $obj = $this->sanitize($obj);

        $this->_validatePrimaryKey($obj);
        $this->_validateNotNullFields($obj);

        $tmKey = $obj->tm_key ?? throw new Exception("Invalid Key value");

        $query = "DELETE FROM " . self::TABLE . " WHERE uid = :uid and key_value = :key_value";

        $stmt = $this->database->getConnection()->prepare($query);
        $stmt->execute([
            'uid' => $obj->uid,
            'key_value' => $tmKey->key
        ]);

        if ($stmt->rowCount() > 0) {
            return $obj;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function disable(MemoryKeyStruct $obj): ?MemoryKeyStruct
    {
        $obj = $this->sanitize($obj);

        $this->_validatePrimaryKey($obj);
        $this->_validateNotNullFields($obj);

        $tmKey = $obj->tm_key ?? throw new Exception("Invalid Key value");

        $query = "UPDATE " . self::TABLE . " set deleted = 1 WHERE uid = :uid and key_value = :key_value";

        $stmt = $this->database->getConnection()->prepare($query);
        $stmt->execute([
            'uid' => $obj->uid,
            'key_value' => $tmKey->key
        ]);

        if ($stmt->rowCount() > 0) {
            return $obj;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function enable(MemoryKeyStruct $obj): ?MemoryKeyStruct
    {
        $obj = $this->sanitize($obj);

        $this->_validatePrimaryKey($obj);
        $this->_validateNotNullFields($obj);

        $tmKey = $obj->tm_key ?? throw new Exception("Invalid Key value");

        $query = "UPDATE " . self::TABLE . " set deleted = 0 WHERE uid = :uid and key_value = :key_value";

        $stmt = $this->database->getConnection()->prepare($query);
        $stmt->execute([
            'uid' => $obj->uid,
            'key_value' => $tmKey->key
        ]);

        if ($stmt->rowCount() > 0) {
            return $obj;
        }

        return null;
    }


    /**
     * @param MemoryKeyStruct[] $obj_arr
     *
     * @throws Exception
     */
    public function createList(array $obj_arr): void
    {
        $obj_arr = self::sanitizeArray($obj_arr);

        $query = "INSERT INTO " . self::TABLE .
            " ( uid, key_value, key_name, key_tm, key_glos, creation_date)
                VALUES ";

        $values = [];

        $objects = array_chunk($obj_arr, self::MAX_INSERT_NUMBER);

        foreach ($objects as $chunk) {
            $insert_query = $query;
            /** @var MemoryKeyStruct[] $chunk */
            foreach ($chunk as $obj) {
                $this->_validateNotNullFields($obj);
                $tmKey = $obj->tm_key ?? throw new Exception("Key value cannot be null");
                $insert_query .= "( ?, ?, ?, ?, ?, NOW() ),";

                $values[] = $obj->uid;
                $values[] = $tmKey->key ?? throw new Exception("Key value cannot be null");
                $values[] = $tmKey->name ?? '';
                $values[] = $tmKey->tm ?? true;
                $values[] = $tmKey->glos ?? true;
            }

            $insert_query = rtrim($insert_query, ",");

            $stmt = $this->database->getConnection()->prepare($insert_query);
            $stmt->execute($values);
            $values = [];
        }
    }

    /**
     * @param MemoryKeyStruct $input
     *
     * @return MemoryKeyStruct
     * @throws Exception
     */
    public function sanitize(IDaoStruct $input): MemoryKeyStruct
    {
        parent::_sanitizeInput($input, self::STRUCT_TYPE);

        return $input;
    }

    /**
     * @param MemoryKeyStruct[] $input
     *
     * @return MemoryKeyStruct[]
     * @throws Exception
     */
    public static function sanitizeArray(array $input): array
    {
        $result = [];
        foreach ($input as $elem) {
            if (!$elem instanceof MemoryKeyStruct) {
                throw new Exception("Invalid input. Expected " . self::STRUCT_TYPE, -1);
            }
            $result[] = $elem;
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    protected function _validatePrimaryKey(IDaoStruct $obj): void
    {
        if (!$obj instanceof MemoryKeyStruct) {
            throw new Exception("Expected MemoryKeyStruct");
        }

        if (empty($obj->uid)) {
            throw new Exception("Invalid Uid");
        }

        if ($obj->tm_key === null || empty($obj->tm_key->key)) {
            throw new Exception("Invalid Key value");
        }
    }

    /**
     * @throws Exception
     */
    protected function _validateNotNullFields(IDaoStruct $obj): void
    {
        if (!$obj instanceof MemoryKeyStruct) {
            throw new Exception("Expected MemoryKeyStruct");
        }

        if (empty($obj->uid)) {
            throw new Exception("Uid cannot be null");
        }

        if ($obj->tm_key === null || $obj->tm_key->key === null) {
            throw new Exception("Key value cannot be null");
        }
    }

    /**
     * Builds an array with a result set according to the data structure it handles.
     *
     * @param list<ShapelessConcreteStruct> $array_result
     *
     * @return list<MemoryKeyStruct>
     *
     * @throws \DomainException
     */
    protected function _buildResult(array $array_result): array
    {
        $result = [];

        foreach ($array_result as $item) {
            $owner_uids = explode(",", $item['owner_uids']);

            $build_arr = [
                'uid' => $item['uid'],
                'tm_key' => new TmKeyStruct([
                        'key' => (string)$item['key_value'],
                        'name' => (string)$item['key_name'],
                        'tm' => (bool)$item['tm'],
                        'glos' => (bool)$item['glos'],
                        'is_shared' => ($item['owners_tot'] > 1),
                        'in_users' => $item['in_users'],
                        'owner' => in_array($item['uid'], $owner_uids),
                    ]
                )
            ];

            $obj = new MemoryKeyStruct($build_arr);

            $result[] = $obj;
        }

        return $result;
    }


}
