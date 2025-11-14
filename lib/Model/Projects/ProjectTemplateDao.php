<?php

namespace Model\Projects;

use DateTime;
use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
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
use ReflectionException;
use stdClass;
use Utils\Engines\EnginesFactory;
use Utils\Langs\Languages;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\Tools\Utils;

class ProjectTemplateDao extends AbstractDao {
    const TABLE = 'project_templates';

    const query_by_id         = "SELECT * FROM " . self::TABLE . " WHERE id = :id";
    const query_by_id_and_uid = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND uid = :uid";
    const query_default       = "SELECT * FROM " . self::TABLE . " WHERE is_default = :is_default AND uid = :uid";
    const query_paginated     = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid ORDER BY id LIMIT %u OFFSET %u ";
    const paginated_map_key   = __CLASS__ . "::getAllPaginated";

    /**
     * @param $uid
     *
     * @return ProjectTemplateStruct
     * @throws Exception
     */
    public static function getDefaultTemplate( $uid ): ProjectTemplateStruct {
        $defaultProject = self::getTheDefaultProject( $uid );
        $team           = ( new TeamDao() )->getPersonalByUid( $uid );

        $default                               = new ProjectTemplateStruct();
        $default->id                           = 0;
        $default->name                         = "Matecat original settings";
        $default->is_default                   = empty( $defaultProject );
        $default->id_team                      = $team->id;
        $default->uid                          = $uid;
        $default->pretranslate_100             = false;
        $default->pretranslate_101             = true;
        $default->tm_prioritization            = false;
        $default->dialect_strict               = false;
        $default->get_public_matches           = true;
        $default->character_counter_count_tags = false;
        $default->character_counter_mode       = "google_ads";
        $default->public_tm_penalty            = 0;
        $default->payable_rate_template_id     = 0;
        $default->qa_model_template_id         = 0;
        $default->xliff_config_template_id     = 0;
        $default->filters_template_id          = 0;
        $default->mt_quality_value_in_editor   = 85;
        $default->subject                      = "general";
        $default->source_language              = "en-US";
        $default->target_language              = serialize( [ "fr-FR" ] );
        $default->segmentation_rule            = json_encode( [
                "name" => "General",
                "id"   => "standard"
        ] );

        // MT
        $default->mt = json_encode( self::getUserDefaultMt() );

        $default->tm                    = json_encode( [] );
        $default->created_at            = date( "Y-m-d H:i:s" );
        $default->modified_at           = date( "Y-m-d H:i:s" );
        $default->subfiltering_handlers = json_encode( [] );

        return $default;
    }

    /**
     * @return array
     */
    private static function getUserDefaultMt(): array {
        return [
                'id'    => 1,
                'extra' => new stdClass()
        ];
    }

    /**
     * @var ProjectTemplateDao|null
     */
    private static ?self $instance = null;

