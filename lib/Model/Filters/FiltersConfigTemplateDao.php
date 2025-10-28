<?php

namespace Model\Filters;

use DateTime;
use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Pagination\Pager;
use Model\Pagination\PaginationParameters;
use Model\Projects\ProjectTemplateDao;
use Model\Projects\ProjectTemplateStruct;
use PDO;
use ReflectionException;
use Utils\Tools\Utils;

class FiltersConfigTemplateDao extends AbstractDao {
    const string TABLE = 'filters_config_templates';

    const string query_by_id         = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND deleted_at IS NULL";
    const string query_by_id_and_uid = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND uid = :uid AND deleted_at IS NULL";
    const string query_by_uid_name   = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND name = :name AND deleted_at IS NULL";
    const string query_paginated     = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid ORDER BY id LIMIT %u OFFSET %u ";
    const string paginated_map_key   = __CLASS__ . "::getAllPaginated";

    /**
     * @var FiltersConfigTemplateDao|null
     */
    private static ?FiltersConfigTemplateDao $instance = null;

    /**
     * @return FiltersConfigTemplateDao
     */
    private static function getInstance(): FiltersConfigTemplateDao {
        if ( !isset( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param string $json
     * @param int    $uid
     *
     * @return FiltersConfigTemplateStruct
     * @throws Exception
     */
    public static function createFromJSON( string $json, int $uid ): FiltersConfigTemplateStruct {
        $templateStruct = new FiltersConfigTemplateStruct();
        $templateStruct->hydrateFromJSON( $json, $uid );

        return self::save( $templateStruct );
    }

    /**
     * @param FiltersConfigTemplateStruct $templateStruct
     * @param string                      $json
     * @param int                         $uid
     *
     * @return FiltersConfigTemplateStruct
     * @throws Exception
     */
    public static function editFromJSON( FiltersConfigTemplateStruct $templateStruct, string $json, int $uid ): FiltersConfigTemplateStruct {
        $templateStruct->hydrateFromJSON( $json, $uid );

        return self::update( $templateStruct );
    }

    /**
     * @param int    $uid
     * @param string $baseRoute
     * @param int    $current
     * @param int    $pagination
     * @param int    $ttl
     *
     * @return array
     * @throws ReflectionException
     */
    public static function getAllPaginated( int $uid, string $baseRoute, int $current = 1, int $pagination = 20, int $ttl = 60 * 60 * 24 ): array {
        $conn = Database::obtain()->getConnection();

        $pager = new Pager( $conn );

        $totals = $pager->count(
                "SELECT count(id) FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid",
                [ 'uid' => $uid ]
        );

        $paginationParameters = new PaginationParameters( static::query_paginated, [ 'uid' => $uid ], ShapelessConcreteStruct::class, $baseRoute, $current, $pagination );
        $paginationParameters->setCache( self::paginated_map_key . ":" . $uid, $ttl );

        $result = $pager->getPagination( $totals, $paginationParameters );

        $models = [];

        /**
         * @var FiltersConfigTemplateStruct $item
         */
        foreach ( $result[ 'items' ] as $item ) {
            $models[] = self::hydrateTemplateStruct( $item->getArrayCopy() );
        }

        $result[ 'items' ] = $models;

        return $result;

    }

    /**
     * @param int $id
     * @param int $ttl
     *
     * @return FiltersConfigTemplateStruct|null
     * @throws ReflectionException
     */
    public static function getById( int $id, int $ttl = 60 ): ?FiltersConfigTemplateStruct {
        $stmt   = self::getInstance()->_getStatementForQuery( self::query_by_id );
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, ShapelessConcreteStruct::class, [
                'id' => $id,
        ] );

        if ( empty( $result ) ) {
            return null;
        }

        return self::hydrateTemplateStruct( (array)$result[ 0 ] );
    }

    /**
     * @param int $id
     * @param int $uid
     * @param int $ttl
     *
     * @return FiltersConfigTemplateStruct|null
     * @throws ReflectionException
     */
    public static function getByIdAndUser( int $id, int $uid, int $ttl = 60 ): ?FiltersConfigTemplateStruct {
        $stmt   = self::getInstance()->_getStatementForQuery( self::query_by_id_and_uid );
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, ShapelessConcreteStruct::class, [
                'id'  => $id,
                'uid' => $uid,
        ] );

        if ( empty( $result ) ) {
            return null;
        }

        return self::hydrateTemplateStruct( (array)$result[ 0 ] );
    }

    /**
     * @param int    $uid
     * @param string $name
     * @param int    $ttl
     *
     * @return FiltersConfigTemplateStruct|null
     * @throws ReflectionException
     */
    public static function getByUidAndName( int $uid, string $name, int $ttl = 60 ): ?FiltersConfigTemplateStruct {
        $stmt   = self::getInstance()->_getStatementForQuery( self::query_by_uid_name );
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, ProjectTemplateStruct::class, [
                'uid'  => $uid,
                'name' => $name,
        ] );

        if ( empty( $result ) ) {
            return null;
        }

        return self::hydrateTemplateStruct( (array)$result[ 0 ] );
    }

