<?php

namespace Model\Projects;

use DateTime;
use Exception;
use Matecat\Locales\Languages;
use Matecat\SubFiltering\Enum\InjectableFiltersTags;
use Matecat\SubFiltering\HandlersSorter;
use Model\DataAccess\AbstractDao;
use Model\Filters\FiltersConfigTemplateDao;
use Model\LQA\QAModelTemplate\QAModelTemplateDao;
use Model\Pagination\Pager;
use Model\Pagination\PaginationParameters;
use Model\PayableRates\CustomPayableRateDao;
use Model\Teams\MembershipDao;
use Model\Teams\TeamDao;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use Model\Xliff\XliffConfigTemplateDao;
use PDO;
use PDOException;
use ReflectionException;
use stdClass;
use TypeError;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\Tools\Utils;

/**
 * @phpstan-import-type HydrationInput from ProjectTemplateStruct
 */
class ProjectTemplateDao extends AbstractDao
{
    const string TABLE = 'project_templates';

    const string query_by_id_and_uid = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND uid = :uid";
    const string query_default = "SELECT * FROM " . self::TABLE . " WHERE is_default = :is_default AND uid = :uid";
    const string query_paginated = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid ORDER BY id LIMIT %u OFFSET %u ";
    const string paginated_map_key = __CLASS__ . "::getAllPaginated";

    /**
     * @return array{id: int, extra: stdClass}
     */
    private function getUserDefaultMt(): array
    {
        return [
            'id' => 1,
            'extra' => new stdClass()
        ];
    }

    /**
     * @param int $uid
     *
     * @return ProjectTemplateStruct
     * @throws Exception
     * @throws TypeError
     */
    public function getDefaultTemplate(int $uid): ProjectTemplateStruct
    {
        $defaultProject = $this->getTheDefaultProject($uid);
        $team = (new TeamDao($this->database))->getPersonalByUid($uid);

        $default = new ProjectTemplateStruct();
        $default->id = 0;
        $default->name = "Matecat original settings";
        $default->is_default = empty($defaultProject);
        $default->id_team = (int)$team->id;
        $default->uid = $uid;
        $default->pretranslate_100 = false;
        $default->pretranslate_101 = true;
        $default->tm_prioritization = false;
        $default->dialect_strict = false;
        $default->get_public_matches = true;
        $default->character_counter_count_tags = false;
        $default->character_counter_mode = "google_ads";
        $default->public_tm_penalty = 0;
        $default->payable_rate_template_id = 0;
        $default->qa_model_template_id = 0;
        $default->xliff_config_template_id = 0;
        $default->filters_template_id = 0;
        $default->mt_quality_value_in_editor = 85;
        $default->subject = "general";
        $default->source_language = "en-US";
        $default->target_language = serialize(["fr-FR"]);
        $default->segmentation_rule = json_encode([
            "name" => "General",
            "id" => "standard"
        ]) ?: null;

        $default->mandatory_issues = json_encode(["r1", "r2"]) ?: null;

        // MT
        $default->mt = json_encode($this->getUserDefaultMt()) ?: null;

        $default->tm = json_encode([]) ?: null;
        $default->created_at = date("Y-m-d H:i:s");
        $default->modified_at = date("Y-m-d H:i:s");
        $default->subfiltering_handlers = json_encode(
            InjectableFiltersTags::tagNamesForArrayClasses(
                array_keys(HandlersSorter::getDefaultInjectedHandlers())
            )
        ) ?: null;
        $default->icu_enabled = true;

        return $default;
    }

