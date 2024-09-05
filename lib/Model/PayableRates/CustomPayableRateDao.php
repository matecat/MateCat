<?php

namespace PayableRates;

use Analysis_PayableRates;
use DataAccess_AbstractDao;
use Database;
use Date\DateTimeUtil;
use DateTime;
use Exception;
use Pagination\Pager;
use Pagination\PaginationParameters;
use PDO;
use ReflectionException;
use Swaggest\JsonSchema\InvalidValue;
use Utils;

class CustomPayableRateDao extends DataAccess_AbstractDao {
    const TABLE           = 'payable_rate_templates';
    const TABLE_JOB_PIVOT = 'job_custom_payable_rates';

    const query_by_uid_name    = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid AND name = :name";
    const query_by_id_and_user = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND id = :id AND uid = :uid";
    const query_by_id          = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND id = :id";
    const query_paginated      = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid LIMIT %u OFFSET %u ";
    const paginated_map_key    = __CLASS__ . "::getAllPaginated";

    /**
     * @var null
     */
    private static $instance = null;

    /**
     * @return CustomPayableRateDao|null
     */
    private static function getInstance() {
        if ( !isset( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param int $uid
     *
     * @return array
     * @throws Exception
     */
    public static function getDefaultTemplate( int $uid ): array {
        return [
                'id'                         => 0,
                'uid'                        => $uid,
                'payable_rate_template_name' => 'Default',
                'version'                    => 1,
                'breakdowns'                 => [
                        'default' => Analysis_PayableRates::$DEFAULT_PAYABLE_RATES
                ],
                'createdAt'                  => DateTimeUtil::formatIsoDate( date( "Y-m-d H:i:s" ) ),
                'modifiedAt'                 => DateTimeUtil::formatIsoDate( date( "Y-m-d H:i:s" ) ),
        ];
    }

    /**
     * @param int $uid
     * @param int $current
     * @param int $pagination
     * @param int $ttl
     *
     * @return array
     * @throws ReflectionException
     */
    public static function getAllPaginated( int $uid, string $baseRoute, int $current = 1, int $pagination = 20, int $ttl = 60 * 60 * 24 ): array {

        $conn = Database::obtain()->getConnection();

        $pager  = new Pager( $conn );
        $totals = $pager->count(
                "SELECT count(id) FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid",
                [ 'uid' => $uid ]
        );

        $paginationParameters = new PaginationParameters( self::query_paginated, [ 'uid' => $uid ], CustomPayableRateStruct::class, $baseRoute, $current, $pagination );
        $paginationParameters->setCache( self::paginated_map_key . ":" . $uid, $ttl );

        return $pager->getPagination( $totals, $paginationParameters );
    }

    /**
     * @param int $id
     * @param int $ttl
     *
     * @return CustomPayableRateStruct
     * @throws ReflectionException
     */
    public static function getById( int $id, int $ttl = 60 ): ?CustomPayableRateStruct {
        $stmt   = self::getInstance()->_getStatementForQuery( self::query_by_id );
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new CustomPayableRateStruct(), [
                'id' => $id,
        ] );

        /**
         * @var $result CustomPayableRateStruct[]
         */
        return $result[ 0 ] ?? null;
    }

    /**
     * @param int $id
     * @param int $uid
     * @param int $ttl
     *
     * @return CustomPayableRateStruct
     * @throws ReflectionException
     */
    public static function getByIdAndUser( int $id, int $uid, int $ttl = 60 ): ?CustomPayableRateStruct {
        $stmt   = self::getInstance()->_getStatementForQuery( self::query_by_id_and_user );
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new CustomPayableRateStruct(), [
                'id'  => $id,
                'uid' => $uid,
        ] );

        /**
         * @var $result CustomPayableRateStruct[]
         */
        return $result[ 0 ] ?? null;
    }

    /**
     * @param int    $uid
     * @param string $name
     * @param int    $ttl
     *
     * @return CustomPayableRateStruct
     * @throws ReflectionException
     */
    public static function getByUidAndName( int $uid, string $name, int $ttl = 60 ) {
        $stmt   = self::getInstance()->_getStatementForQuery( self::query_by_uid_name );
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new CustomPayableRateStruct(), [
                'uid'  => $uid,
                'name' => $name,
        ] );

        /**
         * @var $result CustomPayableRateStruct[]
         */
        return $result[ 0 ] ?? null;
    }

