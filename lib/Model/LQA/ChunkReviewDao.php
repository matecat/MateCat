<?php

namespace LQA ;

class ChunkReviewDao extends \DataAccess_AbstractDao {

    const TABLE = "qa_chunk_reviews";

    public static $primary_keys = array(
        'id'
    );

    protected function _buildResult( $array_result ) {

    }

    public function updatePassword($id_job, $password, $old_password) {
        $sql = "UPDATE qa_chunk_reviews SET password = :new_password
               WHERE id_job = :id_job AND password = :password " ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(array(
                'id_job'       => $id_job,
                'password'     => $old_password,
                'new_password' => $password
        ));

        return $stmt->rowCount();
    }
    /**
     * @param $id_job
     *
     * @return ChunkReviewStruct[]
     */
    public static function findByIdJob( $id_job ) {
        $sql = "SELECT * FROM qa_chunk_reviews " .
                " WHERE id_job = :id_job ORDER BY id";
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute(array('id_job' => $id_job ));
        return $stmt->fetchAll();
    }

    /**
     * @param $id
     *
     * @return ChunkReviewStruct
     */
    public static function findById( $id ) {
        $sql = "SELECT * FROM qa_chunk_reviews " .
            " WHERE id = :id ";
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute(array('id' => $id ));
        return $stmt->fetch();

    }

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return int
     */
    public static function getPenaltyPointsForChunk( \Chunks_ChunkStruct $chunk ) {
        $sql = "SELECT SUM(penalty_points) FROM qa_entries e
            JOIN segment_translations st
            ON st.version_number = e.translation_version
            AND st.id_segment = e.id_segment
            JOIN jobs on jobs.id = st.id_job
            WHERE jobs.id = :id_job AND jobs.password = :password
            AND st.id_segment
              BETWEEN jobs.job_first_segment AND jobs.job_last_segment
             ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute(array('id_job' => $chunk->id , 'password' => $chunk->password ));
        $count =  $stmt->fetch();

        $penalty_points = $count[0] == null ? 0 : $count[0];
        return $penalty_points ;
    }

    /**
     * @param \Chunks_ChunkStruct $chunk
     *
     * @return int
     */
    public static function getReviewedWordsCountForChunk( \Chunks_ChunkStruct $chunk ) {
        $statuses = \Constants_TranslationStatus::$REVISION_STATUSES ;
        $statuses_placeholder = str_repeat ('?, ',  count ( $statuses ) - 1) . '?';

        $sql = "SELECT SUM(segments.raw_word_count) FROM segment_translations st
            JOIN segments ON segments.id = st.id_segment
            JOIN jobs on jobs.id = st.id_job
            WHERE jobs.id = ? AND jobs.password = ?
            AND st.status IN ( $statuses_placeholder )
            AND st.id_segment
              BETWEEN jobs.job_first_segment AND jobs.job_last_segment
             ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( array_merge( [$chunk->id , $chunk->password ],  $statuses ) ) ;

        $count =  $stmt->fetch();

        $score = $count[0] == null ? 0 : $count[0];
        return $score ;
    }

    /**
     * @param array $chunk_ids Example: array( array($id_job, $password), ... )
     *
     * @return ChunkReviewStruct[]
     */

    public static function findChunkReviewsByChunkIds( array $chunk_ids ) {
        $sql_condition = '' ;

        if ( count($chunk_ids)  > 0 ) {
            $conditions = array_map( function($ids) {
                return " ( jobs.id = " . $ids[0] .
                " AND jobs.password = '" . $ids[1] . "' ) ";
            }, $chunk_ids );
            $sql_condition =  "WHERE " . implode( ' OR ', $conditions ) ;
        }

        $sql = "SELECT qa_chunk_reviews.* " .
            " FROM jobs INNER JOIN qa_chunk_reviews ON " .
            " jobs.id = qa_chunk_reviews.id_job AND " .
            " jobs.password = qa_chunk_reviews.password " .
             $sql_condition ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return ChunkReviewStruct
     */
    public static function findOneChunkReviewByIdJobAndPassword($id_job, $password) {
        $records = self::findChunkReviewsByChunkIds(array(
            array( $id_job, $password)
        ));
        return @$records[0];
    }

    /**
     * @return ChunkReviewStruct[]
     */

    public static function findByProjectId( $id_project ) {
        $sql = "SELECT * FROM qa_chunk_reviews " .
            " WHERE id_project = :id_project ORDER BY id ";
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute(array('id_project' => $id_project));
        return $stmt->fetchAll() ;
    }

    /**
     * @return ChunkReviewStruct
     */

    public static function findByReviewPasswordAndJobId( $review_password, $id_job ) {
        $sql = "SELECT * FROM qa_chunk_reviews " .
            " WHERE review_password = :review_password " .
            " AND id_job = :id_job " ;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute(
            array(
                'review_password' => $review_password,
                'id_job'          => $id_job
            )
        );
        return $stmt->fetch() ;

    }

    /**
     * @param      $data array of data to use
     *
     * @param bool $setDefaults
     *
     * @return ChunkReviewStruct
     *
     * @throws \Exceptions\ValidationError
     */
    public static function createRecord( $data ) {
        $struct = new ChunkReviewStruct( $data );

        $struct->ensureValid();
        $struct->setDefaults();

        $attrs = $struct->attributes( [
                'id_project',
                'id_job',
                'password',
                'review_password'
        ] );

        $sql = "INSERT INTO " . self::TABLE .
            " ( id_project, id_job, password, review_password ) " .
            " VALUES " .
            " ( :id_project, :id_job, :password, :review_password ) ";

        $conn = \Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->execute( $attrs );

        $lastId = $conn->lastInsertId();
        $record =  self::findById( $lastId );

        return $record ;
    }

    public static function deleteByJobId($id_job) {
        $sql = "DELETE FROM qa_chunk_reviews " .
            " WHERE id_job = :id_job " ;
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        return $stmt->execute( array('id_job' => $id_job ) ) ;
    }

}
