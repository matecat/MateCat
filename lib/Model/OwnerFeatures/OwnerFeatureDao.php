<?php

namespace Model\OwnerFeatures;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\IDaoStruct;
use PDOException;
use ReflectionException;
use Throwable;
use Utils\Logger\LoggerFactory;

class OwnerFeatureDao extends AbstractDao
{

    const string TABLE = 'owner_features';

    const string query_by_user_email = " SELECT * FROM owner_features INNER JOIN users ON users.uid = owner_features.uid WHERE users.email = :id_customer AND owner_features.enabled ORDER BY id ";
    const string query_user_id = "SELECT * FROM owner_features WHERE uid = :uid ORDER BY id";

    /**
     * @param OwnerFeatureStruct $obj
     *
     * @return ?OwnerFeatureStruct
     * @throws PDOException
     * @throws ReflectionException
     * @throws Exception
     * @throws Throwable
     */
    public function create(IDaoStruct $obj): ?OwnerFeatureStruct
    {
        return $this->database->transaction(function () use ($obj): ?OwnerFeatureStruct {
            $conn = $this->database->getConnection();

            /**
             * @var OwnerFeatureStruct $obj
             */
            $obj->create_date = date('Y-m-d H:i:s');
            $obj->last_update = date('Y-m-d H:i:s');

            $stmt = $conn->prepare(
                "INSERT INTO owner_features " .
                " ( uid, feature_code, options, create_date, last_update, enabled, id_team )" .
                " VALUES " .
                " ( :uid, :feature_code, :options, :create_date, :last_update, :enabled, :id_team );"
            );

            LoggerFactory::doJsonLog($obj->toArray());

            $values = array_diff_key($obj->toArray(), ['id' => null]);

            $stmt->execute($values);

            return $this->fetchById((int)$conn->lastInsertId(), OwnerFeatureStruct::class);
        });
    }

    /**
     * @return OwnerFeatureStruct[]
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function getByIdCustomer(string $id_customer, int $ttl = 3600): array
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(self::query_by_user_email);

        return $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, OwnerFeatureStruct::class, [
            'id_customer' => $id_customer
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    public function destroyCacheByIdCustomer(string $id_customer): bool
    {
        $stmt = $this->_getStatementForQuery(self::query_by_user_email);

        return $this->_destroyObjectCache($stmt, OwnerFeatureStruct::class, ['id_customer' => $id_customer]);
    }

    /**
     * @return OwnerFeatureStruct[]
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function getByUserId(?int $uid, int $ttl = 3600): array
    {
        if (empty($uid)) {
            return [];
        }

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(self::query_user_id);

        return $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, OwnerFeatureStruct::class, [
            'uid' => $uid
        ]);
    }

    /**
     * @throws ReflectionException
     * @throws PDOException
     */
    public function destroyCacheByUserId(int $uid): bool
    {
        $stmt = $this->_getStatementForQuery(self::query_user_id);

        return $this->_destroyObjectCache($stmt, OwnerFeatureStruct::class, ['uid' => $uid]);
    }


}
