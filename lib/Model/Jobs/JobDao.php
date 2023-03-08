<?php

use DataAccess\LoudArray;
use DataAccess\ShapelessConcreteStruct;

class Jobs_JobDao extends DataAccess_AbstractDao {

    const TABLE       = "jobs";
    const STRUCT_TYPE = "Jobs_JobStruct";

    protected static $auto_increment_field = [ 'id' ];
    protected static $primary_keys         = [ 'id', 'password' ];

    protected static $_sql_update_password = "UPDATE jobs SET password = :new_password WHERE id = :id AND password = :old_password ";

    protected static $_sql_get_jobs_by_project = "SELECT * FROM jobs WHERE id_project = ? AND status_owner != ? ORDER BY id, job_first_segment ASC;";

    protected static $_sql_get_by_segment_translation = "select * from jobs where id = :id_job AND jobs.job_first_segment <= :id_segment AND jobs.job_last_segment >= :id_segment ";

    /**
     * This method is not static and used to cache at Redis level the values for this Job
     *
     * Use when counters of the job value are not important but only the metadata are needed
     *
     * XXX: Be careful, used by the ContributionSetStruct
     *
     * @param Jobs_JobStruct $jobQuery
     *
     * @return DataAccess_IDaoStruct[]|Jobs_JobStruct[]
     * @see \AsyncTasks\Workers\SetContributionWorker
     * @see \Contribution\ContributionSetStruct
     *
     */
    public function read( Jobs_JobStruct $jobQuery ) {

        $stmt = $this->_getStatementForCache( null );

        return $this->_fetchObject( $stmt,
                $jobQuery,
                [
                        'id_job'   => $jobQuery->id,
                        'password' => $jobQuery->password
                ]
        );

    }

    /**
     *
     * @return PDOStatement
     */
    protected function _getStatementForCache( $query ) {

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(
                "SELECT * FROM jobs WHERE " .
                " id = :id_job AND password = :password "
        );

        return $stmt;
    }

    /**
     * @param array $array_result
     *
     * @return DataAccess_IDaoStruct|DataAccess_IDaoStruct[]|void
     */
    protected function _buildResult( $array_result ) {
    }

    /**
     * Destroy a cached object
     *
     * @param Jobs_JobStruct $jobQuery
     *
     * @return bool
     * @throws Exception
     */
    public function destroyCache( Jobs_JobStruct $jobQuery ) {
        /*
        * build the query
        */
        $stmt = $this->_getStatementForCache( null );

        return $this->_destroyObjectCache( $stmt,
                [
                        'id_job'   => $jobQuery->id,
                        'password' => $jobQuery->password
                ]
        );
    }

