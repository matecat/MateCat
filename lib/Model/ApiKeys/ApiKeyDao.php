<?php

namespace Model\ApiKeys;

use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use PDO;
use PDOException;
use ReflectionException;
use RuntimeException;

class ApiKeyDao extends AbstractDao
{

    const string TABLE = 'api_keys';

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
     *
     * @return ApiKeyStruct
     * @throws PDOException
     * @throws ReflectionException
     * @throws RuntimeException
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
        $result = $this->fetchById((int)$conn->lastInsertId(), ApiKeyStruct::class);
        $this->database->commit();

        return $result ?? throw new RuntimeException('Failed to retrieve created API key');
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
     *
     * @return int
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
