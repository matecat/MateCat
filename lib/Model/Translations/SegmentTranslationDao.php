<?php

class Translations_SegmentTranslationDao extends DataAccess_AbstractDao {

    public static function findBySegmentAndJob( $id_segment, $id_job ) {
        Log::doLog( $id_segment, $id_job );

        $conn = Database::obtain()->getConnection();

        $sql = "SELECT * FROM segment_translations WHERE " .
            " id_segment = :id_segment AND " .
            " id_job = :id_job " ;

        $stmt = $conn->prepare( $sql );

        $stmt->execute( array(
            'id_segment' => $id_segment,
            'id_job'     => $id_job
        ));

        $stmt->setFetchMode(PDO::FETCH_CLASS,
            'Translations_SegmentTranslationStruct');

        return $stmt->fetch();
    }

    public function lastTranslationByJobOrChunk( $chunk ) {
      $conn = Database::obtain()->getConnection();
      $query = "SELECT * FROM segment_translations " .
        " WHERE id_job = :id_job " .
        " AND segment_translations.id_segment BETWEEN :job_first_segment AND :job_last_segment " .
        " ORDER BY translation_date DESC " .
        " LIMIT 1 " ;

      Log::doLog( $query );

      $stmt = $conn->prepare( $query );

      $array = array(
        'id_job' => $chunk->id,
        'job_first_segment' => $chunk->job_first_segment ,
        'job_last_segment' => $chunk->job_last_segment
      ) ;

      $stmt->execute( $array );

      $stmt->setFetchMode(PDO::FETCH_CLASS, 'Translations_SegmentTranslationStruct');

      return $stmt->fetch();
    }

    public function getByJobId($id_job) {
        $conn = $this->con->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM segment_translations " .
            " WHERE id_job = ? " );

        $stmt->execute( array( $id_job ) );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Translations_SegmentTranslationStruct');

        return $stmt->fetchAll( );
    }

    protected function _buildResult( $array_result ) {

    }

}