    /**
     * @param CustomPayableRateStruct $customPayableRateStruct
     *
     * @return CustomPayableRateStruct
     * @throws Exception
     */
    public static function save( CustomPayableRateStruct $customPayableRateStruct ): CustomPayableRateStruct {

        $sql = "INSERT INTO " . self::TABLE .
                " ( `uid`, `version`, `name`, `breakdowns`, `created_at`, `modified_at` ) " .
                " VALUES " .
                " ( :uid, :version, :name, :breakdowns, :now, :now ); ";

        $now = ( new DateTime() )->format( 'Y-m-d H:i:s' );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'uid'        => $customPayableRateStruct->uid,
                'version'    => 1,
                'name'       => $customPayableRateStruct->name,
                'breakdowns' => $customPayableRateStruct->breakdownsToJson(),
                'now'        => $now,
        ] );

        $customPayableRateStruct->id          = $conn->lastInsertId();
        $customPayableRateStruct->version     = 1;
        $customPayableRateStruct->created_at  = $now;
        $customPayableRateStruct->modified_at = $now;

        self::destroyQueryPaginated( $customPayableRateStruct->uid );

        return $customPayableRateStruct;
    }

    /**
     * @param CustomPayableRateStruct $customPayableRateStruct
     *
     * @return CustomPayableRateStruct
     * @throws Exception
     */
    public static function update( CustomPayableRateStruct $customPayableRateStruct ): CustomPayableRateStruct {

        $sql = "UPDATE " . self::TABLE . " SET `uid` = :uid, `version` = :version, `name` = :name, `breakdowns` = :breakdowns, `modified_at` = :now WHERE id = :id ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id'         => $customPayableRateStruct->id,
                'uid'        => $customPayableRateStruct->uid,
                'version'    => ( $customPayableRateStruct->version + 1 ),
                'name'       => $customPayableRateStruct->name,
                'breakdowns' => $customPayableRateStruct->breakdownsToJson(),
                'now'        => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        self::destroyQueryByIdCache( $conn, $customPayableRateStruct->id );
        self::destroyQueryByUidAndNameCache( $conn, $customPayableRateStruct->uid, $customPayableRateStruct->name );
        self::destroyQueryByIdAndUserCache( $conn, $customPayableRateStruct->id, $customPayableRateStruct->uid );
        self::destroyQueryPaginated( $customPayableRateStruct->uid );

        return $customPayableRateStruct;

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
        $stmt = $conn->prepare( "UPDATE " . self::TABLE . " SET `name` = :name, `deleted_at` = :now WHERE id = :id AND uid = :uid AND `deleted_at` IS NULL;" );
        $stmt->execute( [
                'id'   => $id,
                'uid'  => $uid,
                'name' => 'deleted_' . Utils::randomString(),
                'now'  => ( new DateTime() )->format( 'Y-m-d H:i:s' ),
        ] );

        self::destroyQueryByIdCache( $conn, $id );
        self::destroyQueryByIdAndUserCache( $conn, $id, $uid );
        self::destroyQueryPaginated( $uid );

        $queryAffected = $stmt->rowCount();

        $stmt = $conn->prepare( "UPDATE project_templates SET payable_rate_template_id = :zero WHERE uid = :uid AND payable_rate_template_id = :id " );
        $stmt->execute( [
                'zero' => 0,
                'id'   => $id,
                'uid'  => $uid,
        ] );

        return $queryAffected;
    }

    /**
     * validate a json against schema and then
     * create a Payable Rate model template from it
     *
     * @param string   $json
     * @param int|null $uid
     *
     * @return CustomPayableRateStruct
     * @throws Exception
     */
    public static function createFromJSON( string $json, int $uid = null ): CustomPayableRateStruct {

        $customPayableRateStruct = new CustomPayableRateStruct();
        $customPayableRateStruct->hydrateFromJSON( $json );

        if ( $uid ) {
            $customPayableRateStruct->uid = $uid;
        }

        return self::save( $customPayableRateStruct );
    }

    /**
     * @param CustomPayableRateStruct $customPayableRateStruct
     * @param string                  $json
     *
     * @return CustomPayableRateStruct
     * @throws InvalidValue
     * @throws Exception
     */
    public static function editFromJSON( CustomPayableRateStruct $customPayableRateStruct, string $json ): CustomPayableRateStruct {
        $customPayableRateStruct->hydrateFromJSON( $json );

        return self::update( $customPayableRateStruct );
    }

    /**
     * @param PDO $conn
     * @param int $id
     *
     * @throws ReflectionException
     */
    private static function destroyQueryByIdCache( PDO $conn, int $id ) {
        $stmt = $conn->prepare( self::query_by_id );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'id' => $id, ] );
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
        self::getInstance()->_destroyObjectCache( $stmt, [ 'uid' => $uid, 'name' => $name, ] );
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
        $stmt = $conn->prepare( self::query_by_id_and_user );
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
     * @param int    $modelId
     * @param int    $idJob
     * @param int    $version
     * @param string $name
     *
     * @return string
     */
    public static function assocModelToJob( int $modelId, int $idJob, int $version, string $name ): string {
        $sql = "INSERT INTO " . self::TABLE_JOB_PIVOT .
                " ( `id_job`, `custom_payable_rate_model_id`, `custom_payable_rate_model_version`, `custom_payable_rate_model_name` ) " .
                " VALUES " .
                " ( :id_job, :model_id, :version, :model_name ); ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'     => $idJob,
                'model_id'   => $modelId,
                'version'    => $version,
                'model_name' => $name,
        ] );

        return $conn->lastInsertId();
    }
}
