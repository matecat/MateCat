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
use PayableRates\CustomPayableRateDao;
use PDO;
use QAModelTemplate\QAModelTemplateDao;
use Teams\TeamDao;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyStruct;

class ProjectTemplateDao extends DataAccess_AbstractDao
{
    const TABLE = 'project_templates';

    const query_by_id = "SELECT * FROM " . self::TABLE . " WHERE id = :id";
    const query_default = "SELECT * FROM " . self::TABLE . " WHERE is_default = :is_default AND uid = :uid";
    const query_by_uid_name = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND name = :name";

    /**
     * @param $uid
     * @return ProjectTemplateStruct
     * @throws Exception
     */
    public static function getDefaultTemplate($uid)
    {
        $defaultProject = self::getTheDefaultProject($uid);
        $team = (new TeamDao())->getPersonalByUid($uid);

        $default = new ProjectTemplateStruct();
        $default->id = 0;
        $default->name = "Standard";
        $default->speech2text = false;
        $default->is_default = empty($defaultProject);
        $default->id_team = $team->id;
        $default->lexica = true;
        $default->tag_projection = true;
        $default->uid = $uid;
        $default->pretranslate_100 = false;
        $default->pretranslate_101 = true;
        $default->get_public_matches = true;
        $default->payable_rate_template_id = 0;
        $default->qa_model_template_id = 0;
        $default->filters_xliff_config_template_id = 0;
        $default->segmentation_rule = [
            "name" => "General",
            "id" => "standard"
        ];

        // MT
        $default->mt = self::getUserDefaultMt();

        $default->tm = [];
        $default->created_at = date("Y-m-d H:i:s");
        $default->modified_at = date("Y-m-d H:i:s");

        return $default;
    }

    /**
     * @return array
     */
    private static function getUserDefaultMt()
    {
        return [
            'id' => 1,
            'extra' => new \stdClass()
        ];
    }

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

        self::checkValues($projectTemplateStruct);

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
        $projectTemplateStruct->modified_at = (new DateTime())->format('Y-m-d H:i:s');

        $saved = self::getById($id);

        if($saved->name !== $projectTemplateStruct->name){
            $projectTemplateStruct = self::findUniqueName($projectTemplateStruct, $projectTemplateStruct->name, $uid);
        }

        self::checkValues($projectTemplateStruct);

