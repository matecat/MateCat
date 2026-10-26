<?php


namespace Model\Translations;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\Jobs\JobStruct;
use Model\Jobs\WarningsCountStruct;
use Model\Warnings\GlobalWarningStruct;
use PDOException;
use ReflectionException;
use Utils\Constants\TranslationStatus;

class WarningDao extends AbstractDao
{

    protected string $_query_warnings_by_chunk = "
          SELECT count(1) AS count, jobs.id AS id_job, jobs.password
            FROM jobs
              JOIN segment_translations st ON st.id_job = jobs.id AND id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
          WHERE ( st.warning & :level ) = :level
            AND id = :id AND password = :password
            AND st.status != :status
        ";

    /**
     * `warnings_count`, the warnings list of `/warnings` and the job's translation mismatches are
     * all cached this long, under one key map per job, and evicted by every write that can change them.
     */
    public const int WARNINGS_CACHE_TTL = 5 * 60;

    /**
     * Segments counted by `warnings_count`: the same ones `getWarningsByJobIdAndPassword()` lists,
     * so the count and the `/warnings` endpoint never disagree.
     */
    protected const string _query_warnings_count_by_job = "
        SELECT COUNT(st.id_segment) AS count,
               jobs.id AS id_job,
               jobs.password,
               GROUP_CONCAT(st.id_segment ORDER BY st.id_segment) AS segment_list
          FROM segment_translations st
          JOIN jobs ON st.id_job = jobs.id AND st.id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
         WHERE jobs.id = :id_job
           AND jobs.password = :password
           AND ( st.warning & 1 ) = 1
           AND st.status != :status_new
         GROUP BY jobs.id, jobs.password
    ";

    /**
     * The count, the warnings list and the translation mismatches of every chunk and every password
     * of one job id live in the same map, so a write that only knows the job id evicts all of them at once.
     */
    public static function warningsKeyMap(int $id_job): string
    {
        return self::class . "::warnings-" . $id_job;
    }

    /**
     * @return WarningsCountStruct|null null when no segment of the chunk is flagged
     *
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    public function getWarningsCountByJob(int $id_job, string $password): ?WarningsCountStruct
    {
        $stmt = $this->_getStatementForQuery(self::_query_warnings_count_by_job);

        return $this->_fetchObjectMap($stmt, WarningsCountStruct::class, [
            'id_job'     => $id_job,
            'password'   => $password,
            'status_new' => TranslationStatus::STATUS_NEW,
        ], self::warningsKeyMap($id_job))[0] ?? null;
    }

    /**
     * Called by every write that can change `segment_translations.warning`, `.status`, `.translation`
     * or `.serialized_errors_list`, and by split/merge, which move segments between chunks. Inside an
     * open transaction the eviction is queued for the commit.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function destroyCachesByJobId(int $id_job): void
    {
        $this->_deleteCacheByKey(self::warningsKeyMap($id_job), false);
    }

    /**
     * @param JobStruct $chunk
     *
     * @return int
     * @throws PDOException
     */
    public function getErrorsByChunk(JobStruct $chunk): int
    {
        $con = $this->database->getConnection();

        $stmt = $con->prepare($this->_query_warnings_by_chunk);
        $stmt->execute([
            'id' => $chunk->id,
            'password' => $chunk->password,
            'level' => WarningModel::ERROR,
            'status' => TranslationStatus::STATUS_NEW
        ]);

        $result = $stmt->fetch() ?: [];

        return $result['count'] ?? 0;
    }

    /**
     * @param int $jid
     * @param string $jpassword
     *
     * @return array<int, GlobalWarningStruct>
     *
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    public function getWarningsByJobIdAndPassword(int $jid, string $jpassword): array
    {
        $query = "SELECT id_segment, serialized_errors_list
		FROM segment_translations
		JOIN jobs ON jobs.id = id_job AND id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
		WHERE jobs.id = :id_job
		  AND jobs.password = :password
		  AND segment_translations.status != :segment_status
		-- following is a condition on bitmask to filter by severity ERROR
		  AND warning & 1 = 1 ";

        $stmt = $this->_getStatementForQuery($query);

        return $this->_fetchObjectMap($stmt, GlobalWarningStruct::class, [
            'id_job'         => $jid,
            'password'       => $jpassword,
            'segment_status' => TranslationStatus::STATUS_NEW,
        ], self::warningsKeyMap($jid));
    }

}
