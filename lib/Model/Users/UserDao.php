<?php

namespace Model\Users;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use PDO;
use PDOException;
use ReflectionException;
use TypeError;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/04/15
 * Time: 12.54
 */
class UserDao extends AbstractDao
{

    const string TABLE = "users";
    const string STRUCT_TYPE = UserStruct::class;

    /** @var list<string> */
    protected static array $auto_increment_field = ['uid'];
    /** @var list<string> */
    protected static array $primary_keys = ['uid'];

    protected static string $_query_user_by_uid = " SELECT * FROM users WHERE uid = :uid ";
    protected static string $_query_user_by_email = " SELECT * FROM users WHERE email = :email ";
    protected static string $_query_assignee_by_project_id = "SELECT * FROM users 
        INNER JOIN projects ON projects.id_assignee = users.uid 
        WHERE projects.id = :id_project
        LIMIT 1 ";

    protected static string $_query_owner_by_job_id = "SELECT * FROM users 
        INNER JOIN jobs ON jobs.owner = users.email
        WHERE jobs.id = :job_id
        LIMIT 1 ";

    /**
     * @param UserStruct $userStruct
     *
     * @return int
     * @throws PDOException
     */
    public function delete(UserStruct $userStruct): int
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(" DELETE FROM users WHERE uid = ?");
        $stmt->execute([$userStruct->uid]);

