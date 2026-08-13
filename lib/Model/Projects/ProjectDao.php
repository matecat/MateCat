<?php

namespace Model\Projects;

use DomainException;
use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\IDaoStruct;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Exceptions\NotFoundException;
use Model\RemoteFiles\RemoteFileServiceNameStruct;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PDO;
use PDOException;
use ReflectionException;
use Utils\Constants\ProjectStatus;

class ProjectDao extends AbstractDao
{
    const string TABLE = "projects";

    /**
     * The statement findByJobId() reads through. Eviction has to rebuild it byte for byte, because the
     * cache key is the md5 of the query string plus its bound parameters: a single changed space would
     * leave the entry unreachable and permanently stale. Shared as a constant so it cannot drift.
     */
    private const string SQL_FIND_BY_JOB_ID = "SELECT projects.* FROM projects " .
    " INNER JOIN jobs ON projects.id = jobs.id_project " .
    " WHERE jobs.id = :id_job " .
    " LIMIT 1 ";

    /** @var list<string> */
    protected static array $auto_increment_field = ['id'];
    /** @var list<string> */
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
     * @param int|float|string|bool|null $value
     *
     * @return ProjectStruct
     * @throws DomainException
     * @throws PDOException
     * @throws ReflectionException
     */
    public function updateField(ProjectStruct $project, string $field, int|float|string|bool|null $value): ProjectStruct
    {
        $data = [];
        $data[$field] = $value;
        $where = ["id" => $project->id];

        $success = $this->updateFields($data, $where);

        if ($success) {
            $project->$field = $value;
            $this->destroyCache((int)$project->id);
        }

        return $project;
    }

    /**
     * @param ProjectStruct $project
     * @param string $newPass
     *
     * @return ProjectStruct
     * @throws DomainException
     * @throws PDOException
     * @throws ReflectionException
     * @internal param $pid
     */
    public function changePassword(ProjectStruct $project, string $newPass): ProjectStruct
    {
        $project->id ?? throw new DomainException("Project ID must not be null when changing password");

        $oldPass = $project->password;

        $updated = $this->updateField($project, 'password', $newPass);

        // the eviction inside updateField() runs against the row as it is now, so it can only reach the
        // keys built from the new password. The old one is no longer discoverable anywhere: pass it in,
        // or every entry cached under it stays readable until it expires on its own.
        $this->destroyCache((int)$updated->id, $oldPass);

        return $updated;
    }

    /**
     * @param ProjectStruct $project
     * @param string $name
     *
     * @return ProjectStruct
     * @throws DomainException
     * @throws PDOException
     * @throws ReflectionException
     */
    public function changeName(ProjectStruct $project, string $name): ProjectStruct
    {
        $project->id ?? throw new DomainException("Project ID must not be null when changing name");

        return $this->updateField($project, 'name', $name);
    }

    /**
     *
     * This update can easily become massive in case of long lived teams.
     *
     * @param TeamStruct $team
     * @param UserStruct $user
     *
     * @return int
     * @throws PDOException
     * @throws ReflectionException
     */
    public function unassignProjects(TeamStruct $team, UserStruct $user): int
    {
        $affectedIds = $this->getIdsByTeam((int)$team->id, (int)$user->uid);

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(static::$_sql_for_project_unassignment);
        $stmt->execute([
            'id_assignee' => $user->uid,
            'id_team' => $team->id
        ]);

        $this->destroyCacheByIds($affectedIds);

        return $stmt->rowCount();
    }

    /**
     * @param TeamStruct $team
     * @param UserStruct $user
     * @param TeamStruct $personalTeam
     *
     * @return int
     * @throws PDOException
     * @throws ReflectionException
     */
    public function massiveSelfAssignment(TeamStruct $team, UserStruct $user, TeamStruct $personalTeam): int
    {
        $affectedIds = $this->getIdsByTeam((int)$team->id);

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(static::$_sql_massive_self_assignment);
        $stmt->execute([
            'id_assignee' => $user->uid,
            'id_team' => $team->id,
            'personal_team' => $personalTeam->id
        ]);

        $this->destroyCacheByIds($affectedIds);

        return $stmt->rowCount();
    }

