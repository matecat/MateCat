<?php

namespace PayableRates;

use Analysis_PayableRates;
use DataAccess_AbstractDao;
use Database;
use Date\DateTimeUtil;
use DateTime;
use PDO;

class CustomPayableRateDao extends DataAccess_AbstractDao
{
    const TABLE = 'payable_rate_templates';
    const TABLE_JOB_PIVOT = 'job_custom_payable_rates';

    const query_by_uid_name = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND uid = :uid AND name = :name";
    const query_by_id = "SELECT * FROM " . self::TABLE . " WHERE deleted_at IS NULL AND id = :id";

    /**
     * @var null
     */
    private static $instance = null;

    /**
     * @return CustomPayableRateDao|null
     */
    private static function getInstance(){
        if(!isset(self::$instance)){
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param $uid
     * @return array
     * @throws \Exception
     */
    private static function getDefaultTemplate($uid)
    {
        return [
            'id' => 0,
            'uid' => (int)$uid,
            'payable_rate_template_name' => 'Default',
            'version' => 1,
            'breakdowns' => [
                'default' => Analysis_PayableRates::$DEFAULT_PAYABLE_RATES
            ],
            'createdAt' => DateTimeUtil::formatIsoDate(date("Y-m-d H:i:s")),
            'modifiedAt' => DateTimeUtil::formatIsoDate(date("Y-m-d H:i:s")),
            'deletedAt' => null,
        ];
    }

    /**
     * @param $uid
     * @param int $current
     * @param int $pagination
     * @return array
     * @throws \Exception
     */
    public static function getAllPaginated($uid, $current = 1, $pagination = 20)
    {
        $conn = \Database::obtain()->getConnection();

        $stmt = $conn->prepare( "SELECT count(id) as count FROM ".self::TABLE." WHERE deleted_at IS NULL AND uid = :uid");
        $stmt->execute([
            'uid' => $uid
        ]);

        $count = $stmt->fetch(\PDO::FETCH_ASSOC);
        $pages = ceil($count['count'] / $pagination);
        $prev = ($current !== 1) ? "/api/v3/payable_rate?page=".($current-1) : null;
        $next = ($current < $pages) ? "/api/v3/payable_rate?page=".($current+1) : null;
        $offset = ($current - 1) * $pagination;

        $models = [];
        $models[] = self::getDefaultTemplate($uid);

        $stmt = $conn->prepare( "SELECT id FROM ".self::TABLE." WHERE deleted_at IS NULL AND uid = :uid LIMIT $pagination OFFSET $offset ");
        $stmt->execute([
            'uid' => $uid
        ]);

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $model){
            $models[] = self::getById($model['id']);
        }

        return [
            'current_page' => (int)$current,
            'per_page' => (int)$pagination,
            'last_page' => (int)$pages,
            'prev' => $prev,
            'next' => $next,
            'items' => $models,
        ];
    }

    /**
     * @param $id
     * @param int $ttl
     * @return CustomPayableRateStruct
     */
    public static function getById( $id, $ttl = 60 ) {
        $stmt = self::getInstance()->_getStatementForCache(self::query_by_id);
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new CustomPayableRateStruct(), [
            'id' => $id,
        ] );

        return @$result[0];
    }

    /**
     * @param $uid
     * @param $name
     * @param int $ttl
     * @return CustomPayableRateStruct
     */
    public static function getByUidAndName( $uid, $name, $ttl = 60 ) {
        $stmt = self::getInstance()->_getStatementForCache(self::query_by_uid_name);
        $result = self::getInstance()->setCacheTTL( $ttl )->_fetchObject( $stmt, new CustomPayableRateStruct(), [
            'uid' => $uid,
            'name' => $name,
        ] );

        return @$result[0];
    }