        return self::update($projectTemplateStruct);
    }

    /**
     * Check if the template values are valid.
     *
     * The checks includes:
     *
     * - id_team
     * - qa_model_template_id
     * - payable_rate_template_id
     * - mt
     * - tm
     *
     * @param ProjectTemplateStruct $projectTemplateStruct
     * @throws Exception
     */
    private static function checkValues(ProjectTemplateStruct $projectTemplateStruct)
    {
        // check id_team
        $teamDao = new TeamDao();
        $personalTeam =  $teamDao->getPersonalByUid($projectTemplateStruct->uid);

        if($personalTeam === null){
            $team = $teamDao->findById($projectTemplateStruct->id_team);

            if($team === null){
                throw new Exception("User group not found.");
            }

            if(!$team->hasUser($projectTemplateStruct->uid)){
                throw new Exception("This user does not belong to this group.");
            }
        }


        // check qa_id
        if($projectTemplateStruct->qa_model_template_id > 0){
            $qaModel = QAModelTemplateDao::get([
                'id' => $projectTemplateStruct->qa_model_template_id
            ]);

            if(empty($qaModel)){
                throw new Exception("Not existing QA template.");
            }

            if($qaModel->uid !== $projectTemplateStruct->uid){
                throw new Exception("QA model doesn't belong to the user.");
            }
        }

        // check pr_id
        if($projectTemplateStruct->payable_rate_template_id > 0){
            $payableRateModel = CustomPayableRateDao::getById($projectTemplateStruct->payable_rate_template_id);

            if(empty($payableRateModel)){
                throw new Exception("Not existing payable rate template.");
            }

            if($payableRateModel->uid !== $projectTemplateStruct->uid){
                throw new Exception("Billing model doesn't belong to the user.");
            }
        }

        // check mt
        if($projectTemplateStruct->mt !== null){
            $mt = $projectTemplateStruct->getMt();

            if(isset($mt->id)){
                $engine = Engine::getInstance($mt->id);

                if(empty($engine)){
                    throw new Exception("Not existing engine.");
                }

                $engineRecord = $engine->getEngineRecord();

                if($engineRecord->id > 1 and $engineRecord->uid !== $projectTemplateStruct->uid){
                    throw new Exception("Engine doesn't belong to the user.");
                }
            }


        }

        // check tm
        if($projectTemplateStruct->tm !== null){
            $tmKeys = $projectTemplateStruct->getTm();
            $mkDao = new TmKeyManagement_MemoryKeyDao();

            foreach ($tmKeys as $tmKey){
                $keyRing = $mkDao->read(
                    ( new TmKeyManagement_MemoryKeyStruct( [
                        'uid'    => $projectTemplateStruct->uid,
                        'tm_key' => new TmKeyManagement_TmKeyStruct( $tmKey->key )
                    ] )
                    )
                );

                if ( empty($keyRing) ) {
                    throw new Exception("TM key doesn't belong to the user.");
                }
            }
        }
    }

    /**
     * @param ProjectTemplateStruct $projectTemplateStruct
     * @param $name
     * @param $uid
     * @return ProjectTemplateStruct
     */
    private static function findUniqueName(ProjectTemplateStruct $projectTemplateStruct, $name, $uid)
    {
        $check = ProjectTemplateDao::getByUidAndName($uid, $name, 0); // potrebbe essere più veloce fare una query con LIKE name%

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
        $prev = ($current !== 1) ? "/api/v3/project-template?page=".($current-1) : null;
        $next = ($current < $pages) ? "/api/v3/project-template?page=".($current+1) : null;
        $offset = ($current - 1) * $pagination;

        $models = [];
        $models[] = self::getDefaultTemplate($uid);

        $stmt = $conn->prepare( "SELECT * FROM ".self::TABLE." WHERE uid = :uid ORDER BY id ASC LIMIT $pagination OFFSET $offset ");
        $stmt->setFetchMode(PDO::FETCH_CLASS, ProjectTemplateStruct::class);
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
     * @param $uid
     * @param int $ttl
     * @return \DataAccess_IDaoStruct
     */
    public static function getTheDefaultProject($uid, $ttl = 0)
    {
        $stmt = self::getInstance()->_getStatementForCache(self::query_default);
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ProjectTemplateStruct(), [
            'uid' => $uid,
            'is_default' => 1,
        ] );

        return @$result[0];
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
            " ( `name`, `is_default`, `uid`, `id_team`, `speech2text`, `lexica`, `tag_projection`, `cross_language_matches`, `segmentation_rule`, `tm`, `mt`, `payable_rate_template_id`,`qa_model_template_id`, `filters_xliff_config_template_id`, `pretranslate_100`, `pretranslate_101`, `get_public_matches`, `created_at`, `modified_at` ) " .
            " VALUES " .
            " ( :name, :is_default, :uid, :id_team, :speech2text, :lexica, :tag_projection, :cross_language_matches, :segmentation_rule, :tm, :mt, :payable_rate_template_id, :qa_model_template_id, :filters_xliff_config_template_id, :pretranslate_100, :pretranslate_101, :get_public_matches, :now, :now ); ";

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
            "cross_language_matches" => $projectTemplateStruct->crossLanguageMatchesToJson(),
            "segmentation_rule" => $projectTemplateStruct->segmentationRuleToJson(),
            "mt" => $projectTemplateStruct->mtToJson(),
            "tm" => $projectTemplateStruct->tmToJson(),
            "pretranslate_100" => $projectTemplateStruct->pretranslate_100,
            "pretranslate_101" => $projectTemplateStruct->pretranslate_101,
            "get_public_matches" => $projectTemplateStruct->get_public_matches,
            "payable_rate_template_id" => $projectTemplateStruct->payable_rate_template_id,
            "qa_model_template_id" => $projectTemplateStruct->qa_model_template_id,
            "filters_xliff_config_template_id" => $projectTemplateStruct->filters_xliff_config_template_id,
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
        ] );

        $projectTemplateStruct->id = $conn->lastInsertId();
        $projectTemplateStruct->created_at = $now;
        $projectTemplateStruct->modified_at = $now;

        if($projectTemplateStruct->is_default === true){
            self::markAsNotDefault($projectTemplateStruct->uid, $projectTemplateStruct->id);
        }

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
            `pretranslate_100` = :pretranslate_100,
            `pretranslate_101` = :pretranslate_101,
            `get_public_matches` = :get_public_matches,
            `payable_rate_template_id` = :payable_rate_template_id, 
            `qa_model_template_id` = :qa_model_template_id, 
            `filters_xliff_config_template_id` = :filters_xliff_config_template_id, 
            `modified_at` = :now 
         WHERE id = :id;";

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
            "cross_language_matches" => $projectTemplateStruct->crossLanguageMatchesToJson(),
            "segmentation_rule" => $projectTemplateStruct->segmentationRuleToJson(),
            "mt" => $projectTemplateStruct->mtToJson(),
            "tm" => $projectTemplateStruct->tmToJson(),
            "pretranslate_100" => $projectTemplateStruct->pretranslate_100,
            "pretranslate_101" => $projectTemplateStruct->pretranslate_101,
            "get_public_matches" => $projectTemplateStruct->get_public_matches,
            "payable_rate_template_id" => $projectTemplateStruct->payable_rate_template_id,
            "qa_model_template_id" => $projectTemplateStruct->qa_model_template_id,
            "filters_xliff_config_template_id" => $projectTemplateStruct->filters_xliff_config_template_id,
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
        ] );

        self::destroyQueryByIdCache($conn, $projectTemplateStruct->id);
        self::destroyQueryByUidAndNameCache($conn, $projectTemplateStruct->uid, $projectTemplateStruct->name);

        if($projectTemplateStruct->is_default === true){
            self::markAsNotDefault($projectTemplateStruct->uid, $projectTemplateStruct->id);
        }

        return $projectTemplateStruct;
    }

    /**
     * @param $uid
     * @param $excludeId
     */
    public static function markAsNotDefault($uid, $excludeId)
    {
        $sql = "UPDATE " . self::TABLE . " SET 
            `is_default` = :is_default
             WHERE uid= :uid 
             AND id != :id
         ;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
            "uid" => $uid,
            "id" => $excludeId,
            "is_default" => false,
        ] );

        // destroy cache
        $stmt = $conn->prepare( "SELECT id FROM ".self::TABLE." WHERE uid = :uid ");
        $stmt->execute([
            'uid' => $uid
        ]);

        foreach ($stmt->fetchAll() as $project){
            self::destroyQueryByIdCache($conn, $project['id']);
        }
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