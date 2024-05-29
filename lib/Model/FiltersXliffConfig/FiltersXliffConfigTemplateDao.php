<?php

namespace FiltersXliffConfig;

use CatUtils;
use DataAccess\ShapelessConcreteStruct;
use DataAccess_AbstractDao;
use Database;
use DateTime;
use Exception;
use FiltersXliffConfig\Filters\DTO\Json;
use FiltersXliffConfig\Filters\FiltersConfigModel;
use FiltersXliffConfig\Xliff\XliffConfigModel;
use PDO;
use Projects\ProjectTemplateStruct;

class FiltersXliffConfigTemplateDao extends DataAccess_AbstractDao
{
    const TABLE = 'filters_xliff_config_templates';

    const query_by_id = "SELECT * FROM " . self::TABLE . " WHERE id = :id";
    const query_by_uid = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid";
    const query_by_uid_name = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND name = :name";

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
        $default->name = "default";

        $default->setFilters(new FiltersConfigModel());
        $default->setXliff(new XliffConfigModel());

        $default->created_at = date("Y-m-d H:i:s");
        $default->modified_at = date("Y-m-d H:i:s");

        return $default;
    }

    /**
     * @param $json
     * @param $uid
     * @return FiltersXliffConfigTemplateStruct
     * @throws Exception
     */
    public static function createFromJSON($json, $uid)
    {
        self::validateJSON($json);

        $templateStruct = new FiltersXliffConfigTemplateStruct();
        $templateStruct->hydrateFromJSON($json);

        // check name
        $templateStruct = self::findUniqueName($templateStruct, $uid);
        $templateStruct->uid = $uid;

        return self::save($templateStruct);
    }

    /**
     * @param FiltersXliffConfigTemplateStruct $templateStruct
     * @param $json
     * @param $uid
     * @return FiltersXliffConfigTemplateStruct
     * @throws Exception
     */
    public static function editFromJSON(FiltersXliffConfigTemplateStruct $templateStruct, $json, $uid)
    {
        self::validateJSON($json);
        $templateStruct->hydrateFromJSON($json);
        $templateStruct->uid = $uid;

        $saved = self::getById($templateStruct->id);

        if($saved->name !== $templateStruct->name){
            $templateStruct = self::findUniqueName($templateStruct, $uid);
        }

        return self::update($templateStruct);
    }

    /**
     * @param $json
     * @throws \Swaggest\JsonSchema\InvalidValue
     * @throws \Exception
     */
    private static function validateJSON($json)
    {
        $validatorObject = new \Validator\JSONValidatorObject();
        $validatorObject->json = $json;
        $jsonSchema = file_get_contents( __DIR__ . '/../../../inc/validation/schema/filters_xliff_config_template.json' );
        $validator = new \Validator\JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        if(!$validator->isValid()){
            throw $validator->getErrors()[0]->error;
        }
    }

    /**
     * @param FiltersXliffConfigTemplateStruct $projectTemplateStruct
     * @param $name
     * @param $uid
     * @return FiltersXliffConfigTemplateStruct
     */
    private static function findUniqueName(FiltersXliffConfigTemplateStruct $projectTemplateStruct, $uid)
    {
        $check = FiltersXliffConfigTemplateDao::getByUidAndName($uid, $projectTemplateStruct->name, 0);

        if($check === null){
            return $projectTemplateStruct;
        }

        $projectTemplateStruct->name = CatUtils::getUniqueName($projectTemplateStruct->name);

        return self::findUniqueName($projectTemplateStruct, $uid);
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
     * @throws Exception
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
     * @param $uid
     * @param $name
     * @param int $ttl
     * @return FiltersXliffConfigTemplateStruct|null
     */
    public static function getByUidAndName( $uid, $name, $ttl = 60 )
    {
        $stmt = self::getInstance()->_getStatementForCache(self::query_by_uid_name);
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ProjectTemplateStruct(), [
            'uid' => $uid,
            'name' => $name,
        ] );

        if(empty($result)){
            return null;
        }

        return self::hydrateTemplateStruct((array)$result[0]);
    }

    /**
     * @param $id
     * @param $uid
     * @return int
     * @throws Exception
     */
    public static function remove( $id, $uid )
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "UPDATE ".self::TABLE." SET `deleted_at` = :now WHERE id = :id " );
        $stmt->execute( [
            'id' => $id,
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
        ] );

        self::destroyQueryByIdCache($conn, $id);
        self::destroyQueryByUidCache($conn, $uid);

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
     * @param PDO $conn
     * @param $uid
     */
    private static function destroyQueryByUidCache(PDO $conn, $uid)
    {
        $stmt = $conn->prepare( self::query_by_uid );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'uid' => $uid  ] );
    }

    /**
     * @param PDO $conn
     * @param string $uid
     * @param string $name
     */
    private static function destroyQueryByUidAndNameCache(PDO $conn, $uid, $name)
    {
        $stmt = $conn->prepare( self::query_by_uid_name );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'uid' => $uid, 'name' => $name,  ] );
    }

    /**
     * @param $data
     * @return FiltersXliffConfigTemplateStruct|null
     * @throws Exception
     */
    private static function hydrateTemplateStruct($data)
    {
        if(
            !isset($data['id']) and
            !isset($data['uid']) and
            !isset($data['name']) and
            !isset($data['created_at']) and
            !isset($data['deleted_at']) and
            !isset($data['modified_at']) and
            !isset($data['xliff']) and
            !isset($data['filters'])
        ){
            return null;
        }

        $struct = new FiltersXliffConfigTemplateStruct();

        return $struct->hydrateFromJSON(json_encode($data));
    }

    /**
     * @param FiltersXliffConfigTemplateStruct $templateStruct
     * @return FiltersXliffConfigTemplateStruct
     * @throws Exception
     */
    public static function save(FiltersXliffConfigTemplateStruct $templateStruct )
    {
        $sql = "INSERT INTO " . self::TABLE .
            " ( `uid`, `name`, `filters`, `xliff`, `created_at`, `modified_at` ) " .
            " VALUES " .
            " ( :uid, :name, :filters, :xliff, :now, :now ); ";

        $now = (new DateTime())->format('Y-m-d H:i:s');

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
            "uid" => $templateStruct->uid,
            "name" => $templateStruct->name,
            "filters" => $templateStruct->getFilters()->serialize(),
            "xliff" => $templateStruct->getXliff()->serialize(),
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
        ] );

        $templateStruct->id = $conn->lastInsertId();
        $templateStruct->created_at = $now;
        $templateStruct->modified_at = $now;

        return $templateStruct;
    }

    /**
     * @param FiltersXliffConfigTemplateStruct $templateStruct
     * @return FiltersXliffConfigTemplateStruct
     * @throws Exception
     */
    public static function update(FiltersXliffConfigTemplateStruct $templateStruct)
    {
        $sql = "UPDATE " . self::TABLE . " SET 
            `uid` = :uid, 
            `name` = :name,
            `filters` = :filters, 
            `xliff` = :xliff,
            `modified_at` = :now 
         WHERE id = :id;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
            "id" => $templateStruct->id,
            "uid" => $templateStruct->uid,
            "name" => $templateStruct->name,
            "filters" => serialize($templateStruct->getFilters()),
            "xliff" => serialize($templateStruct->getXliff()),
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
        ] );

        self::destroyQueryByIdCache($conn, $templateStruct->id);
        self::destroyQueryByUidCache($conn, $templateStruct->uid);
        self::destroyQueryByUidAndNameCache($conn, $templateStruct->uid, $templateStruct->name);

        return $templateStruct;
    }
}