<?php

namespace Model\Projects;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\Database;
use Model\DataAccess\IDaoStruct;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Exceptions\NotFoundException;
use Model\Jobs\JobStruct;
use Model\RemoteFiles\RemoteFileServiceNameStruct;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PDO;
use ReflectionException;
use Utils\Constants\ProjectStatus;
use Utils\Logger\LoggerFactory;
use Utils\Tools\Utils;

class ProjectDao extends AbstractDao
{
    const string TABLE = "projects";

    protected static array $auto_increment_field = ['id'];
    protected static array $primary_keys = ['id'];

    protected static string $_sql_project_data = "
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

    protected static string $_sql_get_by_id_and_password = "SELECT * FROM projects WHERE id = :id AND password = :password ";

    /**
     * @var string
     */
    protected static string $_sql_for_project_unassignment = "
        UPDATE projects SET id_assignee = NULL WHERE id_assignee = :id_assignee and id_team = :id_team ;
    ";

    /**
     * @var string
     */
    protected static string $_sql_massive_self_assignment = "
        UPDATE projects SET id_assignee = :id_assignee , id_team = :personal_team WHERE id_team = :id_team ;
    ";

    /**
     * @var string
     */
    protected static string $_sql_get_projects_for_team = "SELECT * FROM projects WHERE id_team = :id_team AND status_analysis NOT IN( :status1, :status2 ) ";

    /**
     * @param ProjectStruct $project
     * @param string $field
     * @param                        $value
     *
     * @return ProjectStruct
     */
    public function updateField(ProjectStruct $project, string $field, $value): ProjectStruct
    {
        $data = [];
        $data[$field] = $value;
        $where = ["id" => $project->id];

        $success = self::updateFields($data, $where);

        if ($success) {
            $project->$field = $value;
        }

        return $project;
    }

    /**
     * @param ProjectStruct $project
     * @param string $newPass
     *
     * @return ProjectStruct
     * @throws ReflectionException
     * @internal param $pid
     */
    public function changePassword(ProjectStruct $project, string $newPass): ProjectStruct
    {
        $res = $this->updateField($project, 'password', $newPass);
        $this->destroyCacheById($project->id);

        return $res;
    }

    /**
     * @param ProjectStruct $project
     * @param string $name
     *
     * @return ProjectStruct
     * @throws ReflectionException
     */
    public function changeName(ProjectStruct $project, string $name): ProjectStruct
    {
        $res = $this->updateField($project, 'name', $name);
        $this->destroyCacheById($project->id);

        return $res;
    }

    public function deleteFailedProject(?int $idProject): int
    {
        if (empty($idProject)) {
            return 0;
        }

        $sql = "DELETE FROM projects WHERE id = :id_project";
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id_project' => $idProject]);

        return $stmt->rowCount();
    }

    /**
     *
     * This update can easily become massive in case of long lived teams.
     *
     * @param TeamStruct $team
     * @param UserStruct $user
     *
     * @return int
     */
    public function unassignProjects(TeamStruct $team, UserStruct $user): int
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(static::$_sql_for_project_unassignment);
        $stmt->execute([
            'id_assignee' => $user->uid,
            'id_team' => $team->id
        ]);

