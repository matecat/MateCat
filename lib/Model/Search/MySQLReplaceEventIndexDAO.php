<?php

use Search\ReplaceEventCurrentVersionStruct;

class Search_MySQLReplaceEventIndexDAO extends DataAccess_AbstractDao implements Search_ReplaceEventIndexDAOInterface {

    const STRUCT_TYPE = ReplaceEventCurrentVersionStruct::class;
    const TABLE       = 'replace_events_current_version';

    /**
     * @param $idJob
     *
     * @return int
     */
    public function getActualIndex( $idJob ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT version as v FROM " . self::TABLE . " WHERE id_job=:id_job";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [
                ':id_job' => $idJob,
        ] );

        return (int)$stmt->fetch()[ 0 ][ 'v' ];
    }

    /**
     * @param $id_job
     * @param $version
     *
     * @return int
     */
    public function save( $id_job, $version ) {
        $conn = Database::obtain()->getConnection();

        if ( 0 !== $this->getActualIndex( $id_job ) ) {
            $query = "UPDATE " . self::TABLE . " SET version = :version WHERE id_job=:id_job";
        } else {
            $query = "INSERT INTO " . self::TABLE . " (`version`, `id_job`) VALUES (:version, :id_job)";
        }

        $stmt = $conn->prepare( $query );
        $stmt->execute( [
                ':id_job'  => $id_job,
                ':version' => $version,
        ] );

        return $stmt->rowCount();
    }

    public function setTtl( $ttl ) {
        // TODO: Implement setTtl() method.
    }
}