    /**
     * @param int $id
     * @param int $uid
     *
     * @return int
     * @throws ReflectionException
     */
    public static function remove( int $id, int $uid ): int {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "UPDATE " . self::TABLE . " SET `name` = :name , `deleted_at` = :now WHERE id = :id AND uid = :uid AND `deleted_at` IS NULL;" );
        $stmt->execute( [
                'id'   => $id,
                'uid'  => $uid,
                'now'  => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
                'name' => 'deleted_' . Utils::randomString()
        ] );

        self::destroyQueryByIdCache( $conn, $id );
        self::destroyQueryByIdAndUserCache( $conn, $id, $uid );
        self::destroyQueryPaginated( $uid );

        ProjectTemplateDao::removeSubTemplateByIdAndUser( $id, $uid, 'filters_template_id' );

        return $stmt->rowCount();
    }

    /**
     * @param PDO $conn
     * @param int $id
     *
     * @throws ReflectionException
     */
    private static function destroyQueryByIdCache( PDO $conn, int $id ) {
        $stmt = $conn->prepare( self::query_by_id );
        self::getInstance()->_destroyObjectCache( $stmt, ShapelessConcreteStruct::class, [ 'id' => $id, ] );
    }

    /**
     * @param PDO $conn
     * @param int $id
     * @param int $uid
     *
     * @throws ReflectionException
     */
    private static function destroyQueryByIdAndUserCache( PDO $conn, int $id, int $uid ) {
        $stmt = $conn->prepare( self::query_by_id_and_uid );
        self::getInstance()->_destroyObjectCache( $stmt, ShapelessConcreteStruct::class, [ 'id' => $id, 'uid' => $uid ] );
    }

    /**
     * @param PDO    $conn
     * @param int    $uid
     * @param string $name
     *
     * @throws ReflectionException
     */
    private static function destroyQueryByUidAndNameCache( PDO $conn, int $uid, string $name ) {
        $stmt = $conn->prepare( self::query_by_uid_name );
        self::getInstance()->_destroyObjectCache( $stmt, ProjectTemplateStruct::class, [ 'uid' => $uid, 'name' => $name, ] );
    }

    /**
     * @param int $uid
     *
     * @throws ReflectionException
     */
    private static function destroyQueryPaginated( int $uid ) {
        self::getInstance()->_deleteCacheByKey( self::paginated_map_key . ":" . $uid, false );
    }

    /**
     * @param array $data
     *
     * @return FiltersConfigTemplateStruct|null
     */
    private static function hydrateTemplateStruct( array $data ): ?FiltersConfigTemplateStruct {
        if (
                !isset( $data[ 'id' ] ) or
                !isset( $data[ 'uid' ] ) or
                !isset( $data[ 'name' ] )
        ) {
            return null;
        }

        $struct              = new FiltersConfigTemplateStruct();
        $struct->id          = $data[ 'id' ];
        $struct->uid         = $data[ 'uid' ];
        $struct->name        = $data[ 'name' ];
        $struct->created_at  = $data[ 'created_at' ];
        $struct->modified_at = $data[ 'modified_at' ];
        $struct->deleted_at  = $data[ 'deleted_at' ];

        $struct->hydrateAllDto( $data );

        return $struct;
    }

    /**
     * @param FiltersConfigTemplateStruct $templateStruct
     *
     * @return FiltersConfigTemplateStruct
     * @throws Exception
     */
    public static function save( FiltersConfigTemplateStruct $templateStruct ): FiltersConfigTemplateStruct {
        $sql = "INSERT INTO " . self::TABLE .
                " ( `uid`, `name`, `json`, `xml`, `yaml`, `ms_excel`, `ms_word`, `ms_powerpoint`, `dita`, `created_at` ) " .
                " VALUES " .
                " ( :uid, :name, :json, :xml, :yaml, :ms_excel, :ms_word, :ms_powerpoint, :dita, :now ); ";

        $now = ( new DateTime() )->format( 'Y-m-d H:i:s' );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                "uid"           => $templateStruct->uid,
                "name"          => $templateStruct->name,
                "json"          => json_encode( $templateStruct->getJson() ),
                "xml"           => json_encode( $templateStruct->getXml() ),
                "yaml"          => json_encode( $templateStruct->getYaml() ),
                "ms_excel"      => json_encode( $templateStruct->getMsExcel() ),
                "ms_word"       => json_encode( $templateStruct->getMsWord() ),
                "ms_powerpoint" => json_encode( $templateStruct->getMsPowerpoint() ),
                "dita"          => json_encode( $templateStruct->getDita() ),
                'now'           => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        $templateStruct->id         = $conn->lastInsertId();
        $templateStruct->created_at = $now;

        self::destroyQueryByIdCache( $conn, $templateStruct->id );
        self::destroyQueryByIdAndUserCache( $conn, $templateStruct->id, $templateStruct->uid );
        self::destroyQueryByUidAndNameCache( $conn, $templateStruct->uid, $templateStruct->name );
        self::destroyQueryPaginated( $templateStruct->uid );

        return $templateStruct;
    }

    /**
     * @param FiltersConfigTemplateStruct $templateStruct
     *
     * @return FiltersConfigTemplateStruct
     * @throws Exception
     */
    public static function update( FiltersConfigTemplateStruct $templateStruct ): FiltersConfigTemplateStruct {
        $sql = "UPDATE " . self::TABLE . " SET 
            `uid` = :uid, 
            `name` = :name,
            `json` = :json, 
            `xml` = :xml, 
            `yaml` = :yaml, 
            `ms_excel` = :ms_excel, 
            `ms_word` = :ms_word, 
            `ms_powerpoint` = :ms_powerpoint, 
            `dita` = :dita, 
            `modified_at` = :now 
         WHERE id = :id;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                "id"            => $templateStruct->id,
                "uid"           => $templateStruct->uid,
                "name"          => $templateStruct->name,
                "json"          => json_encode( $templateStruct->getJson() ),
                "xml"           => json_encode( $templateStruct->getXml() ),
                "yaml"          => json_encode( $templateStruct->getYaml() ),
                "ms_excel"      => json_encode( $templateStruct->getMsExcel() ),
                "ms_word"       => json_encode( $templateStruct->getMsWord() ),
                "ms_powerpoint" => json_encode( $templateStruct->getMsPowerpoint() ),
                "dita"          => json_encode( $templateStruct->getDita() ),
                'now'           => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        self::destroyQueryByIdCache( $conn, $templateStruct->id );
        self::destroyQueryByUidAndNameCache( $conn, $templateStruct->uid, $templateStruct->name );
        self::destroyQueryByIdAndUserCache( $conn, $templateStruct->id, $templateStruct->uid );
        self::destroyQueryPaginated( $templateStruct->uid );

        return $templateStruct;
    }
}