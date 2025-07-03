<?php

namespace Model\Jobs;

use Constants_JobStatus;
use Constants_TranslationStatus;
use Database;
use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\EditLog\EditLogSegmentStruct;
use Model\Exceptions\ValidationError;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use PDOException;
use PDOStatement;
use ReflectionException;

class JobDao extends AbstractDao {

    const TABLE       = "jobs";
    const STRUCT_TYPE = "JobStruct";

    protected static array $auto_increment_field = [ 'id' ];
    protected static array $primary_keys         = [ 'id', 'password' ];

    protected static string $_sql_update_password = "UPDATE jobs SET password = :new_password, last_update = :last_update WHERE id = :id AND password = :old_password ";

    protected static string $_sql_get_jobs_by_project = "SELECT * FROM jobs WHERE id_project = ? AND status_owner != ? ORDER BY id, job_first_segment;";

    protected static string $_sql_get_by_segment_translation = "select * from jobs where id = :id_job AND jobs.job_first_segment <= :id_segment AND jobs.job_last_segment >= :id_segment ";

    protected static string $_query_cache = "SELECT * FROM jobs WHERE  id = :id_job AND password = :password ";

    /**
     * This method is not static and used to cache at Redis level the values for this Job
     *
     * Use when counters of the job value are not important but only the metadata are needed
     *
     * @param JobStruct $jobQuery
     *
     * @return JobStruct[]
     * @throws ReflectionException
     * @see \Contribution\ContributionSetStruct
     *
     * @see \AsyncTasks\Workers\SetContributionWorker
     */
    public function read( JobStruct $jobQuery ): array {

        $stmt = $this->_getStatementForQuery( self::$_query_cache );

        /** @var JobStruct[] */
        return $this->_fetchObjectMap( $stmt,
                get_class( $jobQuery ),
                [
                        'id_job'   => $jobQuery->id,
                        'password' => $jobQuery->password
                ]
        );

    }

    /**
     * @param array $array_result
     *
     * @return void
     */
    protected function _buildResult( array $array_result ) {
    }

    /**
     * Destroy a cached object
     *
     * @param JobStruct $jobQuery
     *
     * @return bool
     * @throws Exception
     */
    public function destroyCache( JobStruct $jobQuery ): bool {
        /*
        * build the query
        */
        $stmt = $this->_getStatementForQuery( self::$_query_cache );

        return $this->_destroyObjectCache( $stmt,
                JobStruct::class,
                [
                        'id_job'   => $jobQuery->id,
                        'password' => $jobQuery->password
                ]
        );
    }