    /**
     * @return ProjectTemplateDao
     */
    private static function getInstance(): ProjectTemplateDao {
        if ( !isset( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param object     $decodedObject
     * @param UserStruct $user
     *
     * @return ProjectTemplateStruct
     * @throws ReflectionException
     * @throws Exception
     */
    public static function createFromJSON( object $decodedObject, UserStruct $user ): ProjectTemplateStruct {

        $projectTemplateStruct = new ProjectTemplateStruct();
        $projectTemplateStruct->hydrateFromJSON( $decodedObject, $user->uid );

        self::checkValues( $projectTemplateStruct, $user );

        return self::save( $projectTemplateStruct );
    }

    /**
     * @param ProjectTemplateStruct $projectTemplateStruct
     * @param object                $json
     * @param int                   $id
     * @param UserStruct            $user
     *
     * @return ProjectTemplateStruct
     * @throws ReflectionException
     * @throws Exception
     */
    public static function editFromJSON( ProjectTemplateStruct $projectTemplateStruct, object $json, int $id, UserStruct $user ): ProjectTemplateStruct {

        $projectTemplateStruct->hydrateFromJSON( $json, $user->uid, $id );

        self::checkValues( $projectTemplateStruct, $user );

        return self::update( $projectTemplateStruct );
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
     * @param UserStruct            $user
     *
     * @throws ReflectionException
     * @throws Exception
     */
    private static function checkValues( ProjectTemplateStruct $projectTemplateStruct, UserStruct $user ) {

        // check subfiltering string
        // we don't need to check the subfiltering_handlers because it's already checked in the validation schema

        // check id_team
        $team = ( new MembershipDao() )->setCacheTTL( 60 * 5 )->findTeamByIdAndUser(
                $projectTemplateStruct->id_team,
                $user
        );

        if ( empty( $team ) ) {
            throw new Exception( "This user does not belong to this group.", 403 );
        }

        // source_language
        if ( $projectTemplateStruct->source_language !== null ) {
            $languages = Languages::getInstance();
            $language  = Utils::trimAndLowerCase( $projectTemplateStruct->source_language );

            try {
                $languages->validateLanguage( $language );
            } catch ( Exception $e ) {
                throw new $e( $e->getMessage(), 403 );
            }

        }

        // target_language
        if ( $projectTemplateStruct->target_language !== null ) {

            $targetLanguages = unserialize( $projectTemplateStruct->target_language );

            if ( !is_array( $targetLanguages ) ) {
                throw new Exception( "target language is not an array", 403 );
            }

            $languages = Languages::getInstance();

            try {
                $languages->validateLanguageList( $targetLanguages );
            } catch ( Exception $e ) {
                throw new $e( $e->getMessage(), 403 );
            }

        }

        // check xliff_config_template_id
        if ( $projectTemplateStruct->xliff_config_template_id > 0 ) {
            $xliffConfigModel = XliffConfigTemplateDao::getByIdAndUser( $projectTemplateStruct->xliff_config_template_id, $projectTemplateStruct->uid );

            if ( empty( $xliffConfigModel ) ) {
                throw new Exception( "Not existing Xliff template.", 404 );
            }

        }

        // check filters_template_id
        if ( $projectTemplateStruct->filters_template_id > 0 ) {
            $filtersConfigModel = FiltersConfigTemplateDao::getByIdAndUser( $projectTemplateStruct->filters_template_id, $projectTemplateStruct->uid );

            if ( empty( $filtersConfigModel ) ) {
                throw new Exception( "Not existing Filters config template.", 404 );
            }

        }

        // check qa_id
        if ( $projectTemplateStruct->qa_model_template_id > 0 ) {
            $qaModel = QAModelTemplateDao::getQaModelTemplateByIdAndUid( Database::obtain()->getConnection(), [
                    'id'  => $projectTemplateStruct->qa_model_template_id,
                    'uid' => $projectTemplateStruct->uid
            ] );

            if ( empty( $qaModel ) ) {
                throw new Exception( "Not existing QA template.", 404 );
            }

        }

        // check pr_id
        if ( $projectTemplateStruct->payable_rate_template_id > 0 ) {
            $payableRateModel = CustomPayableRateDao::getByIdAndUser( $projectTemplateStruct->payable_rate_template_id, $projectTemplateStruct->uid );

            if ( empty( $payableRateModel ) ) {
                throw new Exception( "Not existing payable rate template.", 404 );
            }

        }

        // check mt
        if ( $projectTemplateStruct->mt !== null ) {
            $mt = $projectTemplateStruct->getMt();

            if ( isset( $mt->id ) ) {
                $engine = EnginesFactory::getInstance( $mt->id );

                $engineRecord = $engine->getEngineRecord();

                if ( $engineRecord->id > 1 and $engineRecord->uid != $projectTemplateStruct->uid ) {
                    throw new Exception( "Engine doesn't belong to the user.", 403 );
                }

                if ( isset( $mt->extra ) and !$engine->validateConfigurationParams( $mt->extra ) ) {
                    throw new Exception( "Engine config parameters are not valid.", 401 );
                }
            }
        }

        // check tm
        if ( $projectTemplateStruct->tm !== null ) {
            $tmKeys = $projectTemplateStruct->getTm();
            $mkDao  = new MemoryKeyDao();

            foreach ( $tmKeys as $tmKey ) {
                $keyRing = $mkDao->read(
                        ( new MemoryKeyStruct( [
                                'uid'    => $projectTemplateStruct->uid,
                                'tm_key' => new TmKeyStruct( $tmKey )
                        ] )
                        )
                );

                if ( empty( $keyRing ) ) {
                    throw new Exception( "TM key doesn't belong to the user.", 403 );
                }
            }
        }
    }

    /**
     * @param int    $uid
     * @param string $baseRoute
     * @param int    $current
     * @param int    $pagination
     * @param int    $ttl
     *
     * @return array
     * @throws Exception
     */
    public static function getAllPaginated( int $uid, string $baseRoute, int $current = 1, int $pagination = 20, int $ttl = 60 * 60 * 24 ): array {

        $pdo = Database::obtain()->getConnection();

        $pager = new Pager( $pdo );

        $totals = $pager->count(
                "SELECT count(id) FROM " . self::TABLE . " WHERE uid = :uid",
                [ 'uid' => $uid ]
        );

        $paginationParameters = new PaginationParameters( static::query_paginated, [ 'uid' => $uid ], ProjectTemplateStruct::class, $baseRoute, $current, $pagination );
        $paginationParameters->setCache( self::paginated_map_key . ":" . $uid, $ttl );

        return $pager->getPagination( $totals, $paginationParameters );

    }

    /**
     * @param int $uid
     * @param int $ttl
     *
     * @return ProjectTemplateStruct|null
     * @throws ReflectionException
     */
    public static function getTheDefaultProject( int $uid, int $ttl = 60 * 60 * 24 ): ?ProjectTemplateStruct {
        $stmt = self::getInstance()->_getStatementForQuery( self::query_default );
        /**
         * @var $result ProjectTemplateStruct[]
         */
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, ProjectTemplateStruct::class, [
                'uid'        => $uid,
                'is_default' => 1,
        ] );

        return $result[ 0 ] ?? null;
    }

    /**
     * @param int $id
     * @param int $ttl
     *
     * @return ProjectTemplateStruct|null
     * @throws ReflectionException
     */
    public
    static function getById( int $id, int $ttl = 60 ): ?ProjectTemplateStruct {
        $stmt = self::getInstance()->_getStatementForQuery( self::query_by_id );
        /**
         * @var $result ProjectTemplateStruct[]
         */
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, ProjectTemplateStruct::class, [
                'id' => $id,
        ] );

        return $result[ 0 ] ?? null;
    }

    /**
     * @param int $id
     * @param int $uid
     * @param int $ttl
     *
     * @return ProjectTemplateStruct|null
     * @throws ReflectionException
     */
    public
    static function getByIdAndUser( int $id, int $uid, int $ttl = 60 ): ?ProjectTemplateStruct {
        $stmt = self::getInstance()->_getStatementForQuery( self::query_by_id_and_uid );
        /**
         * @var $result ProjectTemplateStruct[]
         */
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, ProjectTemplateStruct::class, [
                'id'  => $id,
                'uid' => $uid,
        ] );

        return $result[ 0 ] ?? null;
    }

    /**
     * @param ProjectTemplateStruct $projectTemplateStruct
     *
     * @return ProjectTemplateStruct
     * @throws Exception
     */
    public
    static function save( ProjectTemplateStruct $projectTemplateStruct ): ProjectTemplateStruct {
        $sql = "INSERT INTO " . self::TABLE .
                " ( `name`, `is_default`, `uid`, `id_team`, `segmentation_rule`, `tm`, `mt`, `payable_rate_template_id`,`qa_model_template_id`, `filters_template_id`, `xliff_config_template_id`, `pretranslate_100`, `pretranslate_101`, `tm_prioritization`, `dialect_strict`, `get_public_matches`, `public_tm_penalty`, `subject`, `source_language`, `target_language`, `character_counter_count_tags`, `character_counter_mode`, `mt_quality_value_in_editor`, `subfiltering_handlers`, `created_at` ) " .
                " VALUES " .
                " ( :name, :is_default, :uid, :id_team, :segmentation_rule, :tm, :mt, :payable_rate_template_id, :qa_model_template_id, :filters_template_id, :xliff_config_template_id, :pretranslate_100, :pretranslate_101, :tm_prioritization, :dialect_strict, :get_public_matches, :public_tm_penalty, :subject, :source_language, :target_language, :character_counter_count_tags, :character_counter_mode, :mt_quality_value_in_editor, :subfiltering_handlers, :now ); ";

        $now = ( new DateTime() )->format( 'Y-m-d H:i:s' );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                "name"                         => $projectTemplateStruct->name,
                "subfiltering_handlers"        => $projectTemplateStruct->subfiltering_handlers,
                "is_default"                   => $projectTemplateStruct->is_default,
                "uid"                          => $projectTemplateStruct->uid,
                "id_team"                      => $projectTemplateStruct->id_team,
                "segmentation_rule"            => $projectTemplateStruct->segmentation_rule,
                "mt"                           => $projectTemplateStruct->mt,
                "tm"                           => $projectTemplateStruct->tm,
                "pretranslate_100"             => $projectTemplateStruct->pretranslate_100,
                "pretranslate_101"             => $projectTemplateStruct->pretranslate_101,
                "tm_prioritization"            => $projectTemplateStruct->tm_prioritization,
                "dialect_strict"               => $projectTemplateStruct->dialect_strict,
                "get_public_matches"           => $projectTemplateStruct->get_public_matches,
                "public_tm_penalty"            => $projectTemplateStruct->public_tm_penalty,
                "payable_rate_template_id"     => $projectTemplateStruct->payable_rate_template_id,
                "qa_model_template_id"         => $projectTemplateStruct->qa_model_template_id,
                "filters_template_id"          => $projectTemplateStruct->filters_template_id,
                "xliff_config_template_id"     => $projectTemplateStruct->xliff_config_template_id,
                "subject"                      => $projectTemplateStruct->subject,
                "mt_quality_value_in_editor"   => $projectTemplateStruct->mt_quality_value_in_editor,
                "source_language"              => $projectTemplateStruct->source_language,
                "target_language"              => $projectTemplateStruct->target_language,
                "character_counter_count_tags" => $projectTemplateStruct->character_counter_count_tags,
                "character_counter_mode"       => $projectTemplateStruct->character_counter_mode,
                'now'                          => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        $projectTemplateStruct->id          = $conn->lastInsertId();
        $projectTemplateStruct->created_at  = $now;
        $projectTemplateStruct->modified_at = $now;

        if ( $projectTemplateStruct->is_default === true ) {
            self::markAsNotDefault( $projectTemplateStruct->uid, $projectTemplateStruct->id );
        }

        self::destroyQueryByIdCache( $conn, $projectTemplateStruct->id );
        self::destroyQueryByIdAndUserCache( $conn, $projectTemplateStruct->id, $projectTemplateStruct->uid );
        self::destroyQueryPaginated( $projectTemplateStruct->uid );

        return $projectTemplateStruct;
    }

    /**
     * @param ProjectTemplateStruct $projectTemplateStruct
     *
     * @return ProjectTemplateStruct
     * @throws Exception
     */
    public
    static function update( ProjectTemplateStruct $projectTemplateStruct ): ProjectTemplateStruct {
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
            `modified_at` = :now 
         WHERE id = :id;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                "id"                           => $projectTemplateStruct->id,
                "name"                         => $projectTemplateStruct->name,
                "subfiltering_handlers"        => $projectTemplateStruct->subfiltering_handlers,
                "is_default"                   => $projectTemplateStruct->is_default,
                "uid"                          => $projectTemplateStruct->uid,
                "id_team"                      => $projectTemplateStruct->id_team,
                "segmentation_rule"            => $projectTemplateStruct->segmentation_rule,
                "mt"                           => $projectTemplateStruct->mt,
                "tm"                           => $projectTemplateStruct->tm,
                "pretranslate_100"             => $projectTemplateStruct->pretranslate_100,
                "pretranslate_101"             => $projectTemplateStruct->pretranslate_101,
                "tm_prioritization"            => $projectTemplateStruct->tm_prioritization,
                "dialect_strict"               => $projectTemplateStruct->dialect_strict,
                "get_public_matches"           => $projectTemplateStruct->get_public_matches,
                "public_tm_penalty"            => $projectTemplateStruct->public_tm_penalty,
                "payable_rate_template_id"     => $projectTemplateStruct->payable_rate_template_id,
                "qa_model_template_id"         => $projectTemplateStruct->qa_model_template_id,
                "xliff_config_template_id"     => $projectTemplateStruct->xliff_config_template_id,
                "filters_template_id"          => $projectTemplateStruct->filters_template_id,
                "subject"                      => $projectTemplateStruct->subject,
                "mt_quality_value_in_editor"   => $projectTemplateStruct->mt_quality_value_in_editor,
                "character_counter_count_tags" => $projectTemplateStruct->character_counter_count_tags,
                "character_counter_mode"       => $projectTemplateStruct->character_counter_mode,
                "source_language"              => $projectTemplateStruct->source_language,
                "target_language"              => $projectTemplateStruct->target_language,
                'now'                          => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        self::destroyQueryByIdCache( $conn, $projectTemplateStruct->id );
        self::destroyQueryByIdAndUserCache( $conn, $projectTemplateStruct->id, $projectTemplateStruct->uid );
        self::destroyQueryPaginated( $projectTemplateStruct->uid );

        if ( $projectTemplateStruct->is_default === true ) {
            self::markAsNotDefault( $projectTemplateStruct->uid, $projectTemplateStruct->id );
        }

        return $projectTemplateStruct;
    }

    /**
     * @param int $uid
     * @param int $excludeId
     *
     * @throws ReflectionException
     */
    public
    static function markAsNotDefault( int $uid, int $excludeId ) {
        $sql = "UPDATE " . self::TABLE . " SET 
            `is_default` = :is_default
             WHERE uid= :uid 
             AND id != :id
         ;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                "uid"        => $uid,
                "id"         => $excludeId,
                "is_default" => false,
        ] );

