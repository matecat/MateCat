<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/04/25
 * Time: 18:44
 *
 */

namespace Model\MTQE\PayableRate;

use DateTime;
use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\MTQE\PayableRate\DTO\MTQEPayableRateBreakdowns;
use Model\Pagination\Pager;
use Model\Pagination\PaginationParameters;
use PDOException;
use ReflectionException;
use TypeError;
use Utils\Tools\Utils;

final class MTQEPayableRateTemplateDao extends AbstractDao
{

    const string TABLE = 'mt_qe_payable_rate_templates';

    const string query_by_id = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND deleted_at IS NULL";
    const string query_by_id_and_uid = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND uid = :uid AND deleted_at IS NULL";
    const string query_by_uid = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND deleted_at IS NULL";
    const string query_paginated = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid ORDER BY id LIMIT %u OFFSET %u ";
    const string paginated_map_key = __CLASS__ . "::getAllPaginated";

    /**
     * @param int $uid
     * @param string $baseRoute
     * @param int $current
     * @param int $pagination
     * @param int $ttl
     *
     * @return array<string, mixed>
     * @throws TypeError
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     * @throws \DivisionByZeroError
     */
    public function getAllPaginated(int $uid, string $baseRoute, int $current = 1, int $pagination = 20, int $ttl = 60 * 60 * 24): array
    {
        $pdo = $this->database->getConnection();

        $pager = new Pager($pdo);

        $totals = $pager->count(
            "SELECT count(id) FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid",
            ['uid' => $uid]
        );

        $paginationParameters = new PaginationParameters(static::query_paginated, ['uid' => $uid], ShapelessConcreteStruct::class, $baseRoute, $current, $pagination);
        $paginationParameters->setCache(self::paginated_map_key . ":" . $uid, $ttl);

        $result = $pager->getPagination($totals, $paginationParameters);

        $models = [];

        foreach ($result['items'] as $item) {
            $models[] = $this->hydrateTemplateStruct($item->getArrayCopy());
        }

        $result['items'] = $models;

        return $result;
    }

    /**
     * @param int $id
     * @param int $uid
     * @param int $ttl
     *
     * @return MTQEPayableRateStruct|null
     * @throws Exception
     * @throws TypeError
     * @throws ReflectionException
     */
    public function getByIdAndUser(int $id, int $uid, int $ttl = 60): ?MTQEPayableRateStruct
    {
        $stmt = $this->_getStatementForQuery(self::query_by_id_and_uid);
        $result = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, [
            'id' => $id,
            'uid' => $uid,
        ]);

        if (empty($result)) {
            return null;
        }

        return $this->hydrateTemplateStruct((array)$result[0]);
    }

    /**
     * @param int $uid
     * @param int $ttl
     *
     * @return MTQEPayableRateStruct[]
     * @throws Exception
     * @throws TypeError
     * @throws ReflectionException
     */
    public function getByUid(int $uid, int $ttl = 60): array
    {
        $stmt = $this->_getStatementForQuery(self::query_by_uid);
        $result = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, [
            'uid' => $uid,
        ]);

        if (empty($result)) {
            return [];
        }

        $res = [];

        foreach ($result as $r) {
            $res[] = $this->hydrateTemplateStruct((array)$r);
        }

        return array_values(array_filter($res, fn($item) => $item !== null));
    }

    /**
     * WARNING Use this method only when no user authentication is needed or when it is already performed
     *
     * @param int $id
     * @param int $ttl
     *
     * @return MTQEPayableRateStruct|null
     * @throws Exception
     * @throws TypeError
     * @throws ReflectionException
     */
    public function getById(int $id, int $ttl = 60): ?MTQEPayableRateStruct
    {
        $stmt = $this->_getStatementForQuery(self::query_by_id);
        $result = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, [
            'id' => $id,
        ]);

        if (empty($result)) {
            return null;
        }

        return $this->hydrateTemplateStruct((array)$result[0]);
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
        $stmt = $conn->prepare("UPDATE " . self::TABLE . " SET `name` = :name , `deleted_at` = :now WHERE id = :id AND uid = :uid AND `deleted_at` IS NULL;");
        $stmt->execute([
            'id' => $id,
            'uid' => $uid,
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
            'name' => 'deleted_' . Utils::randomString()
        ]);

        $this->destroyQueryByIdCache($id);
        $this->destroyQueryByIdAndUserCache($id, $uid);
        $this->destroyQueryByUidCache($uid);
        $this->destroyQueryPaginated($uid);

        return $stmt->rowCount();
    }

    /**
     * @param int $uid
     *
     * @return MTQEPayableRateStruct
     */
    public function getDefaultTemplate(int $uid): MTQEPayableRateStruct
    {
        return new MTQEPayableRateStruct([
            'breakdowns' => new MTQEPayableRateBreakdowns(),
            'name' => "Matecat default settings",
            'uid' => $uid,
            'id' => 0,
            'created_at' => date("Y-m-d H:i:s")
        ]);
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
        $this->_destroyObjectCache($stmt, ShapelessConcreteStruct::class, ['id' => $id]);
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
        $stmt = $this->database->getConnection()->prepare(self::query_by_id_and_uid);
        $this->_destroyObjectCache($stmt, ShapelessConcreteStruct::class, ['id' => $id, 'uid' => $uid]);
    }

    /**
     * @param int $uid
     *
     * @throws PDOException
     * @throws ReflectionException
     */
    private function destroyQueryByUidCache(int $uid): void
    {
        $stmt = $this->database->getConnection()->prepare(self::query_by_uid);
        $this->_destroyObjectCache($stmt, ShapelessConcreteStruct::class, ['uid' => $uid]);
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

    /**
     * @param array<string, mixed> $data
     *
     * @return MTQEPayableRateStruct|null
     * @throws TypeError
     */
    private function hydrateTemplateStruct(array $data): ?MTQEPayableRateStruct
    {
        if (
            !isset($data['id']) or
            !isset($data['uid']) or
            !isset($data['name']) or
            !isset($data['breakdowns'])
        ) {
            return null;
        }

        $struct = new MTQEPayableRateStruct();
        $struct->id = $data['id'];
        $struct->uid = $data['uid'];
        $struct->name = $data['name'];

        $struct->created_at = $data['created_at'];
        $struct->modified_at = $data['modified_at'];
        $struct->deleted_at = $data['deleted_at'];
        $struct->hydrateBreakdownsFromJson($data['breakdowns']);

        return $struct;
    }

}
