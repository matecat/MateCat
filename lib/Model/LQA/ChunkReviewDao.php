<?php

namespace LQA;

use Chunks_ChunkStruct;
use Constants;
use DataAccess\ShapelessConcreteStruct;
use DataAccess_IDaoStruct;

class ChunkReviewDao extends \DataAccess_AbstractDao {

    const TABLE = "qa_chunk_reviews";

    public static $primary_keys = [
            'id'
    ];

    protected function _buildResult( $array_result ) {
    }

    public function updatePassword( $id_job, $old_password, $new_password ) {
        $sql = "UPDATE qa_chunk_reviews SET password = :new_password
               WHERE id_job = :id_job AND password = :old_password ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'       => $id_job,
                'old_password' => $old_password,
                'new_password' => $new_password
        ] );

        return $stmt->rowCount();
    }

    /**
     * @param $id_job
     *
     * @return ChunkReviewStruct[]
     */
    public static function findByIdJob( $id_job ) {
        $sql  = "SELECT * FROM qa_chunk_reviews " .
                " WHERE id_job = :id_job ORDER BY id";
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute( [ 'id_job' => $id_job ] );

        return $stmt->fetchAll();
    }

    /**
     * @param $id_job
     * @param $source_page
     *
     * @return ChunkReviewStruct
     */
    public static function findByIdJobAndPasswordAndSourcePage( $id_job, $password, $source_page ) {
        $sql  = "SELECT * FROM qa_chunk_reviews " .
                " WHERE id_job = :id_job 
                AND password = :password
                AND source_page = :source_page ORDER BY id";
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute( [
                'id_job' => $id_job,
                'password' => $password,
                'source_page' => $source_page,
        ] );

        return $stmt->fetchAll()[0];
    }

    /**
     * @param $id
     *
     * @return ChunkReviewStruct
     */
    public static function findById( $id ) {
        $sql  = "SELECT * FROM qa_chunk_reviews " .
                " WHERE id = :id ";
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute( [ 'id' => $id ] );

        return $stmt->fetch();

    }

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return int
     */
    public static function getPenaltyPointsForChunk( Chunks_ChunkStruct $chunk ) {

        $sql = "SELECT SUM(penalty_points)
            FROM segment_translations st
            JOIN jobs on jobs.id = st.id_job
            JOIN qa_entries e ON st.version_number = e.translation_version AND st.id_segment = e.id_segment AND st.id_job = e.id_job
            WHERE jobs.id = :id_job
            AND jobs.password = :password
            AND e.deleted_at IS NULL
            AND st.id_segment
              BETWEEN jobs.job_first_segment AND jobs.job_last_segment
            ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id_job' => $chunk->id, 'password' => $chunk->password ] );
        $count = $stmt->fetch();

        $penalty_points = $count[ 0 ] == null ? 0 : $count[ 0 ];

        return $penalty_points;
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     *
     * @return int
     */
    public static function getReviewedWordsCountForChunk( Chunks_ChunkStruct $chunk ) {
        $statuses             = \Constants_TranslationStatus::$REVISION_STATUSES;
        $statuses_placeholder = str_repeat( '?, ', count( $statuses ) - 1 ) . '?';

        $sql = "SELECT SUM(segments.raw_word_count) FROM segment_translations st
            JOIN segments ON segments.id = st.id_segment
            JOIN jobs on jobs.id = st.id_job
            WHERE jobs.id = ? AND jobs.password = ?
            AND st.status IN ( $statuses_placeholder )

            AND ( st.match_type != 'ICE' OR ( st.match_type = 'ICE' AND locked AND st.version_number > 0 AND time_to_edit != 0) OR ( st.match_type = 'ICE' AND not locked ) )

            AND st.id_segment
              BETWEEN jobs.job_first_segment AND jobs.job_last_segment
             ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $stmt->execute( array_merge( [ $chunk->id, $chunk->password ], $statuses ) );

        $count = $stmt->fetch();

        $score = $count[ 0 ] == null ? 0 : $count[ 0 ];

        return $score;
    }

    /**
     * @param $id_job
     * @param $password
     * @param $source_page
     *
     * @return mixed
     */
    public function getReviewedWordsCountForSecondPass( $chunk, $source_page ) {
        $sql = " SELECT SUM(raw_word_count) FROM segments s 
 
        JOIN segment_translations st on st.id_segment = s.id 
        JOIN jobs j on j.id = st.id_job 
                AND s.id <= j.job_last_segment 
                AND s.id >= j.job_first_segment 
        JOIN 
                segment_translation_events ste on ste.id_segment = s.id 
                AND ste.final_revision = 1      
                AND ste.source_page = :source_page
                AND ste.id_job = :id_job
        WHERE 
                j.id = :id_job AND j.password = :password ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id_job' => $chunk->id, 'password' => $chunk->password, 'source_page' => $source_page ] );

        $result = $stmt->fetch();

        return $result[ 0 ] == null ? 0 : $result[ 0 ];
    }

    /**
     * @param Chunks_ChunkStruct $chunkStruct
     *
     * @return ChunkReviewStruct[]
     */
    public function findChunkReviews( Chunks_ChunkStruct $chunkStruct, $ttl = null ) {
        return $this->_findChunkReviews( [ $chunkStruct ], null, $ttl );
    }

    /**
     * @param Chunks_ChunkStruct[] $chunkStructsArray
     *
     * @return ChunkReviewStruct[]
     */
    public function findChunkReviewsForList( Array $chunkStructsArray ) {
        return $this->_findChunkReviews( $chunkStructsArray );
    }

    /**
     * @param Chunks_ChunkStruct $chunkStruct
     * @param int                $source_page
     *
     * @return ChunkReviewStruct[]
     */
    public function findChunkReviewsForSourcePage( Chunks_ChunkStruct $chunkStruct, $source_page = Constants::SOURCE_PAGE_REVISION ) {
        $sql_condition = " WHERE source_page = $source_page ";

        return $this->_findChunkReviews( [ $chunkStruct ], $sql_condition );
    }

    /**
     * @param Chunks_ChunkStruct[] $chunksArray
     * @param string               $default_condition
     *
     * @return DataAccess_IDaoStruct[]|ChunkReviewStruct[]
     */
    protected function _findChunkReviews( Array $chunksArray, $default_condition = ' WHERE 1 = 1 ' , $ttl = 1 /* 1 second, only to avoid multiple queries to mysql during the same script execution */) {

        $_conditions = [];
        $_parameters = [];
        foreach ( $chunksArray as $chunk ) {
            $_conditions[] = " ( jobs.id = ? AND jobs.password = ? ) ";
            $_parameters[] = $chunk->id;
            $_parameters[] = $chunk->password;
        }

        $default_condition .= " AND " . implode( ' OR ', $_conditions );

        $sql = /** @lang text */
                "SELECT qa_chunk_reviews.* 
                FROM jobs 
                INNER JOIN qa_chunk_reviews ON jobs.id = qa_chunk_reviews.id_job AND jobs.password = qa_chunk_reviews.password 
                " . $default_condition . " 
                ORDER BY source_page";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        return $this->setCacheTTL( $ttl )->_fetchObject( $stmt, new ChunkReviewStruct(), $_parameters );

    }

    /**
     * Return a ShapelessConcreteStruct with 3 boolean fields (1/0):
     * - t
     * - r1
     * - r2
     *
     * @param     $jid
     * @param     $password
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct
     */
    public function isTOrR1OrR2( $jid, $password, $ttl = 3600 ) {

        $sql = "SELECT 
            (SELECT count(id) from qa_chunk_reviews cr where cr.id_job = :jid and cr.password=:password) as t,
            (SELECT count(id) from qa_chunk_reviews cr where cr.id_job = :jid and cr.review_password=:password and cr.source_page = 2) as r1,
            (SELECT count(id) from qa_chunk_reviews cr where cr.id_job = :jid and cr.review_password=:password and cr.source_page = 3) as r2
        from jobs where id = :jid;";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $parameters = [
                'password' => $password,
                'jid'      => $jid
        ];

        return $this->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), $parameters )[0];
    }

    /**
     * @return ChunkReviewStruct[]
     */

    public static function findByProjectId( $id_project ) {
        $sql  = "SELECT * FROM qa_chunk_reviews " .
                " WHERE id_project = :id_project ORDER BY id ";
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute( [ 'id_project' => $id_project ] );

        return $stmt->fetchAll();
    }

    /**
     * @param     $review_password
     * @param     $id_job
     * @param int $source_page
     *
     * @return ChunkReviewStruct
     */

    public static function findByReviewPasswordAndJobId( $review_password, $id_job ) {
        $sql = "SELECT * FROM qa_chunk_reviews " .
                " WHERE review_password = :review_password " .
                " AND id_job = :id_job ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute(
                [
                        'review_password' => $review_password,
                        'id_job'          => $id_job
                ]
        );

        return $stmt->fetch();
    }

    /**
     * @param $id_job
     *
     * @return ChunkReviewStruct
     */
    public function findLatestRevisionByIdJob( $id_job ) {
        $sql = "SELECT * FROM qa_chunk_reviews " .
                " WHERE id_job = :id_job " .
                " ORDER BY id DESC LIMIT 1 ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute(
                [
                        'id_job' => $id_job,
                ]
        );

        return $stmt->fetch();
    }

    /**
     * @return ChunkReviewStruct
     */
    public function findLastReviewByJobIdPasswordAndSourcePage( $id_job, $password, $source_page ) {
        $sql = "SELECT * FROM qa_chunk_reviews " .
                " WHERE password = :password " .
                " AND id_job = :id_job " .
                " AND source_page = :source_page ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute(
                [
                        'password'    => $password,
                        'id_job'      => $id_job,
                        'source_page' => $source_page
                ]
        );

        return $stmt->fetch();
    }

    /**
     * @return ChunkReviewStruct
     */
    public function findByJobIdReviewPasswordAndSourcePage( $id_job, $review_password, $source_page ) {
        $sql = "SELECT * FROM qa_chunk_reviews " .
                " WHERE review_password = :review_password " .
                " AND id_job = :id_job " .
                " AND source_page = :source_page ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute(
                [
                        'review_password'    => $review_password,
                        'id_job'      => $id_job,
                        'source_page' => $source_page
                ]
        );

        return $stmt->fetch();
    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return ChunkReviewStruct[]
     */
    public static function findByJobIdAndPassword( $id_job, $password ) {

        $conn    = \Database::obtain()->getConnection();
        $stmt    = $conn->prepare( " 
            SELECT * FROM " . self::TABLE . " 
            WHERE id_job = :id_job 
            and password = :password 
         " );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute( [
                'id_job'   => $id_job,
                'password' => $password,
        ] );

        return $stmt->fetchAll();
    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return bool
     */
    public function exists( $id_job, $password, $source_page = null ) {

        $params = [
                'id_job'   => $id_job,
                'password' => $password,
        ];

        $query = " SELECT id FROM " . self::TABLE . " WHERE id_job = :id_job and password = :password ";

        if ($source_page) {
            $params['source_page'] = $source_page;
            $query .= " AND source_page=:source_page";
        }

        $conn    = \Database::obtain()->getConnection();
        $stmt    = $conn->prepare( $query );


        $stmt->execute($params );

        $row = $stmt->fetch( \PDO::FETCH_ASSOC );

        if ( !$row ) {
            return false;
        }

        return true;
    }

    /**
     * @param      $data array of data to use
     *
     * @return ChunkReviewStruct
     * @throws \Exceptions\ValidationError
     * @throws \ReflectionException
     * @internal param bool $setDefaults
     */
    public static function createRecord( $data ) {
        $struct = new ChunkReviewStruct( $data );

        $struct->ensureValid();
        $struct->setDefaults();

        $attrs = $struct->toArray( [
                'id_project',
                'id_job',
                'password',
                'review_password',
                'source_page',
                'advancement_wc',
                'total_tte',
                'avg_pee'
        ] );

        $sql = "INSERT INTO " . self::TABLE .
                " ( id_project, id_job, password, review_password, source_page, advancement_wc, total_tte, avg_pee ) " .
                " VALUES " .
                " ( :id_project, :id_job, :password, :review_password, :source_page, :advancement_wc, :total_tte, :avg_pee ) ";

        $conn = \Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->execute( $attrs );

        $struct->id = $conn->lastInsertId();

        return $struct;
    }

    public static function deleteByJobId( $id_job ) {
        $sql  = "DELETE FROM qa_chunk_reviews WHERE id_job = :id_job ";
        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        return $stmt->execute( [ 'id_job' => $id_job ] );
    }

    /**
     * @param array $chunk_ids
     *
     * @return \LQA\ChunkReviewStruct[]
     */
    public static function findSecondRevisionsChunkReviewsByChunkIds( array $chunk_ids ) {
        $source_page = Constants::SOURCE_PAGE_REVISION;

        $sql_condition = " WHERE source_page > $source_page ";

        if ( count( $chunk_ids ) > 0 ) {
            $conditions    = array_map( function ( $ids ) {
                return " ( jobs.id = " . $ids[ 0 ] .
                        " AND jobs.password = '" . $ids[ 1 ] . "' ) ";
            }, $chunk_ids );
            $sql_condition .= " AND " . implode( ' OR ', $conditions );
        }

        $sql = "SELECT qa_chunk_reviews.* " .
                " FROM jobs INNER JOIN qa_chunk_reviews ON " .
                " jobs.id = qa_chunk_reviews.id_job AND " .
                " jobs.password = qa_chunk_reviews.password " .
                $sql_condition;

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( \PDO::FETCH_CLASS, 'LQA\ChunkReviewStruct' );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * @param $chunk
     *
     * @return array
     */
    public function countWordsInRevisionsForChunk( Chunks_ChunkStruct $chunk ) {
        $sql = "SELECT source_page, SUM( eq_word_count ) eq_word_count, SUM( raw_word_count ) raw_word_count
                FROM (
                    SELECT
                      st.id_job, ste.id, st.status, ste.source_page, ste.final_revision, st.id_segment, st.eq_word_count, s.raw_word_count

                    FROM jobs j
                            JOIN segment_translations st ON j.id = st.id_job AND
                            st.id_segment BETWEEN  j.job_first_segment AND j.job_last_segment
                            JOIN segments s on s.id = st.id_segment
                            LEFT JOIN segment_translation_events ste ON ste.id_segment = st.id_segment
                            WHERE st.id_job = :id_job
                                AND j.password = :password
                                AND ( final_revision = 1 OR (
                                    st.status = 'APPROVED' AND ste.id = null
                                ) )
                ) sums GROUP BY id_job, source_page ; ";

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id_job' => $chunk->id, 'password' => $chunk->password ] );

        return $stmt->fetchAll();
    }

}
