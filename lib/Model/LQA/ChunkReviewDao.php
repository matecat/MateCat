<?php

namespace LQA;

use Constants;
use DataAccess\AbstractDao;
use DataAccess\ShapelessConcreteStruct;
use Database;
use Exception;
use Features\ReviewExtended\ReviewUtils;
use Jobs_JobStruct;
use PDO;
use ReflectionException;

class ChunkReviewDao extends AbstractDao {

    const TABLE = "qa_chunk_reviews";

    public static array $primary_keys = [
            'id'
    ];

    protected function _buildResult( array $array_result ) {
    }

    public function updatePassword( $id_job, $old_password, $new_password ) {
        $sql = "UPDATE qa_chunk_reviews SET password = :new_password
               WHERE id_job = :id_job AND password = :old_password ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'       => $id_job,
                'old_password' => $old_password,
                'new_password' => $new_password
        ] );

        return $stmt->rowCount();
    }

    public function updateReviewPassword( $id_job, $old_review_password, $new_review_password, $source_page ) {
        $sql = "UPDATE qa_chunk_reviews SET review_password = :new_review_password
               WHERE id_job = :id_job AND review_password = :old_review_password AND source_page = :source_page";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'              => $id_job,
                'old_review_password' => $old_review_password,
                'new_review_password' => $new_review_password,
                'source_page'         => $source_page
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
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ChunkReviewStruct::class );
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
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ChunkReviewStruct::class );
        $stmt->execute( [
                'id_job'      => $id_job,
                'password'    => $password,
                'source_page' => $source_page,
        ] );

        $results = $stmt->fetchAll();

        return ( isset( $results[ 0 ] ) ) ? $results[ 0 ] : null;
    }

    /**
     * @param $id
     *
     * @return ChunkReviewStruct
     */
    public static function findById( $id ) {
        $sql  = "SELECT * FROM qa_chunk_reviews " .
                " WHERE id = :id ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ChunkReviewStruct::class );
        $stmt->execute( [ 'id' => $id ] );

        return $stmt->fetch();

    }

    /**
     * @param Jobs_JobStruct $chunk
     *
     * @param null               $source_page
     *
     * @return int
     */
    public function getPenaltyPointsForChunk( Jobs_JobStruct $chunk, $source_page = null ) {
        if ( is_null( $source_page ) ) {
            $source_page = Constants::SOURCE_PAGE_REVISION;
        }

        $sql = "SELECT SUM(penalty_points) FROM qa_entries e
                JOIN jobs j on j.id = e.id_job
                    AND e.id_segment >= j.job_first_segment
                    AND e.id_segment <= j.job_last_segment
                WHERE j.id = :id_job
                    AND j.password = :password
                    AND source_page = :source_page
                    AND e.deleted_at IS NULL
        ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'      => $chunk->id,
                'password'    => $chunk->password,
                'source_page' => $source_page
        ] );

        $count = $stmt->fetch();

        $penalty_points = $count[ 0 ] == null ? 0 : $count[ 0 ];

        return $penalty_points;
    }

    public function countTimeToEdit( Jobs_JobStruct $chunk, $source_page ) {
        $sql = "
            SELECT SUM( time_to_edit ) FROM jobs
                JOIN segment_translation_events ste
                  ON jobs.id = ste.id_job
                  AND ste.id_segment >= jobs.job_first_segment AND ste.id_segment <= jobs.job_last_segment

                WHERE jobs.id = :id_job AND jobs.password = :password
                  AND ste.source_page = :source_page

                  GROUP BY ste.source_page

        ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'      => $chunk->id,
                'password'    => $chunk->password,
                'source_page' => $source_page,
        ] );

        $result = $stmt->fetch();

        return ( !$result || $result[ 0 ] == null ) ? 0 : $result[ 0 ];
    }

    /**
     * @param $chunk
     * @param $source_page
     *
     * @return mixed
     */
    public function getReviewedWordsCountForSecondPass( $chunk, $source_page ) {

        $translationStatus = ReviewUtils::sourcePageToTranslationStatus( $source_page );

        $sql = "SELECT SUM(raw_word_count) 
        FROM segments s 
        JOIN segment_translations st on st.id_segment = s.id 
        JOIN jobs j on j.id = st.id_job 
                AND s.id <= j.job_last_segment 
                AND s.id >= j.job_first_segment 
        WHERE 
                j.id = :id_job 
            AND j.password = :password 
            AND st.status = :translation_status
            AND st.version_number != 0
        ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id_job'             => $chunk->id,
                'password'           => $chunk->password,
                'translation_status' => $translationStatus
        ] );

        $result = $stmt->fetch();

        return $result[ 0 ] == null ? 0 : $result[ 0 ];
    }

    /**
     * @param Jobs_JobStruct $chunkStruct
     * @param int|null       $ttl
     *
     * @return ChunkReviewStruct[]
     * @throws ReflectionException
     */
    public function findChunkReviews( Jobs_JobStruct $chunkStruct, ?int $ttl = 0 ): array {
        return $this->_findChunkReviews( [ $chunkStruct ], null, $ttl );
    }

    /**
     * @param Jobs_JobStruct[] $chunkStructsArray
     *
     * @return ChunkReviewStruct[]
     */
    public function findChunkReviewsForList( array $chunkStructsArray ) {
        return $this->_findChunkReviews( $chunkStructsArray );
    }

    /**
     * @param Jobs_JobStruct $chunkStruct
     * @param int                $source_page
     *
     * @return ChunkReviewStruct[]
     */
    public function findChunkReviewsForSourcePage( Jobs_JobStruct $chunkStruct, int $source_page = Constants::SOURCE_PAGE_REVISION ): array {
        $sql_condition = " WHERE source_page = $source_page ";

        return $this->_findChunkReviews( [ $chunkStruct ], $sql_condition );
    }

    /**
     * @param Jobs_JobStruct[] $chunksArray
     * @param string|null      $default_condition
     * @param int|null         $ttl
     *
     * @return ChunkReviewStruct[]
     * @throws ReflectionException
     */
    protected function _findChunkReviews( array $chunksArray, ?string $default_condition = ' WHERE 1 = 1 ', ?int $ttl = 1 /* 1 second, only to avoid multiple queries to mysql during the same script execution */ ): array {

        $findChunkReviewsStatement = $this->_findChunkReviewsStatement( $chunksArray, $default_condition );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $findChunkReviewsStatement[ 'sql' ] );

        return $this->setCacheTTL( $ttl )->_fetchObject( $stmt, new ChunkReviewStruct(), $findChunkReviewsStatement[ 'parameters' ] );

    }

    /**
     * @param Jobs_JobStruct $chunkStruct
     *
     * @return bool|int
     */
    public function destroyCacheForFindChunkReviews( Jobs_JobStruct $chunkStruct ) {

        $findChunkReviewsStatement = $this->_findChunkReviewsStatement( [ $chunkStruct ], null );
        $stmt                      = $this->_getStatementForQuery( $findChunkReviewsStatement[ 'sql' ] );

        return $this->_destroyObjectCache( $stmt, ChunkReviewStruct::class, $findChunkReviewsStatement[ 'parameters' ] );

    }

    /**
     * @param array       $chunksArray
     * @param string|null $default_condition
     *
     * @return array
     */
    private function _findChunkReviewsStatement( array $chunksArray, ?string $default_condition = ' WHERE 1 = 1 ' ): array {
        $_conditions = [];
        $_parameters = [];
        foreach ( $chunksArray as $chunk ) {
            $_conditions[] = " ( jobs.id = ? AND jobs.password = ? ) ";
            $_parameters[] = $chunk->id;
            $_parameters[] = $chunk->password;
        }

        $default_condition .= " AND " . implode( ' OR ', $_conditions );

        $sql =
                "SELECT qa_chunk_reviews.* 
                FROM jobs 
                INNER JOIN qa_chunk_reviews ON jobs.id = qa_chunk_reviews.id_job AND jobs.password = qa_chunk_reviews.password 
                " . $default_condition . " 
                ORDER BY source_page";

        return [
                'sql'        => $sql,
                'parameters' => $_parameters,
        ];
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
     * @return \DataAccess\IDaoStruct
     */
    public function isTOrR1OrR2( $jid, $password, $ttl = 3600 ) {

        $sql = "SELECT 
            (SELECT count(id) from qa_chunk_reviews cr where cr.id_job = :jid and cr.password=:password) as t,
            (SELECT count(id) from qa_chunk_reviews cr where cr.id_job = :jid and cr.review_password=:password and cr.source_page = 2) as r1,
            (SELECT count(id) from qa_chunk_reviews cr where cr.id_job = :jid and cr.review_password=:password and cr.source_page = 3) as r2
        from jobs where id = :jid;";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $parameters = [
                'password' => $password,
                'jid'      => $jid
        ];

        return $this->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), $parameters )[ 0 ];
    }

    /**
     * @return ChunkReviewStruct[]
     */

    public static function findByProjectId( $id_project ) {
        $sql  = "SELECT * FROM qa_chunk_reviews " .
                " WHERE id_project = :id_project ORDER BY id ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ChunkReviewStruct::class );
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

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ChunkReviewStruct::class );
        $stmt->execute(
                [
                        'review_password' => $review_password,
                        'id_job'          => $id_job
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

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ChunkReviewStruct::class );
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

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ChunkReviewStruct::class );
        $stmt->execute(
                [
                        'review_password' => $review_password,
                        'id_job'          => $id_job,
                        'source_page'     => $source_page
                ]
        );

        return $stmt->fetch();
    }


    /**
     * @param      $id_job
     * @param      $password
     * @param null $source_page
     *
     * @return bool
     */
    public function exists( $id_job, $password, $source_page = null ) {

        $params = [
                'id_job'   => $id_job,
                'password' => $password,
        ];

        $query = " SELECT id FROM " . self::TABLE . " WHERE id_job = :id_job and password = :password ";

        if ( $source_page ) {
            $params[ 'source_page' ] = $source_page;
            $query                   .= " AND source_page=:source_page";
        }

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $query );


        $stmt->execute( $params );

        $row = $stmt->fetch( PDO::FETCH_ASSOC );

        if ( !$row ) {
            return false;
        }

        return true;
    }

    /**
     * @param      $data array of data to use
     *
     * @return ChunkReviewStruct
     * @throws ReflectionException
     * @internal param bool $setDefaults
     */
    public static function createRecord( $data ) {
        $struct = new ChunkReviewStruct( $data );

        $struct->setDefaults();

        $attrs = $struct->toArray( [
                'id_project',
                'id_job',
                'password',
                'review_password',
                'source_page',
                'total_tte',
                'avg_pee'
        ] );

        $sql = "INSERT INTO " . self::TABLE .
                " ( id_project, id_job, password, review_password, source_page, total_tte, avg_pee ) " .
                " VALUES " .
                " ( :id_project, :id_job, :password, :review_password, :source_page, :total_tte, :avg_pee ) 
                    ON DUPLICATE KEY UPDATE
                        id_project = :id_project,
                        id_job = :id_job,
                        password = :password,
                        review_password = :review_password,
                        source_page = :source_page,
                        total_tte = :total_tte,
                        avg_pee = :avg_pee
                
                ";

        $conn = Database::obtain()->getConnection();

        $stmt = $conn->prepare( $sql );
        $stmt->execute( $attrs );

        $struct->id = $conn->lastInsertId();

        return $struct;
    }

    public static function deleteByJobId( $id_job ) {
        $sql  = "DELETE FROM qa_chunk_reviews WHERE id_job = :id_job ";
        $conn = Database::obtain()->getConnection();
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

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ChunkReviewStruct::class );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     *
     * @param int   $chunkReviewID
     * @param array $data
     *
     * @throws Exception
     */
    public function passFailCountsAtomicUpdate( $chunkReviewID, $data = [] ) {

        /**
         * @var $chunkReview ChunkReviewStruct
         */
        $chunkReview             = $data[ 'chunkReview' ];
        $data[ 'force_pass_at' ] = ReviewUtils::filterLQAModelLimit( $chunkReview->getChunk()->getProject()->getLqaModel(), $chunkReview->source_page );

        // in MySQL a sum of a null value to an integer returns 0
        // in MySQL division by zero returns NULL, so we have to coalesce null values from is_pass division
        $sql = "INSERT INTO 
            qa_chunk_reviews ( id, id_job, password, penalty_points, reviewed_words_count, total_tte ) 
        VALUES( 
            :id,
            :id_job,
            :password,
            :penalty_points,
            :reviewed_words_count,
            :total_tte
        ) ON DUPLICATE KEY UPDATE
        penalty_points = GREATEST( COALESCE( penalty_points, 0 ) + COALESCE( VALUES( penalty_points ), 0 ), 0 ),
        reviewed_words_count = GREATEST( reviewed_words_count + VALUES( reviewed_words_count ), 0 ),
        total_tte = GREATEST( total_tte + VALUES( total_tte ), 0 ),        
        is_pass = IF( 
				COALESCE(
					penalty_points
					/ reviewed_words_count * 1000 
					, 0
				) <= {$data[ 'force_pass_at' ]}, 1, 0
		);";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'id'                   => $chunkReviewID,
                'id_job'               => $chunkReview->id_job,
                'password'             => $chunkReview->password,
                'penalty_points'       => empty( $data[ 'penalty_points' ] ) ? 0 : $data[ 'penalty_points' ],
                'reviewed_words_count' => $data[ 'reviewed_words_count' ],
                'total_tte'            => $data[ 'total_tte' ],
        ] );

    }

}
