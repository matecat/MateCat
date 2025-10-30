<?php

namespace Model\LQA;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use PDO;
use Plugins\Features\ReviewExtended\ReviewUtils;
use ReflectionException;
use Utils\Constants\SourcePages;

class ChunkReviewDao extends AbstractDao {

    const string TABLE = "qa_chunk_reviews";

    public static array $primary_keys = [
            'id'
    ];

    const string sql_for_get_by_project_id = "SELECT * FROM qa_chunk_reviews WHERE id_project = :id_project ORDER BY id";

    protected function _buildResult( array $array_result ) {
    }

    public function updatePassword( int $id_job, string $old_password, string $new_password ): int {
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

    public function updateReviewPassword( int $id_job, string $old_review_password, string $new_review_password, int $source_page ): int {
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
    public static function findByIdJob( $id_job ): array {
        $sql  = "SELECT * FROM qa_chunk_reviews " .
                " WHERE id_job = :id_job ORDER BY id";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ChunkReviewStruct::class );
        $stmt->execute( [ 'id_job' => $id_job ] );

        return $stmt->fetchAll();
    }

    /**
     * @param int      $id_job
     * @param string   $password
     * @param int|null $source_page
     *
     * @return ChunkReviewStruct
     */
    public static function findByIdJobAndPasswordAndSourcePage( int $id_job, string $password, ?int $source_page ): ?ChunkReviewStruct {
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

        return $results[ 0 ] ?? null;
    }

    /**
     * @param int $id
     *
     * @return ?ChunkReviewStruct
     */
    public static function findById( int $id ): ?ChunkReviewStruct {
        $sql  = "SELECT * FROM qa_chunk_reviews " .
                " WHERE id = :id ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->setFetchMode( PDO::FETCH_CLASS, ChunkReviewStruct::class );
        $stmt->execute( [ 'id' => $id ] );

        return $stmt->fetch() ?: null;

    }

    /**
     * @param JobStruct $chunk
     *
     * @param int|null  $source_page
     *
     * @return int
     */
    public function getPenaltyPointsForChunk( JobStruct $chunk, ?int $source_page = null ): int {
        if ( is_null( $source_page ) ) {
            $source_page = SourcePages::SOURCE_PAGE_REVISION;
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

        $count = $stmt->fetch() ?: [];

        return $count[ 0 ] ?? 0;
    }

    public function countTimeToEdit( JobStruct $chunk, $source_page ) {
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
     * @param JobStruct $chunkStruct
     * @param int|null  $ttl
     *
     * @return ChunkReviewStruct[]
     * @throws ReflectionException
     */
    public function findChunkReviews( JobStruct $chunkStruct, ?int $ttl = 0 ): array {
        return $this->_findChunkReviews( [ $chunkStruct ], null, $ttl );
    }

    /**
     * @param JobStruct $chunkStruct
     * @param int       $source_page
     * @param int       $ttl
     *
     * @return ChunkReviewStruct[]
     * @throws ReflectionException
     */
    public function findChunkReviewsForSourcePage( JobStruct $chunkStruct, int $source_page = SourcePages::SOURCE_PAGE_REVISION, int $ttl = 60 ): array {
        $sql_condition = " WHERE source_page = $source_page ";

        return $this->_findChunkReviews( [ $chunkStruct ], $sql_condition, $ttl );
    }

    /**
     * @param JobStruct[] $chunksArray
     * @param string|null $default_condition
     * @param int|null    $ttl
     *
     * @return ChunkReviewStruct[]
     * @throws ReflectionException
     */
    protected function _findChunkReviews( array $chunksArray, ?string $default_condition = ' WHERE 1 = 1 ', ?int $ttl = 1 /* 1 second, only to avoid multiple queries to mysql during the same script execution */ ): array {

        $findChunkReviewsStatement = $this->_findChunkReviewsStatement( $chunksArray, $default_condition );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $findChunkReviewsStatement[ 'sql' ] );

        return $this->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, ChunkReviewStruct::class, $findChunkReviewsStatement[ 'parameters' ] );

    }

    /**
     * @param JobStruct $chunkStruct
     *
     * @return bool
     * @throws ReflectionException
     */
    public function destroyCacheForFindChunkReviews( JobStruct $chunkStruct ): bool {

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
     * @param int    $jid
     * @param string $password
     * @param int    $ttl
     *
     * @return IDaoStruct
     * @throws ReflectionException
     */
    public function isTOrR1OrR2( int $jid, string $password, int $ttl = 3600 ): ?IDaoStruct {

        $sql = "SELECT 
            (SELECT count(id) from qa_chunk_reviews cr where cr.id_job = :jid and cr.password=:password) as t,
            (SELECT count(id) from qa_chunk_reviews cr where cr.id_job = :jid and cr.review_password=:password and cr.source_page = 2) as r1,
            (SELECT count(id) from qa_chunk_reviews cr where cr.id_job = :jid and cr.review_password=:password and cr.source_page = 3) as r2
        from DUAL";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        $parameters = [
                'password' => $password,
                'jid'      => $jid
        ];

        return $this->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, ShapelessConcreteStruct::class, $parameters )[ 0 ] ?? null;
    }

