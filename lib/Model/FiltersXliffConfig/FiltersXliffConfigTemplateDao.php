<?php

namespace Projects;

use CatUtils;
use DataAccess_AbstractDao;
use Database;
use DateTime;
use Engine;
use EnginesModel_EngineDAO;
use EnginesModel_EngineStruct;
use Exception;
use FiltersXliffConfig\Filters\FiltersConfigModel;
use FiltersXliffConfig\FiltersXliffConfigTemplateStruct;
use FiltersXliffConfig\Xliff\XliffConfigModel;
use PayableRates\CustomPayableRateDao;
use PDO;
use QAModelTemplate\QAModelTemplateDao;
use Teams\TeamDao;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyStruct;

class FiltersXliffConfigTemplateDao extends DataAccess_AbstractDao
{
    const TABLE = 'filters_xliff_config_templates';

    const query_by_id = "SELECT * FROM " . self::TABLE . " WHERE id = :id";
    const query_by_uid = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid";

    /**
     * @param $uid
     * @return FiltersXliffConfigTemplateStruct
     */
    public static function getDefaultTemplate($uid)
    {
        $xliff = new XliffConfigModel([], []);
        $filters = new FiltersConfigModel();

        $default = new FiltersXliffConfigTemplateStruct($xliff, $filters);
        $default->id = 0;
        $default->uid = $uid;

        $default->created_at = date("Y-m-d H:i:s");
        $default->modified_at = date("Y-m-d H:i:s");

        return $default;
    }

    /**
     * @param $uid
     * @param int $current
     * @param int $pagination
     * @return array
     * @throws Exception
     */
    public static function getAllPaginated($uid, $current = 1, $pagination = 20)
    {
        $conn = \Database::obtain()->getConnection();

        $stmt = $conn->prepare( "SELECT count(id) as count FROM ".self::TABLE." WHERE uid = :uid");
        $stmt->execute([
            'uid' => $uid
        ]);

        $count = $stmt->fetch(\PDO::FETCH_ASSOC);
        $count = $count['count'];
        $count = $count + 1;
        $pages = ceil($count / $pagination);
        $prev = ($current !== 1) ? "/api/v3/filters-xliff-config-template?page=".($current-1) : null;
        $next = ($current < $pages) ? "/api/v3/filters-xliff-config-template?page=".($current+1) : null;
        $offset = ($current - 1) * $pagination;

        $models = [];
        $models[] = self::getDefaultTemplate($uid);

        $stmt = $conn->prepare( "SELECT * FROM ".self::TABLE." WHERE uid = :uid ORDER BY id ASC LIMIT $pagination OFFSET $offset ");
        $stmt->setFetchMode(PDO::FETCH_CLASS, FiltersXliffConfigTemplateStruct::class);
        $stmt->execute([
            'uid' => $uid
        ]);

        $models = array_merge($models, $stmt->fetchAll());

        return [
            'current_page' => $current,
            'per_page' => $pagination,
            'last_page' => $pages,
            'total_count' => (int)$count,
            'prev' => $prev,
            'next' => $next,
            'items' => $models,
        ];
    }

    /**
     * @param $id
     * @param int $ttl
     * @return ProjectTemplateStruct|null
     */
    public static function getById($id, $ttl = 60)
    {
        $stmt = self::getInstance()->_getStatementForCache(self::query_by_id);
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, FiltersXliffConfigTemplateStruct::class, [
            'id' => $id,
        ] );

        return @$result[0];
    }
}