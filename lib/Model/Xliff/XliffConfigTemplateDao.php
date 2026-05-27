<?php

namespace Model\Xliff;

use DateTime;
use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\IDatabase;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Pagination\Pager;
use Model\Pagination\PaginationParameters;
use Model\Projects\ProjectTemplateDao;
use PDO;
use PDOException;
use ReflectionException;
use TypeError;
use Utils\Tools\Utils;

class XliffConfigTemplateDao extends AbstractDao
{

    const string TABLE = 'xliff_config_templates';

    const string query_by_id = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND deleted_at IS NULL";
    const string query_by_id_and_uid = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND uid = :uid AND deleted_at IS NULL";
    const string query_by_uid = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND deleted_at IS NULL";
    const string query_paginated = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid ORDER BY id LIMIT %u OFFSET %u ";
    const string paginated_map_key = __CLASS__ . "::getAllPaginated";

    private ProjectTemplateDao $projectTemplateDao;

    public function __construct(
        ?IDatabase $con = null,
        ?ProjectTemplateDao $projectTemplateDao = null,
    ) {
        parent::__construct($con);
        $this->projectTemplateDao = $projectTemplateDao ?? new ProjectTemplateDao();
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
     * @throws Exception
     * @throws TypeError
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

        $paginationParameters = new PaginationParameters(self::query_paginated, ['uid' => $uid], ShapelessConcreteStruct::class, $baseRoute, $current, $pagination);
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
     * @param int $uid
     *
     * @return XliffConfigTemplateStruct
     */
    public function getDefaultTemplate(int $uid): XliffConfigTemplateStruct
    {
        $default = new XliffConfigTemplateStruct();
        $default->id = 0;
        $default->uid = $uid;
        $default->name = "Matecat original settings";
        $default->created_at = date("Y-m-d H:i:s");
        $default->modified_at = date("Y-m-d H:i:s");

        return $default;
    }

    /**
     * @param string $json
     * @param int $uid
     *
     * @return XliffConfigTemplateStruct
     * @throws Exception
     * @throws TypeError
     */
    public function createFromJSON(string $json, int $uid): XliffConfigTemplateStruct
    {
        $templateStruct = new XliffConfigTemplateStruct();
        $templateStruct->hydrateFromJSON($json, $uid);

        return $this->save($templateStruct);
    }

    /**
     * @param XliffConfigTemplateStruct $templateStruct
     * @param string $json
     * @param int $uid
     *
     * @return XliffConfigTemplateStruct
     * @throws Exception
     * @throws TypeError
     */
    public function editFromJSON(XliffConfigTemplateStruct $templateStruct, string $json, int $uid): XliffConfigTemplateStruct
    {
        $templateStruct->hydrateFromJSON($json, $uid);

        return $this->update($templateStruct);
    }

    /**
     * WARNING Use this method only when no user authentication is needed or when it is already performed
     *
     * @param int $id
     * @param int $ttl
     *
     * @return XliffConfigTemplateStruct|null
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function getById(int $id, int $ttl = 60): ?XliffConfigTemplateStruct
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
     * @param int $ttl
     *
     * @return XliffConfigTemplateStruct|null
     * @throws Exception
     * @throws TypeError
     */
    public function getByIdAndUser(int $id, int $uid, int $ttl = 60): ?XliffConfigTemplateStruct
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
     * @return XliffConfigTemplateStruct[]
     * @throws Exception
     * @throws TypeError
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

        $this->destroyQueryByIdCache($conn, $id);
        $this->destroyQueryByIdAndUidCache($conn, $id, $uid);
        $this->destroyQueryByUidCache($conn, $uid);
        $this->destroyQueryPaginated($uid);

        $this->projectTemplateDao->removeSubTemplateByIdAndUser($id, $uid, 'xliff_config_template_id');

        return $stmt->rowCount();
    }

    /**
     * @param PDO $conn
     * @param int $id
     *
     * @throws PDOException
     * @throws ReflectionException
     */
    private function destroyQueryByIdCache(PDO $conn, int $id): void
    {
        $stmt = $conn->prepare(self::query_by_id);
        $this->_destroyObjectCache($stmt, ShapelessConcreteStruct::class, ['id' => $id,]);
    }

