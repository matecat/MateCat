<?php

use Search\ReplaceEventStruct;

class Search_ReplaceEventDAO extends DataAccess_AbstractDao {

    const STRUCT_TYPE = ReplaceEventStruct::class;
    const TABLE       = 'replace_events';

    /**
     * @param $id_project
     * @param $password
     *
     * @return array
     */
    public static function getByProject( $id_project, $password ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT 
            * FROM replace_events r
            INNER JOIN jobs j ON r.id_job = j.id AND r.job_password = j.password
            INNER JOIN projects p on p.id=j.id_project
            WHERE p.id=:id_project AND p.password=:project_password
        ";

        $stmt = $conn->prepare( $query );
        $stmt->execute( [
                ':id_project'       => $id_project,
                ':project_password' => $password,
        ] );

        return @$stmt->fetchAll( \PDO::FETCH_CLASS, self::STRUCT_TYPE );
    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return array
     */
    public static function getByJob( $id_job, $password ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT * FROM " . self::TABLE . " WHERE id_job = :id_job AND job_password = :job_password ";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [
                ':id_job'       => $id_job,
                ':job_password' => $password,
        ] );

        return @$stmt->fetchAll( \PDO::FETCH_CLASS, self::STRUCT_TYPE );
    }

    /**
     * @param $id_segment
     *
     * @return array
     */
    public static function getBySegment( $id_segment ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT * FROM " . self::TABLE . " WHERE id_segment = :id_segment ";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [
                ':id_segment' => $id_segment
        ] );

        return @$stmt->fetchAll( \PDO::FETCH_CLASS, self::STRUCT_TYPE );
    }

    /**
     * @param $eventStructId
     *
     * @return mixed
     */
    public static function getById( $eventStructId ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT * FROM " . self::TABLE . " WHERE id = :id ";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [
                ':id' => $eventStructId
        ] );

        return @$stmt->fetchObject( self::STRUCT_TYPE )[ 0 ];
    }

    /**
     * @param ReplaceEventStruct $eventStruct
     *
     * @return int
     */
    public static function save( ReplaceEventStruct $eventStruct ) {
        $conn  = Database::obtain()->getConnection();

        $query = "INSERT INTO " . self::TABLE . "
        (id_job, job_password, id_segment, source, target, language, created_at)
        VALUES
        (:id_job, :job_password, :id_segment, :source, :target, :language, :created_at)
        ";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [
                ':id_job' => $eventStruct->id_job,
                ':job_password' => $eventStruct->job_password,
                ':id_segment' => $eventStruct->id_segment,
                ':source' => $eventStruct->source,
                ':target' => $eventStruct->target,
                ':language' => $eventStruct->language,
                ':created_at' => date('Y-m-d H:i:s'),
        ] );

        return $stmt->rowCount();
    }
}