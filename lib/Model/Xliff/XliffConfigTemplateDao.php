<?php

namespace Xliff;

use DataAccess\ShapelessConcreteStruct;
use DataAccess_AbstractDao;
use Database;
use DateTime;
use Exception;
use PDO;
use Projects\ProjectTemplateStruct;
use Utils;

class XliffConfigTemplateDao extends DataAccess_AbstractDao {
    const TABLE = 'xliff_config_templates';

    const query_by_id         = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND deleted_at IS NULL";
    const query_by_id_and_uid = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND uid = :uid AND deleted_at IS NULL";
    const query_by_uid        = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND deleted_at IS NULL";
    const query_by_uid_name   = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND name = :name AND deleted_at IS NULL";

    /**
     * @var XliffConfigTemplateDao|null
     */
    private static ?XliffConfigTemplateDao $instance = null;

    /**
     * @return XliffConfigTemplateDao
     */
    private static function getInstance(): XliffConfigTemplateDao {
        if ( !isset( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param $uid
     *
     * @return XliffConfigTemplateStruct
     */
    public static function getDefaultTemplate( $uid ): XliffConfigTemplateStruct {
        $default              = new XliffConfigTemplateStruct();
        $default->id          = 0;
        $default->uid         = $uid;
        $default->name        = "default";
        $default->created_at  = date( "Y-m-d H:i:s" );
        $default->modified_at = date( "Y-m-d H:i:s" );

        return $default;
    }

    /**
     * @param $json
     * @param $uid
     *
     * @return XliffConfigTemplateStruct
     * @throws Exception
     */
    public static function createFromJSON( $json, $uid ): XliffConfigTemplateStruct {
        $templateStruct = new XliffConfigTemplateStruct();
        $templateStruct->hydrateFromJSON( $json, $uid );

        return self::save( $templateStruct );
    }

    /**
     * @param XliffConfigTemplateStruct $templateStruct
     * @param                           $json
     * @param                           $uid
     *
     * @return XliffConfigTemplateStruct
     * @throws Exception
     */
    public static function editFromJSON( XliffConfigTemplateStruct $templateStruct, $json, $uid ): XliffConfigTemplateStruct {
        $templateStruct->hydrateFromJSON( $json, $uid );

        return self::update( $templateStruct );
    }

    /**
     * @param int $uid
     * @param int $current
     * @param int $pagination
     *
     * @return array
     * @throws Exception
     */
    public static function getAllPaginated( int $uid, int $current = 1, int $pagination = 20 ): array {
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( "SELECT count(id) as count FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid" );
        $stmt->execute( [
                'uid' => $uid
        ] );

        $count  = $stmt->fetch( PDO::FETCH_ASSOC );
        $count  = $count[ 'count' ];
        $count  = $count + 1;
        $pages  = ceil( $count / $pagination );
        $prev   = ( $current !== 1 ) ? "/api/v3/xliff-config-template?page=" . ( $current - 1 ) : null;
        $next   = ( $current < $pages ) ? "/api/v3/xliff-config-template?page=" . ( $current + 1 ) : null;
        $offset = ( $current - 1 ) * $pagination;

        $models   = [];
        $models[] = self::getDefaultTemplate( $uid );

        $stmt = $conn->prepare( "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid ORDER BY id LIMIT $pagination OFFSET $offset " );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute( [
                'uid' => $uid
        ] );

        foreach ( $stmt->fetchAll() as $item ) {
            $model = self::hydrateTemplateStruct( $item );

            if ( $model !== null ) {
                $models[] = $model;
            }
        }

        return [
                'current_page' => $current,
                'per_page'     => $pagination,
                'last_page'    => $pages,
                'total_count'  => (int)$count,
                'prev'         => $prev,
                'next'         => $next,
                'items'        => $models,
        ];
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
        $stmt   = self::getInstance()->_getStatementForCache( self::query_by_id );
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
        $stmt   = self::getInstance()->_getStatementForCache( self::query_by_id_and_uid );
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
        $stmt   = self::getInstance()->_getStatementForCache( self::query_by_uid );
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
     * @param int    $uid
     * @param string $name
     * @param int    $ttl
     *
     * @return XliffConfigTemplateStruct|null
     * @throws Exception
     */
    public static function getByUidAndName( int $uid, string $name, int $ttl = 60 ) {
        $stmt   = self::getInstance()->_getStatementForCache( self::query_by_uid_name );
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new ProjectTemplateStruct(), [
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
     */
    public static function remove( int $id, int $uid ): int {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "UPDATE " . self::TABLE . " SET `name` = :name , `deleted_at` = :now WHERE id = :id AND uid = :uid;" );
        $stmt->execute( [
                'id'   => $id,
                'uid'  => $uid,
                'now'  => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
                'name' => 'deleted_' . Utils::randomString()
        ] );

        self::destroyQueryByIdCache( $conn, $id );
        self::destroyQueryByUidCache( $conn, $uid );

        return $stmt->rowCount();
    }

    /**
     * @param PDO $conn
     * @param int $id
     */
    private static function destroyQueryByIdCache( PDO $conn, int $id ) {
        $stmt = $conn->prepare( self::query_by_id );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'id' => $id, ] );
    }

    /**
     * @param PDO $conn
     * @param int $uid
     */
    private static function destroyQueryByUidCache( PDO $conn, int $uid ) {
        $stmt = $conn->prepare( self::query_by_uid );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'uid' => $uid ] );
    }

    /**
     * @param PDO    $conn
     * @param int    $uid
     * @param string $name
     */
    private static function destroyQueryByUidAndNameCache( PDO $conn, int $uid, string $name ) {
        $stmt = $conn->prepare( self::query_by_uid_name );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'uid' => $uid, 'name' => $name, ] );
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

        $struct = new XliffConfigTemplateStruct();

        return $struct->hydrateFromJSON( json_encode( $data ) );
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
        self::destroyQueryByUidCache( $conn, $templateStruct->uid );
        self::destroyQueryByUidAndNameCache( $conn, $templateStruct->uid, $templateStruct->name );

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
            `uid` = :uid, 
            `name` = :name,
            `rules` = :rules,
            `modified_at` = :now 
         WHERE id = :id;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                "id"    => $templateStruct->id,
                "uid"   => $templateStruct->uid,
                "name"  => $templateStruct->name,
                "rules" => $templateStruct->rules,
                'now'   => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        self::destroyQueryByIdCache( $conn, $templateStruct->id );
        self::destroyQueryByUidCache( $conn, $templateStruct->uid );
        self::destroyQueryByUidAndNameCache( $conn, $templateStruct->uid, $templateStruct->name );

        return $templateStruct;
    }
}