<?php

class Translations_SegmentTranslationDao extends DataAccess_AbstractDao {

    public function lastTranslationByChunk( $chunk ) {
      $conn = Database::obtain()->getConnection();
      $stmt = $conn->prepare("SELECT * FROM segment_translations " .
        " WHERE id_job = :id_job " .
        " AND id_segment BETWEEN :first_job_segment AND :last_job_segment " .
        " ORDER BY translation_date DESC " .
        " LIMIT 1 "
      );

      $stmt->setFetchMode(PDO::FETCH_CLASS, 'Translations_SegmentTranslationStruct');
      $stmt->execute( array(
        'id_job' => $chunk->id_job,
        'first_job_segment' => $chunk->first_job_segment ,
        'last_job_segment' => $chunk->last_job_segment
      ));

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
