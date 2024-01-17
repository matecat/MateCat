<?php

namespace Projects;

use CatUtils;
use DataAccess_AbstractDao;
use Database;
use DateTime;
use PDO;

class ProjectTemplateDao extends DataAccess_AbstractDao
{
    const TABLE = 'project_templates';

    const query_by_id = "SELECT * FROM " . self::TABLE . " WHERE id = :id";
    const query_by_uid_name = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND name = :name";

    /**
     * @var null
     */
    private static $instance = null;

    /**
     * @return ProjectTemplateDao|null
     */
    private static function getInstance(){
        if(!isset(self::$instance)){
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param $json
     * @param null $uid
     * @return ProjectTemplateStruct
     * @throws \Swaggest\JsonSchema\InvalidValue
     * @throws \Exception
     */
    public static function createFromJSON($json, $uid)
    {
        self::validateJSON($json);

        $projectTemplateStruct = new ProjectTemplateStruct();
        $projectTemplateStruct->hydrateFromJSON($json);

        // check name
        $jsonDecoded = json_decode($json);
        $projectTemplateStruct = self::findUniqueName($projectTemplateStruct, $jsonDecoded->name, $uid);

        $projectTemplateStruct->uid = $uid;

        return self::save($projectTemplateStruct);
    }

    /**
     * @param ProjectTemplateStruct $projectTemplateStruct
     * @param $json
     * @param $uid
     * @return ProjectTemplateStruct
     * @throws \Swaggest\JsonSchema\InvalidValue
     * @throws \Exception
     */
    public static function editFromJSON(ProjectTemplateStruct $projectTemplateStruct, $json, $uid)
    {
        self::validateJSON($json);
        $id = $projectTemplateStruct->id;
        $projectTemplateStruct->hydrateFromJSON($json);
        $projectTemplateStruct->uid = $uid;
        $projectTemplateStruct->id = $id;

        $saved = self::getById($id);

        if($saved->name !== $projectTemplateStruct->name){
            $projectTemplateStruct = self::findUniqueName($projectTemplateStruct, $projectTemplateStruct->name, $uid);
        }

        return self::update($projectTemplateStruct);
    }

    /**
     * @param ProjectTemplateStruct $projectTemplateStruct
     * @param $name
     * @param $uid
     * @return ProjectTemplateStruct
     */
    private static function findUniqueName(ProjectTemplateStruct $projectTemplateStruct, $name, $uid)
    {
        $check = ProjectTemplateDao::getByUidAndName($uid, $name, 0);

        if($check === null){
            return $projectTemplateStruct;
        }

        $newName = CatUtils::getUniqueName($projectTemplateStruct->name);
        $projectTemplateStruct->name = CatUtils::getUniqueName($projectTemplateStruct->name);

        return self::findUniqueName($projectTemplateStruct, $newName, $uid);
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
        $jsonSchema = file_get_contents( __DIR__ . '/../../../inc/validation/schema/project_template.json' );
        $validator = new \Validator\JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        if(!$validator->isValid()){
            throw $validator->getErrors()[0]->error;
        }
    }

    /**
     * @param $uid
     * @param int $current
     * @param int $pagination
     * @return array
     */
    public static function getAllPaginated($uid, $current = 1, $pagination = 20)
    {
        $conn = \Database::obtain()->getConnection();

        $stmt = $conn->prepare( "SELECT count(id) as count FROM ".self::TABLE." WHERE uid = :uid");
        $stmt->execute([
            'uid' => $uid
        ]);

        $count = $stmt->fetch(\PDO::FETCH_ASSOC);
        $pages = ceil($count['count'] / $pagination);
        $prev = ($current !== 1) ? "/api/v3/project-template?page=".($current-1) : null;
        $next = ($current < $pages) ? "/api/v3/project-template?page=".($current+1) : null;
        $offset = ($current - 1) * $pagination;

        $models = [];

        $stmt = $conn->prepare( "SELECT id FROM ".self::TABLE." WHERE uid = :uid LIMIT $pagination OFFSET $offset ");
        $stmt->execute([
            'uid' => $uid
        ]);

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $model){
            $models[] = self::getById($model['id']);
        }

        return [
            'current_page' => $current,
            'per_page' => $pagination,
            'last_page' => $pages,
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
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ProjectTemplateStruct(), [
            'id' => $id,
        ] );

        return @$result[0];
    }

    /**
     * @param $uid
     * @param $name
     * @param int $ttl
     * @return ProjectTemplateStruct
     */
    public static function getByUidAndName( $uid, $name, $ttl = 60 )
    {
        $stmt = self::getInstance()->_getStatementForCache(self::query_by_uid_name);
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ProjectTemplateStruct(), [
            'uid' => $uid,
            'name' => $name,
        ] );

        return @$result[0];
    }