    /**
     * @phpstan-param HydrationInput $decodedObject
     * @param UserStruct $user
     *
     * @return ProjectTemplateStruct
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function createFromJSON(object $decodedObject, UserStruct $user): ProjectTemplateStruct
    {
        $uid = $user->uid ?? throw new Exception("UserStruct::uid must not be null when creating a project template");

        $projectTemplateStruct = new ProjectTemplateStruct();
        $projectTemplateStruct->hydrateFromJSON($decodedObject, $uid);

        $this->checkValues($projectTemplateStruct, $user);

        return $this->save($projectTemplateStruct);
    }

    /**
     * @param ProjectTemplateStruct $projectTemplateStruct
     * @phpstan-param HydrationInput $json
     * @param int $id
     * @param UserStruct $user
     *
     * @return ProjectTemplateStruct
     * @throws Exception
     * @throws TypeError
     */
    public function editFromJSON(ProjectTemplateStruct $projectTemplateStruct, object $json, int $id, UserStruct $user): ProjectTemplateStruct
    {
        $uid = $user->uid ?? throw new Exception("UserStruct::uid must not be null when editing a project template");

        $projectTemplateStruct->hydrateFromJSON($json, $uid, $id);

        $this->checkValues($projectTemplateStruct, $user);

        return $this->update($projectTemplateStruct);
    }

    /**
     * Check if the template values are valid.
     *
     * The check includes:
     *
     * - id_team
     * - qa_model_template_id
     * - payable_rate_template_id
     * - mt
     * - tm
     *
     * @param ProjectTemplateStruct $projectTemplateStruct
     * @param UserStruct $user
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    private function checkValues(ProjectTemplateStruct $projectTemplateStruct, UserStruct $user): void
    {
        // check id_team
        $team = (new MembershipDao($this->database))->setCacheTTL(60 * 5)->findTeamByIdAndUser(
            $projectTemplateStruct->id_team,
            $user
        );

        if (empty($team)) {
            throw new Exception("This user does not belong to this group.", 403);
        }

        // source_language
        if ($projectTemplateStruct->source_language !== null) {
            $languages = Languages::getInstance();
            $language = Utils::trimAndLowerCase($projectTemplateStruct->source_language);

            try {
                $languages->validateLanguage($language);
            } catch (Exception $e) {
                throw new $e($e->getMessage(), 403);
            }
        }

        // target_language
        if ($projectTemplateStruct->target_language !== null) {
            $targetLanguages = unserialize($projectTemplateStruct->target_language);

            if (!is_array($targetLanguages)) {
                throw new Exception("target language is not an array", 403);
            }

            $languages = Languages::getInstance();

            try {
                $languages->validateLanguageList($targetLanguages);
            } catch (Exception $e) {
                throw new $e($e->getMessage(), 403);
            }
        }

        // check xliff_config_template_id
        if ($projectTemplateStruct->xliff_config_template_id > 0) {
            $xliffConfigModel = (new XliffConfigTemplateDao($this->database))->getByIdAndUser($projectTemplateStruct->xliff_config_template_id, $projectTemplateStruct->uid);

            if (empty($xliffConfigModel)) {
                throw new Exception("Not existing Xliff template.", 404);
            }
        }

        // check filters_template_id
        if ($projectTemplateStruct->filters_template_id > 0) {
            $filtersConfigModel = (new FiltersConfigTemplateDao($this->database))->getByIdAndUser($projectTemplateStruct->filters_template_id, $projectTemplateStruct->uid);

            if (empty($filtersConfigModel)) {
                throw new Exception("Not existing Filters config template.", 404);
            }
        }

        // check qa_id
        if ($projectTemplateStruct->qa_model_template_id > 0) {
            $qaModel = (new QAModelTemplateDao($this->database))->getQaModelTemplateByIdAndUid([
                'id' => $projectTemplateStruct->qa_model_template_id,
                'uid' => $projectTemplateStruct->uid
            ]);

            if (empty($qaModel)) {
                throw new Exception("Not existing QA template.", 404);
            }
        }

        // check pr_id
        if ($projectTemplateStruct->payable_rate_template_id > 0) {
            $payableRateModel = (new CustomPayableRateDao($this->database))->getByIdAndUser($projectTemplateStruct->payable_rate_template_id, $projectTemplateStruct->uid);

            if (empty($payableRateModel)) {
                throw new Exception("Not existing payable rate template.", 404);
            }
        }

        // check mt
        if ($projectTemplateStruct->mt !== null) {
            $mt = $projectTemplateStruct->getMt();

            if (isset($mt->id)) {
                $engine = EnginesFactory::getInstance($mt->id, $this->database, AbstractEngine::class);

                $engineRecord = $engine->getEngineRecord();

                if ($engineRecord->id > 1 and $engineRecord->uid != $projectTemplateStruct->uid) {
                    throw new Exception("Engine doesn't belong to the user.", 403);
                }

                if (isset($mt->extra) and !$engine->validateConfigurationParams($mt->extra)) {
                    throw new Exception("Engine config parameters are not valid.", 401);
                }
            }
        }

        // check tm
        if ($projectTemplateStruct->tm !== null) {
            $tmKeys = $projectTemplateStruct->getTm();
            $mkDao = new MemoryKeyDao($this->database);

            foreach ($tmKeys as $tmKey) {
                $tmKeyJson = json_encode($tmKey);
                if ($tmKeyJson === false) {
                    throw new Exception("Failed to encode TM key to JSON");
                }
                $tmKey = json_decode($tmKeyJson, true);

                $keyRing = $mkDao->read(
                    (new MemoryKeyStruct([
                        'uid' => $projectTemplateStruct->uid,
                        'tm_key' => new TmKeyStruct($tmKey)
                    ])
                    )
                );

                if (empty($keyRing)) {
                    throw new Exception("TM key doesn't belong to the user.", 403);
                }
            }
        }
    }

    /**
     * @param int $uid
     * @param string $baseRoute
     * @param int $current
     * @param int $pagination
     * @param int $ttl
     *
     * @return array<string, mixed>
     * @throws Exception
     * @throws \DivisionByZeroError
     * @throws \TypeError
     */
    public function getAllPaginated(int $uid, string $baseRoute, int $current = 1, int $pagination = 20, int $ttl = 60 * 60 * 24): array
    {
        $pdo = $this->database->getConnection();

        $pager = new Pager($pdo);

        $totals = $pager->count(
            "SELECT count(id) FROM " . self::TABLE . " WHERE uid = :uid",
            ['uid' => $uid]
        );

        $paginationParameters = new PaginationParameters(static::query_paginated, ['uid' => $uid], ProjectTemplateStruct::class, $baseRoute, $current, $pagination);
        $paginationParameters->setCache(self::paginated_map_key . ":" . $uid, $ttl);

        return $pager->getPagination($totals, $paginationParameters);
    }