    /**
     * @param SegmentTranslationStruct $translation
     * @param int                      $ttl
     *
     * @return JobStruct
     * @throws ReflectionException
     */
    public static function getBySegmentTranslation( SegmentTranslationStruct $translation, int $ttl = 0 ): JobStruct {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( static::$_sql_get_by_segment_translation );

        /**
         * @var JobStruct
         */
        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new JobStruct, [
                'id_job'     => $translation->id_job,
                'id_segment' => $translation->id_segment
        ] )[ 0 ];

    }

    /**
     * @param int    $id_job
     * @param string $password
     * @param int    $ttl
     *
     * @return int
     * @throws ReflectionException
     */
    public static function getSegmentsCount( int $id_job, string $password, int $ttl = 0 ): int {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( "
            select count(st.id_segment) as total 
            from segment_translations st
            join jobs j on j.id=st.id_job and st.id_segment BETWEEN j.job_first_segment AND j.job_last_segment
            where j.id = :id_job and j.password = :password" );

        $struct = $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job'   => $id_job,
                'password' => $password
        ] )[ 0 ] ?? null;

        if ( !empty( $struct->total ) ) {
            return (int)$struct->total;
        }

        return 0;

    }

    /**
     * Get the job's owner uid
     *
     * @param int    $id_job
     * @param string $password
     * @param int    $ttl
     *
     * @return null|int
     * @throws ReflectionException
     */
    public static function getOwnerUid( int $id_job, string $password, int $ttl = 86400 ): ?int {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare(
                "
                SELECT uid FROM users u
                join jobs j on j.owner = u.email
                where j.id = :id_job and password = :password
            ;
            "
        );

        $data = $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job'   => $id_job,
                'password' => $password
        ] )[ 0 ] ?? null;

        if ( empty( $data ) ) {
            return null;
        }

        return $data->uid ?? null;
    }

    /**
     * @param int    $id_job
     * @param string $password
     * @param int    $ttl
     *
     * @return JobStruct|null
     * @throws ReflectionException
     */
    public static function getByIdAndPassword( int $id_job, string $password, int $ttl = 0 ): ?JobStruct {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare(
                "SELECT * FROM jobs WHERE " .
                " id = :id_job AND password = :password "
        );

        /**
         * @var $res JobStruct
         */
        $res = $thisDao->setCacheTTL( $ttl )->_fetchObjectMap( $stmt, JobStruct::class, [
                'id_job'   => $id_job,
                'password' => $password
        ] )[ 0 ] ?? null;

        return $res;
    }

    /**
     * @param $project_id
     *
     * @return bool
     * @throws ReflectionException
     */
    public function destroyCacheByProjectId( $project_id ): bool {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( self::$_sql_get_jobs_by_project );

        $this->_destroyObjectCache( $stmt, JobStruct::class, [ $project_id, Constants_JobStatus::STATUS_DELETED ] );

        return $this->_destroyObjectCache( $stmt, JobStruct::class, [ $project_id, Constants_JobStatus::STATUS_DELETED ] );
    }

    /**
     * @param int $id_project
     * @param int $ttl
     *
     * @return JobStruct[]
     * @throws ReflectionException
     */
    public static function getByProjectId( int $id_project, int $ttl = 0 ): array {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( self::$_sql_get_jobs_by_project );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new JobStruct(), [ $id_project, Constants_JobStatus::STATUS_DELETED ] );

    }

    /**
     * @param int    $id
     * @param string $password
     * @param int    $ttl
     *
     * @return ShapelessConcreteStruct[]
     * @throws ReflectionException
     * @internal param $requestedWordsPerSplit
     *
     * @internal param JobStruct $chunk
     */
    public function getSplitData( int $id, string $password, int $ttl = 0 ): array {
        $conn = $this->getDatabaseHandler()->getConnection();

        /**
         * Select all rows raw_word_count and eq_word_count
         * and their totals ( ROLLUP )
         * reserve also two columns for job_first_segment and job_last_segment
         *
         * +----------------+-------------------+---------+-------------------+------------------+
         * | raw_word_count | eq_word_count     | id      | job_first_segment | job_last_segment |
         * +----------------+-------------------+---------+-------------------+------------------+
         * |          26.00 |             22.10 | 2390662 |           2390418 |          2390665 |
         * |          30.00 |             25.50 | 2390663 |           2390418 |          2390665 |
         * |          48.00 |             40.80 | 2390664 |           2390418 |          2390665 |
         * |          45.00 |             38.25 | 2390665 |           2390418 |          2390665 |
         * |        3196.00 |           2697.25 |    NULL |           2390418 |          2390665 |  -- ROLLUP ROW
         * +----------------+-------------------+---------+-------------------+------------------+
         *
         */
        $stmt = $conn->prepare(
                "SELECT
                    SUM( raw_word_count ) AS raw_word_count,
                    SUM( eq_word_count ) AS eq_word_count,
                    SUM( st.standard_word_count ) AS standard_word_count,

                    job_first_segment, job_last_segment, s.id, s.show_in_cattool
                        FROM segments s
                        JOIN files_job fj ON fj.id_file = s.id_file
                        JOIN jobs j ON j.id = fj.id_job
                        LEFT  JOIN segment_translations st ON st.id_segment = s.id AND st.id_job = j.id
                        WHERE s.id BETWEEN j.job_first_segment AND j.job_last_segment
                        AND j.id = :id_job
                        AND j.password = :password
                        AND j.status_owner != :deleted
                        GROUP BY s.id
                    WITH ROLLUP"
        );

        return $this
                ->setCacheTTL( $ttl )
                ->_fetchObject( $stmt, new ShapelessConcreteStruct(), [ 'id_job' => $id, 'password' => $password, 'deleted' => Constants_JobStatus::STATUS_DELETED ] );

    }

    /**
     *
     * @param int $id_job
     * @param int $ttl
     *
     * @return JobStruct[]
     * @throws ReflectionException
     */
    public static function getById( int $id_job, int $ttl = 0 ): array {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( "SELECT * FROM jobs WHERE id = ? AND status_owner != ? ORDER BY job_first_segment" );

        /** @var JobStruct[] */
        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new JobStruct, [ $id_job, Constants_JobStatus::STATUS_DELETED ] );

    }

    /**
     * @param int $id_project
     * @param int $id_job
     * @param int $ttl
     *
     * @return JobStruct[]
     * @throws ReflectionException
     */
    public static function getByIdProjectAndIdJob( int $id_project, int $id_job, int $ttl = 0 ): array {

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM jobs WHERE id_project = :id_project AND id = :id_job" );

        return ( new self() )->setCacheTTL( $ttl )->_fetchObject( $stmt, new JobStruct, [ 'id_project' => $id_project, 'id_job' => $id_job ] );
    }

    /**
     * @param JobStruct $jobStruct
     *
     * @return JobStruct
     * @throws ReflectionException
     */
    public static function createFromStruct( JobStruct $jobStruct ): JobStruct {

        $conn = Database::obtain()->getConnection();

        $jobStructToArray = $jobStruct->toArray();
        $columns          = array_keys( $jobStructToArray );
        $values           = array_values( $jobStructToArray );

        //clean null values
        foreach ( $values as $k => $val ) {
            if ( is_null( $val ) ) {
                unset( $values[ $k ] );
                unset( $columns[ $k ] );
            }
        }

        //reindex the array
        $columns = array_values( $columns );
        $values  = array_values( $values );

        Database::obtain()->begin();

        /** @noinspection SqlInsertValues */
        $stmt = $conn->prepare( 'INSERT INTO `jobs` ( ' . implode( ',', $columns ) . ' ) VALUES ( ' . implode( ',', array_fill( 0, count( $values ), '?' ) ) . ' )' );

        foreach ( $values as $k => $v ) {
            $stmt->bindValue( $k + 1, $v ); //Columns/Parameters are 1-based
        }

        $stmt->execute();

        $job = static::getById( $conn->lastInsertId() )[ 0 ];

        $conn->commit();

        return $job;

    }

    /**
     * @param ProjectStruct $project
     * @param UserStruct    $user
     *
     * @return int the number of rows affected by the statement
     */
    public function updateOwner( ProjectStruct $project, UserStruct $user ): int {
        $sql = " UPDATE jobs SET owner = :email, last_update = :last_update WHERE id_project = :id_project ";

        $stmt = $this->database->getConnection()->prepare( $sql );
        $stmt->execute( [
                'email'       => $user->email,
                'id_project'  => $project->id,
                'last_update' => date( "Y-m-d H:i:s" ),
        ] );

        return $stmt->rowCount();
    }

    public static function getTODOWords( JobStruct $jStruct ) {

        return array_sum( [ $jStruct->new_words, $jStruct->draft_words ] );

    }

    /**
     * @param JobStruct $jStruct
     * @param string    $new_password
     *
     * @return JobStruct
     * @throws ReflectionException
     * @throws Exception
     */
    public function changePassword( JobStruct $jStruct, string $new_password ): JobStruct {

        if ( empty( $new_password ) ) {
            throw new PDOException( "Invalid empty value: password." );
        }

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( self::$_sql_update_password );
        $stmt->execute( [
                'id'           => $jStruct->id,
                'new_password' => $new_password,
                'old_password' => $jStruct->password,
                'last_update'  => date( "Y-m-d H:i:s" ),
        ] );

        $jStruct->password = $new_password;

        $this->destroyCache( $jStruct );
        $this->destroyCacheByProjectId( $jStruct->id_project );

        return $jStruct;


    }

    /**
     * Job Worker gets segments to recount the Job Total weighted PEE
     *
     * @param JobStruct $jStruct
     *
     * @return EditLogSegmentStruct[]
     * @throws ReflectionException
     */
    public function getAllModifiedSegmentsForPee( JobStruct $jStruct ): array {

        $query = "
            SELECT
              s.id,
              suggestion, 
              translation,
              raw_word_count,
              time_to_edit
            FROM segment_translations st
            JOIN segments s ON s.id = st.id_segment
            JOIN jobs j ON j.id = st.id_job
            WHERE id_job = :id_job 
                AND show_in_cattool = 1
                AND  password = :password
                AND st.status NOT IN( :status_new , :status_draft )
                AND time_to_edit/raw_word_count BETWEEN :edit_time_fast_cut AND :edit_time_slow_cut
                AND st.id_segment BETWEEN j.job_first_segment AND j.job_last_segment
        ";

        $stmt = $this->database->getConnection()->prepare( $query );

        /**
         * @var EditLogSegmentStruct[]
         */
        return $this->_fetchObject( $stmt, new EditLogSegmentStruct(), [
                'id_job'             => $jStruct->id,
                'password'           => $jStruct->password,
                'status_new'         => Constants_TranslationStatus::STATUS_NEW,
                'status_draft'       => Constants_TranslationStatus::STATUS_DRAFT,
                'edit_time_fast_cut' => 1000 * EditLogSegmentStruct::EDIT_TIME_FAST_CUT,
                'edit_time_slow_cut' => 1000 * EditLogSegmentStruct::EDIT_TIME_SLOW_CUT
        ] );

    }

    /**
     * @param JobStruct $jStruct
     */
    public function updateJobWeightedPeeAndTTE( JobStruct $jStruct ) {

        $sql = " UPDATE jobs 
                    SET avg_post_editing_effort = :avg_post_editing_effort, 
                        total_time_to_edit = :total_time_to_edit,
                        last_update = :last_update 
                    WHERE id = :id 
                    AND password = :password ";

        $stmt = Database::obtain()->getConnection()->prepare( $sql );
        $stmt->execute( [
                'avg_post_editing_effort' => $jStruct->avg_post_editing_effort,
                'total_time_to_edit'      => $jStruct->total_time_to_edit,
                'last_update'             => date( "Y-m-d H:i:s" ),
                'id'                      => $jStruct->id,
                'password'                => $jStruct->password
        ] );
        $stmt->closeCursor();

    }

    /**
     * @param int    $id_job
     * @param string $password
     *
     * @return ShapelessConcreteStruct
     * @throws ReflectionException
     */
    public function getPeeStats( int $id_job, string $password ): ShapelessConcreteStruct {

        $query = "
            SELECT
                avg_post_editing_effort / SUM( raw_word_count ) AS avg_pee
            FROM segment_translations st
            JOIN segments s ON s.id = st.id_segment
            JOIN jobs j ON j.id = st.id_job
            WHERE id_job = :id_job 
                AND show_in_cattool = 1
                AND  password = :password
                AND st.status NOT IN( :status_new , :status_draft )
                AND time_to_edit/raw_word_count BETWEEN :edit_time_fast_cut AND :edit_time_slow_cut
                AND st.id_segment BETWEEN j.job_first_segment AND j.job_last_segment
        ";

        $stmt = $this->database->getConnection()->prepare( $query );

        /** @var ShapelessConcreteStruct */
        return $this->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job'             => $id_job,
                'password'           => $password,
                'status_new'         => Constants_TranslationStatus::STATUS_NEW,
                'status_draft'       => Constants_TranslationStatus::STATUS_DRAFT,
                'edit_time_fast_cut' => 1000 * EditLogSegmentStruct::EDIT_TIME_FAST_CUT,
                'edit_time_slow_cut' => 1000 * EditLogSegmentStruct::EDIT_TIME_SLOW_CUT
        ] )[ 0 ];

    }

    /**
     * @param JobStruct $jobStruct
     *
     * @return PDOStatement
     */
    public function getSplitJobPreparedStatement( JobStruct $jobStruct ): PDOStatement {

        $jobCopy = $jobStruct->getArrayCopy();

        $columns      = implode( ", ", array_keys( $jobCopy ) );
        $values       = array_values( $jobCopy );
        $placeHolders = implode( ',', array_fill( 0, count( $values ), '?' ) );

        $jobStruct->last_update = date( "Y-m-d H:i:s" );

        $values[] = $jobStruct->last_update;
        $values[] = $jobStruct->last_opened_segment;
        $values[] = $jobStruct->job_first_segment;
        $values[] = $jobStruct->job_last_segment;
        $values[] = $jobStruct->avg_post_editing_effort;

        /** @noinspection SqlInsertValues */
        $query = "INSERT INTO jobs ( $columns ) VALUES ( $placeHolders )
                        ON DUPLICATE KEY UPDATE
                        last_update = ?,
                        last_opened_segment = ?,
                        job_first_segment = ?,
                        job_last_segment = ?,
                        avg_post_editing_effort = ?
                ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $query );

        foreach ( $values as $k => $v ) {
            $stmt->bindValue( $k + 1, $v ); //Columns/Parameters are 1-based
        }

        return $stmt;

    }

    /**
     * @param $id_job
     * @param $source_page
     *
     * @return ShapelessConcreteStruct
     * @throws ReflectionException
     */
    public function getTimeToEdit( $id_job, $source_page ): ShapelessConcreteStruct {

        $query = "SELECT sum(time_to_edit) as tte 
                    FROM segment_translation_events 
                    WHERE id_job=:id_job 
                    AND status=:status  
                    AND source_page=:source_page";

        $status = ( $source_page == 1 ) ? Constants_TranslationStatus::STATUS_TRANSLATED : Constants_TranslationStatus::STATUS_APPROVED;
        $stmt   = $this->database->getConnection()->prepare( $query );

        /** @var ShapelessConcreteStruct */
        return $this->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job'      => $id_job,
                'status'      => $status,
                'source_page' => $source_page
        ] )[ 0 ];
    }

    /**
     * @param int $jobId
     * @param int $standard_analysis_wc
     * @param int $total_raw_wc
     *
     * @return int
     */
    public function updateStdWcAndTotalWc( int $jobId, int $standard_analysis_wc, int $total_raw_wc ): int {
        $query = "UPDATE jobs 
                    SET 
                        last_update = :last_update,
                        total_raw_wc = :total_raw_wc, 
                        standard_analysis_wc = :standard_analysis_wc
                    WHERE id= :id
                ";

        $values = [
                'id'                   => $jobId,
                'standard_analysis_wc' => $standard_analysis_wc,
                'last_update'          => date( "Y-m-d H:i:s" ),
                'total_raw_wc'         => $total_raw_wc,
        ];

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $query );
        $stmt->execute( $values );

        return $stmt->rowCount();
    }

    /**
     * @param JobStruct $first_job
     * @param string    $newPass
     *
     * @return JobStruct
     * @throws ReflectionException
     * @throws ValidationError
     * @throws Exception
     */
    public static function updateForMerge( JobStruct $first_job, string $newPass ): JobStruct {

        static::updateStruct( $first_job );

        if ( $newPass ) {
            self::updateFields(
                    [
                            'password'    => $newPass,
                            'last_update' => date( "Y-m-d H:i:s" ),
                    ],
                    [
                            'id'       => $first_job->id,
                            'password' => $first_job->password
                    ]
            );
            $first_job->password = $newPass;
        }

        return $first_job;

    }

    /**
     * @param JobStruct $first_job
     *
     * @return bool
     */
    public static function deleteOnMerge( JobStruct $first_job ): bool {

        $conn  = Database::obtain()->getConnection();
        $query = "DELETE FROM jobs WHERE id = :id AND password != :first_job_password "; //use new password
        $stmt  = $conn->prepare( $query );

        return $stmt->execute( [
                'id'                 => $first_job->id,
                'first_job_password' => $first_job->password
        ] );

    }

    /**
     * @param JobStruct $chunkStruct
     * @param int       $ttl
     *
     * @return ShapelessConcreteStruct[]
     * @throws ReflectionException
     */
    public static function getFirstSegmentOfFilesInJob( JobStruct $chunkStruct, int $ttl = 0 ): array {

        $thisDao = new self();

        $query = "SELECT 
                id_file,
                MIN( segments.id ) AS first_segment, 
                MAX( segments.id ) AS last_segment,
                filename AS file_name, 
                SUM( st.eq_word_count ) AS weighted_words,
                sum( standard_word_count ) AS standard_words,
                SUM( raw_word_count ) AS raw_words
        FROM files_job
        JOIN files ON files_job.id_file = files.id
        JOIN jobs ON jobs.id = files_job.id_job
        JOIN segments USING( id_file )
        JOIN segment_translations st ON id_segment = segments.id AND st.id_job = jobs.id
        WHERE files_job.id_job = :id_job
        AND segments.show_in_cattool = 1 
        GROUP BY id_file
        ORDER BY first_segment";

        $stmt = $thisDao->getDatabaseHandler()->getConnection()->prepare( $query );

        /** @var ShapelessConcreteStruct[] */
        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job' => $chunkStruct->id
        ] );

    }

    /**
     * @param                $id_project
     * @param                $new_status
     *
     * @throws ReflectionException
     */
    public static function updateAllJobsStatusesByProjectId( $id_project, $new_status ) {
        self::updateFields( [
                'status_owner' => $new_status,
                'last_update'  => date( "Y-m-d H:i:s" ),
        ],
                [
                        'id_project' => $id_project
                ]
        );
        ( new JobDao )->destroyCacheByProjectId( $id_project );

    }

    /**
     * @param JobStruct $jStruct
     *
     * @return int
     */
    public static function setJobComplete( JobStruct $jStruct ): int {
        return self::updateFields( [
                'completed'   => 1,
                'last_update' => date( "Y-m-d H:i:s" ),
        ],
                [
                        'id' => $jStruct->id
                ] );
    }

    /**
     * @param JobStruct $jStruct
     * @param string    $new_status
     *
     * @throws ReflectionException
     */
    public static function updateJobStatus( JobStruct $jStruct, string $new_status ) {
        self::updateFields( [
                'status_owner' => $new_status,
                'last_update'  => date( "Y-m-d H:i:s" ),
        ],
                [
                        'id' => $jStruct->id
                ] );
        ( new JobDao )->destroyCacheByProjectId( $jStruct->id_project );
    }

    /**
     * Get reviewed_words_count grouped by file parts
     *
     * @param int    $id_job
     * @param string $password
     * @param int    $revisionNumber
     * @param int    $ttl
     *
     * @return ShapelessConcreteStruct[]
     * @throws ReflectionException
     */
    public static function getReviewedWordsCountGroupedByFileParts( int $id_job, string $password, int $revisionNumber, int $ttl = 0 ): array {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $query   = "SELECT 
                f.filename,
                s.id_file_part,
                s.id_file,
                SUM(raw_word_count) as reviewed_words_count,
                fp.id as id_file_part_external_reference,
                fp.tag_key,
                fp.tag_value
            FROM
                segment_translation_events se
                    JOIN
                segments s ON se.id_segment = s.id
                    JOIN
                jobs j ON j.id = se.id_job
                    JOIN
                files f ON f.id = s.id_file
                    LEFT JOIN
                files_parts fp ON fp.id = s.id_file_part
            WHERE
                se.id_job = :id_job
                AND j.password = :password
                AND se.final_revision = 1
                AND se.source_page = :revisionNumber
            GROUP BY s.id_file_part;
        ";

        $stmt = $conn->prepare( $query );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job'         => $id_job,
                'password'       => $password,
                'revisionNumber' => $revisionNumber
        ] );
    }

    /**
     * @param array $idJobs
     * @param int   $ttl
     *
     * @return int|null
     * @throws ReflectionException
     */
    public static function getSegmentTranslationsCount( array $idJobs, int $ttl = 0 ): ?int {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();

        $query = "select count(*) as total from segment_translations where id_job IN ( " . implode( ', ', $idJobs ) . " );";

        $stmt    = $conn->prepare( $query );
        $records = $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [] );

        if ( empty( $records ) ) {
            return null;
        }

        if ( !empty( $records[ 0 ]->total ) ) {
            return (int)$records[ 0 ]->total;
        }

        return null;

    }

    /**
     * @param int $id_job
     * @param int $ttl
     *
     * @return bool
     * @throws ReflectionException
     */
    public static function hasACustomPayableRate( int $id_job, int $ttl = 86400 ): bool {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();

        $stmt = $conn->prepare( "
            SELECT * 
            FROM job_custom_payable_rates 
            where id_job = :id_job
        " );

        $object = $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job' => $id_job,
        ] )[ 0 ] ?? null;

        return $object !== null;
    }
}
