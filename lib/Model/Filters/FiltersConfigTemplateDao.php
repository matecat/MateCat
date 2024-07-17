<?php

namespace Filters;

use CatUtils;
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
use INIT;
use PDO;
use Projects\ProjectTemplateStruct;
use Swaggest\JsonSchema\InvalidValue;
use Validator\JSONValidator;
use Validator\JSONValidatorObject;

class FiltersConfigTemplateDao extends DataAccess_AbstractDao
{
    const TABLE = 'filters_config_templates';

    const query_by_id       = "SELECT * FROM " . self::TABLE . " WHERE id = :id AND deleted_at IS NULL";
    const query_by_uid      = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND deleted_at IS NULL";
    const query_by_uid_name = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND name = :name AND deleted_at IS NULL";

    /**
     * @var null
     */
    private static $instance = null;

    /**
     * @return FiltersXliffConfigTemplateDao|null
     */
    private static function getInstance() {
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
    public static function getDefaultTemplate( $uid ) {
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
     * @return FiltersXliffConfigTemplateStruct
     * @throws Exception
     */
    public static function createFromJSON( $json, $uid ) {
        self::validateJSON( $json );

        $templateStruct = new FiltersConfigTemplateStruct();
        $templateStruct->hydrateFromJSON( $json, $uid );

        // check name
        $templateStruct = self::findUniqueName( $templateStruct, $uid );

        return self::save( $templateStruct );
    }

    /**
     * @param FiltersConfigTemplateStruct $templateStruct
     * @param $json
     * @param $uid
     *
     * @return FiltersConfigTemplateStruct
     * @throws Exception
     */
    public static function editFromJSON(FiltersConfigTemplateStruct $templateStruct, $json, $uid ) {
        self::validateJSON( $json );
        $templateStruct->hydrateFromJSON( $json, $uid );

        $saved = self::getByUid( $templateStruct->uid );

        foreach ( $saved as $savedElement ) {
            if (
                    $savedElement->id !== $templateStruct->id and
                    $savedElement->name === $templateStruct->name
            ) {
                $templateStruct = self::findUniqueName( $templateStruct, $uid );
            }
        }

        return self::update( $templateStruct );
    }

    /**
     * @param $json
     *
     * @throws InvalidValue
     * @throws Exception
     */
    private static function validateJSON( $json ) {
        $validatorObject       = new JSONValidatorObject();
        $validatorObject->json = $json;
        $jsonSchema            = file_get_contents( INIT::$ROOT . '/inc/validation/schema/filters_extraction_parameters.json' );
        $validator             = new JSONValidator( $jsonSchema );
        $validator->validate( $validatorObject );

        if ( !$validator->isValid() ) {
            throw $validator->getExceptions()[ 0 ]->error;
        }
    }

    /**
     * @param FiltersConfigTemplateStruct $projectTemplateStruct
     * @param                                  $uid
     *
     * @return FiltersConfigTemplateStruct
     * @throws Exception
     */
    private static function findUniqueName(FiltersConfigTemplateStruct $projectTemplateStruct, $uid ) {
        $check = FiltersConfigTemplateDao::getByUidAndName( $uid, $projectTemplateStruct->name, 0 );

        if ( $check === null ) {
            return $projectTemplateStruct;
        }

        $projectTemplateStruct->name = CatUtils::getUniqueName( $projectTemplateStruct->name );

        return self::findUniqueName( $projectTemplateStruct, $uid );
    }

    /**
     * @param     $uid
     * @param int $current
     * @param int $pagination
     *
     * @return array
     * @throws Exception
     */
    public static function getAllPaginated( $uid, $current = 1, $pagination = 20 ) {
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
     * @param     $id
     * @param int $ttl
     *
     * @return FiltersXliffConfigTemplateStruct|null
     * @throws Exception
     */
    public static function getById( $id, $ttl = 60 ) {
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
     * @param     $uid
     * @param int $ttl
     *
     * @return FiltersXliffConfigTemplateStruct[]
     * @throws Exception
     */
    public static function getByUid( $uid, $ttl = 60 ) {
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
     * @param     $uid
     * @param     $name
     * @param int $ttl
     *
     * @return FiltersXliffConfigTemplateStruct|null
     * @throws Exception
     */
    public static function getByUidAndName( $uid, $name, $ttl = 60 ) {
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
     * @param $id
     * @param $uid
     *
     * @return int
     * @throws Exception
     */
    public static function remove( $id, $uid ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "UPDATE " . self::TABLE . " SET `deleted_at` = :now WHERE id = :id " );
        $stmt->execute( [
                'id'  => $id,
                'now' => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        self::destroyQueryByIdCache( $conn, $id );
        self::destroyQueryByUidCache( $conn, $uid );

        return $stmt->rowCount();
    }

    /**
     * @param PDO    $conn
     * @param string $id
     */
    private static function destroyQueryByIdCache( PDO $conn, $id ) {
        $stmt = $conn->prepare( self::query_by_id );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'id' => $id, ] );
    }

    /**
     * @param PDO $conn
     * @param     $uid
     */
    private static function destroyQueryByUidCache( PDO $conn, $uid ) {
        $stmt = $conn->prepare( self::query_by_uid );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'uid' => $uid ] );
    }

    /**
     * @param PDO    $conn
     * @param string $uid
     * @param string $name
     */
    private static function destroyQueryByUidAndNameCache( PDO $conn, $uid, $name ) {
        $stmt = $conn->prepare( self::query_by_uid_name );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'uid' => $uid, 'name' => $name, ] );
    }

    /**
     * @param $data
     *
     * @return FiltersConfigTemplateStruct|null
     * @throws Exception
     */
    private static function hydrateTemplateStruct( $data ) {
        if (
                !isset( $data[ 'id' ] ) or
                !isset( $data[ 'uid' ] ) or
                !isset( $data[ 'name' ] ) or
                !isset( $data[ 'xliff' ] ) or
                !isset( $data[ 'filters' ] )
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
    public static function save(FiltersConfigTemplateStruct $templateStruct ) {
        $sql = "INSERT INTO " . self::TABLE .
                " ( `uid`, `name`, `rules`, `created_at`, `modified_at` ) " .
                " VALUES " .
                " ( :uid, :name, :rules, :now, :now ); ";

        $now = ( new DateTime() )->format( 'Y-m-d H:i:s' );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                "uid"     => $templateStruct->uid,
                "name"    => $templateStruct->name,
                "rules"   => $templateStruct->getRulesAsString(),
                'now'     => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
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
    public static function update(FiltersConfigTemplateStruct $templateStruct ) {
        $sql = "UPDATE " . self::TABLE . " SET 
            `uid` = :uid, 
            `name` = :name,
            `rules` = :rules, 
            `modified_at` = :now 
         WHERE id = :id;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                "id"      => $templateStruct->id,
                "uid"     => $templateStruct->uid,
                "name"    => $templateStruct->name,
                "rules"   => $templateStruct->getRulesAsString(),
                'now'     => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        self::destroyQueryByIdCache( $conn, $templateStruct->id );
        self::destroyQueryByUidCache( $conn, $templateStruct->uid );
        self::destroyQueryByUidAndNameCache( $conn, $templateStruct->uid, $templateStruct->name );

        return $templateStruct;
    }
}