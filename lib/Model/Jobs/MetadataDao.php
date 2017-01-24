<?php

namespace Jobs;

use Database ;

class MetadataDao extends \DataAccess_AbstractDao
{

    /**
     * @param $id_job
     * @param $password
     * @param $key
     *
     * @return MetadataStruct[]
     */
    public function getByIdJob( $id_job, $key ) {
        $stmt = $this->_getStatementForCache(
            "SELECT * FROM job_metadata WHERE " .
            " id_job = :id_job " .
            " AND `key` = :key "
        );

        $result = $this->_fetchObject( $stmt, new MetadataStruct() , array(
            'id_job' => $id_job,
            'key' => $key
        ) );

        return @$result;
    }

    /**
     * @param $id_job
     * @param $password
     * @param $key
     * @return MetadataStruct
     */
    public function get( $id_job, $password, $key ) {
        $stmt = $this->_getStatementForCache(
            "SELECT * FROM job_metadata WHERE " .
            " id_job = :id_job " .
            " AND password = :password " .
            " AND `key` = :key "
        );

        $result = $this->_fetchObject( $stmt, new MetadataStruct() , array(
            'id_job' => $id_job,
            'password' => $password,
            'key' => $key
        ) );

        return @$result[0];

    }

    /**
     * @param $id_job
     * @param $password
     * @param $key
     * @param $value
     * @return MetadataStruct
     */
    public function set($id_job, $password, $key, $value) {
        $sql = "INSERT INTO job_metadata " .
            " ( id_job, password, `key`, value ) " .
            " VALUES " .
            " ( :id_job, :password, :key, :value ) " .
            " ON DUPLICATE KEY UPDATE value = :value " ;

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(  $sql );
        $stmt->execute( array(
            'id_job' => $id_job,
            'password' => $password,
            'key' => $key,
            'value' => $value
        ) );

        return $this->get($id_job, $password, $key);
    }

    public function delete($id_job, $password, $key) {
        $sql = "DELETE FROM job_metadata " .
            " WHERE id_job = :id_job AND password = :password " .
            " AND `key` = :key "  ;

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(  $sql );
        $stmt->execute( array(
            'id_job' => $id_job,
            'password' => $password,
            'key' => $key,
        ) );
    }

    protected function _buildResult($array_result)
    {
        // TODO: Implement _buildResult() method.
    }

}