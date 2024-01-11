<?php

use DataAccess\ShapelessConcreteStruct;
use RemoteFiles\RemoteFileServiceNameStruct;
use Teams\TeamStruct;

class Projects_ProjectDao extends DataAccess_AbstractDao {
    const TABLE = "projects";

    protected static $auto_increment_field = [ 'id' ];
    protected static $primary_keys         = [ 'id' ];

    protected static $_sql_project_data = "
            SELECT p.name, j.id AS jid, j.password AS jpassword, j.source, j.target, j.payable_rates, f.id, f.id AS id_file,f.filename, p.status_analysis, j.subject, j.status_owner,
    
                   SUM(s.raw_word_count) AS file_raw_word_count,
                   SUM(st.eq_word_count) AS file_eq_word_count,
                   SUM(st.standard_word_count) AS file_st_word_count,
                   COUNT(s.id) AS total_segments,
    
                   p.fast_analysis_wc,
                   p.tm_analysis_wc,
                   p.standard_analysis_wc
    
                       FROM projects p
                       INNER JOIN jobs j ON p.id=j.id_project AND j.status_owner <> 'deleted'
                       INNER JOIN files f ON p.id=f.id_project
                       INNER JOIN segments s ON s.id_file=f.id
                       LEFT JOIN segment_translations st ON st.id_segment = s.id AND st.id_job = j.id
                       WHERE p.id= ?
                       AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
                       %s
                       %s
                       %s
                       GROUP BY f.id, j.id, j.password
                       ORDER BY j.id,j.create_date, j.job_first_segment
		";

    protected static $_sql_get_by_id_and_password = "SELECT * FROM projects WHERE id = :id AND password = :password ";

    /**
     * @var string
     */
    protected static $_sql_for_project_unassignment = "
        UPDATE projects SET id_assignee = NULL WHERE id_assignee = :id_assignee and id_team = :id_team ;
    ";

    /**
     * @var string
     */
    protected static $_sql_massive_self_assignment = "
        UPDATE projects SET id_assignee = :id_assignee , id_team = :personal_team WHERE id_team = :id_team ;
    ";

    /**
     * @var string
     */
    protected static $_sql_get_projects_for_team = "SELECT * FROM projects WHERE id_team = :id_team ";

    /**
     * @param $project
     * @param $field
     * @param $value
     *
     * @return Projects_ProjectStruct
     */
    public function updateField( Projects_ProjectStruct $project, $field, $value ) {

        $data           = [];
        $data[ $field ] = $value;
        $where          = [ "id" => $project->id ];

        $success = self::updateFields( $data, $where );

        if ( $success ) {
            $project->$field = $value;
        }

        return $project;

    }

    /**
     * @param Projects_ProjectStruct $project
     * @param                        $newPass
     *
     * @return Projects_ProjectStruct
     * @internal param $pid
     */
    public function changePassword( Projects_ProjectStruct $project, $newPass ) {
        $res = $this->updateField( $project, 'password', $newPass );
        $this->destroyCacheById( $project->id );

        return $res;
    }

    public function deleteFailedProject( $idProject ) {

        if ( empty( $idProject ) ) {
            return 0;
        }

        $sql     = "DELETE FROM projects WHERE id = :id_project";
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( $sql );
        $success = $stmt->execute( [ 'id_project' => $idProject ] );

        return $stmt->rowCount();

    }

    /**
     *
     * This update can easily become massive in case of long lived teams.
     * TODO: make this update chunked.
     *
     * @param TeamStruct       $team
     * @param Users_UserStruct $user
     *
     * @return int
     */
    public function unassignProjects( TeamStruct $team, Users_UserStruct $user ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( static::$_sql_for_project_unassignment );
        $stmt->execute( [
                'id_assignee' => $user->uid,
                'id_team'     => $team->id
        ] );

        return $stmt->rowCount();
    }

    /**
     * @param TeamStruct       $team
     * @param Users_UserStruct $user
     * @param TeamStruct       $personalTeam
     *
     * @return int
     */
    public function massiveSelfAssignment( TeamStruct $team, Users_UserStruct $user, TeamStruct $personalTeam ) {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( static::$_sql_massive_self_assignment );
        $stmt->execute( [
                'id_assignee'   => $user->uid,
                'id_team'       => $team->id,
                'personal_team' => $personalTeam->id
        ] );

        return $stmt->rowCount();
    }

