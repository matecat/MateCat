<?php

namespace Model\Users;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use PDO;
use PDOException;
use ReflectionException;

class MetadataDao extends AbstractDao
{

    const string TABLE = 'user_metadata';

    const string _query_metadata_by_uid_key = "SELECT * FROM user_metadata WHERE uid = :uid AND `key` = :key ";

    /**
     * @param array<int, int> $UIDs
     *
     * @return array<int|string, list<MetadataStruct>>
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getAllByUidList(array $UIDs): array
    {
        if (empty($UIDs)) {
            return [];
        }

        $stmt = $this->_getStatementForQuery(
            "SELECT * FROM user_metadata WHERE " .
            " uid IN( " . str_repeat('?,', count($UIDs) - 1) . '?' . " ) "
        );

        $rs = $this->_fetchObjectMap(
            $stmt,
            MetadataStruct::class,
            $UIDs
        );

        /** @var MetadataStruct[] $rs */

        $resultSet = [];
        foreach ($rs as $metaDataRow) {
            $resultSet[$metaDataRow->uid][] = $metaDataRow;
        }

        return $resultSet;
    }

    /**
     * @param int $uid
     *
     * @return array<int, MetadataStruct>
     * @throws PDOException
     */
    public function getAllByUid(int $uid): array
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
            "SELECT * FROM user_metadata WHERE " .
            " uid = :uid "
        );
        $stmt->execute(['uid' => $uid]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, MetadataStruct::class);

        return $stmt->fetchAll();
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
     */
    public function set(int $uid, string $key, array|string $value): MetadataStruct
    {
        $sql = "INSERT INTO user_metadata " .
            " ( uid, `key`, value ) " .
            " VALUES " .
            " ( :uid, :key, :value ) " .
            " ON DUPLICATE KEY UPDATE value = :value ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'uid' => $uid,
            'key' => $key,
            'value' => (is_array($value)) ? serialize($value) : $value,
        ]);

        $this->destroyCacheKey($uid, $key);

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
     */
    public function delete(int $uid, string $key): void
    {
        $sql = "DELETE FROM user_metadata " .
            " WHERE uid = :uid " .
            " AND `key` LIKE :key ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'uid' => $uid,
            'key' => '%' . $key,
        ]);
        $this->destroyCacheKey($uid, $key);
    }
}
