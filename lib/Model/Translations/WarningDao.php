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
     * Segments counted by `warnings_count`: flagged, and in a status a PM looks at.
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
           AND st.warning = 1
           AND st.status IN ( :translated, :approved )
         GROUP BY jobs.id, jobs.password
    ";

    /**
     * Every chunk and every password of one job id lives in the same map, so a write that only
     * knows the job id can evict all of them at once.
     */
    public static function warningsCountKeyMap(int $id_job): string
    {
        return self::class . "::getWarningsCountByJob-" . $id_job;
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
            'translated' => TranslationStatus::STATUS_TRANSLATED,
            'approved'   => TranslationStatus::STATUS_APPROVED,
        ], self::warningsCountKeyMap($id_job))[0] ?? null;
    }

    /**
     * Called by every write that can change `segment_translations.warning` or `.status`, and by
     * split/merge, which move segments between chunks. Inside an open transaction the eviction is
     * queued for the commit.
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function destroyWarningsCountCache(int $id_job): bool
    {
        return $this->_deleteCacheByKey(self::warningsCountKeyMap($id_job), false);
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

        $stmt = $this->database->getConnection()->prepare($query);

        return $this->_fetchObjectMap($stmt, GlobalWarningStruct::class, [
            'id_job'         => $jid,
            'password'       => $jpassword,
            'segment_status' => TranslationStatus::STATUS_NEW,
        ]);
    }

}