    /**
     * @param Translations_SegmentTranslationStruct $translation
     * @param int                                   $ttl
     * @param DataAccess_IDaoStruct                 $fetchObject
     *
     * @return DataAccess_IDaoStruct|Jobs_JobStruct
     */
    public static function getBySegmentTranslation( Translations_SegmentTranslationStruct $translation, $ttl = 0, DataAccess_IDaoStruct $fetchObject = null ) {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( static::$_sql_get_by_segment_translation );

        if ( $fetchObject == null ) {
            $fetchObject = new Jobs_JobStruct();
        }

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, $fetchObject, [
                'id_job'     => $translation->id_job,
                'id_segment' => $translation->id_segment
        ] )[ 0 ];

    }

    /**
     * @param     $id_job
     * @param     $password
     * @param int $ttl
     *
     * @return int
     */
    public static function getSegmentsCount($id_job, $password, $ttl = 0) {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare(
                "SELECT (job_last_segment - job_first_segment + 1 ) as segments_count FROM jobs WHERE " .
                " id = :id_job AND password = :password "
        );

        $struct = @$thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job'   => $id_job,
                'password' => $password
        ] )[ 0 ];

        return ($struct->segments_count) ? (int)$struct->segments_count : 0;
    }

    /**
     * @param                                        $id_job
     * @param                                        $password
     * @param int                                    $ttl
     * @param DataAccess_IDaoStruct                  $fetchObject
     *
     * @return DataAccess_IDaoStruct|Jobs_JobStruct
     */
    public static function getByIdAndPassword( $id_job, $password, $ttl = 0, DataAccess_IDaoStruct $fetchObject = null ) {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare(
                "SELECT * FROM jobs WHERE " .
                " id = :id_job AND password = :password "
        );

        if ( $fetchObject == null ) {
            $fetchObject = new Jobs_JobStruct();
        }

        return @$thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, $fetchObject, [
                'id_job'   => $id_job,
                'password' => $password
        ] )[ 0 ];

    }

    /**
     * @param $project_id
     *
     * @return bool|int
     */
    public function destroyCacheByProjectId( $project_id ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( self::$_sql_get_jobs_by_project );

        return $this->_destroyObjectCache( $stmt, [ $project_id, \Constants_JobStatus::STATUS_DELETED ] );
    }

    /**
     * @param                            $id_project
     * @param int                        $ttl
     * @param DataAccess_IDaoStruct|null $fetchObject
     *
     * @return DataAccess_IDaoStruct[]|Jobs_JobStruct[]
     */
    public static function getByProjectId( $id_project, $ttl = 0, DataAccess_IDaoStruct $fetchObject = null ) {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( self::$_sql_get_jobs_by_project );

        if ( $fetchObject == null ) {
            $fetchObject = new Jobs_JobStruct();
        }

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, $fetchObject, [ $id_project, \Constants_JobStatus::STATUS_DELETED ] );

    }

    /**
     * @param int    $id
     * @param string $password
     * @param int    $ttl
     *
     * @return DataAccess_IDaoStruct[]|LoudArray[]
     * @internal param Chunks_ChunkStruct $chunk
     * @internal param $requestedWordsPerSplit
     *
     */
    public function getSplitData( $id, $password, $ttl = 0 ) {
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
                ->_fetchObject( $stmt, new LoudArray(), [ 'id_job' => $id, 'password' => $password, 'deleted' => Constants_JobStatus::STATUS_DELETED ] );

    }

    /**
     *
     * @param                                        $id_job
     * @param int                                    $ttl
     * @param DataAccess_IDaoStruct|null             $fetchObject
     *
     * @return DataAccess_IDaoStruct[]|Jobs_JobStruct[]
     */
    public static function getById( $id_job, $ttl = 0, DataAccess_IDaoStruct $fetchObject = null ) {

        if ( $fetchObject == null ) {
            $fetchObject = new Jobs_JobStruct();
        }

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( "SELECT * FROM jobs WHERE id = ? AND status_owner != ? ORDER BY job_first_segment" );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, $fetchObject, [ $id_job, \Constants_JobStatus::STATUS_DELETED ] );

    }

    /**
     * @param                            $id_project
     * @param                            $id_job
     * @param                            $ttl
     * @param DataAccess_IDaoStruct|null $fetchObject
     *
     * @return Jobs_JobStruct[]|DataAccess_IDaoStruct[]
     */
    public static function getByIdProjectAndIdJob( $id_project, $id_job, $ttl = 0, DataAccess_IDaoStruct $fetchObject = null ) {

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( "SELECT * FROM jobs WHERE id_project = :id_project AND id = :id_job" );

        if ( $fetchObject == null ) {
            $fetchObject = new Jobs_JobStruct();
        }

        return ( new self() )->setCacheTTL( $ttl )->_fetchObject( $stmt, $fetchObject, [ 'id_project' => $id_project, 'id_job' => $id_job ] );
    }

    /**
     * @param Jobs_JobStruct $jobStruct
     *
     * @return Jobs_JobStruct
     * @throws ReflectionException
     */
    public static function createFromStruct( Jobs_JobStruct $jobStruct ) {

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

        \Database::obtain()->begin();

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
     * @param Projects_ProjectStruct $project
     * @param Users_UserStruct       $user
     *
     * @return int the number of rows affected by the statement
     */
    public function updateOwner( Projects_ProjectStruct $project, Users_UserStruct $user ) {
        $sql = " UPDATE jobs SET owner = :email WHERE id_project = :id_project ";

        $stmt = $this->database->getConnection()->prepare( $sql );
        $stmt->execute( [ 'email' => $user->email, 'id_project' => $project->id ] );

        return $stmt->rowCount();
    }

    public static function getTODOWords( Jobs_JobStruct $jStruct ) {

        return array_sum( [ $jStruct->new_words, $jStruct->draft_words ] );

    }

    public function changePassword( Jobs_JobStruct $jStruct, $new_password ) {

        if ( empty( $new_password ) ) {
            throw new PDOException( "Invalid empty value: password." );
        }

        $conn = \Database::obtain()->getConnection();
        $stmt = $conn->prepare( self::$_sql_update_password );
        $stmt->execute( [
                'id'           => $jStruct->id,
                'new_password' => $new_password,
                'old_password' => $jStruct->password
        ] );

        $jStruct->password = $new_password;

        $this->destroyCache( $jStruct );
        $this->destroyCacheByProjectId( $jStruct->id_project );

        return $jStruct;


    }

    /**
     * Job Worker gets segments to recount the Job Total weighted PEE
     *
     * @param Jobs_JobStruct $jStruct
     *
     * @return EditLog_EditLogSegmentStruct[]|DataAccess_IDaoStruct[]
     */
    public function getAllModifiedSegmentsForPee( Jobs_JobStruct $jStruct ) {

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

        return $this->_fetchObject( $stmt, new EditLog_EditLogSegmentStruct(), [
                'id_job'             => $jStruct->id,
                'password'           => $jStruct->password,
                'status_new'         => Constants_TranslationStatus::STATUS_NEW,
                'status_draft'       => Constants_TranslationStatus::STATUS_DRAFT,
                'edit_time_fast_cut' => 1000 * EditLog_EditLogModel::EDIT_TIME_FAST_CUT,
                'edit_time_slow_cut' => 1000 * EditLog_EditLogModel::EDIT_TIME_SLOW_CUT
        ] );

    }

    /**
     * @param Jobs_JobStruct $jStruct
     */
    public function updateJobWeightedPeeAndTTE( Jobs_JobStruct $jStruct ) {

        $sql = " UPDATE jobs 
                    SET avg_post_editing_effort = :avg_post_editing_effort, 
                        total_time_to_edit = :total_time_to_edit 
                    WHERE id = :id 
                    AND password = :password ";

        $stmt = Database::obtain()->getConnection()->prepare( $sql );
        $stmt->execute( [
                'avg_post_editing_effort' => $jStruct->avg_post_editing_effort,
                'total_time_to_edit'      => $jStruct->total_time_to_edit,
                'id'                      => $jStruct->id,
                'password'                => $jStruct->password
        ] );
        $stmt->closeCursor();

    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return ShapelessConcreteStruct
     */
    public function getPeeStats( $id_job, $password ) {

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

        return $this->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job'             => $id_job,
                'password'           => $password,
                'status_new'         => Constants_TranslationStatus::STATUS_NEW,
                'status_draft'       => Constants_TranslationStatus::STATUS_DRAFT,
                'edit_time_fast_cut' => 1000 * EditLog_EditLogModel::EDIT_TIME_FAST_CUT,
                'edit_time_slow_cut' => 1000 * EditLog_EditLogModel::EDIT_TIME_SLOW_CUT
        ] )[ 0 ];

    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return ShapelessConcreteStruct
     */
    public function getJobRawStats( $id_job, $password ) {

        $queryAllSegments = "
          SELECT
            SUM(time_to_edit) AS tot_tte,
            SUM(raw_word_count) AS raw_words,
            SUM(time_to_edit)/SUM(raw_word_count) AS secs_per_word
          FROM segment_translations st
            JOIN segments s ON s.id = st.id_segment
            JOIN jobs j ON j.id = st.id_job
          WHERE id_job = :id_job 
            AND  password = :password
            AND st.id_segment BETWEEN j.job_first_segment AND j.job_last_segment
            ";

        $stmt = $this->database->getConnection()->prepare( $queryAllSegments );

        return $this->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job'   => $id_job,
                'password' => $password
        ] )[ 0 ];

    }

    /**
     * @param Jobs_JobStruct $jobStruct
     *
     * @return PDOStatement
     * @throws ReflectionException
     */
    public function getSplitJobPreparedStatement( Jobs_JobStruct $jobStruct ) {

        $jobCopy = $jobStruct->getArrayCopy();

        $columns      = implode( ", ", array_keys( $jobCopy ) );
        $values       = array_values( $jobCopy );
        $placeHolders = implode( ',', array_fill( 0, count( $values ), '?' ) );

        $values[] = $jobStruct->last_opened_segment;
        $values[] = $jobStruct->job_first_segment;
        $values[] = $jobStruct->job_last_segment;
        $values[] = $jobStruct->avg_post_editing_effort;

        $query = "INSERT INTO jobs ( $columns ) VALUES ( $placeHolders )
                        ON DUPLICATE KEY UPDATE
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
     * @return DataAccess_IDaoStruct
     */
    public function getTimeToEdit($id_job, $source_page) {

        $query = "SELECT sum(time_to_edit) as tte 
                    FROM segment_translation_events 
                    WHERE id_job=:id_job 
                    AND status=:status  
                    AND source_page=:source_page";

        $status = ($source_page == 1) ? Constants_TranslationStatus::STATUS_TRANSLATED : Constants_TranslationStatus::STATUS_APPROVED;
        $stmt = $this->database->getConnection()->prepare( $query );

        return $this->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job'   => $id_job,
                'status'   => $status,
                'source_page' => $source_page
        ] )[ 0 ];
    }

    /**
     * @param $jobId
     * @param $standard_analysis_wc
     * @param $total_raw_wc
     *
     * @return int
     */
    public function updateStdWcAndTotalWc( $jobId, $standard_analysis_wc, $total_raw_wc ){
        $query = "UPDATE jobs 
                    SET total_raw_wc = :total_raw_wc, standard_analysis_wc = :standard_analysis_wc
                    WHERE id= :id
                ";

        $values = [
                'id' => $jobId,
                'standard_analysis_wc' => $standard_analysis_wc,
                'total_raw_wc' => $total_raw_wc,
        ];

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $query );
        $stmt->execute($values);

        return $stmt->rowCount();
    }

    /**
     * @param Jobs_JobStruct $first_job
     * @param                $newPass
     *
     * @return Jobs_JobStruct
     * @throws ReflectionException
     * @throws \Exceptions\ValidationError
     */
    public static function updateForMerge( Jobs_JobStruct $first_job, $newPass ) {

        static::updateStruct( $first_job );

        if ( $newPass ) {
            self::updateFields( [ 'password' => $newPass ], [ 'id' => $first_job->id, 'password' => $first_job->password ] );
            $first_job->password = $newPass;
        }

        return $first_job;

    }

    /**
     * @param Jobs_JobStruct $first_job
     *
     * @return bool
     */
    public static function deleteOnMerge( Jobs_JobStruct $first_job ) {

        $conn  = Database::obtain()->getConnection();
        $query = "DELETE FROM jobs WHERE id = :id AND password != :first_job_password "; //use new password
        $stmt  = $conn->prepare( $query );

        return $stmt->execute( [
                'id'                 => $first_job->id,
                'first_job_password' => $first_job->password
        ] );

    }

    /**
     * @param Jobs_JobStruct $chunkStruct
     * @param int            $ttl
     *
     * @return DataAccess_IDaoStruct[]
     */
    public static function getFirstSegmentOfFilesInJob( Jobs_JobStruct $chunkStruct, $ttl = 0  ) {

        $thisDao = new self();
        $thisDao->getDatabaseHandler();

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

        return $thisDao->setCacheTTL($ttl)->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job' => $chunkStruct->id
        ] );
    }

    /**
     * @param                $id_project
     * @param                $new_status
     */
    public static function updateAllJobsStatusesByProjectId( $id_project, $new_status ) {
        self::updateFields( [ 'status_owner' => $new_status ], [ 'id_project' => $id_project ] );
        ( new Jobs_JobDao )->destroyCacheByProjectId( $id_project );

    }

    /**
     * @param Jobs_JobStruct $jStruct
     *
     * @return int
     */
    public static function setJobComplete( Jobs_JobStruct $jStruct ) {
        return self::updateFields( [ 'completed' => 1 ], [ 'id' => $jStruct->id ] );
    }

    /**
     * @param Jobs_JobStruct $jStruct
     * @param                $new_status
     */
    public static function updateJobStatus( Jobs_JobStruct $jStruct, $new_status ) {
        self::updateFields( [ 'status_owner' => $new_status ], [ 'id' => $jStruct->id ] );
        ( new Jobs_JobDao )->destroyCacheByProjectId( $jStruct->id_project );
    }

    /**
     * @param Jobs_JobStruct $jStruct
     * @param                $segmentTimeToEdit
     *
     * @return float|int
     */
    public static function updateTotalTimeToEdit( Jobs_JobStruct $jStruct, $segmentTimeToEdit ) {

        $db = Database::obtain();

        //Update in Transaction
        $query = "UPDATE jobs AS j SET
                  total_time_to_edit = coalesce( total_time_to_edit, 0 ) + :tte
               WHERE j.id = :jid
               AND j.password = :password";

        $stmt = $db->getConnection()->prepare( $query );

        try {

            $stmt->execute( [
                    'tte'      => $segmentTimeToEdit,
                    'jid'      => $jStruct->id,
                    'password' => $jStruct->password
            ] );

        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );

            return $e->getCode();
        }

        return $stmt->rowCount();
    }

    /**
     * get the sum of equivalent word count of segment translations of a job
     *
     * @param     $id_job
     * @param     $password
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct
     */
    public static function getEquivalentWordTotal($id_job, $password , $ttl = 0 ) {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $query = "select 
                sum(st.eq_word_count) as s
                from segment_translations st
                join jobs j on j.id = st.id_job 
                where j.id = :id_job
                and j.password = :password;";
        $stmt    = $conn->prepare($query  );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
                'id_job'     => $id_job,
                'password' => $password
        ] )[ 0 ];

    }

    /**
     * Get reviewed_words_count grouped by file parts
     *
     * @param int    $id_job
     * @param string $password
     * @param int    $revisionNumber
     * @param int    $ttl
     *
     * @return DataAccess_IDaoStruct[]
     */
    public static function getReviewedWordsCountGroupedByFileParts ($id_job, $password, $revisionNumber, $ttl = 0) {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $query = "SELECT 
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

        $stmt = $conn->prepare($query  );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
            'id_job'   => $id_job,
            'password' => $password,
            'revisionNumber' => $revisionNumber
        ] );
    }

    /**
     * @param array $idJobs
     * @param int $ttl
     * @return int|null
     */
    public static function getSegmentTranslationsCount(array $idJobs, $ttl = 0)
    {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();

        $query = "select count(*) as total from segment_translations where id_job IN ( " . implode(', ' , $idJobs ) . " );";

        $stmt = $conn->prepare($query  );
        $records = $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [] );

        if(empty($records)){
            return null;
        }

        return (int)$records[0]->total;
    }

    /**
     * @param $id_job
     * @param $password
     * @param int $ttl
     * @return float|null
     */
    public static function getStandardWordCount( $id_job, $password, $ttl = 86400 ) {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( "
            SELECT sum(standard_word_count) as standard_word_count 
            FROM segment_translations st
            join jobs j on j.id = st.id_job
             where j.id = :id_job and j.password = :password
        " );

        $object = @$thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), [
            'id_job'   => $id_job,
            'password' => $password,
        ] )[0];

        if($object === null){
            return null;
        }

        return (float)$object->standard_word_count;
    }
}
