<?php

namespace Xliff;

use DataAccess\ShapelessConcreteStruct;
use DataAccess_AbstractDao;
use Database;
use DateTime;
use Exception;
use Pagination\Pager;
use Pagination\PaginationParameters;
use PDO;
use Projects\ProjectTemplateDao;
use ReflectionException;
use Utils;

class XliffConfigTemplateDao extends DataAccess_AbstractDao {

    const TABLE = 'xliff_config_templates';

    const query_by_id         = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND deleted_at IS NULL";
    const query_by_id_and_uid = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND uid = :uid AND deleted_at IS NULL";
    const query_by_uid        = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND deleted_at IS NULL";
    const query_paginated     = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid ORDER BY id LIMIT %u OFFSET %u ";
    const paginated_map_key   = __CLASS__ . "::getAllPaginated";

    /**
     * @var XliffConfigTemplateDao|null
     */
    private static ?XliffConfigTemplateDao $instance = null;

    /**
     * @return XliffConfigTemplateDao
     */
    private static function getInstance(): XliffConfigTemplateDao {
        if ( !isset( static::$instance ) ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @param int $uid
     *
     * @return XliffConfigTemplateStruct
     */
    public static function getDefaultTemplate( int $uid ): XliffConfigTemplateStruct {
        $default              = new XliffConfigTemplateStruct();
        $default->id          = 0;
        $default->uid         = $uid;
        $default->name        = "Matecat original settings";
        $default->created_at  = date( "Y-m-d H:i:s" );
        $default->modified_at = date( "Y-m-d H:i:s" );

        return $default;
    }

    /**
     * @param string $json
     * @param int    $uid
     *
     * @return XliffConfigTemplateStruct
     * @throws Exception
     */
    public static function createFromJSON( string $json, int $uid ): XliffConfigTemplateStruct {
        $templateStruct = new XliffConfigTemplateStruct();
        $templateStruct->hydrateFromJSON( $json, $uid );

        return self::save( $templateStruct );
    }

    /**
     * @param XliffConfigTemplateStruct $templateStruct
     * @param string                    $json
     * @param int                       $uid
     *
     * @return XliffConfigTemplateStruct
     * @throws Exception
     */
    public static function editFromJSON( XliffConfigTemplateStruct $templateStruct, string $json, int $uid ): XliffConfigTemplateStruct {
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

        $pdo = Database::obtain()->getConnection();

        $pager = new Pager( $pdo );

        $totals = $pager->count(
                "SELECT count(id) FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid",
                [ 'uid' => $uid ]
        );

        $paginationParameters = new PaginationParameters( static::query_paginated, [ 'uid' => $uid ], ShapelessConcreteStruct::class, $baseRoute, $current, $pagination );
        $paginationParameters->setCache( self::paginated_map_key . ":" . $uid, $ttl );

        $result = $pager->getPagination( $totals, $paginationParameters );

        $models = [];
//        $models[] = self::getDefaultTemplate( $uid );

        foreach ( $result[ 'items' ] as $item ) {
            $models[] = self::hydrateTemplateStruct( $item->getArrayCopy() );
        }

        $result[ 'items' ] = $models;

        return $result;

    }

    /**
     * WARNING Use this method only when no user authentication is needed or when it is already performed
     *
     * @param     $id
     * @param int $ttl
     *
     * @return XliffConfigTemplateStruct|null
     * @throws Exception
     */
    public static function getById( $id, int $ttl = 60 ): ?XliffConfigTemplateStruct {
        $stmt   = self::getInstance()->_getStatementForQuery( self::query_by_id );
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
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
     * @return XliffConfigTemplateStruct|null
     * @throws Exception
     */
    public static function getByIdAndUser( int $id, int $uid, int $ttl = 60 ): ?XliffConfigTemplateStruct {
        $stmt   = self::getInstance()->_getStatementForQuery( self::query_by_id_and_uid );
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id'  => $id,
                'uid' => $uid,
        ] );

        if ( empty( $result ) ) {
            return null;
        }

        return self::hydrateTemplateStruct( (array)$result[ 0 ] );
    }

    /**
     * @param int $uid
     * @param int $ttl
     *
     * @return XliffConfigTemplateStruct[]
     * @throws Exception
     */
    public static function getByUid( int $uid, int $ttl = 60 ): array {
        $stmt   = self::getInstance()->_getStatementForQuery( self::query_by_uid );
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'uid' => $uid,
        ] );

        if ( empty( $result ) ) {
            return [];
        }

        $res = [];