        // destroy cache
        $stmt = $conn->prepare( "SELECT id FROM " . self::TABLE . " WHERE uid = :uid " );
        $stmt->execute( [
                'uid' => $uid
        ] );

        foreach ( $stmt->fetchAll() as $project ) {
            self::destroyDefaultTemplateCache( $conn, $uid );
            self::destroyQueryByIdCache( $conn, $project[ 'id' ] );
            self::destroyQueryByIdAndUserCache( $conn, $project[ 'id' ], $uid );
            self::destroyQueryPaginated( $uid );
        }
    }

    /**
     * @param int $id
     * @param int $uid
     *
     * @return int
     * @throws ReflectionException
     */
    public
    static function remove( int $id, int $uid ): int {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "DELETE FROM " . self::TABLE . " WHERE id = :id " );
        $stmt->execute( [ 'id' => $id ] );

        self::destroyQueryByIdCache( $conn, $id );
        self::destroyQueryByIdAndUserCache( $conn, $id, $uid );
        self::destroyQueryPaginated( $uid );

        return $stmt->rowCount();
    }

    /**
     * @throws ReflectionException
     */
    public static function removeSubTemplateByIdAndUser( int $id, int $uid, string $subTemplateField ): int {

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "UPDATE " . self::TABLE . " SET `$subTemplateField` = :zero WHERE uid = :uid and `$subTemplateField` = :id " );
        $stmt->execute( [
                'zero' => 0,
                'id'   => $id,
                'uid'  => $uid,
        ] );

        self::destroyQueryByIdCache( $conn, $id );
        self::destroyQueryByIdAndUserCache( $conn, $id, $uid );
        self::destroyQueryPaginated( $uid );

        return $stmt->rowCount();

    }

    /**
     * @param PDO $conn
     * @param int $id
     *
     * @throws ReflectionException
     */
    private
    static function destroyQueryByIdCache( PDO $conn, int $id ) {
        $stmt = $conn->prepare( self::query_by_id );
        self::getInstance()->_destroyObjectCache( $stmt, ProjectTemplateStruct::class, [ 'id' => $id, ] );
    }

    /**
     * @param PDO $conn
     * @param int $id
     * @param int $uid
     *
     * @throws ReflectionException
     */
    private
    static function destroyQueryByIdAndUserCache( PDO $conn, int $id, int $uid ) {
        $stmt = $conn->prepare( self::query_by_id_and_uid );
        self::getInstance()->_destroyObjectCache( $stmt, ProjectTemplateStruct::class, [ 'id' => $id, 'uid' => $uid ] );
    }

    /**
     * @param int $uid
     *
     * @throws ReflectionException
     */
    private
    static function destroyQueryPaginated( int $uid ) {
        self::getInstance()->_deleteCacheByKey( self::paginated_map_key . ":" . $uid, false );
    }

    /**
     * @param PDO $conn
     * @param int $uid
     *
     * @throws ReflectionException
     */
    public static function destroyDefaultTemplateCache( PDO $conn, int $uid ) {
        $stmt = $conn->prepare( self::query_default );
        self::getInstance()->_destroyObjectCache( $stmt, ProjectTemplateStruct::class, [ 'uid' => $uid, 'is_default' => 1 ] );
    }

}