<?php

namespace Model\ApiKeys;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use PDO;
use PDOException;

class ApiKeyDao extends AbstractDao
{

    /**
     * @param string $key
     *
     * @return ApiKeyStruct|null
     * @throws PDOException
     */
    static function findByKey(string $key): ?ApiKeyStruct
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("SELECT * FROM api_keys WHERE enabled AND api_key = :key ");
        $stmt->execute(['key' => $key]);

        $stmt->setFetchMode(PDO::FETCH_CLASS, ApiKeyStruct::class);

        return $stmt->fetch() ?? null;
    }

    /**
     * @param ApiKeyStruct $obj
     * @throws PDOException
     */
    public function create(ApiKeyStruct $obj): ApiKeyStruct
    {
        $conn = $this->database->getConnection();

        $obj->create_date = date('Y-m-d H:i:s');
        $obj->last_update = date('Y-m-d H:i:s');

        $stmt = $conn->prepare(
            "INSERT INTO api_keys " .
            " ( uid, api_key, api_secret, create_date, last_update, enabled ) " .
            " VALUES " .
            " ( :uid, :api_key, :api_secret, :create_date, :last_update, :enabled ) "
        );

        $values = array_diff_key($obj->toArray(), ['id' => null]);

        $this->database->begin();
        $stmt->execute($values);
        $result = $this->getById((int)$conn->lastInsertId());
        $this->database->commit();

        return $result[0];
    }

    /**
     * @param int $id
     *
     * @return list<ApiKeyStruct>
     * @throws PDOException
     */
    public function getById(int $id): array
    {
        $conn = $this->database->getConnection();

        $stmt = $conn->prepare(" SELECT * FROM api_keys WHERE id = ? ");
        $stmt->execute([$id]);

        return array_values($stmt->fetchAll(PDO::FETCH_CLASS, ApiKeyStruct::class));
    }

    /**
     * @param int $uid
     *
     * @return ApiKeyStruct|null
     * @throws PDOException
     */
    public function getByUid(int $uid): ?ApiKeyStruct
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("SELECT * FROM api_keys WHERE enabled AND uid = :uid ");
        $stmt->execute(['uid' => $uid]);

        $stmt->setFetchMode(PDO::FETCH_CLASS, ApiKeyStruct::class);

        return $stmt->fetch() ?: null;
    }

    /**
     * @param int $uid
     * @throws PDOException
     */
    public function deleteByUid(int $uid): int
    {
        $apiKey = $this->getByUid($uid);

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("DELETE FROM api_keys WHERE id = :id ");
        $stmt->execute(['id' => $apiKey->id]);

        return $stmt->rowCount();
    }
}