    /**
     * @param CustomPayableRateStruct $customPayableRateStruct
     * @return CustomPayableRateStruct
     * @throws \Exception
     */
    public static function save( CustomPayableRateStruct $customPayableRateStruct ) {

        $sql = "INSERT INTO " . self::TABLE .
            " ( `uid`, `version`, `name`, `breakdowns`, `created_at`, `modified_at` ) " .
            " VALUES " .
            " ( :uid, :version, :name, :breakdowns, :now, :now ); ";

        $now = (new DateTime())->format('Y-m-d H:i:s');

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
            'uid'        => $customPayableRateStruct->uid,
            'version'    => 1,
            'name'       => $customPayableRateStruct->name,
            'breakdowns' => $customPayableRateStruct->breakdownsToJson(),
            'now'        => $now,
        ] );

        $customPayableRateStruct->id = $conn->lastInsertId();
        $customPayableRateStruct->version = 1;
        $customPayableRateStruct->created_at = $now;
        $customPayableRateStruct->modified_at = $now;

        return $customPayableRateStruct;
    }

    /**
     * @param CustomPayableRateStruct $customPayableRateStruct
     * @return CustomPayableRateStruct
     * @throws \Exception
     */
    public static function update( CustomPayableRateStruct $customPayableRateStruct ) {

        $sql = "UPDATE " . self::TABLE . " SET `uid` = :uid, `version` = :version, `name` = :name, `breakdowns` = :breakdowns, `modified_at` = :now WHERE id = :id ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
            'id'         => $customPayableRateStruct->id,
            'uid'        => $customPayableRateStruct->uid,
            'version'    => ($customPayableRateStruct->version + 1),
            'name'       => $customPayableRateStruct->name,
            'breakdowns' => $customPayableRateStruct->breakdownsToJson(),
            'now'        => (new DateTime())->format('Y-m-d H:i:s'),
        ] );

        self::destroyQueryByIdCache($conn, $customPayableRateStruct->id);
        self::destroyQueryByUidAndNameCache($conn, $customPayableRateStruct->uid, $customPayableRateStruct->name);

        return $customPayableRateStruct;

    }

    /**
     * @param $id
     * @return int
     * @throws \Exception
     */
    public static function remove( $id ) {

        $payableRateModel = self::getById($id);
        $uid = $payableRateModel->uid;

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "UPDATE ".self::TABLE." SET `name` = :name, `deleted_at` = :now WHERE id = :id " );
        $stmt->execute( [
            'id'  => $id,
            'name'  => uniqid($payableRateModel->name . '_'),
            'now' => (new DateTime())->format('Y-m-d H:i:s'),
        ] );

        self::destroyQueryByIdCache($conn, $id);

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
     * @param $json
     * @param null $uid
     * @return CustomPayableRateStruct
     * @throws \Swaggest\JsonSchema\InvalidValue
     * @throws \Exception
     */
    public static function createFromJSON($json, $uid = null)
    {
        self::validateJSON($json);

        $customPayableRateStruct = new CustomPayableRateStruct();
        $customPayableRateStruct->hydrateFromJSON($json);

        if($uid){
            $customPayableRateStruct->uid = $uid;
        }

        return self::save($customPayableRateStruct);
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
        $jsonSchema = file_get_contents( __DIR__ . '/../../../inc/validation/schema/payable_rate.json' );
        $validator = new \Validator\JSONValidator($jsonSchema);
        $validator->validate($validatorObject);

        if(!$validator->isValid()){
            throw $validator->getErrors()[0]->error;
        }
    }

    /**
     * @param CustomPayableRateStruct $customPayableRateStruct
     * @param $json
     * @return CustomPayableRateStruct
     * @throws \Swaggest\JsonSchema\InvalidValue
     */
    public static function editFromJSON(CustomPayableRateStruct $customPayableRateStruct, $json)
    {
        self::validateJSON($json);
        $customPayableRateStruct->hydrateFromJSON($json);

        return self::update($customPayableRateStruct);
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
     * @param int $modelId
     * @param int $idJob
     * @param int $version
     * @param string $name
     *
     * @return string
     */
    public static function assocModelToJob($modelId, $idJob, $version, $name)
    {
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