    /**
     * The ids a team-wide update is about to touch, read before the write so the rows are still
     * matchable by the old team. Feeds cache eviction: both team updates change `id_team` and
     * `id_assignee`, and nothing else can name the affected projects once the update has run.
     *
     * @param int $id_team
     * @param int|null $id_assignee restricts the set to the projects assigned to this user
     *
     * @return int[]
     * @throws PDOException
     */
    private function getIdsByTeam(int $id_team, ?int $id_assignee = null): array
    {
        $sql = "SELECT id FROM projects WHERE id_team = :id_team ";
        $params = ['id_team' => $id_team];

        if ($id_assignee !== null) {
            $sql .= " AND id_assignee = :id_assignee ";
            $params['id_assignee'] = $id_assignee;
        }

        $stmt = $this->database->getConnection()->prepare($sql);
        $stmt->execute($params);

        /** @var array<int, int|string> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_map('intval', $rows);
    }

    /**
     * @param int[] $ids
     *
     * @return void
     * @throws PDOException
     * @throws ReflectionException
     */
    private function destroyCacheByIds(array $ids): void
    {
        foreach ($ids as $id) {
            $this->destroyCache($id);
        }
    }

    /**
     * The single eviction entry point for a project: call this after ANY write to a projects row and
     * every cached copy of that row goes, whichever read put it there.
     *
     * The row is cached under keys that share nothing but the project: `findById()` keys on the id,
     * `findByJobId()` on each job id, `findByIdAndPassword()` on id plus password, `getProjectData()`
     * on the id with or without the password — and each key is the md5 of its own query string and
     * bound parameters, so none of them can be derived from another. That is why this is a method and
     * not a one-liner, and why callers should never reach for the specialized evictions directly.
     *
     * Call it AFTER the write. The jobs and the current password are read live, and neither changes
     * on the writes that reach here — except a password change, which is why `$password` exists:
     * pass the OLD password so the key it was cached under dies with it.
     *
     * @param int $id
     * @param string|null $password an additional password whose keys must go, on top of the current one
     *
     * @return void
     * @throws PDOException
     * @throws ReflectionException
     */
    public function destroyCache(int $id, ?string $password = null): void
    {
        $this->destroyCacheForProject($id);

        $passwords = [];
        foreach ([$password, $this->getPassword($id)] as $candidate) {
            if ($candidate !== null && !in_array($candidate, $passwords, true)) {
                $passwords[] = $candidate;
            }
        }

        // the password-less key is what CommentController and UrlsController cache under
        $this->destroyCacheForProjectData($id);

        foreach ($passwords as $candidate) {
            $this->destroyCacheByIdAndPassword($id, $candidate);
            $this->destroyCacheForProjectData($id, $candidate);
        }
    }

    /**
     * Read live and deliberately uncached: this feeds cache eviction, so answering it from the cache
     * being evicted would be circular.
     *
     * @param int $id
     *
     * @return string|null null when the project is gone
     * @throws PDOException
     */
    private function getPassword(int $id): ?string
    {
        $stmt = $this->database->getConnection()->prepare("SELECT password FROM projects WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $password = $stmt->fetchColumn();

        return $password === false ? null : (string)$password;
    }

    /**
     * @param int $id
     *
     * @return void
     * @throws PDOException
     */
    private function destroyCacheForProject(int $id): void
    {
        $this->destroyFetchByIdCache($id, ProjectStruct::class);

        $stmt = $this->database->getConnection()->prepare(self::SQL_FIND_BY_JOB_ID);

        // a split job keeps one row per chunk under the same jobs.id, and they all share one cache key
        $jobIds = array_unique(array_map(static fn(array $job): int => (int)$job['id'], $this->getJobIds($id)));

        foreach ($jobIds as $id_job) {
            $this->_destroyObjectCache($stmt, ProjectStruct::class, ['id_job' => $id_job]);
        }
    }

    /**
     * @param array<int, int> $id_list
     *
     * @return ProjectStruct[]|IDaoStruct[]
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getByIdList(array $id_list): array
    {
        if (empty($id_list)) {
            return [];
        }
        $qMarks = str_repeat('?,', count($id_list) - 1) . '?';
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(" SELECT * FROM projects WHERE id IN( $qMarks ) ORDER BY projects.id DESC");

        return $this->_fetchObjectMap($stmt, ProjectStruct::class, $id_list);
    }

    /**
     * @param array<int, int> $project_ids
     *
     * @return RemoteFileServiceNameStruct[]
     * @throws Exception
     * @throws PDOException
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

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);

        return $this->_fetchObjectMap($stmt, RemoteFileServiceNameStruct::class, []);
    }

    /**
     * @return array{0: string, 1: array<int, int|string>}
     */
    protected function _getProjectDataSQLAndValues(int $pid, ?string $project_password = null): array
    {
        $and_1 = null;
        $values = [$pid];

        if (!empty($project_password)) {
            $and_1 = " and p.password = ? ";
            $values[] = $project_password;
        }

        $query = sprintf(self::$_sql_project_data, $and_1);

        return [$query, $values];
    }

    /**
     * @param int $pid
     * @param string|null $project_password
     *
     * @return ShapelessConcreteStruct[]
     * @throws Exception
     * @throws ReflectionException
     */
    public function getProjectData(int $pid, ?string $project_password = null): array
    {
        [$query, $values] = $this->_getProjectDataSQLAndValues($pid, $project_password);

        $stmt = $this->_getStatementForQuery($query);

        return $this->_fetchObjectMap(
            $stmt,
            ShapelessConcreteStruct::class,
            $values
        );
    }

    /**
     * @throws PDOException
     * @throws ReflectionException
     */
    private function destroyCacheForProjectData(int $pid, ?string $project_password = null): bool
    {
        [$query, $values] = $this->_getProjectDataSQLAndValues($pid, $project_password);

        $stmt = $this->_getStatementForQuery($query);

        return $this->_destroyObjectCache($stmt, ShapelessConcreteStruct::class, $values);
    }

    /**
     * @param int $pid
     *
     * @return array<int, array{id: int|string}>
     * @throws PDOException
     */
    public function getJobIds(int $pid): array
    {
        $query = "SELECT jobs.id
                FROM jobs
                WHERE jobs.id_project = :pid
                ";

        $stmt = $this->database->getConnection()->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(['pid' => $pid]);

        return $stmt->fetchAll();
    }

    /**
     * Get a password map (t, r1, r2)
     *
     * @param int $pid
     *
     * @return array<int, array<string, int|string|null>>
     * @throws PDOException
     */
    public function getPasswordsMap(int $pid): array
    {
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

        $stmt = $this->database->getConnection()->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(['pid' => $pid]);

        return $stmt->fetchAll();
    }

    // ─── Instance methods ───

    /**
     * @param int $id_team
     * @param array{limit?: int, offset?: int, search?: array{id?: int, name?: string}} $filter
     * @param int $ttl
     *
     * @return IDaoStruct[]
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function findByTeamId(int $id_team, array $filter = [], int $ttl = 0): array
    {
        $conn = $this->database->getConnection();

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

        // paging without an order is paging over an undefined sequence: rows can repeat or vanish
        // between two pages. `id_team_idx` carries the primary key as its suffix, so ordering by id
        // is the order the index already produces — EXPLAIN reports no filesort for it.
        $query .= ' ORDER BY id ASC ';

        if (isset($limit) and isset($offset)) {
            $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        }

        $stmt = $conn->prepare($query);

        return $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, ProjectStruct::class, $values);
    }

    /**
     * `id_team_idx` covers `id_team` alone, so every candidate row still has to be read from the
     * clustered index to test `status_analysis`. An exact total therefore costs one row read per
     * project in the team, which the largest teams measure in the hundreds of thousands. The count
     * stops at {@see ProjectsCount::DEFAULT_CAP} instead: the work no longer depends on team size,
     * and the caller learns from the result whether the figure is exact.
     *
     * @param int $id_team
     * @param array{search?: array{id?: int, name?: string}} $filter
     * @param int $ttl
     * @param int $cap the point at which counting stops; overridable so the boundary is testable
     *
     * @return ProjectsCount
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function getTotalCountByTeamId(int $id_team, array $filter = [], int $ttl = 0, int $cap = ProjectsCount::DEFAULT_CAP): ProjectsCount
    {
        $conn = $this->database->getConnection();

        $searchId = (isset($filter['search']['id'])) ? $filter['search']['id'] : null;
        $searchName = (isset($filter['search']['name'])) ? $filter['search']['name'] : null;

        $counted = "SELECT id FROM projects WHERE id_team = :id_team AND status_analysis NOT IN( :status1, :status2 ) ";

        $values = [
            'id_team' => $id_team,
            'status1' => ProjectStatus::STATUS_NOT_READY_FOR_ANALYSIS,
            'status2' => ProjectStatus::STATUS_NOT_TO_ANALYZE
        ];

        if ($searchId) {
            $counted .= ' AND id = :id ';
            $values['id'] = $searchId;
        }

        if ($searchName) {
            $counted .= ' AND name = :name ';
            $values['name'] = $searchName;
        }

        $counted .= ' LIMIT ' . ProjectsCount::queryLimit($cap);

        $stmt = $conn->prepare("SELECT count(*) as totals FROM ( $counted ) counted");

        $results = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, $values);

        return ProjectsCount::fromCappedQuery((isset($results[0])) ? (int)$results[0]['totals'] : 0, $cap);
    }

    /**
     * @param int $id_job
     * @param int $ttl
     *
     * @return ProjectStruct|null
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function findByJobId(int $id_job, int $ttl = 0): ?ProjectStruct
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(self::SQL_FIND_BY_JOB_ID);

        /** @var ProjectStruct $result */
        $result = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, ProjectStruct::class, ['id_job' => $id_job])[0] ?? null;

        return $result;
    }

