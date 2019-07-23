<?php

use Search\ReplaceEventCurrentVersionStruct;

class Search_ReplaceEventCurrentVersionDAO extends DataAccess_AbstractDao {

    const STRUCT_TYPE = ReplaceEventCurrentVersionStruct::class;
    const TABLE       = 'replace_events_current_version';

    /**
     * @param $id_job
     *
     * @return int
     */
    public static function getByIdJob( $id_job ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT version as v FROM " . self::TABLE . " WHERE id_job=:id_job";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [
                ':id_job' => $id_job,
        ] );

        return (int)$stmt->fetch()[ 0 ][ 'v' ];
    }

    /**
     * @param $id_job
     * @param $version
     *
     * @return int
     */
    public static function save( $id_job, $version ) {
        $conn = Database::obtain()->getConnection();

        if ( 0 !== self::getByIdJob( $id_job ) ) {
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
}
