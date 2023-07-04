<?php

namespace PayableRates;

use DataAccess_AbstractDao;
use Database;

class CustomPayableRateDao extends DataAccess_AbstractDao
{
    const TABLE = 'payable_rate_templates';

    const query_by_uid_name = "SELECT * FROM " . self::TABLE . " WHERE uid = :uid AND name = :name";
    const query_by_id = "SELECT * FROM " . self::TABLE . " WHERE id = :id";

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

    public static function getAllPaginated($uid, $current = 1, $pagination = 20)
    {
        $conn = \Database::obtain()->getConnection();

        $stmt = $conn->prepare( "SELECT count(id) as count FROM ".self::TABLE." WHERE uid = :uid");
        $stmt->execute([
            'uid' => $uid
        ]);

        $count = $stmt->fetch(\PDO::FETCH_ASSOC);
        $pages = ceil($count['count'] / $pagination);
        $prev = ($current !== 1) ? "/api/v3/payable_rate?page=".($current-1) : null;
        $next = ($current < $pages) ? "/api/v3/payable_rate?page=".($current+1) : null;
        $offset = ($current - 1) * $pagination;

        $models = [];

        $stmt = $conn->prepare( "SELECT id FROM ".self::TABLE." WHERE uid = :uid LIMIT $pagination OFFSET $offset ");
        $stmt->execute([
            'uid' => $uid
        ]);

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $model){
            $models[] = self::getById($model['id']);
        }

        return [
            'current_page' => $current,
            'per_page' => $pagination,
            'last_page' => $pages,
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
     * @return int
     */
    public static function save( CustomPayableRateStruct $customPayableRateStruct ) {

        $sql = "INSERT INTO " . self::TABLE .
            " ( `uid`, `version`, `name`, `breakdowns` ) " .
            " VALUES " .
            " ( :uid, :version, :name, :breakdowns ); ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
            'uid'        => $customPayableRateStruct->uid,
            'version'    => $customPayableRateStruct->version,
            'name'       => $customPayableRateStruct->name,
            'breakdowns' => $customPayableRateStruct->breakdownsToJson(),
        ] );

        return $conn->lastInsertId();
    }

    /**
     * @param CustomPayableRateStruct $customPayableRateStruct
     * @return CustomPayableRateStruct
     */
    public static function update( CustomPayableRateStruct $customPayableRateStruct ) {

        $sql = "UPDATE " . self::TABLE . " SET `uid` = :uid, `version` = :version, `name` = :name, `breakdowns` = :breakdowns WHERE id = :id ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
            'id'         => $customPayableRateStruct->id,
            'uid'        => $customPayableRateStruct->uid,
            'version'    => $customPayableRateStruct->version,
            'name'       => $customPayableRateStruct->name,
            'breakdowns' => $customPayableRateStruct->breakdownsToJson(),
        ] );

        $stmt = $conn->prepare( self::query_by_id );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'id' => $customPayableRateStruct->id, ] );

        $stmt = $conn->prepare( self::query_by_uid_name );
        self::getInstance()->_destroyObjectCache( $stmt, [ 'uid' => $customPayableRateStruct->uid, 'name' => $customPayableRateStruct->name,  ] );

        return self::getInstance()->getByUidAndName( $customPayableRateStruct->uid, $customPayableRateStruct->name );

    }

    /**
     * @param $id
     * @return int
     */
    public static function remove( $id ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "DELETE FROM ".self::TABLE." WHERE id = :id " );
        $stmt->execute( [ 'id' => $id ] );

        return $stmt->rowCount();
    }

    /**
     * validate a json against schema and then
     * create a Payable Rate model template from it
     *
     * @param      $json
     * @param null $uid
     *
     * @return int
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
     * @param                       $json
     *
     * @return mixed
     * @throws \Swaggest\JsonSchema\InvalidValue
     * @throws \Exception
     */
    public static function editFromJSON(CustomPayableRateStruct $customPayableRateStruct, $json)
    {
        self::validateJSON($json);
        $customPayableRateStruct->hydrateFromJSON($json);

        return self::update($customPayableRateStruct);
    }
}
