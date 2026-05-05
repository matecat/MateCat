<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/04/25
 * Time: 18:44
 *
 */

namespace Model\MTQE\Templates;

use DateTime;
use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Model\Pagination\Pager;
use Model\Pagination\PaginationParameters;
use PDO;
use ReflectionException;
use Utils\Tools\Utils;

class MTQEWorkflowTemplateDao extends AbstractDao
{

    const string TABLE = 'mt_qe_templates';

    const string query_by_id = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND deleted_at IS NULL";
    const string query_by_id_and_uid = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND uid = :uid AND deleted_at IS NULL";
    const string query_by_uid = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND deleted_at IS NULL";
    const string query_paginated = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid ORDER BY id LIMIT %u OFFSET %u ";
    const string paginated_map_key = __CLASS__ . "::getAllPaginated";

    /**
     * @var MTQEWorkflowTemplateDao|null
     */
    private static ?MTQEWorkflowTemplateDao $instance = null;

    /**
     * @return MTQEWorkflowTemplateDao
     */
    private static function getInstance(): MTQEWorkflowTemplateDao
    {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @param int $uid
     * @param string $baseRoute
     * @param int $current
     * @param int $pagination
     * @param int $ttl
     *
     * @return array
     * @throws ReflectionException
     */
    public static function getAllPaginated(int $uid, string $baseRoute, int $current = 1, int $pagination = 20, int $ttl = 60 * 60 * 24): array
    {
        $pdo = Database::obtain()->getConnection();

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
            $models[] = self::hydrateTemplateStruct($item->getArrayCopy());
        }

        $result['items'] = $models;

        return $result;
    }

    /**
     * @param int $uid
     *
     * @throws ReflectionException
     */
    private static function destroyQueryPaginated(int $uid): void
    {
        self::getInstance()->_deleteCacheByKey(self::paginated_map_key . ":" . $uid, false);
    }

    /**
     * @param int $id
     * @param int $uid
     * @param int $ttl
     *
     * @return MTQEWorkflowTemplateStruct|null
     * @throws ReflectionException
     */
    public static function getByIdAndUser(int $id, int $uid, int $ttl = 60): ?MTQEWorkflowTemplateStruct
    {
        $stmt = self::getInstance()->_getStatementForQuery(self::query_by_id_and_uid);
        $result = self::getInstance()->setCacheTTL($ttl)->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, [
            'id' => $id,
            'uid' => $uid,
        ]);

        if (empty($result)) {
            return null;
        }

        return self::hydrateTemplateStruct((array)$result[0]);
    }

    /**
     * @param PDO $conn
     * @param int $id
     * @param int $uid
     *
     * @throws ReflectionException
     */
    private static function destroyQueryByIdAndUserCache(PDO $conn, int $id, int $uid): void
    {
        $stmt = $conn->prepare(self::query_by_id_and_uid);
        self::getInstance()->_destroyObjectCache($stmt, ShapelessConcreteStruct::class, ['id' => $id, 'uid' => $uid]);
    }

    /**
     * @param int $uid
     * @param int $ttl
     *
     * @return MTQEWorkflowTemplateStruct[]
     * @throws Exception
     */
    public static function getByUid(int $uid, int $ttl = 60): array
    {
        $stmt = self::getInstance()->_getStatementForQuery(self::query_by_uid);
        $result = self::getInstance()->setCacheTTL($ttl)->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, [
            'uid' => $uid,
        ]);

        if (empty($result)) {
            return [];
        }

        $res = [];

        foreach ($result as $r) {
            $res[] = self::hydrateTemplateStruct((array)$r);
        }

        return $res;
    }

    /**
     * @param PDO $conn
     * @param int $uid
     *
     * @throws ReflectionException
     */
    private static function destroyQueryByUidCache(PDO $conn, int $uid): void
    {
        $stmt = $conn->prepare(self::query_by_uid);
        self::getInstance()->_destroyObjectCache($stmt, ShapelessConcreteStruct::class, ['uid' => $uid]);
    }

    /**
     * WARNING: Use this method only when no user authentication is needed or when it is already performed
     *
     * @param     $id
     * @param int $ttl
     *
     * @return MTQEWorkflowTemplateStruct|null
     * @throws Exception
     */
    public static function getById($id, int $ttl = 60): ?MTQEWorkflowTemplateStruct
    {
        $stmt = self::getInstance()->_getStatementForQuery(self::query_by_id);
        $result = self::getInstance()->setCacheTTL($ttl)->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, [
            'id' => $id,
        ]);

        if (empty($result)) {
            return null;
        }

        return self::hydrateTemplateStruct((array)$result[0]);
    }

    /**
     * @param PDO $conn
     * @param int $id
     *
     * @throws ReflectionException
     */
    private static function destroyQueryByIdCache(PDO $conn, int $id): void
    {
        $stmt = $conn->prepare(self::query_by_id);
        self::getInstance()->_destroyObjectCache($stmt, ShapelessConcreteStruct::class, ['id' => $id,]);
    }

    /**
     * @param int $id
     * @param int $uid
     *
     * @return int
     * @throws ReflectionException
     */
    public static function remove(int $id, int $uid): int
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("UPDATE " . self::TABLE . " SET `name` = :name , `deleted_at` = :now WHERE id = :id AND uid = :uid AND `deleted_at` IS NULL;");
        $stmt->execute([
            'id' => $id,
            'uid' => $uid,
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
            'name' => 'deleted_' . Utils::randomString()
        ]);

        self::destroyQueryByIdCache($conn, $id);
        self::destroyQueryByIdAndUserCache($conn, $id, $uid);
        self::destroyQueryByUidCache($conn, $uid);
        self::destroyQueryPaginated($uid);

//        ProjectTemplateDao::removeSubTemplateByIdAndUser( $id, $uid, 'xliff_config_template_id' );

        return $stmt->rowCount();
    }

    /**
     * @param array $data
     *
     * @return MTQEWorkflowTemplateStruct|null
     */
    private static function hydrateTemplateStruct(array $data): ?MTQEWorkflowTemplateStruct
    {
        if (
            !isset($data['id']) or
            !isset($data['uid']) or
            !isset($data['name']) or
            !isset($data['params'])
        ) {
            return null;
        }

        $struct = new MTQEWorkflowTemplateStruct();
        $struct->id = $data['id'];
        $struct->uid = $data['uid'];
        $struct->name = $data['name'];

        $struct->created_at = $data['created_at'];
        $struct->modified_at = $data['modified_at'];
        $struct->deleted_at = $data['deleted_at'];
        $struct->hydrateParamsFromJson($data['params']);

        return $struct;
    }

    /**
     * @param int $uid
     *
     * @return MTQEWorkflowTemplateStruct
     */
    public static function getDefaultTemplate(int $uid): MTQEWorkflowTemplateStruct
    {
        return new MTQEWorkflowTemplateStruct([
            'params' => new MTQEWorkflowParams(),
            'name' => "Matecat default settings",
            'uid' => $uid,
            'id' => 0,
            'created_at' => date("Y-m-d H:i:s")
        ]);
    }

}