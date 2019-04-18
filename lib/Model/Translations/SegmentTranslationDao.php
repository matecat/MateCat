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

    public static function findBySegmentAndJob( $id_segment, $id_job, $ttl = 0 ) {
        $conn = Database::obtain()->getConnection();

        $sql = "SELECT * FROM segment_translations WHERE " .
            " id_segment = :id_segment AND " .
            " id_job = :id_job " ;

        $stmt = $conn->prepare( $sql );

        $thisDao = new self();
        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new Translations_SegmentTranslationStruct(), [
                'id_job'     => $id_job,
                'id_segment' => $id_segment
        ] )[ 0 ];
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
        'id_job'            => $chunk->id,
        'job_first_segment' => $chunk->job_first_segment ,
        'job_last_segment'  => $chunk->job_last_segment
      ) ;

      $stmt->execute( $array );

      $stmt->setFetchMode(PDO::FETCH_CLASS, 'Translations_SegmentTranslationStruct');

      return $stmt->fetch();
    }

    public function getSegmentsForPropagation( $params, $status = Constants_TranslationStatus::STATUS_TRANSLATED ) {

        /**
         * We want to avoid that a translation overrides a propagation,
         * so we have to set an additional status when the requested status to propagate is TRANSLATE
         */
        $additional_status = '';
        if( $status == Constants_TranslationStatus::STATUS_TRANSLATED ){
            $additional_status = "AND status != '" . Constants_TranslationStatus::STATUS_APPROVED . "'
";
        }

        $selectSegmentsToPropagate = " SELECT * FROM segment_translations " .
                " WHERE id_job = :id_job " .
                " AND segment_hash = :segment_hash " .
                " AND id_segment BETWEEN :job_first_segment AND :job_last_segment " .
                " AND id_segment <> :id_segment $additional_status; ";

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

    /**
     * @param Files_FileStruct $file
     *
     * @return Translations_SegmentTranslationStruct[]
     */
    public function getByFile( Files_FileStruct $file ) {
        $sql = "SELECT * FROM segment_translations st " .
               " JOIN segments s on s.id  = st.id_segment AND s.id_file = :id_file " .
               " WHERE s.show_in_cattool = 1 " ;

        $conn = $this->con->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->execute( ['id_file' => $file->id ] ) ;
        $stmt->setFetchMode(PDO::FETCH_CLASS, 'Translations_SegmentTranslationStruct');
        return $stmt->fetchAll() ;
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

    public static function setAnalysisValue( $data ) {

        $id_segment = (int)$data[ 'id_segment' ];
        $id_job     = (int)$data[ 'id_job' ];

        $where = " id_segment = $id_segment and id_job = $id_job";

        $db = Database::obtain();
        try {
            $affectedRows = $db->update( 'segment_translations', $data, $where );
        } catch ( PDOException $e ) {
            Log::doLog( $e->getMessage() );

            return $e->getCode() * -1;
        }

        return $affectedRows;

    }

    public function setApprovedByChunk( $chunk ) {
        $sql = "UPDATE segment_translations
            SET status = :status
              WHERE id_job = :id_job AND id_segment BETWEEN :first_segment AND :last_segment";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( [
                'status'        => Constants_TranslationStatus::STATUS_APPROVED,
                'id_job'        => $chunk->id,
                'first_segment' => $chunk->job_first_segment,
                'last_segment'  => $chunk->job_last_segment
        ] );

        $counter = new \WordCount_Counter;
        $counter->initializeJobWordCount( $chunk->id, $chunk->password );

        return $stmt->rowCount();
    }

    public function setTranslatedByChunk( $chunk ) {

        $sql = "UPDATE segment_translations
            SET status = :status
              WHERE id_job = :id_job AND id_segment BETWEEN :first_segment AND :last_segment AND status != :approved_status";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( [
                'status'          => Constants_TranslationStatus::STATUS_TRANSLATED,
                'id_job'          => $chunk->id,
                'first_segment'   => $chunk->job_first_segment,
                'last_segment'    => $chunk->job_last_segment,
                'approved_status' => Constants_TranslationStatus::STATUS_APPROVED,
        ] );

        $counter = new \WordCount_Counter;
        $counter->initializeJobWordCount( $chunk->id, $chunk->password );

        return $stmt->rowCount();
    }

    public static function getSegmentsWithIssues( $job_id, $segments_ids ) {
        $where_values = $segments_ids;

        $sql  = "SELECT * FROM segment_translations WHERE id_segment IN (" . str_repeat( '?,', count( $segments_ids ) - 1 ) . '?' . ") AND id_job = ?";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, '\DataAccess\ShapelessConcreteStruct' );
        $where_values[] = $job_id;
        $stmt->execute( $where_values );

        return $stmt->fetchAll();
    }

    public static function updateSegmentStatusBySegmentId( $id_job, $id_segment, $status ) {
        $sql = "UPDATE segment_translations SET status = :status WHERE id_job = :id_job AND id_segment = :id_segment " ;
        $conn         = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql ) ;
        $stmt->execute( ['id_job' => $id_job, 'id_segment' => $id_segment, 'status' => $status ] ) ;

        return $stmt->rowCount() ;
    }

    public static function getUnchangebleStatus( $segments_ids, $status ) {
        $where_values = [];
        $conn         = Database::obtain()->getConnection();

        if ( $status == Constants_TranslationStatus::STATUS_APPROVED ) {
            $where_values[] = Constants_TranslationStatus::STATUS_TRANSLATED;

        } elseif ( $status == Constants_TranslationStatus::STATUS_TRANSLATED ) {
            $where_values[] = Constants_TranslationStatus::STATUS_APPROVED ;
            $where_values[] = Constants_TranslationStatus::STATUS_DRAFT ;
            $where_values[] = Constants_TranslationStatus::STATUS_NEW ;
        }
        else {
            throw new Exception('not allowed to change status to '. $status ) ;
        }

        $status_placeholders       = str_repeat( '?,', count( $where_values ) -1 ) . '?' ;
        $segments_ids_placeholders = str_repeat( '?,', count( $segments_ids ) - 1 ) . '?';

        $sql = "SELECT id_segment FROM segment_translations
                    WHERE
                    (
                      status NOT IN( $status_placeholders )  OR
                      translation IS NULL OR
                      translation = ''
                    ) AND id_segment IN ( $segments_ids_placeholders )
                    ";

        $where_values = array_merge( $where_values, $segments_ids );
        $stmt         = $conn->prepare( $sql );
        $stmt->execute( $where_values );

        return $stmt->fetchAll( PDO::FETCH_FUNC, function ( $id_segment ) {
            return (int)$id_segment;
        } );


    }


}