        return $stmt->rowCount();
    }

    /**
     * @param array<int, int|string|array{uid:int|string}> $uids_array
     *
     * @return UserStruct[]
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getByUids(array $uids_array): array
    {
        $sanitized_array = [];

        foreach ($uids_array as $v) {
            if (is_array($v)) {
                if (isset($v['uid'])) {
                    $sanitized_array[] = (int)$v['uid'];
                }
            } elseif (is_numeric($v)) {
                $sanitized_array[] = (int)$v;
            }
        }

        if (empty($sanitized_array)) {
            return [];
        }

        $query = "SELECT * FROM " . self::TABLE .
            " WHERE uid IN ( " . str_repeat('?,', count($sanitized_array) - 1) . '?' . " ) ";

        $stmt = $this->_getStatementForQuery($query);

        /**
         * @var UserStruct[] $__resultSet
         */
        $__resultSet = $this->_fetchObjectMap(
            $stmt,
            UserStruct::class,
            $sanitized_array
        );

        $resultSet = [];
        if (!is_iterable($__resultSet)) {
            return $resultSet;
        }

        foreach ($__resultSet as $user) {
            if (!$user instanceof UserStruct) {
                continue;
            }

            $resultSet[$user->uid] = $user;
        }

        return $resultSet;
    }

    /**
     * @param string $token
     *
     * @return ?UserStruct
     * @throws PDOException
     */
    public function getByConfirmationToken(string $token): ?UserStruct
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(" SELECT * FROM users WHERE confirmation_token = ?");
        $stmt->execute([$token]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, UserStruct::class);

        return $stmt->fetch() ?: null;
    }

    /**
     * @param UserStruct $obj
     *
     * @return UserStruct|null
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function createUser(UserStruct $obj): ?UserStruct
    {
        $conn = $this->database->getConnection();
        Database::obtain()->begin();

        $obj->create_date = date('Y-m-d H:i:s');
        $stmt = $conn->prepare(
            "INSERT INTO users " .
            " ( uid, email, salt, pass, create_date, first_name, last_name, confirmation_token ) " .
            " VALUES " .
            " ( " .
            " :uid, :email, :salt, :pass, :create_date, " .
            " :first_name, :last_name, :confirmation_token " .
            " )"
        );

        $stmt->execute($obj->toArray([
            'uid',
            'email',
            'salt',
            'pass',
            'create_date',
            'first_name',
            'last_name',
            'confirmation_token'
        ])
        );

        $id = $conn->lastInsertId();
        if ($id === false) {
            throw new Exception('Unable to retrieve last inserted user id');
        }

        $record = $this->getByUid((int)$id);
        $conn->commit();

        if (!$record instanceof UserStruct) {
            throw new Exception('Unable to reload updated user');
        }

        return $record;
    }

    /**
     * @param UserStruct $obj
     *
     * @return UserStruct
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function updateUser(UserStruct $obj): UserStruct
    {
        $conn = $this->database->getConnection();
        Database::obtain()->begin();

        $stmt = $conn->prepare(
            "UPDATE users
            SET 
                uid = :uid, 
                email = :email, 
                salt = :salt, 
                pass = :pass, 
                create_date = :create_date, 
                first_name = :first_name, 
                last_name = :last_name, 
                confirmation_token = :confirmation_token, 
                oauth_access_token = :oauth_access_token 
            WHERE uid = :uid 
        "
        );

        $stmt->execute($obj->toArray([
            'uid',
            'email',
            'salt',
            'pass',
            'create_date',
            'first_name',
            'last_name',
            'confirmation_token',
            'oauth_access_token'
        ])
        );

        $record = $this->getByUid((int)$obj->uid);
        $conn->commit();

        if (!$record instanceof UserStruct) {
            throw new Exception('Unable to reload updated user');
        }

        return $record;
    }

    /**
     * @param int|string $id
     *
     * @return ?UserStruct
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getByUid(int|string $id): ?UserStruct
    {
        $stmt = $this->_getStatementForQuery(self::$_query_user_by_uid);

        /**
         * @var ?UserStruct $res
         */
        $res = $this->_fetchObjectMap(
            $stmt,
            UserStruct::class,
            [
                'uid' => $id,
            ]
        )[0] ?? null;

        if (!$res instanceof UserStruct) {
            return null;
        }

        return $res;
    }

    /**
     * @throws PDOException
     * @throws ReflectionException
     */
    public function destroyCacheByUid(int|string $uid): bool
    {
        $stmt = $this->_getStatementForQuery(self::$_query_user_by_uid);

        return $this->_destroyObjectCache(
            $stmt,
            UserStruct::class,
            [
                'uid' => $uid,
            ]
        );
    }

    /**
     * @param string $email
     *
     * @return ?UserStruct
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getByEmail(string $email): ?UserStruct
    {
        $stmt = $this->_getStatementForQuery(self::$_query_user_by_email);

        /**
         * @var ?UserStruct $res
         */
        $res = $this->_fetchObjectMap(
            $stmt,
            UserStruct::class,
            ['email' => $email]
        )[0] ?? null;

        if (!$res instanceof UserStruct) {
            return null;
        }

        return $res;
    }

    /**
     * @param string $email
     *
     * @return bool
     * @throws PDOException
     * @throws ReflectionException
     * @throws TypeError
     */
    public function destroyCacheByEmail(string $email): bool
    {
        $stmt = $this->_getStatementForQuery(self::$_query_user_by_email);
        $userQuery = new UserStruct();
        $userQuery->email = $email;

        return $this->_destroyObjectCache($stmt, UserStruct::class, ['email' => $userQuery->email]);
    }


    /**
     *
     * This method is not static and used also to cache at Redis level the values for this Job
     *
     * Use when only the metadata is necessary
     *
     * @param UserStruct $UserQuery
     *
     * @return UserStruct[]
     * @throws Exception
     */
    public function read(UserStruct $UserQuery): array
    {
        [$query, $where_parameters] = $this->_buildReadQuery($UserQuery);
        $stmt = $this->_getStatementForQuery($query);

        /** @var UserStruct[] */
        return $this->_fetchObjectMap(
            $stmt,
            UserStruct::class,
            $where_parameters
        );
    }

    /**
     * @return array{0:string,1:array<string,int|string>}
     * @throws Exception
     */
    protected function _buildReadQuery(UserStruct $UserQuery): array
    {
        $UserQuery = $this->sanitize($UserQuery);

        $where_conditions = [];
        $where_parameters = [];

        $query = "SELECT uid,
                                    email,
                                    create_date,
                                    first_name,
                                    last_name
                             FROM " . self::TABLE . " WHERE %s";

        if ($UserQuery->uid !== null) {
            $where_conditions[] = "uid = :uid";
            $where_parameters['uid'] = $UserQuery->uid;
        }

        if ($UserQuery->email !== null) {
            $where_conditions[] = "email = :email";
            $where_parameters['email'] = $UserQuery->email;
        }

        if (count($where_conditions)) {
            $where_string = implode(" AND ", $where_conditions);
        } else {
            throw new Exception("Where condition needed.");
        }

        return [sprintf($query, $where_string), $where_parameters];
    }

    /**
     * @param int $job_id
     *
     * @return ?UserStruct
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getProjectOwner(int $job_id): ?UserStruct
    {
        $stmt = $this->_getStatementForQuery(self::$_query_owner_by_job_id);

        /**
         * @var UserStruct $res
         */
        $res = $this->_fetchObjectMap(
            $stmt,
            UserStruct::class,
            ['job_id' => $job_id]
        )[0] ?? null;

        if (!$res instanceof UserStruct) {
            return null;
        }

        return $res;
    }

    /**
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getProjectAssignee(int $project_id): ?UserStruct
    {
        $stmt = $this->_getStatementForQuery(self::$_query_assignee_by_project_id);

        /** @var UserStruct $res */
        $res = $this->_fetchObjectMap(
            $stmt,
            UserStruct::class,
            ['id_project' => $project_id]
        )[0] ?? null;

        if (!$res instanceof UserStruct) {
            return null;
        }

        return $res;
    }

    /**
     * @param string[] $email_list
     *
     * @return UserStruct[]
     * @throws PDOException
     */
    public function getByEmails(array $email_list): array
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(" SELECT * FROM users WHERE email IN ( " . str_repeat('?,', count($email_list) - 1) . '?' . " ) ");
        $stmt->execute($email_list);
        $stmt->setFetchMode(PDO::FETCH_CLASS, UserStruct::class);
        $res = $stmt->fetchAll();
        $userMap = [];
        foreach ($res as $user) {
            $userMap[$user->email] = $user;
        }

        return $userMap;
    }

    /**
     * @param UserStruct $input
     *
     * @return UserStruct
     * @throws Exception
     */
    public function sanitize(IDaoStruct $input): UserStruct
    {
        parent::_sanitizeInput($input, self::STRUCT_TYPE);

        $input->uid = ($input->uid !== null) ? (int)$input->uid : null;

        return $input;
    }

    /**
     * @param array<int, array<string, mixed>> $array_result
     *
     * @return array<int, UserStruct>
     * @deprecated Use instead PDO::setFetchMode()
     */
    protected function _buildResult(array $array_result): array
    {
        $result = [];

        foreach ($array_result as $item) {
            $build_arr = [
                'uid'         => (int) $item['uid'],
                'email'       => $item['email'],
                'create_date' => $item['create_date'],
                'first_name'  => $item['first_name'],
                'last_name'   => $item['last_name'],
            ];

            $obj = new UserStruct($build_arr);

            $result[] = $obj;
        }

        return $result;
    }

}