    /**
     * @param int $id_project
     * @param int $ttl
     *
     * @return array
     * @throws ReflectionException
     */
    public static function findByProjectId( int $id_project, int $ttl = 60 * 60 ): array {
        $self = new self();
        $self->setCacheTTL( $ttl );
        $stmt = $self->_getStatementForQuery( self::sql_for_get_by_project_id );

        return $self->_fetchObjectMap( $stmt, ChunkReviewStruct::class, [ 'id_project' => $id_project ] );

    }

    /**
     * @throws ReflectionException
     */
    public static function destroyCacheByProjectId( int $id_project ): bool {
        $self = new self();
        $stmt = $self->_getStatementForQuery( self::sql_for_get_by_project_id );

        return $self->_destroyObjectCache( $stmt, ChunkReviewStruct::class, [ 'id_project' => $id_project ] );
    }

    /**
     * @param     $review_password
     * @param     $id_job
     *
     * @return ?ChunkReviewStruct
     */

    public static function findByReviewPasswordAndJobId( $review_password, $id_job ): ?ChunkReviewStruct {
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

        return $stmt->fetch() ?: null;
    }

    /**
     * @param $id_job
     * @param $password
     * @param $source_page
     *
     * @return ?ChunkReviewStruct
     */
    public function findLastReviewByJobIdPasswordAndSourcePage( $id_job, $password, $source_page ): ?ChunkReviewStruct {
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

        return $stmt->fetch() ?: null;
    }

    /**
     * @return ChunkReviewStruct
     * @throws ReflectionException
     */
    public function findByJobIdReviewPasswordAndSourcePage( int $id_job, string $review_password, int $source_page, int $ttl = 60 * 60 ): ?ChunkReviewStruct {
        $sql = "SELECT * FROM qa_chunk_reviews " .
                " WHERE review_password = :review_password " .
                " AND id_job = :id_job " .
                " AND source_page = :source_page ";

        $this->setCacheTTL( $ttl );
        $stmt = $this->_getStatementForQuery( $sql );
        /** @var $retValue ChunkReviewStruct */
        $retValue = $this->_fetchObjectMap( $stmt, ChunkReviewStruct::class, [
                'review_password' => $review_password,
                'id_job'          => $id_job,
                'source_page'     => $source_page
        ] )[ 0 ] ?? null;

        return $retValue;
    }


    /**
     * @param int      $id_job
     * @param string   $password
     * @param int|null $source_page
     *
     * @return bool
     */
    public function exists( int $id_job, string $password, ?int $source_page = null ): bool {

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
     * @internal param bool $setDefaults
     */
    public static function createRecord( array $data ): ChunkReviewStruct {
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

    public static function deleteByJobId( int $id_job ): bool {
        $sql  = "DELETE FROM qa_chunk_reviews WHERE id_job = :id_job ";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        return $stmt->execute( [ 'id_job' => $id_job ] );
    }

    /**
     *
     * @param int   $chunkReviewID
     * @param array $data
     *
     * @throws Exception
     */
    public function passFailCountsAtomicUpdate( int $chunkReviewID, array $data = [] ) {

        /**
         * @var $chunkReview ChunkReviewStruct
         */
        $chunkReview             = $data[ 'chunkReview' ];
        $data[ 'force_pass_at' ] = ReviewUtils::filterLQAModelLimit( $chunkReview->getChunk()->getProject()->getLqaModel(), $chunkReview->source_page );

        // in MySQL a sum of a null value to an integer returns 0
        // in MySQL, division by zero returns NULL, so we have to coalesce null values from is_pass division
        $sql = "INSERT INTO 
            qa_chunk_reviews ( id, id_job, id_project, password, review_password, penalty_points, reviewed_words_count, total_tte ) 
        VALUES( 
            :id,
            :id_job,
            :id_project,
            :password,
            :review_password,
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
                'id_project'           => $chunkReview->id_project,
                'review_password'      => $chunkReview->review_password,
                'password'             => $chunkReview->password,
                'penalty_points'       => empty( $data[ 'penalty_points' ] ) ? 0 : $data[ 'penalty_points' ],
                'reviewed_words_count' => $data[ 'reviewed_words_count' ],
                'total_tte'            => $data[ 'total_tte' ],
        ] );

    }

}