    /**
     * @param int $uid
     * @param int $ttl
     *
     * @return ProjectTemplateStruct|null
     * @throws Exception
     * @throws ReflectionException
     */
    public function getTheDefaultProject(int $uid, int $ttl = 60 * 60 * 24): ?ProjectTemplateStruct
    {
        $stmt = $this->_getStatementForQuery(self::query_default);
        /**
         * @var ProjectTemplateStruct[] $result
         */
        $result = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, ProjectTemplateStruct::class, [
            'uid' => $uid,
            'is_default' => 1,
        ]);

        return $result[0] ?? null;
    }

    /**
     * @param int $id
     * @param int $uid
     * @param int $ttl
     *
     * @return ProjectTemplateStruct|null
     * @throws Exception
     * @throws ReflectionException
     */
    public function getByIdAndUser(int $id, int $uid, int $ttl = 60): ?ProjectTemplateStruct
    {
        $stmt = $this->_getStatementForQuery(self::query_by_id_and_uid);
        /**
         * @var ProjectTemplateStruct[] $result
         */
        $result = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, ProjectTemplateStruct::class, [
            'id' => $id,
            'uid' => $uid,
        ]);

        return $result[0] ?? null;
    }

    /**
     * @param ProjectTemplateStruct $projectTemplateStruct
     *
     * @return ProjectTemplateStruct
     * @throws Exception
     * @throws TypeError
     */
    public function save(ProjectTemplateStruct $projectTemplateStruct): ProjectTemplateStruct
    {
        $sql = "INSERT INTO " . self::TABLE . " (
                    `name`,
                    `is_default`,
                    `uid`,
                    `id_team`,
                    `segmentation_rule`,
                    `tm`,
                    `mt`,
                    `payable_rate_template_id`,
                    `qa_model_template_id`,
                    `filters_template_id`,
                    `xliff_config_template_id`,
                    `pretranslate_100`,
                    `pretranslate_101`,
                    `tm_prioritization`,
                    `dialect_strict`,
                    `get_public_matches`,
                    `public_tm_penalty`,
                    `subject`,
                    `source_language`,
                    `target_language`,
                    `character_counter_count_tags`,
                    `character_counter_mode`,
                    `mt_quality_value_in_editor`,
                    `subfiltering_handlers`,
                    `created_at`,
                    `icu_enabled`,
                    `mandatory_issues`
                ) VALUES (
                    :name,
                    :is_default,
                    :uid,
                    :id_team,
                    :segmentation_rule,
                    :tm,
                    :mt,
                    :payable_rate_template_id,
                    :qa_model_template_id,
                    :filters_template_id,
                    :xliff_config_template_id,
                    :pretranslate_100,
                    :pretranslate_101,
                    :tm_prioritization,
                    :dialect_strict,
                    :get_public_matches,
                    :public_tm_penalty,
                    :subject,
                    :source_language,
                    :target_language,
                    :character_counter_count_tags,
                    :character_counter_mode,
                    :mt_quality_value_in_editor,
                    :subfiltering_handlers,
                    :now,
                    :icu_enabled,
                    :mandatory_issues
                ); ";

        $now = (new DateTime())->format('Y-m-d H:i:s');

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            "name" => $projectTemplateStruct->name,
            "subfiltering_handlers" => $projectTemplateStruct->subfiltering_handlers,
            "is_default" => $projectTemplateStruct->is_default,
            "uid" => $projectTemplateStruct->uid,
            "id_team" => $projectTemplateStruct->id_team,
            "segmentation_rule" => $projectTemplateStruct->segmentation_rule,
            "mt" => $projectTemplateStruct->mt,
            "tm" => $projectTemplateStruct->tm,
            "pretranslate_100" => $projectTemplateStruct->pretranslate_100,
            "pretranslate_101" => $projectTemplateStruct->pretranslate_101,
            "tm_prioritization" => $projectTemplateStruct->tm_prioritization,
            "dialect_strict" => $projectTemplateStruct->dialect_strict,
            "get_public_matches" => $projectTemplateStruct->get_public_matches,
            "public_tm_penalty" => $projectTemplateStruct->public_tm_penalty,
            "payable_rate_template_id" => $projectTemplateStruct->payable_rate_template_id,
            "qa_model_template_id" => $projectTemplateStruct->qa_model_template_id,
            "filters_template_id" => $projectTemplateStruct->filters_template_id,
            "xliff_config_template_id" => $projectTemplateStruct->xliff_config_template_id,
            "subject" => $projectTemplateStruct->subject,
            "mt_quality_value_in_editor" => $projectTemplateStruct->mt_quality_value_in_editor,
            "source_language" => $projectTemplateStruct->source_language,
            "target_language" => $projectTemplateStruct->target_language,
            "character_counter_count_tags" => $projectTemplateStruct->character_counter_count_tags,
            "character_counter_mode" => $projectTemplateStruct->character_counter_mode,
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
            'icu_enabled' => $projectTemplateStruct->icu_enabled,
            'mandatory_issues' => $projectTemplateStruct->mandatory_issues,
        ]);

        $projectTemplateStruct->id = (int)$conn->lastInsertId();
        $projectTemplateStruct->created_at = $now;
        $projectTemplateStruct->modified_at = $now;

        if ($projectTemplateStruct->is_default === true) {
            $this->markAsNotDefault($projectTemplateStruct->uid, $projectTemplateStruct->id);
        }

        $this->destroyFetchByIdCache($projectTemplateStruct->id, ProjectTemplateStruct::class);
        $this->destroyQueryByIdAndUserCache($conn, $projectTemplateStruct->id, $projectTemplateStruct->uid);
        $this->destroyQueryPaginated($projectTemplateStruct->uid);

        return $projectTemplateStruct;
    }

    /**
     * @param ProjectTemplateStruct $projectTemplateStruct
     *
     * @return ProjectTemplateStruct
     * @throws Exception
     */
    public function update(ProjectTemplateStruct $projectTemplateStruct): ProjectTemplateStruct
    {
        $id = $projectTemplateStruct->id ?? throw new Exception("ProjectTemplateStruct::id must not be null when updating");

        $sql = "UPDATE " . self::TABLE . " SET 
            `name` = :name, 
            `is_default` = :is_default, 
            `uid` = :uid, 
            `id_team` = :id_team, 
            `subfiltering_handlers` = :subfiltering_handlers, 
            `segmentation_rule` = :segmentation_rule, 
            `tm` = :tm, 
            `mt` = :mt, 
            `pretranslate_100` = :pretranslate_100,
            `pretranslate_101` = :pretranslate_101,
            `tm_prioritization` = :tm_prioritization,
            `dialect_strict` = :dialect_strict,
            `get_public_matches` = :get_public_matches,
            `public_tm_penalty` = :public_tm_penalty,
            `payable_rate_template_id` = :payable_rate_template_id, 
            `qa_model_template_id` = :qa_model_template_id, 
            `filters_template_id` = :filters_template_id, 
            `xliff_config_template_id` = :xliff_config_template_id, 
            `subject` = :subject,
            `source_language` = :source_language,
            `target_language` = :target_language,
            `character_counter_count_tags` = :character_counter_count_tags,
            `character_counter_mode` = :character_counter_mode,
            `mt_quality_value_in_editor` = :mt_quality_value_in_editor,
            `modified_at` = :now,
            `icu_enabled` = :icu_enabled,
            `mandatory_issues` = :mandatory_issues
         WHERE id = :id;";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            "id" => $id,
            "name" => $projectTemplateStruct->name,
            "subfiltering_handlers" => $projectTemplateStruct->subfiltering_handlers,
            "is_default" => $projectTemplateStruct->is_default,
            "uid" => $projectTemplateStruct->uid,
            "id_team" => $projectTemplateStruct->id_team,
            "segmentation_rule" => $projectTemplateStruct->segmentation_rule,
            "mt" => $projectTemplateStruct->mt,
            "tm" => $projectTemplateStruct->tm,
            "pretranslate_100" => $projectTemplateStruct->pretranslate_100,
            "pretranslate_101" => $projectTemplateStruct->pretranslate_101,
            "tm_prioritization" => $projectTemplateStruct->tm_prioritization,
            "dialect_strict" => $projectTemplateStruct->dialect_strict,
            "get_public_matches" => $projectTemplateStruct->get_public_matches,
            "public_tm_penalty" => $projectTemplateStruct->public_tm_penalty,
            "payable_rate_template_id" => $projectTemplateStruct->payable_rate_template_id,
            "qa_model_template_id" => $projectTemplateStruct->qa_model_template_id,
            "xliff_config_template_id" => $projectTemplateStruct->xliff_config_template_id,
            "filters_template_id" => $projectTemplateStruct->filters_template_id,
            "subject" => $projectTemplateStruct->subject,
            "mt_quality_value_in_editor" => $projectTemplateStruct->mt_quality_value_in_editor,
            "character_counter_count_tags" => $projectTemplateStruct->character_counter_count_tags,
            "character_counter_mode" => $projectTemplateStruct->character_counter_mode,
            "source_language" => $projectTemplateStruct->source_language,
            "target_language" => $projectTemplateStruct->target_language,
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
            'icu_enabled' => $projectTemplateStruct->icu_enabled,
            'mandatory_issues' => $projectTemplateStruct->mandatory_issues,
        ]);

        $this->destroyFetchByIdCache($id, ProjectTemplateStruct::class);
        $this->destroyQueryByIdAndUserCache($conn, $id, $projectTemplateStruct->uid);
        $this->destroyQueryPaginated($projectTemplateStruct->uid);

        if ($projectTemplateStruct->is_default === true) {
            $this->markAsNotDefault($projectTemplateStruct->uid, $id);
        }

        return $projectTemplateStruct;
    }

    /**
     * @param int $uid
     * @param int $excludeId
     *
     * @throws PDOException
     * @throws ReflectionException
     * @throws Exception
     */
    public function markAsNotDefault(int $uid, int $excludeId): void
    {
        $sql = "UPDATE " . self::TABLE . " SET 
            `is_default` = :is_default
             WHERE uid= :uid 
             AND id != :id
         ;";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            "uid" => $uid,
            "id" => $excludeId,
            "is_default" => false,
        ]);

        // destroy cache
        $stmt = $conn->prepare("SELECT id FROM " . self::TABLE . " WHERE uid = :uid ");
        $stmt->execute([
            'uid' => $uid
        ]);

        foreach ($stmt->fetchAll() as $project) {
            $this->destroyDefaultTemplateCache($conn, $uid);
            $this->destroyFetchByIdCache($project['id'], ProjectTemplateStruct::class);
            $this->destroyQueryByIdAndUserCache($conn, $project['id'], $uid);
            $this->destroyQueryPaginated($uid);
        }
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
        $stmt = $conn->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id ");
        $stmt->execute(['id' => $id]);

        $this->destroyFetchByIdCache($id, ProjectTemplateStruct::class);
        $this->destroyQueryByIdAndUserCache($conn, $id, $uid);
        $this->destroyQueryPaginated($uid);

        return $stmt->rowCount();
    }

    /**
     * @throws PDOException
     * @throws ReflectionException
     * @throws Exception
     */
    public function removeSubTemplateByIdAndUser(int $id, int $uid, string $subTemplateField): int
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare("UPDATE " . self::TABLE . " SET `$subTemplateField` = :zero WHERE uid = :uid and `$subTemplateField` = :id ");
        $stmt->execute([
            'zero' => 0,
            'id' => $id,
            'uid' => $uid,
        ]);

        $this->destroyFetchByIdCache($id, ProjectTemplateStruct::class);
        $this->destroyQueryByIdAndUserCache($conn, $id, $uid);
        $this->destroyQueryPaginated($uid);

        return $stmt->rowCount();
    }

    /**
     * @param PDO $conn
     * @param int $id
     * @param int $uid
     *
     * @throws PDOException
     * @throws ReflectionException
     * @throws \Psr\Log\InvalidArgumentException
     */
    private function destroyQueryByIdAndUserCache(PDO $conn, int $id, int $uid): void
    {
        $stmt = $conn->prepare(self::query_by_id_and_uid);
        $this->_destroyObjectCache($stmt, ProjectTemplateStruct::class, ['id' => $id, 'uid' => $uid]);
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
     * @param PDO $conn
     * @param int $uid
     *
     * @throws PDOException
     * @throws ReflectionException
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function destroyDefaultTemplateCache(PDO $conn, int $uid): void
    {
        $stmt = $conn->prepare(self::query_default);
        $this->_destroyObjectCache($stmt, ProjectTemplateStruct::class, ['uid' => $uid, 'is_default' => 1]);
    }

}