    /**
     * @param ProjectTemplateStruct $projectTemplateStruct
     * @return ProjectTemplateStruct
     * @throws \Exception
     */
    public static function save(ProjectTemplateStruct $projectTemplateStruct )
    {
        $sql = "INSERT INTO " . self::TABLE .
            " ( `name`, `is_default`, `uid`, `id_team`, `speech2text`, `lexica`, `tag_projection`, `cross_language_matches`, `segmentation_rule`, `tm`, `mt`, `payable_rate_template_id`,`qa_model_template_id`, `created_at`, `modified_at` ) " .
            " VALUES " .
            " ( :name, :is_default, :uid, :id_team, :speech2text, :lexica, :tag_projection, :cross_language_matches, :segmentation_rule, :tm, :mt, :payable_rate_template_id, :qa_model_template_id, :now, :now ); ";

        $now = (new DateTime())->format('Y-m-d H:i:s');

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
            "name" => $projectTemplateStruct->name,
            "is_default" => $projectTemplateStruct->is_default,
            "uid" => $projectTemplateStruct->uid,
            "id_team" => $projectTemplateStruct->id_team,
            "speech2text" => $projectTemplateStruct->speech2text,
            "lexica" => $projectTemplateStruct->lexica,
            "tag_projection" => $projectTemplateStruct->tag_projection,
            "cross_language_matches" => $projectTemplateStruct->cross_language_matches,
            "segmentation_rule" => $projectTemplateStruct->segmentation_rule,
            "mt" => $projectTemplateStruct->mtToJson(),
            "tm" => $projectTemplateStruct->tmToJson(),
            "payable_rate_template_id" => $projectTemplateStruct->payable_rate_template_id,
            "qa_model_template_id" => $projectTemplateStruct->qa_model_template_id,
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
        ] );

        $projectTemplateStruct->id = $conn->lastInsertId();
        $projectTemplateStruct->created_at = $now;
        $projectTemplateStruct->modified_at = $now;

        return $projectTemplateStruct;
    }

    /**
     * @param ProjectTemplateStruct $projectTemplateStruct
     * @return ProjectTemplateStruct
     * @throws \Exception
     */
    public static function update(ProjectTemplateStruct $projectTemplateStruct )
    {
        $sql = "UPDATE " . self::TABLE . " SET 
            `name` = :name, 
            `is_default` = :is_default, 
            `uid` = :uid, 
            `id_team` = :id_team, 
            `speech2text` = :speech2text,
             `lexica` = :lexica, 
             `tag_projection` = :tag_projection, 
             `cross_language_matches` = :cross_language_matches, 
             `segmentation_rule` = :segmentation_rule, 
             `tm` = :tm, 
             `mt` = :mt, 
             `payable_rate_template_id` = :payable_rate_template_id, 
             `qa_model_template_id` = :qa_model_template_id, 
             `modified_at` = :now 
             WHERE id = :id
         ;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
            "id" => $projectTemplateStruct->id,
            "name" => $projectTemplateStruct->name,
            "is_default" => $projectTemplateStruct->is_default,
            "uid" => $projectTemplateStruct->uid,
            "id_team" => $projectTemplateStruct->id_team,
            "speech2text" => $projectTemplateStruct->speech2text,
            "lexica" => $projectTemplateStruct->lexica,
            "tag_projection" => $projectTemplateStruct->tag_projection,
            "cross_language_matches" => $projectTemplateStruct->cross_language_matches,
            "segmentation_rule" => $projectTemplateStruct->segmentation_rule,
            "mt" => $projectTemplateStruct->mtToJson(),
            "tm" => $projectTemplateStruct->tmToJson(),
            "payable_rate_template_id" => $projectTemplateStruct->payable_rate_template_id,
            "qa_model_template_id" => $projectTemplateStruct->qa_model_template_id,
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
        ] );

        self::destroyQueryByIdCache($conn, $projectTemplateStruct->id);
        self::destroyQueryByUidAndNameCache($conn, $projectTemplateStruct->uid, $projectTemplateStruct->name);

        return $projectTemplateStruct;
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
     * @param string $uid
     * @param string $name
     */
    private static function destroyQueryByUidAndNameCache(PDO $conn, $uid, $name)
    {
        $stmt = $conn->prepare( self::query_by_uid_name );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'uid' => $uid, 'name' => $name,  ] );
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