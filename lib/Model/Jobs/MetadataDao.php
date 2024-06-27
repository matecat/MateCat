<?php

namespace Jobs;

use DataAccess_IDaoStruct;
use Database;

class MetadataDao extends \DataAccess_AbstractDao {

    const TABLE = 'job_metadata';

    const _query_metadata_by_job_id_key = "SELECT * FROM job_metadata WHERE id_job = :id_job AND `key` = :key ";

    /**
     * @param     $id_job
     * @param     $key
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]|MetadataStruct[]
     */
    public function getByIdJob( $id_job, $key, $ttl = 0 ) {

        $stmt = $this->_getStatementForCache( self::_query_metadata_by_job_id_key );

        return $this->setCacheTTL( $ttl )->_fetchObject( $stmt, new MetadataStruct(), [
                'id_job' => $id_job,
                'key'    => $key
        ] );
    }

    public function destroyCacheByJobId( $id_job, $key ) {
        $stmt = $this->_getStatementForCache( self::_query_metadata_by_job_id_key );

        return $this->_destroyObjectCache( $stmt, [ 'id_job' => $id_job, 'key' => $key ] );
    }

    /**
     * @param     $id_job
     * @param     $password
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct[]
     */
    public function getByJobIdAndPassword( $id_job, $password, $ttl = 0 ) {

        $stmt = $this->_getStatementForCache(
                "SELECT * FROM job_metadata WHERE " .
                " id_job = :id_job " .
                " AND password = :password "
        );

        $result = $this->setCacheTTL( $ttl )->_fetchObject( $stmt, new MetadataStruct(), [
                'id_job'   => $id_job,
                'password' => $password,
        ] );

        return @$result;
    }

    /**
     * @param     $id_job
     * @param     $password
     * @param     $key
     * @param int $ttl
     *
     * @return MetadataStruct
     */
    public function get( $id_job, $password, $key, $ttl = 0 ) {
        $stmt = $this->_getStatementForCache(
                "SELECT * FROM job_metadata WHERE " .
                " id_job = :id_job " .
                " AND password = :password " .
                " AND `key` = :key "
        );

        $result = $this->setCacheTTL( $ttl )->_fetchObject( $stmt, new MetadataStruct(), [
                'id_job'   => $id_job,
                'password' => $password,
                'key'      => $key
        ] );

        /**
         * @var $r MetadataStruct
         */
        $r = $result[ 0 ] ?? null;

        return $r;

    }

    /**
     * @param $id_job
     * @param $password
     * @param $key
     * @param $value
     *
     * @return MetadataStruct
     */
    public function set( $id_job, $password, $key, $value ) {
        $sql = "INSERT INTO job_metadata " .
                " ( id_job, password, `key`, value ) " .
                " VALUES " .
                " ( :id_job, :password, :key, :value ) " .
                " ON DUPLICATE KEY UPDATE value = :value ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'   => $id_job,
                'password' => $password,
                'key'      => $key,
                'value'    => $value
        ] );

        $this->destroyCacheByJobId( $id_job, $key );

        return $this->get( $id_job, $password, $key );
    }

    public function delete( $id_job, $password, $key ) {
        $sql = "DELETE FROM job_metadata " .
                " WHERE id_job = :id_job AND password = :password " .
                " AND `key` = :key ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'   => $id_job,
                'password' => $password,
                'key'      => $key,
        ] );
    }

    protected function _buildResult( $array_result ) {
        // TODO: Implement _buildResult() method.
    }

}