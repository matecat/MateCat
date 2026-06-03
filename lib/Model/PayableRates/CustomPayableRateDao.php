<?php

namespace Model\PayableRates;

use DateTime;
use Exception;
use Model\Analysis\PayableRates;
use Model\DataAccess\AbstractDao;
use Model\Pagination\Pager;
use Model\Pagination\PaginationParameters;
use Model\Projects\ProjectTemplateDao;
use PDOException;
use ReflectionException;
use Swaggest\JsonSchema\InvalidValue;
use TypeError;
use Utils\Date\DateTimeUtil;
use Utils\Tools\Utils;

class CustomPayableRateDao extends AbstractDao
{
    const string TABLE = 'payable_rate_templates';
    const string TABLE_JOB_PIVOT = 'job_custom_payable_rates';

    const string query_by_id_and_user = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND id = :id AND uid = :uid";
    const string query_by_id = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND id = :id";
    const string query_paginated = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid LIMIT %u OFFSET %u ";
    const string paginated_map_key = __CLASS__ . "::getAllPaginated";

    /**
     * @param int $uid
     *
     * @return array{id: int, uid: int, payable_rate_template_name: string, version: int, breakdowns: array{default: array<string, mixed>}, createdAt: string|null, modifiedAt: string|null}
     * @throws Exception
     */
    public function getDefaultTemplate(int $uid): array
    {
        return [
            'id' => 0,
            'uid' => $uid,
            'payable_rate_template_name' => 'Matecat original settings',
            'version' => 1,
            'breakdowns' => [
                'default' => PayableRates::$DEFAULT_PAYABLE_RATES
            ],
            'createdAt' => DateTimeUtil::formatIsoDate(date("Y-m-d H:i:s")),
            'modifiedAt' => DateTimeUtil::formatIsoDate(date("Y-m-d H:i:s")),
        ];
    }

    /**
     * @param int $uid
     * @param string $baseRoute
     * @param int $current
     * @param int $pagination
     * @param int $ttl
     *
     * @return array<string, mixed>
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     * @throws \DivisionByZeroError
     * @throws \TypeError
     */
    public function getAllPaginated(int $uid, string $baseRoute, int $current = 1, int $pagination = 20, int $ttl = 60 * 60 * 24): array
    {
        $conn = $this->database->getConnection();

        $pager = new Pager($conn);
        $totals = $pager->count(
            "SELECT count(id) FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid",
            ['uid' => $uid]
        );

        $paginationParameters = new PaginationParameters(self::query_paginated, ['uid' => $uid], CustomPayableRateStruct::class, $baseRoute, $current, $pagination);
        $paginationParameters->setCache(self::paginated_map_key . ":" . $uid, $ttl);

        return $pager->getPagination($totals, $paginationParameters);
    }

