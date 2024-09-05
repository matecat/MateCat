<?php

namespace Projects;

use DataAccess_AbstractDao;
use Database;
use DateTime;
use Engine;
use Exception;
use Filters\FiltersConfigTemplateDao;
use Pagination\Pager;
use Pagination\PaginationParameters;
use PayableRates\CustomPayableRateDao;
use PDO;
use QAModelTemplate\QAModelTemplateDao;
use ReflectionException;
use stdClass;
use Swaggest\JsonSchema\InvalidValue;
use Teams\TeamDao;
use TmKeyManagement_MemoryKeyDao;
use TmKeyManagement_MemoryKeyStruct;
use TmKeyManagement_TmKeyStruct;
use Xliff\XliffConfigTemplateDao;

class ProjectTemplateDao extends DataAccess_AbstractDao {
    const TABLE = 'project_templates';

    const query_by_id         = "SELECT * FROM " . self::TABLE . " WHERE id = :id";
    const query_by_id_and_uid = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND uid = :uid";
    const query_default       = "SELECT * FROM " . self::TABLE . " WHERE is_default = :is_default AND uid = :uid";
    const query_by_uid_name   = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND name = :name";
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

        $default                           = new ProjectTemplateStruct();
        $default->id                       = 0;
        $default->name                     = "Standard";
        $default->speech2text              = false;
        $default->is_default               = empty( $defaultProject );
        $default->id_team                  = $team->id;
        $default->lexica                   = true;
        $default->tag_projection           = true;
        $default->uid                      = $uid;
        $default->pretranslate_100         = false;
        $default->pretranslate_101         = true;
        $default->get_public_matches       = true;
        $default->payable_rate_template_id = 0;
        $default->qa_model_template_id     = 0;
        $default->xliff_config_template_id = 0;
        $default->filters_template_id      = 0;
        $default->segmentation_rule        = json_encode( [
                "name" => "General",
                "id"   => "standard"
        ] );

        // MT
        $default->mt = json_encode( self::getUserDefaultMt() );

        $default->tm          = json_encode( [] );
        $default->created_at  = date( "Y-m-d H:i:s" );
        $default->modified_at = date( "Y-m-d H:i:s" );

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
     * @param string $json
     * @param int    $uid
     *
     * @return ProjectTemplateStruct
     * @throws InvalidValue
     * @throws Exception
     */
    public static function createFromJSON( string $json, int $uid ): ProjectTemplateStruct {

        $projectTemplateStruct = new ProjectTemplateStruct();
        $projectTemplateStruct->hydrateFromJSON( $json, $uid );

        self::checkValues( $projectTemplateStruct );

        return self::save( $projectTemplateStruct );
    }

