<?php

namespace Filters;

use DataAccess\ShapelessConcreteStruct;
use DataAccess_AbstractDao;
use Database;
use DateTime;
use Exception;
use Filters\DTO\Json;
use Filters\DTO\MSExcel;
use Filters\DTO\MSPowerpoint;
use Filters\DTO\MSWord;
use Filters\DTO\Xml;
use Filters\DTO\Yaml;
use PDO;
use Projects\ProjectTemplateStruct;
use Utils;

class FiltersConfigTemplateDao extends DataAccess_AbstractDao {
    const TABLE = 'filters_config_templates';

    const query_by_id         = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND deleted_at IS NULL";
    const query_by_id_and_uid = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND uid = :uid AND deleted_at IS NULL";
    const query_by_uid        = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND deleted_at IS NULL";
    const query_by_uid_name   = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND name = :name AND deleted_at IS NULL";

    /**
     * @var null
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
     * @param $uid
     *
     * @return FiltersConfigTemplateStruct
     */
    public static function getDefaultTemplate( $uid ): FiltersConfigTemplateStruct {
        $default       = new FiltersConfigTemplateStruct();
        $default->id   = 0;
        $default->uid  = $uid;
        $default->name = "default";

        $default->setJson( new Json() );
        $default->setYaml( new Yaml() );
        $default->setXml( new Xml() );
        $default->setMsWord( new MSWord() );
        $default->setMsExcel( new MSExcel() );
        $default->setMsPowerpoint( new MSPowerpoint() );

        $default->created_at  = date( "Y-m-d H:i:s" );
        $default->modified_at = date( "Y-m-d H:i:s" );

        return $default;
    }

    /**
     * @param $json
     * @param $uid
     *
     * @return FiltersConfigTemplateStruct
     * @throws Exception
     */
    public static function createFromJSON( $json, $uid ): FiltersConfigTemplateStruct {
        $templateStruct = new FiltersConfigTemplateStruct();
        $templateStruct->hydrateFromJSON( $json, $uid );

        return self::save( $templateStruct );
    }

    /**
     * @param FiltersConfigTemplateStruct $templateStruct
     * @param                             $json
     * @param                             $uid
     *
     * @return FiltersConfigTemplateStruct
     * @throws Exception
     */
    public static function editFromJSON( FiltersConfigTemplateStruct $templateStruct, $json, $uid ): FiltersConfigTemplateStruct {
        $templateStruct->hydrateFromJSON( $json, $uid );

        return self::update( $templateStruct );
    }

    /**
     * @param int $uid
     * @param int $current
     * @param int $pagination
     *
     * @return array
     */
    public static function getAllPaginated( int $uid, int $current = 1, int $pagination = 20 ) {
        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( "SELECT count(id) as count FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid" );
        $stmt->execute( [
                'uid' => $uid
        ] );

        $count  = $stmt->fetch( PDO::FETCH_ASSOC );
        $count  = $count[ 'count' ];
        $count  = $count + 1;
        $pages  = ceil( $count / $pagination );
        $prev   = ( $current !== 1 ) ? "/api/v3/filters-config-template?page=" . ( $current - 1 ) : null;
        $next   = ( $current < $pages ) ? "/api/v3/filters-config-template?page=" . ( $current + 1 ) : null;
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
     * @param int $id
     * @param int $ttl
     *
     * @return FiltersConfigTemplateStruct|null
     */
    public static function getById( int $id, int $ttl = 60 ): ?FiltersConfigTemplateStruct {
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
     * @return FiltersConfigTemplateStruct|null
     */
    public static function getByIdAndUser( int $id, int $uid, int $ttl = 60 ) {
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
     * @param int    $uid
     * @param string $name
     * @param int    $ttl
     *
     * @return FiltersConfigTemplateStruct|null
     */
    public static function getByUidAndName( int $uid, string $name, int $ttl = 60 ): ?FiltersConfigTemplateStruct {
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

        $struct = new FiltersConfigTemplateStruct();

        return $struct->hydrateFromJSON( json_encode( $data ) );
    }

    /**
     * @param FiltersConfigTemplateStruct $templateStruct
     *
     * @return FiltersConfigTemplateStruct
     * @throws Exception
     */
    public static function save( FiltersConfigTemplateStruct $templateStruct ): FiltersConfigTemplateStruct {
        $sql = "INSERT INTO " . self::TABLE .
                " ( `uid`, `name`, `json`, `xml`, `yaml`, `ms_excel`, `ms_word`, `ms_powerpoint`, `created_at`, `modified_at` ) " .
                " VALUES " .
                " ( :uid, :name, :json, :xml, :yaml, :ms_excel, :ms_word, :ms_powerpoint, :now, :now ); ";

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
                'now'           => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
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
                'now'           => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        self::destroyQueryByIdCache( $conn, $templateStruct->id );
        self::destroyQueryByUidCache( $conn, $templateStruct->uid );
        self::destroyQueryByUidAndNameCache( $conn, $templateStruct->uid, $templateStruct->name );

        return $templateStruct;
    }
}