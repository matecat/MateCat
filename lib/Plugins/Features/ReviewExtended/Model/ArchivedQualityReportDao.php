<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 17/05/2017
 * Time: 16:33
 */

namespace Features\ReviewExtended\Model;
use Chunks_ChunkStruct;

class ArchivedQualityReportDao extends \DataAccess_AbstractDao  {

    const TABLE       = "qa_archived_reports";
    const STRUCT_TYPE = "\\Features\\ReviewExtended\\Model\\ArchivedQualityReportStruct" ;

    protected static $auto_increment_field = array('id');

    protected function _buildResult( $result_array ) {}

    public function archiveQualityReport( ArchivedQualityReportStruct $report ) {
        return self::insertStruct( $report, [ 'no_nulls' => true ] );
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     * @param                    $versionNumber
     * @return ArchivedQualityReportStruct
     */
    public function getByChunkAndVersionNumber( Chunks_ChunkStruct $chunk, $versionNumber ) {
        $sql = "SELECT * FROM qa_archived_reports WHERE
                id_job = :id_job AND password = :password AND
                job_first_segment = :job_first_segment AND job_last_segment = :job_last_segment AND
                version = :version " ;

        $stmt = $this->getDatabaseHandler()->getConnection()->prepare( $sql ) ;
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'Features\ReviewExtended\Model\ArchivedQualityReportStruct' );
        $stmt->execute( array(
                'id_job'              => $chunk->id,
                'password'            => $chunk->password,
                'job_first_segment'   => $chunk->job_first_segment,
                'job_last_segment'    => $chunk->job_last_segment,
                'version'             => $versionNumber
        ) ) ;

        return $stmt->fetch() ;
    }


    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return \Features\ReviewExtended\Model\ArchivedQualityReportStruct[]
     *
     */
    public function getAllByChunk( Chunks_ChunkStruct $chunk ) {
        $sql = "SELECT * FROM qa_archived_reports WHERE
                id_job = :id_job AND password = :password AND
                job_first_segment = :job_first_segment AND job_last_segment = :job_last_segment ";

        $stmt = $this->getDatabaseHandler()->getConnection()->prepare( $sql ) ;
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'Features\ReviewExtended\Model\ArchivedQualityReportStruct' );
        $stmt->execute( array(
                'id_job'              => $chunk->id,
                'password'            => $chunk->password,
                'job_first_segment'   => $chunk->job_first_segment,
                'job_last_segment'    => $chunk->job_last_segment,
        ) ) ;

        return $stmt->fetchAll() ;
    }

    public function getLastVersionNumber( Chunks_ChunkStruct $chunk ) {
        $conn = $this->getDatabaseHandler()->getConnection();
        $stmt = $conn->prepare("
            SELECT MAX(version) FROM qa_archived_reports
              WHERE id_job = :id_job
                AND password = :password
                AND job_first_segment = :job_first_segment
                AND job_last_segment = :job_last_segment
            ") ;

        $stmt->execute(array(
                'id_job'              => $chunk->id,
                'password'            => $chunk->password,
                'job_first_segment'   => $chunk->job_first_segment,
                'job_last_segment'    => $chunk->job_last_segment
        )) ;

        $record = $stmt->fetch();

        return $record[0] ? $record[0] : 0 ;

    }
}