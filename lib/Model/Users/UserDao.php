<?php

namespace Model\Users;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use Model\DataAccess\InvalidatesUserProfileCache;
use PDO;
use PDOException;
use ReflectionException;
use RuntimeException;
use Throwable;
use TypeError;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 01/04/15
 * Time: 12.54
 */
class UserDao extends AbstractDao
{

    use InvalidatesUserProfileCache;

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
     * @throws Exception
     */
    public function delete(UserStruct $userStruct): int
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(" DELETE FROM users WHERE uid = ?");
        $stmt->execute([$userStruct->uid]);
        $deleted = $stmt->rowCount();

        if ($deleted > 0) {
            // Gated on the row count rather than on the struct: a deleted row proves the uid it
            // matched was a real one, so there is nothing to narrow here.
            $this->invalidateUserProfileCache((int)$userStruct->uid);
        }

        return $deleted;
    }

    /**
     * The cache address of one user, shared by the single and the batched read.
     *
     * Both have to name the same entry: it is what lets the uid eviction behind `destroyCache()`
     * — which knows one uid and nothing about any list that uid appears in — evict what a member
     * list cached.
     */
    private static function uidKeyMapPrefix(): string
    {
        return self::class . '::getByUid-';
    }

    /**
     * Load several users at once.
     *
     * Each user is cached under its own entry rather than the set under one, so renaming a user
     * evicts it everywhere through `destroyCache()`, and adding or removing a member leaves the
     * other members cached. The cache read is a single round trip; only members that missed are
     * read from the database.
     *
     * @param array<int, int|string|array{uid:int|string}> $uids_array
     *
     * @return UserStruct[] Keyed by uid. Uids that match no row are absent.
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

        $perUid = $this->_fetchObjectMapPerId(
            $sanitized_array,
            self::$_query_user_by_uid,
            'uid',
            UserStruct::class,
            self::uidKeyMapPrefix(),
            fn(array $missing): array => $this->loadUsersByUids($missing)
        );

        $resultSet = [];

        foreach ($perUid as $uid => $rows) {
            $user = $rows[0] ?? null;

            if ($user instanceof UserStruct) {
                $resultSet[$uid] = $user;
            }
        }

        return $resultSet;
    }

    /**
     * The uncached bulk read behind `getByUids()`. It fills cache entries but is never itself
     * cached: a result addressed by the whole uid list is the thing no eviction door can reach.
     *
     * @param list<int|string> $uids
     *
     * @return array<int, list<UserStruct>>
     * @throws PDOException
     */
    private function loadUsersByUids(array $uids): array
    {
        $query = "SELECT * FROM " . self::TABLE .
            " WHERE uid IN ( " . str_repeat('?,', count($uids) - 1) . '?' . " ) ";

        $stmt = $this->_getStatementForQuery($query);
        $stmt->setFetchMode(PDO::FETCH_CLASS, UserStruct::class);
        $stmt->execute(array_values($uids));

        $byUid = [];

        foreach ($stmt->fetchAll() as $user) {
            if ($user instanceof UserStruct) {
                $byUid[(int)$user->uid][] = $user;
            }
        }

        return $byUid;
    }

    /**
     * Finds the account holding $rawToken for one flow only.
     *
     * Tokens are stored with a scope marker, and links carry only the random part, so each flow
     * prepends its own marker here. Presenting a token to the wrong endpoint therefore matches
     * nothing — which is what stops one flow's link being spent on another, and keeps each flow's
     * lifetime governing only its own tokens.
     *
     * The stored value is a digest, so the presented token is hashed before the lookup and the raw
     * value never reaches a query. A stored value lifted from the table is therefore not a spendable
     * link: presented as a token it would be hashed again, and the result matches no row.
     *
     * Tokens minted by earlier versions are not recognised. Both flows re-issue on request, so a
     * link that predates the deploy is answered by asking for a new one.
     *
     * @param string $rawToken the value taken from the link, without a marker
     * @param AuthTokenScope $scope the flow the caller is serving
     *
     * @throws PDOException
     */
    public function getByScopedConfirmationToken(string $rawToken, AuthTokenScope $scope): ?UserStruct
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(" SELECT * FROM users WHERE confirmation_token = ?");
        $stmt->execute([$scope->storedForm($rawToken)]);
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
     * @throws Throwable
     */
    public function createUser(UserStruct $obj): ?UserStruct
    {
        $conn = $this->database->getConnection();

        [$id, $record] = $this->database->transaction(function () use ($conn, $obj): array {
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

            return [(int)$id, $this->getByUid((int)$id)];
        });

        // Both of these stay outside the scope, where the raw commit used to put them: the throw
        // reports a row that is already durable, and rolling the insert back because the reload came
        // up empty would turn a failed read into a failed write.
        if (!$record instanceof UserStruct) {
            throw new Exception('Unable to reload updated user');
        }

        $this->invalidateUserProfileCache($id);

        return $record;
    }

    /**
     * @param UserStruct $obj
     *
     * @return UserStruct
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function updateUser(UserStruct $obj): UserStruct
    {
        $conn = $this->database->getConnection();

        $record = $this->database->transaction(function () use ($conn, $obj): ?UserStruct {
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

            return $this->getByUid((int)$obj->uid);
        });

        // Outside the scope, where the raw commit used to put them: the update is already durable,
        // and rolling it back because the reload came up empty would turn a failed read into a
        // failed write.
        if (!$record instanceof UserStruct) {
            throw new Exception('Unable to reload updated user');
        }

        $this->invalidateUserProfileCache((int)$obj->uid);

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
            ],
            // Named rather than left to the backtrace default, because getByUids() has to build
            // the identical address from outside this method. The string is what that default
            // produced, so entries already in Redis keep being found.
            self::uidKeyMapPrefix() . $id
        )[0] ?? null;

        if (!$res instanceof UserStruct) {
            return null;
        }

        return $res;
    }

    /**
     * The one way in from outside: a caller names the user it already holds, never a cache key it
     * cannot see. A user row is reachable under two addresses — `getByUid()` and `getByEmail()` —
     * and both have to go together, because a row left cached under either one answers lookups with
     * the value it held before the write for the whole TTL. The door is what makes that pairing a
     * property of the code rather than something each caller has to remember.
     *
     * `$retiredEmail` exists because the address is updatable — `updateUser()` writes
     * `email = :email` — and a struct handed here after such a write carries only the new value, so
     * the entry keyed on the old one is not derivable from it. `UserGDPRAnonymizeTask` is the caller
     * that needs it: it rewrites the address to a tombstone and then has to evict the address the
     * account was actually reachable under, which nothing on the struct still names.
     *
     * A null `email` is skipped rather than evicted: `users.email` is NOT NULL and `getByEmail()`
     * takes a non-nullable string, so no stored row was ever cached under a null bind. There is no
     * entry at that address to remove.
     *
     * This does not call `invalidateUserProfileCache()`. That bust hangs off the write methods in
     * {@see InvalidatesUserProfileCache}, so it has already run by the time a caller gets here;
     * repeating it would make the door look like the place that owns it.
     *
     * @throws PDOException
     * @throws ReflectionException
     * @throws RuntimeException when the struct carries no uid, which is the identity the entry is
     *                          addressed by — there would be nothing to evict and a caller would
     *                          believe it had invalidated the row
     * @throws TypeError
     */
    public function destroyCache(UserStruct $user, ?string $retiredEmail = null): void
    {
        $uid = $user->uid ?? throw new RuntimeException('User uid must be set before cache invalidation');

        $this->destroyCacheByUid($uid);

        if ($user->email !== null) {
            $this->destroyCacheByEmail($user->email);
        }

        if ($retiredEmail !== null && $retiredEmail !== $user->email) {
            $this->destroyCacheByEmail($retiredEmail);
        }
    }

    /**
     * @throws PDOException
     * @throws ReflectionException
     */
    private function destroyCacheByUid(int|string $uid): bool
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
    private function destroyCacheByEmail(string $email): bool
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
