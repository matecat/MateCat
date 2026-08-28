<?php

namespace Model\Users;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\InvalidatesUserProfileCache;
use PDO;
use PDOException;
use ReflectionException;

class MetadataDao extends AbstractDao
{

    use InvalidatesUserProfileCache;

    const string TABLE = 'user_metadata';

    const string _query_metadata_by_uid_key = "SELECT * FROM user_metadata WHERE uid = :uid AND `key` = :key ";

    const string _query_metadata_by_uid = "SELECT * FROM user_metadata WHERE uid = :uid ";

    /**
     * The cache address of one user's metadata, shared by the single and the batched read.
     *
     * Both have to name the same entry: it is what lets a write to one user's metadata — which
     * knows one uid and nothing about any list that uid appears in — evict what a member list
     * cached.
     */
    private static function uidKeyMapPrefix(): string
    {
        return self::class . '::getAllByUid-';
    }

    /**
     * Load the metadata of several users at once.
     *
     * Each user's rows are cached under their own entry rather than the set under one, so a write
     * to one user evicts it everywhere, and adding or removing a member leaves the other members
     * cached. The cache read is a single round trip; only members that missed are read from the
     * database.
     *
     * @param array<int, int> $UIDs
     *
     * @return array<int|string, list<MetadataStruct>> Keyed by uid. Uids with no metadata are absent.
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getAllByUidList(array $UIDs): array
    {
        if (empty($UIDs)) {
            return [];
        }

        $perUid = $this->_fetchObjectMapPerId(
            array_values($UIDs),
            self::_query_metadata_by_uid,
            'uid',
            MetadataStruct::class,
            self::uidKeyMapPrefix(),
            fn(array $missing): array => $this->loadMetadataByUids($missing)
        );

        $resultSet = [];

        foreach ($perUid as $uid => $rows) {
            if ($rows !== []) {
                $resultSet[$uid] = $rows;
            }
        }

        return $resultSet;
    }

    /**
     * @param int $uid
     *
     * @return array<int, MetadataStruct>
     * @throws PDOException
     * @throws ReflectionException
     * @throws Exception
     */
    public function getAllByUid(int $uid): array
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_uid);

        /** @var list<MetadataStruct> $rows */
        $rows = $this->_fetchObjectMap(
            $stmt,
            MetadataStruct::class,
            ['uid' => $uid],
            // Named rather than left to the backtrace default, because getAllByUidList() has to
            // build the identical address from outside this method.
            self::uidKeyMapPrefix() . $uid
        );

        return $rows;
    }

    /**
     * Evict one user's metadata. The door the batched read needs: it names a uid, which is all a
     * write to that user's metadata knows, and it reaches every list that cached it.
     *
     * @throws PDOException
     * @throws ReflectionException
     */
    public function destroyCacheAllByUid(int $uid): bool
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_uid);

        return $this->_destroyObjectCache($stmt, MetadataStruct::class, ['uid' => $uid]);
    }

    /**
     * The uncached bulk read behind `getAllByUidList()`. It fills cache entries but is never itself
     * cached: a result addressed by the whole uid list is the thing no eviction door can reach.
     *
     * @param list<int|string> $uids
     *
     * @return array<int, list<MetadataStruct>>
     * @throws PDOException
     */
    private function loadMetadataByUids(array $uids): array
    {
        $stmt = $this->_getStatementForQuery(
            "SELECT * FROM user_metadata WHERE " .
            " uid IN( " . str_repeat('?,', count($uids) - 1) . '?' . " ) "
        );

        $stmt->setFetchMode(PDO::FETCH_CLASS, MetadataStruct::class);
        $stmt->execute(array_values($uids));

        $byUid = [];

        foreach ($stmt->fetchAll() as $row) {
            if ($row instanceof MetadataStruct) {
                $byUid[(int)$row->uid][] = $row;
            }
        }

        return $byUid;
    }

    /**
     * @param int $uid
     * @param string $key
     *
     * @return MetadataStruct|null
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function get(int $uid, string $key): ?MetadataStruct
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_uid_key);
        /** @var MetadataStruct[] $result */
        $result = $this->_fetchObjectMap($stmt, MetadataStruct::class, [
            'uid' => $uid,
            'key' => $key
        ]);

        return $result[0] ?? null;
    }

    /**
     * @param int $uid
     * @param string $key
     *
     * @throws PDOException
     * @throws ReflectionException
     */
    public function destroyCacheKey(int $uid, string $key): bool
    {
        $stmt = $this->_getStatementForQuery(self::_query_metadata_by_uid_key);

        return $this->_destroyObjectCache($stmt, MetadataStruct::class, ['uid' => $uid, 'key' => $key]);
    }

    /**
     * @param int $uid
     * @param string $key
     * @param array<int|string, mixed>|string $value
     *
     * @return MetadataStruct
     * @throws PDOException
     * @throws ReflectionException
     * @throws Exception
     */
    public function set(int $uid, string $key, array|string $value): MetadataStruct
    {
        $sql = "INSERT INTO user_metadata " .
            " ( uid, `key`, value ) " .
            " VALUES " .
            " ( :uid, :key, :value ) " .
            " ON DUPLICATE KEY UPDATE value = :value ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'uid' => $uid,
            'key' => $key,
            'value' => (is_array($value)) ? serialize($value) : $value,
        ]);

        $this->destroyCacheKey($uid, $key);
        $this->destroyCacheAllByUid($uid);
        $this->invalidateUserProfileCache($uid);

        return new MetadataStruct([
            'id' => $conn->lastInsertId(),
            'uid' => $uid,
            'key' => $key,
            'value' => $value
        ]);
    }


    /**
     * @param int $uid
     * @param string $key
     *
     * @throws PDOException
     * @throws ReflectionException
     * @throws Exception
     */
    public function delete(int $uid, string $key): void
    {
        $sql = "DELETE FROM user_metadata " .
            " WHERE uid = :uid " .
            " AND `key` LIKE :key ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'uid' => $uid,
            'key' => '%' . $key,
        ]);
        $this->destroyCacheKey($uid, $key);
        $this->destroyCacheAllByUid($uid);
        $this->invalidateUserProfileCache($uid);
    }
}
