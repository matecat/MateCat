<?php

namespace Projects;

use DataAccess_AbstractDao;
use Database;
use Exception;
use FiltersXliffConfig\FiltersXliffConfigTemplateStruct;
use PDO;

class FiltersXliffConfigTemplateDao extends DataAccess_AbstractDao
{
    const TABLE = 'filters_xliff_config_templates';

    const query_by_id = "SELECT * FROM " . self::TABLE . " WHERE id = :id";
    const query_by_uid = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid";

    /**
     * @var null
     */
    private static $instance = null;

    /**
     * @return FiltersXliffConfigTemplateDao|null
     */
    private static function getInstance(){
        if(!isset(self::$instance)){
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param $uid
     * @return FiltersXliffConfigTemplateStruct
     */
    public static function getDefaultTemplate($uid)
    {
        $default = new FiltersXliffConfigTemplateStruct();
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
     * @return FiltersXliffConfigTemplateStruct|null
     */
    public static function getById($id, $ttl = 60)
    {
        $stmt = self::getInstance()->_getStatementForCache(self::query_by_id);
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new FiltersXliffConfigTemplateStruct(), [
            'id' => $id,
        ] );

        return @$result[0];
    }

    /**
     * @param $id
     * @param int $ttl
     * @return FiltersXliffConfigTemplateStruct|null
     */
    public static function getByUid($id, $ttl = 60)
    {
        $stmt = self::getInstance()->_getStatementForCache(self::query_by_uid);
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new FiltersXliffConfigTemplateStruct(), [
            'id' => $id,
        ] );

        return @$result[0];
    }

    /**
     * @param $id
     * @return int
     */
    public static function remove( $id )
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "DELETE FROM ".self::TABLE." WHERE id = :id " );
        $stmt->execute( [ 'id' => $id ] );

        self::destroyQueryByIdCache($conn, $id);

        return $stmt->rowCount();
    }

    /**
     * @param PDO $conn
     * @param string $id
     */
    private static function destroyQueryByIdCache(PDO $conn, $id)
    {
        $stmt = $conn->prepare( self::query_by_id );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'id' => $id, ] );
    }
}