    /**
     * @param int   $id_team
     * @param int   $ttl
     * @param array $filter
     *
     * @return DataAccess_IDaoStruct[]
     */
    public static function findByTeamId( $id_team, $filter = [], $ttl = 0 ) {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();

        $limit      = ( isset( $filter[ 'limit' ] ) ) ? $filter[ 'limit' ] : null;
        $offset     = ( isset( $filter[ 'offset' ] ) ) ? $filter[ 'offset' ] : null;
        $searchId   = ( isset( $filter[ 'search' ][ 'id' ] ) ) ? $filter[ 'search' ][ 'id' ] : null;
        $searchName = ( isset( $filter[ 'search' ][ 'name' ] ) ) ? $filter[ 'search' ][ 'name' ] : null;

        $query = self::$_sql_get_projects_for_team;

        $values = [
            'id_team' => (int)$id_team,
        ];

        if ( $searchId ) {
            $query .= ' AND id = :id ';
            $values['id'] = $searchId;
        }

        if ( $searchName ) {
            $query .= ' AND name = :name ';
            $values['name'] = $searchName;
        }

        if ( $limit and $offset ) {
            $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        $stmt   = $conn->prepare( $query );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new Projects_ProjectStruct(), $values );
    }

    /**
     * @param     $id_team
     * @param int $ttl
     *
     * @return mixed
     */
    public static function getTotalCountByTeamId( $id_team, $filter = [], $ttl = 0 ) {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();

        $searchId   = ( isset( $filter[ 'search' ][ 'id' ] ) ) ? $filter[ 'search' ][ 'id' ] : null;
        $searchName = ( isset( $filter[ 'search' ][ 'name' ] ) ) ? $filter[ 'search' ][ 'name' ] : null;

        $query = "SELECT count(id) as totals FROM projects WHERE id_team = :id_team ";

        $values = [
                'id_team' => (int)$id_team,
        ];

        if ( $searchId ) {
            $query .= ' AND id = :id ';
            $values['id'] = $searchId;
        }

        if ( $searchName ) {
            $query .= ' AND name = :name ';
            $values['name'] = $searchName;
        }

        $stmt  = $conn->prepare( $query );

        $results = $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new ShapelessConcreteStruct(), $values );