    /**
     * @param int|string $id_customer
     *
     * @return ProjectStruct[]
     * @throws PDOException
     */
    public function findByIdCustomer(int|string $id_customer): array
    {
        $conn = $this->database->getConnection();
        $sql = "SELECT projects.* FROM projects " .
            " WHERE id_customer = :id_customer ";

        $stmt = $conn->prepare($sql);
        $stmt->execute(['id_customer' => $id_customer]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, ProjectStruct::class);

        return $stmt->fetchAll();
    }

    /**
     * @param int $id
     * @param int $ttl
     *
     * @return ?ProjectStruct
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function findById(int $id, int $ttl = 0): ?ProjectStruct
    {
        /** @var ?ProjectStruct $res */
        $res = $this->fetchById($id, ProjectStruct::class, $ttl ?: null);

        return $res;
    }

    /**
     * @param int $id
     *
     * @return bool
     * @throws PDOException
     */
    public function exists(int $id): bool
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare("SELECT * FROM projects WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param int $id
     * @param string $password
     * @param int $ttl
     *
     * @return ProjectStruct
     * @throws Exception
     * @throws PDOException
     * @throws NotFoundException|ReflectionException
     */
    public function findByIdAndPassword(int $id, string $password, int $ttl = 0): ProjectStruct
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(self::$_sql_get_by_id_and_password);
        $fetched = $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, ProjectStruct::class, ['id' => $id, 'password' => $password])[0] ?? null;

        if (!$fetched) {
            throw new NotFoundException("No project found.");
        }

        /** @var ProjectStruct $fetched */
        return $fetched;
    }

    /**
     * @throws PDOException
     * @throws ReflectionException
     */
    private function destroyCacheByIdAndPassword(int $id, string $password): bool
    {
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare(self::$_sql_get_by_id_and_password);

        return $this->_destroyObjectCache($stmt, ProjectStruct::class, ['id' => $id, 'password' => $password]);
    }

    /**
     * @throws PDOException
     */
    public function isGDriveProject(int $id_project): bool
    {
        $conn = $this->database->getConnection();

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
     * @throws PDOException
     * @throws ReflectionException
     */
    public function updateAnalysisStatus(int $project_id, string $status, int $stWordCount): bool
    {
        $update_project_count = "
            UPDATE projects
              SET status_analysis = :status_analysis,
                  standard_analysis_wc = :standard_analysis_wc
            WHERE id = :id
        ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($update_project_count);

        $success = $stmt->execute([
            'status_analysis' => $status,
            'standard_analysis_wc' => $stWordCount,
            'id' => $project_id
        ]);

        if ($success) {
            $this->destroyCache($project_id);
        }

        return $success;
    }

    /**
     * @throws DomainException
     * @throws PDOException
     * @throws ReflectionException
     */
    public function changeProjectStatus(int $pid, string $status): int
    {
        $data = [];
        $data['status_analysis'] = $status;
        $where = ["id" => $pid];

        $affected = $this->updateFields($data, $where);

        if ($affected > 0) {
            $this->destroyCache($pid);
        }

        return $affected;
    }

    /**
     * Atomically set the project status unless it is already DONE, so a concurrent TM-worker
     * completion (which sets status_analysis = DONE) is never overwritten by a late FAST_OK/BUSY
     * write from the fast-analysis daemon. A single conditional UPDATE — no read-then-write race,
     * unlike select-then-update which a non-locking snapshot read leaves open under REPEATABLE READ.
     *
     * @return int affected rows: 0 means the project was already DONE (or gone) and the write was
     *             intentionally skipped
     * @throws PDOException
     * @throws ReflectionException
     */
    public function changeProjectStatusIfNotDone(int $pid, string $status): int
    {
        $stmt = $this->database->getConnection()->prepare(
            "UPDATE projects SET status_analysis = :status WHERE id = :id AND status_analysis != :done"
        );
        $stmt->execute([
            'status' => $status,
            'id'     => $pid,
            'done'   => ProjectStatus::STATUS_DONE,
        ]);

        $affected = $stmt->rowCount();

        if ($affected > 0) {
            $this->destroyCache($pid);
        }

        return $affected;
    }

    /**
     * @param int $pid Project ID
     *
     * @return array<int, array<string, int|string|null>>
     * @throws PDOException
     */
    public function getProjectAndJobData(int $pid): array
    {
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

        $stmt = $this->database->getConnection()->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(['pid' => $pid]);

        return $stmt->fetchAll();
    }
}