        foreach ( $result as $r ) {
            $res[] = self::hydrateTemplateStruct( (array)$r );
        }

        return $res;
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
        self::destroyQueryByIdAndUidCache( $conn, $id, $uid );
        self::destroyQueryByUidCache( $conn, $uid );
        self::destroyQueryPaginated( $uid );

        ProjectTemplateDao::removeSubTemplateByIdAndUser( $id, $uid, 'xliff_config_template_id' );

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
     * @param int $uid
     *
     * @throws ReflectionException
     */
    private static function destroyQueryByUidCache( PDO $conn, int $uid ) {
        $stmt = $conn->prepare( self::query_by_uid );
        self::getInstance()->_destroyObjectCache( $stmt, ShapelessConcreteStruct::class, [ 'uid' => $uid ] );
    }

    /**
     * @param PDO $conn
     * @param int $id
     * @param int $uid
     *
     * @throws ReflectionException
     */
    private static function destroyQueryByIdAndUidCache( PDO $conn, int $id, int $uid ) {
        $stmt = $conn->prepare( self::query_by_id_and_uid );
        self::getInstance()->_destroyObjectCache( $stmt, ShapelessConcreteStruct::class, [ 'id' => $id, 'uid' => $uid ] );
    }

    /**
     * @param int $uid
     *
     * @throws ReflectionException
     */
    private static function destroyQueryPaginated( int $uid ) {
        self::getInstance()->_destroyCache( self::paginated_map_key . ":" . $uid, false );
    }


    /**
     * @param array $data
     *
     * @return XliffConfigTemplateStruct|null
     */
    private static function hydrateTemplateStruct( array $data ): ?XliffConfigTemplateStruct {
        if (
                !isset( $data[ 'id' ] ) or
                !isset( $data[ 'uid' ] ) or
                !isset( $data[ 'name' ] ) or
                !isset( $data[ 'rules' ] )
        ) {
            return null;
        }

        $struct       = new XliffConfigTemplateStruct();
        $struct->id   = $data[ 'id' ];
        $struct->uid  = $data[ 'uid' ];
        $struct->name = $data[ 'name' ];

        $struct->created_at  = $data[ 'created_at' ];
        $struct->modified_at = $data[ 'modified_at' ];
        $struct->deleted_at  = $data[ 'deleted_at' ];
        $struct->hydrateRulesFromJson( $data[ 'rules' ] );

        return $struct;
    }

    /**
     * @param XliffConfigTemplateStruct $templateStruct
     *
     * @return XliffConfigTemplateStruct
     * @throws Exception
     */
    public static function save( XliffConfigTemplateStruct $templateStruct ): XliffConfigTemplateStruct {
        $sql = "INSERT INTO " . self::TABLE .
                " ( `uid`, `name`, `rules`, `created_at`, `modified_at` ) " .
                " VALUES " .
                " ( :uid, :name, :rules, :now, :now ); ";

        $now = ( new DateTime() )->format( 'Y-m-d H:i:s' );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                "uid"   => $templateStruct->uid,
                "name"  => $templateStruct->name,
                "rules" => $templateStruct->rules,
                'now'   => $now,
        ] );

        $templateStruct->id          = $conn->lastInsertId();
        $templateStruct->created_at  = $now;
        $templateStruct->modified_at = $now;

        self::destroyQueryByIdCache( $conn, $templateStruct->id );
        self::destroyQueryByIdAndUidCache( $conn, $templateStruct->id, $templateStruct->uid );
        self::destroyQueryByUidCache( $conn, $templateStruct->uid );
        self::destroyQueryPaginated( $templateStruct->uid );

        return $templateStruct;
    }

    /**
     * @param XliffConfigTemplateStruct $templateStruct
     *
     * @return XliffConfigTemplateStruct
     * @throws Exception
     */
    public static function update( XliffConfigTemplateStruct $templateStruct ): XliffConfigTemplateStruct {
        $sql = "UPDATE " . self::TABLE . " SET 
            `name` = :name,
            `rules` = :rules,
            `modified_at` = :now 
         WHERE id = :id;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                "id"    => $templateStruct->id,
                "name"  => $templateStruct->name,
                "rules" => $templateStruct->rules,
                'now'   => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        self::destroyQueryByIdCache( $conn, $templateStruct->id );
        self::destroyQueryByIdAndUidCache( $conn, $templateStruct->id, $templateStruct->uid );
        self::destroyQueryByUidCache( $conn, $templateStruct->uid );
        self::destroyQueryPaginated( $templateStruct->uid );

        return $templateStruct;
    }
}