    /**
     * @param PDO $conn
     * @param int $uid
     *
     * @throws PDOException
     * @throws ReflectionException
     */
    private function destroyQueryByUidCache(PDO $conn, int $uid): void
    {
        $stmt = $conn->prepare(self::query_by_uid);
        $this->_destroyObjectCache($stmt, ShapelessConcreteStruct::class, ['uid' => $uid]);
    }

    /**
     * @param PDO $conn
     * @param int $id
     * @param int $uid
     *
     * @throws PDOException
     * @throws ReflectionException
     */
    private function destroyQueryByIdAndUidCache(PDO $conn, int $id, int $uid): void
    {
        $stmt = $conn->prepare(self::query_by_id_and_uid);
        $this->_destroyObjectCache($stmt, ShapelessConcreteStruct::class, ['id' => $id, 'uid' => $uid]);
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
     * @param array<string, int|string|null> $data
     *
     * @return XliffConfigTemplateStruct|null
     * @throws Exception
     * @throws TypeError
     */
    private function hydrateTemplateStruct(array $data): ?XliffConfigTemplateStruct
    {
        if (
            !isset($data['id']) or
            !isset($data['uid']) or
            !isset($data['name']) or
            !isset($data['rules'])
        ) {
            return null;
        }

        $struct = new XliffConfigTemplateStruct();
        $struct->id = (int)$data['id'];
        $struct->uid = (int)$data['uid'];
        $struct->name = (string)$data['name'];

        $struct->created_at = isset($data['created_at']) ? (string)$data['created_at'] : null;
        $struct->modified_at = isset($data['modified_at']) ? (string)$data['modified_at'] : null;
        $struct->deleted_at = isset($data['deleted_at']) ? (string)$data['deleted_at'] : null;
        $struct->hydrateRulesFromJson((string)$data['rules']);

        return $struct;
    }

    /**
     * @param XliffConfigTemplateStruct $templateStruct
     *
     * @return XliffConfigTemplateStruct
     * @throws Exception
     * @throws TypeError
     */
    public function save(XliffConfigTemplateStruct $templateStruct): XliffConfigTemplateStruct
    {
        $sql = "INSERT INTO " . self::TABLE .
            " ( `uid`, `name`, `rules`, `created_at`, `modified_at` ) " .
            " VALUES " .
            " ( :uid, :name, :rules, :now, :now ); ";

        $now = (new DateTime())->format('Y-m-d H:i:s');

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            "uid" => $templateStruct->uid,
            "name" => $templateStruct->name,
            "rules" => $templateStruct->rules,
            'now' => $now,
        ]);

        $templateStruct->id = (int)$conn->lastInsertId();
        $templateStruct->created_at = $now;
        $templateStruct->modified_at = $now;

        $this->destroyQueryByIdCache($conn, $templateStruct->id);
        $this->destroyQueryByIdAndUidCache($conn, $templateStruct->id, $templateStruct->uid);
        $this->destroyQueryByUidCache($conn, $templateStruct->uid);
        $this->destroyQueryPaginated($templateStruct->uid);

        return $templateStruct;
    }

    /**
     * @param XliffConfigTemplateStruct $templateStruct
     *
     * @return XliffConfigTemplateStruct
     * @throws Exception
     */
    public function update(XliffConfigTemplateStruct $templateStruct): XliffConfigTemplateStruct
    {
        $sql = "UPDATE " . self::TABLE . " SET 
            `name` = :name,
            `rules` = :rules,
            `modified_at` = :now 
         WHERE id = :id;";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            "id" => $templateStruct->id,
            "name" => $templateStruct->name,
            "rules" => $templateStruct->rules,
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
        ]);

        $this->destroyQueryByIdCache($conn, $templateStruct->id);
        $this->destroyQueryByIdAndUidCache($conn, $templateStruct->id, $templateStruct->uid);
        $this->destroyQueryByUidCache($conn, $templateStruct->uid);
        $this->destroyQueryPaginated($templateStruct->uid);

        return $templateStruct;
    }
}