    /**
     * @param ProjectTemplateStruct $projectTemplateStruct
     * @param string                $json
     * @param int                   $id
     * @param int                   $uid
     *
     * @return ProjectTemplateStruct
     * @throws Exception
     */
    public static function editFromJSON( ProjectTemplateStruct $projectTemplateStruct, string $json, int $id, int $uid ): ProjectTemplateStruct {

        $projectTemplateStruct->hydrateFromJSON( $json, $uid, $id );

        self::checkValues( $projectTemplateStruct );

        return self::update( $projectTemplateStruct );
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
     *
     * @throws Exception
     */
    private static function checkValues( ProjectTemplateStruct $projectTemplateStruct ) {
        // check id_team
        $teamDao      = new TeamDao();
        $personalTeam = $teamDao->getPersonalByUid( $projectTemplateStruct->uid );

        if ( $personalTeam === null ) {
            $team = $teamDao->findById( $projectTemplateStruct->id_team );

            if ( $team === null ) {
                throw new Exception( "User group not found.", 404 );
            }

            if ( !$team->hasUser( $projectTemplateStruct->uid ) ) {
                throw new Exception( "This user does not belong to this group.", 403 );
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
                $engine = Engine::getInstance( $mt->id );

                if ( empty( $engine ) ) {
                    throw new Exception( "Not existing engine." );
                }

                $engineRecord = $engine->getEngineRecord();

                if ( $engineRecord->id > 1 and $engineRecord->uid != $projectTemplateStruct->uid ) {
                    throw new Exception( "Engine doesn't belong to the user.", 403 );
                }
            }
        }

        // check tm
        if ( $projectTemplateStruct->tm !== null ) {
            $tmKeys = $projectTemplateStruct->getTm();
            $mkDao  = new TmKeyManagement_MemoryKeyDao();

            foreach ( $tmKeys as $tmKey ) {
                $keyRing = $mkDao->read(
                        ( new TmKeyManagement_MemoryKeyStruct( [
                                'uid'    => $projectTemplateStruct->uid,
                                'tm_key' => new TmKeyManagement_TmKeyStruct( $tmKey->key )
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
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ProjectTemplateStruct(), [
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
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ProjectTemplateStruct(), [
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
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ProjectTemplateStruct(), [
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
                " ( `name`, `is_default`, `uid`, `id_team`, `speech2text`, `lexica`, `tag_projection`, `cross_language_matches`, `segmentation_rule`, `tm`, `mt`, `payable_rate_template_id`,`qa_model_template_id`, `filters_template_id`, `xliff_config_template_id`, `pretranslate_100`, `pretranslate_101`, `get_public_matches`, `created_at` ) " .
                " VALUES " .
                " ( :name, :is_default, :uid, :id_team, :speech2text, :lexica, :tag_projection, :cross_language_matches, :segmentation_rule, :tm, :mt, :payable_rate_template_id, :qa_model_template_id, :filters_template_id, :xliff_config_template_id, :pretranslate_100, :pretranslate_101, :get_public_matches, :now ); ";

        $now = ( new DateTime() )->format( 'Y-m-d H:i:s' );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                "name"                     => $projectTemplateStruct->name,
                "is_default"               => $projectTemplateStruct->is_default,
                "uid"                      => $projectTemplateStruct->uid,
                "id_team"                  => $projectTemplateStruct->id_team,
                "speech2text"              => $projectTemplateStruct->speech2text,
                "lexica"                   => $projectTemplateStruct->lexica,
                "tag_projection"           => $projectTemplateStruct->tag_projection,
                "cross_language_matches"   => $projectTemplateStruct->cross_language_matches,
                "segmentation_rule"        => $projectTemplateStruct->segmentation_rule,
                "mt"                       => $projectTemplateStruct->mt,
                "tm"                       => $projectTemplateStruct->tm,
                "pretranslate_100"         => $projectTemplateStruct->pretranslate_100,
                "pretranslate_101"         => $projectTemplateStruct->pretranslate_101,
                "get_public_matches"       => $projectTemplateStruct->get_public_matches,
                "payable_rate_template_id" => $projectTemplateStruct->payable_rate_template_id,
                "qa_model_template_id"     => $projectTemplateStruct->qa_model_template_id,
                "filters_template_id"      => $projectTemplateStruct->filters_template_id,
                "xliff_config_template_id" => $projectTemplateStruct->xliff_config_template_id,
                'now'                      => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        $projectTemplateStruct->id          = $conn->lastInsertId();
        $projectTemplateStruct->created_at  = $now;
        $projectTemplateStruct->modified_at = $now;

        if ( $projectTemplateStruct->is_default === true ) {
            self::markAsNotDefault( $projectTemplateStruct->uid, $projectTemplateStruct->id );
        }

        self::destroyQueryByIdCache( $conn, $projectTemplateStruct->id );
        self::destroyQueryByIdAndUserCache( $conn, $projectTemplateStruct->id, $projectTemplateStruct->uid );
        self::destroyQueryByUidAndNameCache( $conn, $projectTemplateStruct->uid, $projectTemplateStruct->name );
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
            `filters_template_id` = :filters_template_id, 
            `xliff_config_template_id` = :xliff_config_template_id, 
            `modified_at` = :now 
         WHERE id = :id;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                "id"                       => $projectTemplateStruct->id,
                "name"                     => $projectTemplateStruct->name,
                "is_default"               => $projectTemplateStruct->is_default,
                "uid"                      => $projectTemplateStruct->uid,
                "id_team"                  => $projectTemplateStruct->id_team,
                "speech2text"              => $projectTemplateStruct->speech2text,
                "lexica"                   => $projectTemplateStruct->lexica,
                "tag_projection"           => $projectTemplateStruct->tag_projection,
                "cross_language_matches"   => $projectTemplateStruct->cross_language_matches,
                "segmentation_rule"        => $projectTemplateStruct->segmentation_rule,
                "mt"                       => $projectTemplateStruct->mt,
                "tm"                       => $projectTemplateStruct->tm,
                "pretranslate_100"         => $projectTemplateStruct->pretranslate_100,
                "pretranslate_101"         => $projectTemplateStruct->pretranslate_101,
                "get_public_matches"       => $projectTemplateStruct->get_public_matches,
                "payable_rate_template_id" => $projectTemplateStruct->payable_rate_template_id,
                "qa_model_template_id"     => $projectTemplateStruct->qa_model_template_id,
                "xliff_config_template_id" => $projectTemplateStruct->xliff_config_template_id,
                "filters_template_id"      => $projectTemplateStruct->filters_template_id,
                'now'                      => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        self::destroyQueryByIdCache( $conn, $projectTemplateStruct->id );
        self::destroyQueryByIdAndUserCache( $conn, $projectTemplateStruct->id, $projectTemplateStruct->uid );
        self::destroyQueryByUidAndNameCache( $conn, $projectTemplateStruct->uid, $projectTemplateStruct->name );
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
     * @param PDO    $conn
     * @param int    $uid
     * @param string $name
     *
     * @throws ReflectionException
     */
    private
    static function destroyQueryByUidAndNameCache( PDO $conn, int $uid, string $name ) {
        $stmt = $conn->prepare( self::query_by_uid_name );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'uid' => $uid, 'name' => $name, ] );
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
        self::getInstance()->_destroyObjectCache( $stmt, [ 'id' => $id, ] );
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
        self::getInstance()->_destroyObjectCache( $stmt, [ 'id' => $id, 'uid' => $uid ] );
    }

    /**
     * @param int $uid
     *
     * @throws ReflectionException
     */
    private
    static function destroyQueryPaginated( int $uid ) {
        self::getInstance()->_destroyCache( self::paginated_map_key . ":" . $uid, false );
    }

    /**
     * @throws ReflectionException
     */
    public static function destroyDefaultTemplateCache( PDO $conn, int $uid ) {
        $stmt = $conn->prepare( self::query_default );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'uid' => $uid, ] );
    }

}