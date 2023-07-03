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
     * @param $id
     * @param int $ttl
     * @return CustomPayableRateStruct
     */
    public function getById( $id, $ttl = 60 ) {
        $stmt = $this->_getStatementForCache(self::query_by_id);
        $result = $this->setCacheTTL( $ttl )->_fetchObject( $stmt, new CustomPayableRateStruct(), [
            'id' => $id,
        ] );

        return @$result;
    }

    /**
     * @param $uid
     * @param $name
     * @param int $ttl
     * @return CustomPayableRateStruct
     */
    public function getByUidAndName( $uid, $name, $ttl = 60 ) {
        $stmt = $this->_getStatementForCache(self::query_by_uid_name);
        $result = $this->setCacheTTL( $ttl )->_fetchObject( $stmt, new CustomPayableRateStruct(), [
            'uid' => $uid,
            'name' => $name,
        ] );

        return @$result;
    }

    /**
     * @param $uid
     * @param $version
     * @param $name
     * @param $breakdowns
     * @return CustomPayableRateStruct
     */
    public function insert( $uid, $version, $name, $breakdowns ) {

        $sql = "INSERT INTO " . self::TABLE .
            " ( `uid`, `version`, `name`, `breakdowns` ) " .
            " VALUES " .
            " ( :uid, :version, :name, :breakdowns ); ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
            'uid'        => $uid,
            'version'    => $version,
            'name'       => $name,
            'breakdowns' => $breakdowns,
        ] );

        return $this->getByUidAndName( $uid, $name );
    }

    /**
     * @param $id
     * @param $uid
     * @param $version
     * @param $name
     * @param $breakdowns
     * @return CustomPayableRateStruct
     */
    public function update( $id, $uid, $version, $name, $breakdowns ) {

        $sql = "UPDATE " . self::TABLE . " SET `uid` = :uid, `version` = :version, `name` = :name, `breakdowns` = :breakdowns WHERE id = :id ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
            'id'         => $id,
            'uid'        => $uid,
            'version'    => $version,
            'name'       => $name,
            'breakdowns' => $breakdowns,
        ] );

        $stmt = $conn->prepare( self::query_by_id );
        $this->_destroyObjectCache( $stmt, [ 'id' => $id, ] );

        $stmt = $conn->prepare( self::query_by_uid_name );
        $this->_destroyObjectCache( $stmt, [ 'uid' => $uid, 'name' => $name,  ] );

        return $this->getByUidAndName( $uid, $name );

    }

    /**
     * @param $id
     * @return int
     */
    public function delete( $id ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "DELETE FROM ".self::TABLE." WHERE id = :id " );
        $stmt->execute( [ 'id' => $id ] );

        return $stmt->rowCount();
    }
}