    /**
     * @param int $id
     * @param int $ttl
     *
     * @return CustomPayableRateStruct|null
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function findById(int $id, int $ttl = 60): ?CustomPayableRateStruct
    {
        $stmt = $this->_getStatementForQuery(self::query_by_id);
        $result = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, CustomPayableRateStruct::class, [
            'id' => $id,
        ]);

        /** @var CustomPayableRateStruct[] $result */
        return $result[0] ?? null;
    }

    /**
     * @param int $id
     * @param int $uid
     * @param int $ttl
     *
     * @return CustomPayableRateStruct|null
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getByIdAndUser(int $id, int $uid, int $ttl = 60): ?CustomPayableRateStruct
    {
        $stmt = $this->_getStatementForQuery(self::query_by_id_and_user);
        $result = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, CustomPayableRateStruct::class, [
            'id' => $id,
            'uid' => $uid,
        ]);

        /** @var CustomPayableRateStruct[] $result */
        return $result[0] ?? null;
    }

    /**
     * @param CustomPayableRateStruct $customPayableRateStruct
     *
     * @return CustomPayableRateStruct
     * @throws Exception
     * @throws TypeError
     */
    public function save(CustomPayableRateStruct $customPayableRateStruct): CustomPayableRateStruct
    {
        $uid = $customPayableRateStruct->uid ?? throw new Exception("CustomPayableRateStruct::uid must not be null when saving");

        $sql = "INSERT INTO " . self::TABLE .
            " ( `uid`, `version`, `name`, `breakdowns`, `created_at`, `modified_at` ) " .
            " VALUES " .
            " ( :uid, :version, :name, :breakdowns, :now, :now ); ";

        $now = (new DateTime())->format('Y-m-d H:i:s');

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'uid' => $uid,
            'version' => 1,
            'name' => $customPayableRateStruct->name,
            'breakdowns' => $customPayableRateStruct->breakdownsToJson(),
            'now' => $now,
        ]);

        $customPayableRateStruct->id = (int)$conn->lastInsertId();
        $customPayableRateStruct->version = 1;
        $customPayableRateStruct->created_at = $now;
        $customPayableRateStruct->modified_at = $now;

        $this->destroyQueryPaginated($uid);

        return $customPayableRateStruct;
    }

    /**
     * @param CustomPayableRateStruct $customPayableRateStruct
     *
     * @return CustomPayableRateStruct
     * @throws Exception
     * @throws TypeError
     */
    public function update(CustomPayableRateStruct $customPayableRateStruct): CustomPayableRateStruct
    {
        $id = $customPayableRateStruct->id ?? throw new Exception("CustomPayableRateStruct::id must not be null when updating");
        $uid = $customPayableRateStruct->uid ?? throw new Exception("CustomPayableRateStruct::uid must not be null when updating");

        $sql = "UPDATE " . self::TABLE . " SET `uid` = :uid, `version` = :version, `name` = :name, `breakdowns` = :breakdowns, `modified_at` = :now WHERE id = :id ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'uid' => $uid,
            'version' => ($customPayableRateStruct->version + 1),
            'name' => $customPayableRateStruct->name,
            'breakdowns' => $customPayableRateStruct->breakdownsToJson(),
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        $this->destroyQueryByIdCache($id);
        $this->destroyQueryByIdAndUserCache($id, $uid);
        $this->destroyQueryPaginated($uid);

        return $customPayableRateStruct;
    }

    /**
     * @param int $id
     * @param int $uid
     *
     * @return int
     * @throws PDOException
     * @throws ReflectionException
     * @throws Exception
     */
    public function remove(int $id, int $uid): int
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare("UPDATE " . self::TABLE . " SET `name` = :name, `deleted_at` = :now WHERE id = :id AND uid = :uid AND `deleted_at` IS NULL;");
        $stmt->execute([
            'id' => $id,
            'uid' => $uid,
            'name' => 'deleted_' . Utils::randomString(),
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        $this->destroyQueryByIdCache($id);
        $this->destroyQueryByIdAndUserCache($id, $uid);
        $this->destroyQueryPaginated($uid);

        $queryAffected = $stmt->rowCount();
        (new ProjectTemplateDao())->removeSubTemplateByIdAndUser($id, $uid, 'payable_rate_template_id');

        return $queryAffected;
    }

    /**
     * @param string $json
     * @param int|null $uid
     *
     * @return CustomPayableRateStruct
     * @throws Exception
     * @throws TypeError
     */
    public function createFromJSON(string $json, int $uid = null): CustomPayableRateStruct
    {
        $customPayableRateStruct = new CustomPayableRateStruct();
        $customPayableRateStruct->hydrateFromJSON($json);

        if ($uid) {
            $customPayableRateStruct->uid = $uid;
        }

        return $this->save($customPayableRateStruct);
    }

    /**
     * @param CustomPayableRateStruct $customPayableRateStruct
     * @param string $json
     *
     * @return CustomPayableRateStruct
     * @throws InvalidValue
     * @throws Exception
     * @throws TypeError
     */
    public function editFromJSON(CustomPayableRateStruct $customPayableRateStruct, string $json): CustomPayableRateStruct
    {
        $customPayableRateStruct->hydrateFromJSON($json);

        return $this->update($customPayableRateStruct);
    }

    /**
     * @param int $modelId
     * @param int $idJob
     * @param int $version
     * @param string $name
     *
     * @return string
     * @throws PDOException
     */
    public function assocModelToJob(int $modelId, int $idJob, int $version, string $name): string
    {
        $sql = "INSERT INTO " . self::TABLE_JOB_PIVOT .
            " ( `id_job`, `custom_payable_rate_model_id`, `custom_payable_rate_model_version`, `custom_payable_rate_model_name` ) " .
            " VALUES " .
            " ( :id_job, :model_id, :version, :model_name ); ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_job' => $idJob,
            'model_id' => $modelId,
            'version' => $version,
            'model_name' => $name,
        ]);

        return (string)$conn->lastInsertId();
    }

    /**
     * @param int $id
     *
     * @throws PDOException
     * @throws ReflectionException
     */
    private function destroyQueryByIdCache(int $id): void
    {
        $stmt = $this->database->getConnection()->prepare(self::query_by_id);
        $this->_destroyObjectCache($stmt, CustomPayableRateStruct::class, ['id' => $id]);
    }

    /**
     * @param int $id
     * @param int $uid
     *
     * @throws PDOException
     * @throws ReflectionException
     */
    private function destroyQueryByIdAndUserCache(int $id, int $uid): void
    {
        $stmt = $this->database->getConnection()->prepare(self::query_by_id_and_user);
        $this->_destroyObjectCache($stmt, CustomPayableRateStruct::class, ['id' => $id, 'uid' => $uid]);
    }

    /**
     * @param int $uid
     *
     * @throws ReflectionException
     * @throws Exception
     */
    private function destroyQueryPaginated(int $uid): void
    {
        $this->_deleteCacheByKey(self::paginated_map_key . ":" . $uid, false);
    }

}
