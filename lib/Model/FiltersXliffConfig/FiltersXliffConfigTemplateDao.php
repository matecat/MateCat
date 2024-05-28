<?php

namespace FiltersXliffConfig;

use DataAccess\ShapelessConcreteStruct;
use DataAccess_AbstractDao;
use Database;
use Exception;
use FiltersXliffConfig\Filters\DTO\Json;
use FiltersXliffConfig\Filters\FiltersConfigModel;
use FiltersXliffConfig\Xliff\XliffConfigModel;
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

        $default->setFilters(new FiltersConfigModel());
        $default->setXliff(new XliffConfigModel());

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
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute([
            'uid' => $uid
        ]);

        foreach ($stmt->fetchAll() as $item){
            $model = self::hydrateTemplateStruct($item);

            if($model !== null){
                $models[] = $model;
            }
        }

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
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
            'id' => $id,
        ] );

        if(empty($result)){
            return null;
        }

        return self::hydrateTemplateStruct((array)$result[0]);
    }

    /**
     * @param $uid
     * @param int $ttl
     * @return FiltersXliffConfigTemplateStruct|null
     */
    public static function getByUid($uid, $ttl = 60)
    {
        $stmt = self::getInstance()->_getStatementForCache(self::query_by_uid);
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
            'uid' => $uid,
        ] );

        if(empty($result)){
            return null;
        }

        return self::hydrateTemplateStruct((array)$result[0]);
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

    /**
     * @param $data
     * @return FiltersXliffConfigTemplateStruct
     */
    private static function hydrateTemplateStruct($data)
    {
        if(
            !isset($data['id']) and
            !isset($data['uid']) and
            !isset($data['created_at']) and
            !isset($data['deleted_at']) and
            !isset($data['modified_at']) and
            !isset($data['xliff']) and
            !isset($data['filters'])
        ){
            return null;
        }

        $struct = new FiltersXliffConfigTemplateStruct();
        $struct->id = $data['id'];
        $struct->uid = $data['uid'];
        $struct->created_at = $data['created_at'];
        $struct->deleted_at = $data['deleted_at'];
        $struct->modified_at = $data['modified_at'];

        $xliff = json_decode($data['xliff']);
        $filters = json_decode($data['filters']);

        $filtersConfig = new FiltersConfigModel();
        $xliffConfig = new XliffConfigModel();

        // xliff
        if(!empty($xliff)){}

        // filters
        if(!empty($filters)){

            if(isset($filters->json)){
                $jsonDto = new Json();

                if($filters->json->extract_arrays){
                    $jsonDto->setExtractArrays($filters->json->extract_arrays);
                }

                if(isset($filters->json->escape_forward_slashes)){
                    $jsonDto->setEscapeForwardSlashes($filters->json->escape_forward_slashes);
                }

                if(isset($filters->json->translate_keys)){
                    $jsonDto->setTranslateKeys($filters->json->translate_keys);
                }

                if(isset($filters->json->do_not_translate_keys)){
                    $jsonDto->setDoNotTranslateKeys($filters->json->do_not_translate_keys);
                }

                if(isset($filters->json->context_keys)){
                    $jsonDto->setContextKeys($filters->json->context_keys);
                }

                $filtersConfig->setJson($jsonDto);
            }

            $struct->setFilters($filtersConfig);
            $struct->setXliff($xliffConfig);
        }

        return $struct;
    }
}