        return $stmt->rowCount();
    }

    public function assignToAssignee(int $pid, int $idAssignee)
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("UPDATE projects SET id_assignee = :id_assignee WHERE id = :id ;");
        $stmt->execute([
            'id' => $pid,
            'id_assignee' => $idAssignee
        ]);

        return $stmt->rowCount();
    }

    public function assignToTeam(int $pid, int $idTeam)
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare("UPDATE projects SET id_team = :id_team WHERE id = :id ;");
        $stmt->execute([
            'id' => $pid,
            'id_team' => $idTeam
        ]);
    }

    /**
     * @param TeamStruct $team
     * @param UserStruct $user
     * @param TeamStruct $personalTeam
     *
     * @return int
     */
    public function massiveSelfAssignment(TeamStruct $team, UserStruct $user, TeamStruct $personalTeam): int
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(static::$_sql_massive_self_assignment);
        $stmt->execute([
            'id_assignee' => $user->uid,
            'id_team' => $team->id,
            'personal_team' => $personalTeam->id
        ]);

        return $stmt->rowCount();
    }

    /**
     * @param int $id_team
     * @param int $ttl
     * @param array $filter
     *
     * @return IDaoStruct[]
     * @throws ReflectionException
     */
    public static function findByTeamId(int $id_team, array $filter = [], int $ttl = 0): array
    {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();

        $limit = (isset($filter['limit'])) ? $filter['limit'] : null;
        $offset = (isset($filter['offset'])) ? $filter['offset'] : null;
        $searchId = (isset($filter['search']['id'])) ? $filter['search']['id'] : null;
        $searchName = (isset($filter['search']['name'])) ? $filter['search']['name'] : null;

        $query = self::$_sql_get_projects_for_team;

        $values = [
            'id_team' => $id_team,
            'status1' => ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS,
            'status2' => ProjectStatus::STATUS_NOT_TO_ANALYZE
        ];

        if ($searchId) {
            $query .= ' AND id = :id ';
            $values['id'] = $searchId;
        }

        if ($searchName) {
            $query .= ' AND name = :name ';
            $values['name'] = $searchName;
        }

        if (isset($limit) and isset($offset)) {
            $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        $stmt = $conn->prepare($query);

        return $thisDao->setCacheTTL($ttl)->_fetchObjectMap($stmt, ProjectStruct::class, $values);
    }

    /**
     * @param int $id_team
     * @param array $filter
     * @param int $ttl
     *
     * @return int
     * @throws ReflectionException
     */
    public static function getTotalCountByTeamId(int $id_team, array $filter = [], int $ttl = 0): int
    {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();

        $searchId = (isset($filter['search']['id'])) ? $filter['search']['id'] : null;
        $searchName = (isset($filter['search']['name'])) ? $filter['search']['name'] : null;

        $query = "SELECT count(id) as totals FROM projects WHERE id_team = :id_team AND status_analysis NOT IN( :status1, :status2 ) ";

        $values = [
            'id_team' => $id_team,
            'status1' => ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS,
            'status2' => ProjectStatus::STATUS_NOT_TO_ANALYZE
        ];

        if ($searchId) {
            $query .= ' AND id = :id ';
            $values['id'] = $searchId;
        }

        if ($searchName) {
            $query .= ' AND name = :name ';
            $values['name'] = $searchName;
        }

        $stmt = $conn->prepare($query);

        $results = $thisDao->setCacheTTL($ttl)->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, $values);

        return (isset($results[0])) ? (int)$results[0]['totals'] : 0;
    }

    /**
     * @param int $id_job
     * @param int $ttl
     *
     * @return ProjectStruct|null
     * @throws ReflectionException
     */
    public static function findByJobId(int $id_job, int $ttl = 0): ?ProjectStruct
    {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $sql = "SELECT projects.* FROM projects " .
            " INNER JOIN jobs ON projects.id = jobs.id_project " .
            " WHERE jobs.id = :id_job " .
            " LIMIT 1 ";
        $stmt = $conn->prepare($sql);

        /** @var ProjectStruct $result */
        $result = $thisDao->setCacheTTL($ttl)->_fetchObjectMap($stmt, ProjectStruct::class, ['id_job' => $id_job])[0] ?? null;

        return $result;
    }

    /**
     * @param $id_customer
     *
     * @return ProjectStruct[]
     */

    static function findByIdCustomer($id_customer): array
    {
        $conn = Database::obtain()->getConnection();
        $sql = "SELECT projects.* FROM projects " .
            " WHERE id_customer = :id_customer ";

        $stmt = $conn->prepare($sql);
        $stmt->execute(['id_customer' => $id_customer]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, ProjectStruct::class);

        return $stmt->fetchAll();
    }

    /**
     * @param     $id
     * @param int $ttl
     *
     * @return ?ProjectStruct
     * @throws ReflectionException
     */
    public static function findById($id, int $ttl = 0): ?ProjectStruct
    {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(" SELECT * FROM projects WHERE id = :id ");

        /** @var ?ProjectStruct $res */
        $res = $thisDao->setCacheTTL($ttl)->_fetchObjectMap($stmt, ProjectStruct::class, ['id' => $id])[0] ?? null;

        return $res;
    }

    /**
     * @param int $id
     *
     * @return bool
     */
    public static function exists(int $id): bool
    {
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(" SELECT id FROM projects WHERE id = :id ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        return true;
    }

    /**
     * @throws ReflectionException
     */
    public static function destroyCacheById(int $id): bool
    {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(" SELECT * FROM projects WHERE id = :id ");

        return $thisDao->_destroyObjectCache($stmt, ProjectStruct::class, ['id' => $id]);
    }

    /**
     * @param array $id_list
     *
     * @return ProjectStruct[]|IDaoStruct[]|[]
     * @throws ReflectionException
     */
    public function getByIdList(array $id_list): array
    {
        if (empty($id_list)) {
            return [];
        }
        $qMarks = str_repeat('?,', count($id_list) - 1) . '?';
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(" SELECT * FROM projects WHERE id IN( $qMarks ) ORDER BY projects.id DESC");

        return $this->_fetchObjectMap($stmt, ProjectStruct::class, $id_list);
    }

    /**
     * @param     $id
     * @param     $password
     *
     * @param int $ttl
     *
     * @return ProjectStruct
     * @throws NotFoundException|ReflectionException
     */
    static function findByIdAndPassword($id, $password, int $ttl = 0): ProjectStruct
    {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(self::$_sql_get_by_id_and_password);
        $fetched = $thisDao->setCacheTTL($ttl)->_fetchObjectMap($stmt, ProjectStruct::class, ['id' => $id, 'password' => $password])[0] ?? null;

        if (!$fetched) {
            throw new NotFoundException("No project found.");
        }

        /** @var ProjectStruct $fetched */
        return $fetched;
    }

    /**
     * @throws ReflectionException
     */
    static function destroyCacheByIdAndPassword(int $id, string $password): bool
    {
        $thisDao = new self();
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare(self::$_sql_get_by_id_and_password);

        return $thisDao->_destroyObjectCache($stmt, ProjectStruct::class, ['id' => $id, 'password' => $password]);
    }

    /**
     * Returns uncompleted chunks by project ID. Requires 'is_review' to be passed
     * as a param to filter the query.
     *
     * @return JobStruct[]
     *
     * @throws Exception
     */
    static function uncompletedChunksByProjectId($id_project, $params = []): array
    {
        $params = Utils::ensure_keys($params, ['is_review']);
        $is_review = $params['is_review'] ?: false;

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

        LoggerFactory::doJsonLog($sql);

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'is_review' => $is_review,
            'id_project' => $id_project
        ]);

        $stmt->setFetchMode(PDO::FETCH_CLASS, JobStruct::class);

        return $stmt->fetchAll();
    }

    static function isGDriveProject($id_project): bool
    {
        $conn = Database::obtain()->getConnection();

        $sql = "  SELECT count(f.id) "
            . "  FROM files f "
            . " INNER JOIN remote_files r "
            . "    ON f.id = r.id_file "
            . " WHERE f.id_project = :id_project "
            . "   AND r.is_original = 1 ";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['id_project' => $id_project]);
        $stmt->setFetchMode(PDO::FETCH_NUM);

        $result = $stmt->fetch();

        $countFiles = $result[0];

        if ($countFiles > 0) {
            return true;
        }

        return false;
    }


    /**
     * @param array $project_ids
     *
     * @return RemoteFileServiceNameStruct[]
     * @throws ReflectionException
     */
    public function getRemoteFileServiceName(array $project_ids): array
    {
        $project_ids = implode(', ', array_map(function ($id) {
            return (int)$id;
        }, $project_ids));

        $sql = "SELECT id_project, c.service
          FROM files
          JOIN remote_files on files.id = remote_files.id_file
          JOIN connected_services c on c.id = connected_service_id
          WHERE id_project in ( $project_ids )
          GROUP BY id_project, c.service ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($sql);

        return $this->_fetchObjectMap($stmt, RemoteFileServiceNameStruct::class, []);
    }

    protected function _getProjectDataSQLAndValues(int $pid, ?string $project_password = null, ?int $jid = null, ?string $jpassword = null): array
    {
        $query = self::$_sql_project_data;

        $and_1 = $and_2 = $and_3 = null;
        $values = [$pid];

        if (!empty($project_password)) {
            $and_1 = " and p.password = ? ";
            $values[] = $project_password;
        }

        if (!empty($jid)) {
            $and_2 = " and j.id = ? ";
            $values[] = $jid;
        }

        if (!empty($jpassword)) {
            $and_3 = " and j.password = ? ";
            $values[] = $jpassword;
        }

        $query = sprintf($query, $and_1, $and_2, $and_3);

        return [$query, $values];
    }

    /**
     * @param int $pid
     * @param string|null $project_password
     * @param int|null $jid
     * @param string|null $jpassword
     *
     * @return ShapelessConcreteStruct[]
     * @throws ReflectionException
     */
    public function getProjectData(int $pid, ?string $project_password = null, ?int $jid = null, ?string $jpassword = null): array
    {
        [$query, $values] = $this->_getProjectDataSQLAndValues($pid, $project_password, $jid, $jpassword);

        $stmt = $this->_getStatementForQuery($query);

        return $this->_fetchObjectMap(
            $stmt,
            ShapelessConcreteStruct::class,
            $values
        );
    }

    /**
     * @throws ReflectionException
     */
    public function destroyCacheForProjectData($pid, $project_password = null, $jid = null, $jpassword = null): bool
    {
        [$query, $values] = $this->_getProjectDataSQLAndValues($pid, $project_password, $jid, $jpassword);

        $stmt = $this->_getStatementForQuery($query);

        return $this->_destroyObjectCache($stmt, ShapelessConcreteStruct::class, $values);
    }

    public static function updateAnalysisStatus($project_id, $status, $stWordCount): bool
    {
        $update_project_count = "
            UPDATE projects
              SET status_analysis = :status_analysis, 
                  standard_analysis_wc = :standard_analysis_wc
            WHERE id = :id
        ";

        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare($update_project_count);

        return $stmt->execute([
            'status_analysis' => $status,
            'standard_analysis_wc' => $stWordCount,
            'id' => $project_id
        ]);
    }

    public static function changeProjectStatus($pid, $status): int
    {
        $data = [];
        $data['status_analysis'] = $status;
        $where = ["id" => $pid];

        return self::updateFields($data, $where);
    }

    /**
     * @param int $pid Project ID
     *
     * @return array
     */
    public static function getProjectAndJobData(int $pid): array
    {
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

        $stmt = $db->getConnection()->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(['pid' => $pid]);

        return $stmt->fetchAll();
    }

    /**
     * @param $pid
     *
     * @return array
     */
    public function getJobIds($pid): array
    {
        $db = Database::obtain();

        $query = "SELECT jobs.id
                FROM jobs
                WHERE jobs.id_project = :pid
                ";

        $stmt = $db->getConnection()->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(['pid' => $pid]);

        return $stmt->fetchAll();
    }

    /**
     * Get a password map (t, r1, r2)
     *
     * @param $pid
     *
     * @return array
     */
    public function getPasswordsMap($pid): array
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

        $stmt = $db->getConnection()->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(['pid' => $pid]);

        return $stmt->fetchAll();
    }
}
