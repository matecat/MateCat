<?php

use Search\ReplaceEventStruct;

class Search_MySQLReplaceEventDAO extends DataAccess_AbstractDao implements Search_ReplaceEventDAOInterface {

    const STRUCT_TYPE = ReplaceEventStruct::class;
    const TABLE       = 'replace_events';

    /**
     * @param $idJob
     * @param $version
     *
     * @return ReplaceEventStruct[]
     */
    public function getEvents( $idJob, $version ) {
        $conn  = Database::obtain()->getConnection();
        $query = "SELECT * FROM " . self::TABLE . " WHERE id_job = :id_job  AND bulk_version = :bulk_version ORDER BY created_at DESC";

        $stmt = $conn->prepare( $query );
        $stmt->execute( [
                ':id_job' => $idJob,
                ':bulk_version' => $version,
        ] );

        return @$stmt->fetchAll( \PDO::FETCH_CLASS, self::STRUCT_TYPE );
    }

    /**
     * @param ReplaceEventStruct $eventStruct
     *
     * @return int
     */
    public function save( ReplaceEventStruct $eventStruct ) {
        $conn = Database::obtain()->getConnection();

        // if not directly passed
        // try to assign the current version of the segment if it exists
        if ( null === $eventStruct->segment_version ) {
            $segment = (new Translations_SegmentTranslationDao())->getByJobId($eventStruct->id_job)[0];
            $eventStruct->segment_version = $segment->version_number;
        }

        // insert query
        $query = "INSERT INTO " . self::TABLE . "
        (id_job, bulk_version, job_password, id_segment, source, target, replacement, segment_version, translation_before_replacement, translation_after_replacement, created_at)
        VALUES
        (:id_job, :bulk_version, :job_password, :id_segment, :source, :target, :replacement, :segment_version, :translation_before_replacement, :translation_after_replacement, :created_at)
        ";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( [
                ':id_job'                         => $eventStruct->id_job,
                ':bulk_version'                   => $eventStruct->bulk_version,
                ':job_password'                   => $eventStruct->job_password,
                ':id_segment'                     => $eventStruct->id_segment,
                ':source'                         => $eventStruct->source,
                ':target'                         => $eventStruct->target,
                ':replacement'                    => $eventStruct->replacement,
                ':segment_version'                => $eventStruct->segment_version,
                ':translation_before_replacement' => $eventStruct->translation_before_replacement,
                ':translation_after_replacement'  => $eventStruct->translation_after_replacement,
                ':created_at'                     => date( 'Y-m-d H:i:s' ),
        ] );

        return $stmt->rowCount();
    }
}
