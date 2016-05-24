<?php

class Translations_SegmentTranslationDao extends DataAccess_AbstractDao {

    public static $primary_keys = array(
        'id_job',
        'id_segment'
    );

    const TABLE = "segment_translations";

    /**
     * @param $id_segment
     * @param $id_job
     * @return Translations_SegmentTranslationStruct
     */

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

    /**
     * @param $chunk
     *
     * @return Translations_SegmentTranslationStruct
     */
    public function lastTranslationByJobOrChunk( $chunk ) {
      $conn = Database::obtain()->getConnection();
      $query = "SELECT * FROM segment_translations " .
        " WHERE id_job = :id_job " .
        " AND segment_translations.id_segment " .
        " BETWEEN :job_first_segment AND :job_last_segment " .
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

    public function getSegmentsForPropagation( $params ) {
        $selectSegmentsToPropagate = " SELECT * FROM segment_translations " .
                " WHERE id_job = :id_job " .
                " AND segment_hash = :segment_hash " .
                " AND id_segment BETWEEN :job_first_segment AND :job_last_segment " .
                " AND id_segment <> :id_segment " ;

        $conn =  $this->con->getConnection() ;
        $stmt = $conn->prepare( $selectSegmentsToPropagate );
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Translations_SegmentTranslationStruct');
        $stmt->execute( $params ) ;

        return $stmt->fetchAll();
    }
    /**
     * @param $id_job
     *
     * @return Translations_SegmentTranslationStruct[]
     */

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

    public static function updateSeverity( Translations_SegmentTranslationStruct $struct, $severity ) {
        $sql = "UPDATE segment_translations
            SET warning = :warning
              WHERE id_segment = :id_segment
              AND id_job = :id_job
              AND segment_hash = :segment_hash";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( array(
                'id_segment'    => $struct->id_segment ,
                'id_job'        => $struct->id_job,
                'segment_hash'  => $struct->segment_hash,
                'warning'       => $severity
        ) );

        return $stmt->rowCount();
    }
    /**
     * @param $data
     *
     * @return int
     */
    public static function updateEditDistanceForSetTranslation($data) {
        $sql = "UPDATE segment_translations
            SET edit_distance = :edit_distance
              WHERE id_segment = :id_segment
              AND id_job = :id_job
              AND segment_hash = :segment_hash";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $exec = $stmt->execute( array(
                'id_segment'    => $data[ 'id_segment' ],
                'id_job'        => $data[ 'id_job' ],
                'segment_hash'  => $data[ 'segment_hash' ],
                'edit_distance' => $data[ 'edit_distance' ]
        ) );

        return $stmt->rowCount();
    }


    public static function updateEditDistanceForPropagation($data) {
        $sql = "UPDATE segment_translations
            SET edit_distance = :edit_distance
              WHERE id_segment <> :id_segment
              AND id_job = :id_job
              AND segment_hash = :segment_hash         ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $exec = $stmt->execute(array(
            'id_segment' => $data['id_segment'],
            'id_job' => $data['id_job'],
            'segment_hash' => $data['segment_hash'],
            'edit_distance' => $data['edit_distance']
        ) );

        return $stmt->rowCount();
    }

}