        return ( isset( $results[ 0 ] ) ) ? $results[ 0 ][ 'totals' ] : 0;
    }

    /**
     * @param int $id_job
     * @param int $ttl
     *
     * @return Projects_ProjectStruct
     */
    public static function findByJobId( $id_job, $ttl = 0 ) {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $sql     = "SELECT projects.* FROM projects " .
                " INNER JOIN jobs ON projects.id = jobs.id_project " .
                " WHERE jobs.id = :id_job " .
                " LIMIT 1 ";
        $stmt    = $conn->prepare( $sql );

        return $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new Projects_ProjectStruct(), [ 'id_job' => $id_job ] )[ 0 ];
    }

    /**
     * @param $id_customer
     *
     * @return Projects_ProjectStruct[]
     */

    static function findByIdCustomer( $id_customer ) {
        $conn = Database::obtain()->getConnection();
        $sql  = "SELECT projects.* FROM projects " .
                " WHERE id_customer = :id_customer ";

        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id_customer' => $id_customer ] );
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Projects_ProjectStruct' );

        return $stmt->fetchAll();
    }

    /**
     * @param     $id
     * @param int $ttl
     *
     * @return DataAccess_IDaoStruct|Projects_ProjectStruct
     */
    public static function findById( $id, $ttl = 0 ) {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( " SELECT * FROM projects WHERE id = :id " );

        return @$thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new Projects_ProjectStruct(), [ 'id' => $id ] )[ 0 ];
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public static function exists( $id ) {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( " SELECT id FROM projects WHERE id = :id " );
        $stmt->execute( [ 'id' => $id ] );
        $row = $stmt->fetch( PDO::FETCH_ASSOC );

        if ( !$row ) {
            return false;
        }

        return true;
    }

    public static function destroyCacheById( $id ) {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( " SELECT * FROM projects WHERE id = :id " );

        return $thisDao->_destroyObjectCache( $stmt, [ 'id' => $id ] );
    }

    /**
     * @param array $id_list
     *
     * @return Projects_ProjectStruct[]|DataAccess_IDaoStruct[]|[]
     */
    public function getByIdList( array $id_list ) {
        if ( empty( $id_list ) ) {
            return [];
        }
        $qMarks = str_repeat( '?,', count( $id_list ) - 1 ) . '?';
        $conn   = Database::obtain()->getConnection();
        $stmt   = $conn->prepare( " SELECT * FROM projects WHERE id IN( $qMarks ) ORDER BY projects.id DESC" );

        return $this->_fetchObject( $stmt, new Projects_ProjectStruct(), $id_list );
    }

    /**
     * @param     $id
     * @param     $password
     *
     * @param int $ttl
     *
     * @return Projects_ProjectStruct
     * @throws \Exceptions\NotFoundException
     */
    static function findByIdAndPassword( $id, $password, $ttl = 0 ) {

        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( self::$_sql_get_by_id_and_password );
        $fetched = $thisDao->setCacheTTL( $ttl )->_fetchObject( $stmt, new Projects_ProjectStruct(), [ 'id' => $id, 'password' => $password ] )[ 0 ];

        if ( !$fetched ) {
            throw new Exceptions\NotFoundException( "No project found." );
        }

        return $fetched;
    }

    static function destroyCacheByIdAndPassword( $id, $password ) {
        $thisDao = new self();
        $conn    = Database::obtain()->getConnection();
        $stmt    = $conn->prepare( self::$_sql_get_by_id_and_password );

        return $thisDao->_destroyObjectCache( $stmt, [ 'id' => $id, 'password' => $password ] );
    }

    /**
     * Returns uncompleted chunks by project ID. Requires 'is_review' to be passed
     * as a param to filter the query.
     *
     * @return Chunks_ChunkStruct[]
     *
     * @throws Exception
     */
    static function uncompletedChunksByProjectId( $id_project, $params = [] ) {
        $params    = Utils::ensure_keys( $params, [ 'is_review' ] );
        $is_review = $params[ 'is_review' ] || false;

        $sql = " SELECT jobs.* FROM jobs INNER join ( " .
                " SELECT j.id, j.password from jobs j
                LEFT JOIN chunk_completion_events events
                ON events.id_job = j.id and events.password = j.password
                LEFT JOIN chunk_completion_updates updates
                ON updates.id_job = j.id and updates.password = j.password
                AND updates.is_review = events.is_review
                WHERE
                (events.is_review = :is_review OR events.is_review IS NULL )
                AND
                ( j.id_project = :id_project AND events.id IS NULL )
                OR events.create_date < updates.last_translation_at
                GROUP BY j.id, j.password
            ) uncomplete ON jobs.id = uncomplete.id
                AND jobs.password = uncomplete.password
                AND jobs.id_project = :id_project
                ";

        \Log::doJsonLog( $sql );

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [
                'is_review'  => $is_review,
                'id_project' => $id_project
        ] );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Chunks_ChunkStruct' );

        return $stmt->fetchAll();

    }

    static function isGDriveProject( $id_project ) {
        $conn = Database::obtain()->getConnection();

        $sql  = "  SELECT count(f.id) "
                . "  FROM files f "
                . " INNER JOIN remote_files r "
                . "    ON f.id = r.id_file "
                . " WHERE f.id_project = :id_project "
                . "   AND r.is_original = 1 ";
        $stmt = $conn->prepare( $sql );
        $stmt->execute( [ 'id_project' => $id_project ] );
        $stmt->setFetchMode( PDO::FETCH_NUM );

        $result = $stmt->fetch();

        $countFiles = $result[ 0 ];

        if ( $countFiles > 0 ) {
            return true;
        }

        return false;
    }


    /**
     * @param $project_ids
     *
     * @return DataAccess_IDaoStruct[]|RemoteFileServiceNameStruct[] *
     */
    public function getRemoteFileServiceName( $project_ids ) {

        $project_ids = implode( ', ', array_map( function ( $id ) {
            return (int)$id;
        }, $project_ids ) );

        $sql = "SELECT id_project, c.service
          FROM files
          JOIN remote_files on files.id = remote_files.id_file
          JOIN connected_services c on c.id = connected_service_id
          WHERE id_project in ( $project_ids )
          GROUP BY id_project, c.service ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql );

        return $this->_fetchObject( $stmt, new RemoteFileServiceNameStruct(), [] );

    }

    protected function _getProjectDataSQLAndValues( $pid, $project_password = null, $jid = null, $jpassword = null ) {

        $query = self::$_sql_project_data;

        $and_1  = $and_2 = $and_3 = null;
        $values = [ $pid ];

        if ( !empty( $project_password ) ) {
            $and_1    = " and p.password = ? ";
            $values[] = $project_password;
        }

        if ( !empty( $jid ) ) {
            $and_2    = " and j.id = ? ";
            $values[] = $jid;
        }

        if ( !empty( $jpassword ) ) {
            $and_3    = " and j.password = ? ";
            $values[] = $jpassword;
        }

        $query = sprintf( $query, $and_1, $and_2, $and_3 );

        return [ $query, $values ];

    }

    /**
     * @param             $pid
     * @param string|null $project_password
     * @param string|null $jid
     * @param string|null $jpassword
     *
     * @return ShapelessConcreteStruct[]
     */
    public function getProjectData( $pid, $project_password = null, $jid = null, $jpassword = null ) {

        list( $query, $values ) = $this->_getProjectDataSQLAndValues( $pid, $project_password, $jid, $jpassword );

        $stmt = $this->_getStatementForCache( $query );

        return $this->_fetchObject( $stmt,
                new ShapelessConcreteStruct(),
                $values
        );

    }

    public function destroyCacheForProjectData( $pid, $project_password = null, $jid = null, $jpassword = null ) {
        list( $query, $values ) = $this->_getProjectDataSQLAndValues( $pid, $project_password, $jid, $jpassword );

        $stmt = $this->_getStatementForCache( $query );

        return $this->_destroyObjectCache( $stmt, $values );

    }

    public static function updateAnalysisStatus( $project_id, $status, $stWordCount ) {

        $update_project_count = "
            UPDATE projects
              SET status_analysis = :status_analysis, 
                  standard_analysis_wc = :standard_analysis_wc
            WHERE id = :id
        ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $update_project_count );

        return $stmt->execute( [
                'status_analysis'      => $status,
                'standard_analysis_wc' => $stWordCount,
                'id'                   => $project_id
        ] );

    }

    public static function changeProjectStatus( $pid, $status ) {
        $data                      = [];
        $data[ 'status_analysis' ] = $status;
        $where                     = [ "id" => $pid ];

        return self::updateFields( $data, $where );
    }

    /**
     * @param int $pid Project Id
     *
     * @return array
     */
    public static function getProjectAndJobData( $pid ) {


        $db = Database::obtain();

        $query = "SELECT projects.id AS pid,
            projects.name AS pname,
            projects.password AS ppassword,
            projects.status_analysis,
            projects.standard_analysis_wc,
            projects.fast_analysis_wc,
            projects.tm_analysis_wc,
            projects.create_date,
            jobs.id AS jid,
            jobs.password AS jpassword,
            job_first_segment,
            job_last_segment,
            jobs.subject,
            jobs.payable_rates,
            CONCAT( jobs.id , '-', jobs.password ) AS jid_jpassword,
            CONCAT( jobs.source, '|', jobs.target ) AS lang_pair,
            CONCAT( projects.name, '/', jobs.source, '-', jobs.target, '/', jobs.id , '-', jobs.password ) AS job_url,
            status_owner
                FROM jobs
                JOIN projects ON jobs.id_project = projects.id
                WHERE projects.id = :pid
                ORDER BY jid, job_last_segment
                ";

        $stmt = $db->getConnection()->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute( [ 'pid' => $pid ] );

        return $stmt->fetchAll();


    }

    /**
     * @param $pid
     *
     * @return array
     */
    public function getJobIds( $pid ) {
        $db = Database::obtain();

        $query = "SELECT jobs.id
                FROM jobs
                WHERE jobs.id_project = :pid
                ";

        $stmt = $db->getConnection()->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute( [ 'pid' => $pid ] );

        return $stmt->fetchAll();
    }

    /**
     * Get a password map (t, r1, r2)
     *
     * @param $pid
     * @return array
     */
    public function getPasswordsMap($pid)
    {
        $db = Database::obtain();

        $query = "select
            j.id as id_job	,
            j.job_first_segment,
            j.job_last_segment,
         j.password as t_password, 
         r.review_password as r_password,
         r2.review_password as r2_password
         from jobs j
         left join qa_chunk_reviews r on r.id_job = j.id and r.source_page = 2 and r.password = j.password
         left join qa_chunk_reviews r2 on r2.id_job = j.id and r2.source_page = 3 and r2.password = j.password
         where j.id_project = :pid;";

        $stmt = $db->getConnection()->prepare( $query );
        $stmt->setFetchMode( PDO::FETCH_ASSOC );
        $stmt->execute( [ 'pid' => $pid ] );

        return $stmt->fetchAll();
    }
}
