<?php

namespace Model\LQA;

use Exception;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\IDaoStruct;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use PDO;
use PDOException;
use Plugins\Features\ReviewExtended\ReviewUtils;
use ReflectionException;
use RuntimeException;
use TypeError;
use Utils\Constants\SourcePages;

class ChunkReviewDao extends AbstractDao
{

    const string TABLE = "qa_chunk_reviews";

    /** @var list<string> */
    public static array $primary_keys = [
        'id'
    ];

    const string sql_for_get_by_project_id = "SELECT * FROM qa_chunk_reviews WHERE id_project = :id_project ORDER BY id";

    const string sql_get_from_review_password_and_id_job = "SELECT * FROM qa_chunk_reviews WHERE review_password = :review_password AND id_job = :id_job";


    // The query text is part of the cache key, so the trailing space on the first line is not
    // cosmetic: dropping it renames every entry in flight, and a fleet still running the old text
    // keeps repopulating the name this one no longer produces. Do not trim it.
    const string sql_is_t_or_r1_or_r2 = "SELECT 
            (SELECT count(id) from qa_chunk_reviews cr where cr.id_job = :jid and cr.password=:password) as t,
            (SELECT count(id) from qa_chunk_reviews cr where cr.id_job = :jid and cr.review_password=:password and cr.source_page = 2) as r1,
            (SELECT count(id) from qa_chunk_reviews cr where cr.id_job = :jid and cr.review_password=:password and cr.source_page = 3) as r2
        from DUAL";

    /**
     * @throws PDOException
     */
    public function updatePassword(int $id_job, string $old_password, string $new_password): int
    {
        $sql = "UPDATE qa_chunk_reviews SET password = :new_password
               WHERE id_job = :id_job AND password = :old_password ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_job' => $id_job,
            'old_password' => $old_password,
            'new_password' => $new_password
        ]);

        return $stmt->rowCount();
    }

    /**
     * @throws PDOException
     */
    public function updateReviewPassword(int $id_job, string $old_review_password, string $new_review_password, int $source_page): int
    {
        $sql = "UPDATE qa_chunk_reviews SET review_password = :new_review_password
               WHERE id_job = :id_job AND review_password = :old_review_password AND source_page = :source_page";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_job' => $id_job,
            'old_review_password' => $old_review_password,
            'new_review_password' => $new_review_password,
            'source_page' => $source_page
        ]);

        return $stmt->rowCount();
    }

    /**
     * @param int $id_job
     *
     * @return ChunkReviewStruct[]
     * @throws PDOException
     */
    public function findByIdJob(int $id_job): array
    {
        $sql = "SELECT * FROM qa_chunk_reviews " .
            " WHERE id_job = :id_job ORDER BY id";
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, ChunkReviewStruct::class);
        $stmt->execute(['id_job' => $id_job]);

        return $stmt->fetchAll();
    }

    /**
     * @param int $id_job
     * @param string $password
     * @param int|null $source_page
     *
     * @return ChunkReviewStruct|null
     * @throws PDOException
     */
    public function findByIdJobAndPasswordAndSourcePage(int $id_job, string $password, ?int $source_page): ?ChunkReviewStruct
    {
        $sql = "SELECT * FROM qa_chunk_reviews " .
            " WHERE id_job = :id_job
                AND password = :password
                AND source_page = :source_page ORDER BY id";
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, ChunkReviewStruct::class);
        $stmt->execute([
            'id_job' => $id_job,
            'password' => $password,
            'source_page' => $source_page,
        ]);

        $results = $stmt->fetchAll();

        return $results[0] ?? null;
    }

    /**
     * @param int $id
     *
     * @return ?ChunkReviewStruct
     * @throws PDOException
     * @throws ReflectionException
     * @throws \Exception
     */
    public function findById(int $id): ?ChunkReviewStruct
    {
        /** @var ?ChunkReviewStruct $res */
        $res = $this->fetchById($id, ChunkReviewStruct::class);

        return $res;
    }

    /**
     * @param JobStruct $chunk
     *
     * @param int|null $source_page
     *
     * @return float
     * @throws PDOException
     */
    public function getPenaltyPointsForChunk(JobStruct $chunk, ?int $source_page = null): float
    {
        if (is_null($source_page)) {
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

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_job' => $chunk->id,
            'password' => $chunk->password,
            'source_page' => $source_page
        ]);

        $count = $stmt->fetch() ?: [];

        // penalty_points is double(20,2) and PDO hands the SUM back as a string; an int return
        // type would silently truncate "7.50" to 7 and the recount would then write that
        // truncated value back as an absolute, corrupting a previously correct row.
        return (float)($count[0] ?? 0);
    }

    /**
     * Finds qa_chunk_reviews rows whose recorded penalty_points has drifted away from the true
     * live sum of qa_entries.penalty_points (non-deleted, same job/source_page). Used by the
     * standing consistency check and the batch repair CLI task — both need the same identity
     * (id, id_job, password, source_page) plus the recorded vs. actual values for reporting.
     *
     * @param int|null $minJobId Only consider jobs above this id. A starting watermark, not a cap.
     * @param int|null $limit    Maximum rows to return; null is unbounded. Anything that renders the
     *                           result — a console table, an alert email — should pass one and report
     *                           countPenaltyPointsMismatches() as the total, or a first run after
     *                           deploy can emit an unbounded report.
     *
     * @return array<int, array{id:int,id_job:int,password:string,source_page:int,recorded_penalty_points:float,actual_penalty_points:float}>
     * @throws PDOException
     */
    public function findPenaltyPointsMismatches(?int $minJobId = null, ?int $limit = null): array
    {
        [$sql, $parameters] = $this->penaltyPointsMismatchesQuery($minJobId);

        $sql .= " ORDER BY r.id_job, r.source_page";

        // Interpolated, not bound: execute() with an array binds every value as a string, which turns
        // LIMIT into LIMIT '50' and a syntax error. The int cast is the injection guard. Same shape as
        // ProjectDao::getProjectsByUser().
        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($parameters);

        /** @var array<int, array{id:int,id_job:int,password:string,source_page:int,recorded_penalty_points:float,actual_penalty_points:float}> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * How many rows findPenaltyPointsMismatches() would return unbounded.
     *
     * Lets a caller render a page of the drift while still reporting the true total, so a capped
     * alert email reads "showing 50 of 812" instead of quietly looking like the whole picture. Built
     * from the same predicate as the page so the count and the page can never disagree.
     *
     * @throws PDOException
     */
    public function countPenaltyPointsMismatches(?int $minJobId = null): int
    {
        [$inner, $parameters] = $this->penaltyPointsMismatchesQuery($minJobId);

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare("SELECT COUNT(*) FROM ( " . $inner . " ) mismatches");
        $stmt->execute($parameters);

        return (int)$stmt->fetchColumn();
    }

    /**
     * The shared body of the drift query: every qa_chunk_reviews row whose recorded penalty_points
     * disagrees with the live SUM(qa_entries.penalty_points) for the same chunk and source_page.
     *
     * @param int|null $minJobId Only consider jobs above this id. A starting watermark, not a cap.
     *
     * @return array{0: string, 1: array<string, int>}
     */
    private function penaltyPointsMismatchesQuery(?int $minJobId): array
    {
        // ABS(difference) rather than ROUND(a,2) != ROUND(b,2): penalty_points is double(20,2) in
        // production, one side accumulates incrementally while the other comes from a single SUM(),
        // and two logically equal values can therefore differ in the last bits. ROUND() returns a
        // DOUBLE, so comparing rounded values is still a float comparison and a hair of residue is
        // enough to report a row as drifted — which the repair cannot settle, because it writes the
        // sum and the column rounds it back, so the row is flagged again on the next scan. 0.005 is
        // half the smallest storable unit at 2dp, i.e. "differs by at least one storable cent".
        $sql = "SELECT
                r.id,
                r.id_job,
                r.password,
                r.source_page,
                COALESCE(r.penalty_points, 0) AS recorded_penalty_points,
                COALESCE(SUM(e.penalty_points), 0) AS actual_penalty_points
            FROM qa_chunk_reviews r
            JOIN jobs j ON j.id = r.id_job AND j.password = r.password
            LEFT JOIN qa_entries e
                ON e.id_job = j.id
                AND e.id_segment >= j.job_first_segment
                AND e.id_segment <= j.job_last_segment
                AND e.source_page = r.source_page
                AND e.deleted_at IS NULL
            " . ($minJobId !== null ? "WHERE r.id_job > :min_job_id " : "") . "
            GROUP BY r.id
            HAVING ABS(actual_penalty_points - recorded_penalty_points) > 0.005";

        return [$sql, $minJobId !== null ? ['min_job_id' => $minJobId] : []];
    }

    /**
     * @throws PDOException
     */
    public function countTimeToEdit(JobStruct $chunk, int $source_page): int
    {
        $sql = "
            SELECT SUM( time_to_edit ) FROM jobs
                JOIN segment_translation_events ste
                  ON jobs.id = ste.id_job
                  AND ste.id_segment >= jobs.job_first_segment AND ste.id_segment <= jobs.job_last_segment

                WHERE jobs.id = :id_job AND jobs.password = :password
                  AND ste.source_page = :source_page

                  GROUP BY ste.source_page

        ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_job' => $chunk->id,
            'password' => $chunk->password,
            'source_page' => $source_page,
        ]);

        $result = $stmt->fetch();

        return (!$result || $result[0] == null) ? 0 : $result[0];
    }

    /**
     * @param JobStruct $chunk
     * @param int|null $source_page
     *
     * @return int
     * @throws PDOException
     */
    public function getReviewedWordsCountForSecondPass(JobStruct $chunk, ?int $source_page = null): int
    {
        $translationStatus = ReviewUtils::sourcePageToTranslationStatus($source_page);

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

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            'id_job' => $chunk->id,
            'password' => $chunk->password,
            'translation_status' => $translationStatus
        ]);

        $result = $stmt->fetch();

        return (!$result || $result[0] === null) ? 0 : (int)$result[0];
    }

    /**
     * @param JobStruct $chunkStruct
     * @param int|null $ttl
     *
     * @return ChunkReviewStruct[]
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function findChunkReviews(JobStruct $chunkStruct, ?int $ttl = 0): array
    {
        return $this->_findChunkReviews([$chunkStruct], null, $ttl);
    }

    /**
     * @param JobStruct $chunkStruct
     * @param int $source_page
     * @param int $ttl
     *
     * @return ChunkReviewStruct[]
     * @throws Exception
     * @throws ReflectionException
     */
    public function findChunkReviewsForSourcePage(JobStruct $chunkStruct, int $source_page = SourcePages::SOURCE_PAGE_REVISION, int $ttl = 60): array
    {
        // Each phase is given its own key map. An eviction deletes a whole key map at once, and the
        // bind parameters are the same for every phase because the source page is written into the
        // query text, so on the key map derived from them evicting one phase would take the others
        // and the unfiltered read down with it.
        return $this->_findChunkReviews(
            [$chunkStruct],
            self::_sourcePageCondition($source_page),
            $ttl,
            self::_sourcePageKeyMap($chunkStruct, $source_page)
        );
    }

    /**
     * The condition is interpolated into the query text, and the query text is part of the cache
     * key, so the read and the eviction of a source page have to build it here or they would key on
     * two different strings.
     */
    private static function _sourcePageCondition(int $source_page): string
    {
        return " WHERE source_page = $source_page ";
    }

    private static function _sourcePageKeyMap(JobStruct $chunkStruct, int $source_page): string
    {
        return self::class . '::findChunkReviewsForSourcePage-' . $chunkStruct->id . ':' . $chunkStruct->password . ':' . $source_page;
    }

    /**
     * @param JobStruct[] $chunksArray
     * @param string|null $default_condition
     * @param int|null $ttl
     * @param string|null $keyMap Left null to group the entry with the other reads of the same
     *                            chunks, since an eviction deletes a whole key map.
     *
     * @return ChunkReviewStruct[]
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    protected function _findChunkReviews(
        array $chunksArray,
        ?string $default_condition = ' WHERE 1 = 1 ',
        ?int $ttl = 1 /* 1 second, only to avoid multiple queries to mysql during the same script execution */,
        ?string $keyMap = null
    ): array
    {
        $findChunkReviewsStatement = $this->_findChunkReviewsStatement($chunksArray, $default_condition);

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($findChunkReviewsStatement['sql']);

        return $this->setCacheTTL($ttl)->_fetchObjectMap(
            $stmt,
            ChunkReviewStruct::class,
            $findChunkReviewsStatement['parameters'],
            $keyMap
        );
    }

    /**
     * @param JobStruct $chunkStruct
     *
     * @return bool
     * @throws PDOException
     * @throws ReflectionException
     */
    public function destroyCacheForFindChunkReviews(JobStruct $chunkStruct): bool
    {
        $findChunkReviewsStatement = $this->_findChunkReviewsStatement([$chunkStruct], null);
        $stmt = $this->_getStatementForQuery($findChunkReviewsStatement['sql']);

        return $this->_destroyObjectCache($stmt, ChunkReviewStruct::class, $findChunkReviewsStatement['parameters']);
    }

    /**
     * Evict findChunkReviewsForSourcePage() for one phase of a chunk.
     *
     * The entry is keyed on the job credential but its value is the review password of that phase,
     * which is what the editor is handed, so a review password rotation has to evict it as well.
     *
     * @param JobStruct $chunkStruct
     * @param int $source_page
     *
     * @return bool
     * @throws PDOException
     * @throws ReflectionException
     */
    public function destroyCacheForFindChunkReviewsForSourcePage(JobStruct $chunkStruct, int $source_page): bool
    {
        $findChunkReviewsStatement = $this->_findChunkReviewsStatement(
            [$chunkStruct],
            self::_sourcePageCondition($source_page)
        );
        $stmt = $this->_getStatementForQuery($findChunkReviewsStatement['sql']);

        return $this->_destroyObjectCache($stmt, ChunkReviewStruct::class, $findChunkReviewsStatement['parameters']);
    }

    /**
     * A rotation evicts the entries of the password it replaces, which no struct carries any more.
     */
    private static function _chunkFor(int $id_job, string $password): JobStruct
    {
        $chunkStruct = new JobStruct();
        $chunkStruct->id = $id_job;
        $chunkStruct->password = $password;

        return $chunkStruct;
    }

    /**
     * @param JobStruct[] $chunksArray
     * @param string|null $default_condition
     *
     * @return array{sql:string,parameters:list<int|string|null>}
     * @throws PDOException
     */
    private function _findChunkReviewsStatement(array $chunksArray, ?string $default_condition = ' WHERE 1 = 1 '): array
    {
        $_conditions = [];
        $_parameters = [];
        foreach ($chunksArray as $chunk) {
            $_conditions[] = " ( jobs.id = ? AND jobs.password = ? ) ";
            $_parameters[] = $chunk->id;
            $_parameters[] = $chunk->password;
        }

        $default_condition .= " AND " . implode(' OR ', $_conditions);

        $sql =
            "SELECT qa_chunk_reviews.* 
                FROM jobs 
                INNER JOIN qa_chunk_reviews ON jobs.id = qa_chunk_reviews.id_job AND jobs.password = qa_chunk_reviews.password 
                " . $default_condition . " 
                ORDER BY source_page";

        return [
            'sql' => $sql,
            'parameters' => $_parameters,
        ];
    }

    /**
     * Return a ShapelessConcreteStruct with 3 boolean fields (1/0):
     * - t
     * - r1
     * - r2
     *
     * @param int $jid
     * @param string $password
     * @param int $ttl
     *
     * @return IDaoStruct|null
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function isTOrR1OrR2(int $jid, string $password, int $ttl = 3600): ?IDaoStruct
    {
        $stmt = $this->_getStatementForQuery(self::sql_is_t_or_r1_or_r2);

        return $this->setCacheTTL($ttl)->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, self::isTOrR1OrR2Params($jid, $password))[0] ?? null;
    }

    /**
     * Drop what isTOrR1OrR2() cached for a password, so a rotated password stops resolving a phase
     * before its TTL expires.
     *
     * @param int $jid
     * @param string $password
     *
     * @return bool
     * @throws PDOException
     * @throws ReflectionException
     */
    public function destroyCacheForIsTOrR1OrR2(int $jid, string $password): bool
    {
        $stmt = $this->_getStatementForQuery(self::sql_is_t_or_r1_or_r2);

        return $this->_destroyObjectCache($stmt, ShapelessConcreteStruct::class, self::isTOrR1OrR2Params($jid, $password));
    }

    /**
     * @param int $jid
     * @param string $password
     *
     * @return array<string, int|string>
     */
    private static function isTOrR1OrR2Params(int $jid, string $password): array
    {
        return [
            'password' => $password,
            'jid' => $jid
        ];
    }

    /**
     * @param int $id_project
     * @param int $ttl
     *
     * @return ChunkReviewStruct[]
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function findByProjectId(int $id_project, int $ttl = 60 * 60): array
    {
        $this->setCacheTTL($ttl);
        $stmt = $this->_getStatementForQuery(self::sql_for_get_by_project_id);

        return $this->_fetchObjectMap($stmt, ChunkReviewStruct::class, ['id_project' => $id_project]);
    }

    /**
     * @throws PDOException
     * @throws ReflectionException
     */
    public function destroyCacheByProjectId(int $id_project): bool
    {
        $stmt = $this->_getStatementForQuery(self::sql_for_get_by_project_id);

        return $this->_destroyObjectCache($stmt, ChunkReviewStruct::class, ['id_project' => $id_project]);
    }

    /**
     * @param string $review_password
     * @param int $id_job
     * @param int $ttl
     * @return ?ChunkReviewStruct
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     */
    public function findByReviewPasswordAndJobId(string $review_password, int $id_job, int $ttl = 0): ?ChunkReviewStruct
    {
        $this->setCacheTTL($ttl);
        $stmt = $this->_getStatementForQuery(self::sql_get_from_review_password_and_id_job);
        return $this->_fetchObjectMap(
            $stmt,
            ChunkReviewStruct::class,
            [
                'review_password' => $review_password,
                'id_job' => $id_job
            ]
        )[0] ?? null;
    }

    /**
     * Drop what findByReviewPasswordAndJobId() cached for a review password. That query authenticates
     * a reviewer, and callers cache it for up to a day, so a rotated password must be evicted here or
     * it keeps opening the editor until the TTL expires.
     *
     * @param string $review_password
     * @param int $id_job
     *
     * @return bool
     * @throws PDOException
     * @throws ReflectionException
     */
    public function destroyCacheForReviewPasswordAndJobId(string $review_password, int $id_job): bool
    {
        $stmt = $this->_getStatementForQuery(self::sql_get_from_review_password_and_id_job);

        return $this->_destroyObjectCache($stmt, ChunkReviewStruct::class, [
            'review_password' => $review_password,
            'id_job' => $id_job
        ]);
    }

    /**
     * Evict every cached read this DAO keys on a job credential, whether that credential is a
     * translate or a review password.
     *
     * A rotation must be called for the password it replaces, which would otherwise keep opening the
     * editor for the whole TTL, and for the password replacing it, whose entries may hold a miss
     * cached by a lookup made before the rotation.
     *
     * @param int $id_job
     * @param string $password
     *
     * @throws PDOException
     * @throws ReflectionException
     */
    public function destroyCacheForJobPassword(int $id_job, string $password): void
    {
        $chunkStruct = self::_chunkFor($id_job, $password);

        $this->destroyCacheForFindChunkReviews($chunkStruct);
        $this->destroyCacheForIsTOrR1OrR2($id_job, $password);
        $this->destroyCacheForReviewPasswordAndJobId($password, $id_job);

        // There is no review phase to read on the translate page, so only the two revision phases
        // have a per phase entry to evict.
        foreach ([SourcePages::SOURCE_PAGE_REVISION, SourcePages::SOURCE_PAGE_REVISION_2] as $sourcePage) {
            $this->destroyCacheForFindChunkReviewsForSourcePage($chunkStruct, $sourcePage);
        }
    }

    /**
     * @param int $id_job
     * @param string $password
     * @param int $source_page
     *
     * @return ?ChunkReviewStruct
     * @throws PDOException
     */
    public function findLastReviewByJobIdPasswordAndSourcePage(int $id_job, string $password, int $source_page): ?ChunkReviewStruct
    {
        $sql = "SELECT * FROM qa_chunk_reviews " .
            " WHERE password = :password " .
            " AND id_job = :id_job " .
            " AND source_page = :source_page ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_CLASS, ChunkReviewStruct::class);
        $stmt->execute(
            [
                'password' => $password,
                'id_job' => $id_job,
                'source_page' => $source_page
            ]
        );

        return $stmt->fetch() ?: null;
    }

    /**
     * Invalidates every Redis-cached read that can serve a stale penalty_points/is_pass/
     * counters value for this chunk review.
     *
     * Call it inline, right after the write. Busting while the writing transaction is still open
     * would let a concurrent reader miss the cache, read the pre-commit row and repopulate from it,
     * leaving a stale value that outlives the commit for the full TTL - so DaoCacheTrait holds each
     * eviction back until the transaction commits. Callers do not schedule that themselves; wrapping
     * this in IDatabase::onCommit() by hand only adds a layer that defers what is already deferred.
     *
     * @throws PDOException
     * @throws ReflectionException
     */
    public function destroyCachesFor(ChunkReviewStruct $chunkReview): void
    {
        // The credential-keyed door covers the per source page reads, which are the ones that can
        // still hand back a struct with the pre-write counters; the project keyed read is separate
        // because a credential says nothing about the project it belongs to.
        $this->destroyCacheForJobPassword($chunkReview->id_job, $chunkReview->password);
        $this->destroyCacheByProjectId($chunkReview->id_project);
    }


    /**
     * @param int $id_job
     * @param string $password
     * @param int|null $source_page
     *
     * @return bool
     * @throws PDOException
     */
    public function exists(int $id_job, string $password, ?int $source_page = null): bool
    {
        $params = [
            'id_job' => $id_job,
            'password' => $password,
        ];

        $query = " SELECT id FROM " . self::TABLE . " WHERE id_job = :id_job and password = :password ";

        if ($source_page) {
            $params['source_page'] = $source_page;
            $query .= " AND source_page=:source_page";
        }

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($query);


        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $data array of data to use
     *
     * @return ChunkReviewStruct
     * @throws PDOException
     * @throws ReflectionException
     * @throws RuntimeException if the row cannot be read back after the write
     * @throws TypeError
     */
    public function createRecord(array $data): ChunkReviewStruct
    {
        $struct = new ChunkReviewStruct($data);

        $struct->setDefaults();

        $attrs = $struct->toArray([
            'id_project',
            'id_job',
            'password',
            'review_password',
            'source_page',
            'total_tte',
            'avg_pee'
        ]);

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

        $conn = $this->database->getConnection();

        $stmt = $conn->prepare($sql);
        $stmt->execute($attrs);

        // Not lastInsertId(): when ON DUPLICATE KEY UPDATE takes the *update* branch MySQL leaves
        // LAST_INSERT_ID() at 0 (or at a value left by an earlier statement on this connection), so
        // the caller got id 0 or someone else's id for an existing chunk review. That id then flows
        // into recountAndUpdatePassFailResult() — whose updateStruct keys on the primary key and so
        // silently updates nothing — and into passFailCountsAtomicUpdate(), where an unmatched id
        // takes the insert branch and creates a duplicate row. Both branches leave exactly one row
        // identified by job_pw_source_page, so read it back; the lookup is uncached, so it sees the
        // row this statement just wrote inside the caller's transaction.
        $struct = $this->findByIdJobAndPasswordAndSourcePage(
            $struct->id_job,
            $struct->password,
            $struct->source_page
        ) ?? throw new RuntimeException('qa_chunk_reviews row not found after createRecord for job ' . $struct->id_job);

        // A new row changes what findChunkReviews()/getByProjectId() should return.
        $this->destroyCachesFor($struct);

        return $struct;
    }

    /**
     * @throws PDOException
     * @throws ReflectionException
     */
    public function deleteByJobId(int $id_job): bool
    {
        // Read the rows before deleting them: their password/source_page are what identify the cache
        // keys, and after the DELETE there is nothing left to derive them from. This is the worst
        // staleness case in the system otherwise — split/merge deletes a job's chunk reviews while
        // the 10-minute ProjectUrls cache keeps serving revise URLs built from review_passwords that
        // no longer exist. The read is free of new locking: split/merge already holds these rows.
        $rows = $this->findByIdJob($id_job);

        $sql = "DELETE FROM qa_chunk_reviews WHERE id_job = :id_job ";
        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);

        $deleted = $stmt->execute(['id_job' => $id_job]);

        foreach ($rows as $row) {
            $this->destroyCachesFor($row);
        }

        return $deleted;
    }

    /**
     * Serializes every writer of a job's qa_chunk_reviews rows by taking InnoDB row locks on them,
     * held until the surrounding transaction commits or rolls back.
     *
     * This replaces a Redis advisory lock, which released in a `finally` block while the caller's
     * transaction was still open — so a second process could enter the critical section, read state
     * that did not include the uncommitted change, and write an absolute value over it. Locking the
     * rows themselves ties the release to the commit, covers every writer automatically, and has no
     * fail-open path.
     *
     * Deliberately locks by id_job rather than by row id: split/merge deletes all of a job's rows and
     * recreates them, so there is no stable row to lock.
     *
     * REQUIRES REPEATABLE READ. That is InnoDB's default and what this installation runs, but nothing
     * in lib/, inc/ or INSTALL/ sets or asserts it, so the requirement is stated here. The gap lock on
     * the id_job index range is what covers the recreate window: while the job's rows are deleted the
     * SELECT matches nothing, so there are no record locks to take, and the gap lock is the only thing
     * standing between two concurrent recreates. READ COMMITTED disables gap locking, so the same
     * SELECT would lock nothing and still return success — this method would degrade to a silent
     * no-op precisely in the window it exists to protect. Do not lower the isolation level to relieve
     * contention here without first replacing this with a lock on a row that always exists, e.g.
     * SELECT id FROM jobs WHERE id = :id_job FOR UPDATE, which takes a real record lock at any
     * isolation level. (That swap is not free either: it would put `jobs` at the head of this lock
     * chain while BatchReviewProcessor::updateJobWordCounter() writes `jobs` at its tail, turning a
     * single-table lock graph into a cross-table one.)
     *
     * Lock order, repo-wide: qa_chunk_reviews before qa_entries, always. TranslationIssueModel and
     * BatchReviewProcessor both take this lock before touching qa_entries; acquiring in the opposite
     * order deadlocks against them.
     *
     * @throws PDOException
     * @throws RuntimeException if called outside a transaction, where FOR UPDATE would acquire the
     *                          locks and drop them again immediately, silently protecting nothing.
     */
    public function lockByJobId(int $id_job): void
    {
        $conn = $this->database->getConnection();

        if (!$conn->inTransaction()) {
            throw new RuntimeException(
                'ChunkReviewDao::lockByJobId requires an open transaction: outside one, autocommit '
                . 'releases the FOR UPDATE locks as soon as the statement returns.'
            );
        }

        // The KEY id_job range scan visits the job's rows in primary-key order, and every caller uses
        // the same predicate, so they all lock the same set in the same order — that is what rules out
        // grabbing it from opposite ends. The ORDER BY only makes that order explicit in the
        // statement; it does not control acquisition, which happens during the scan as rows are read,
        // before any sort could be applied.
        $stmt = $conn->prepare("SELECT id FROM qa_chunk_reviews WHERE id_job = :id_job ORDER BY id FOR UPDATE");
        $stmt->execute(['id_job' => $id_job]);
        $stmt->fetchAll();
    }

    /**
     *
     * @param int $chunkReviewID
     * @param array{chunkReview: ChunkReviewStruct, penalty_points?: float, reviewed_words_count: int, total_tte: int} $data
     *
     * @throws Exception
     */
    public function passFailCountsAtomicUpdate(int $chunkReviewID, array $data): void
    {
        $chunkReview = $data['chunkReview'];
        $project = $chunkReview->getChunk(new JobDao($this->database))->getProject(new ProjectDao($this->database));
        $lqaModel = $project->id_qa_model !== null ? (new ModelDao($this->database))->findById($project->id_qa_model) : null;

        // The deltas are bound a second time, under their own :*_delta names, rather than read back
        // with VALUES(). VALUES(col) yields "the value that would have been inserted" — which is the
        // *clamped* expression in the VALUES list below — so a decrement would come back as
        // GREATEST(-3, 0) = 0 and silently never apply. The insert branch needs the clamp (a
        // subtract must not create a negative row); the update branch needs the raw signed delta.
        //
        // in MySQL a sum of a null value to an integer returns 0
        $setClauses = [
            "penalty_points = GREATEST( COALESCE( penalty_points, 0 ) + COALESCE( :penalty_points_delta, 0 ), 0 )",
            "reviewed_words_count = GREATEST( reviewed_words_count + :reviewed_words_count_delta, 0 )",
            "total_tte = GREATEST( total_tte + :total_tte_delta, 0 )",
        ];

        // is_pass needs a project LQA model to resolve force_pass_at; without one, the counters
        // above still get updated but is_pass is left untouched (NULL by schema default).
        if ($lqaModel !== null) {
            $forcePassAt = ReviewUtils::filterLQAModelLimit($lqaModel, $chunkReview->source_page);
            // in MySQL, division by zero returns NULL, so we have to coalesce null values from is_pass division
            $setClauses[] = "is_pass = IF( COALESCE( penalty_points / reviewed_words_count * 1000, 0 ) <= {$forcePassAt}, 1, 0 )";
        }

        // source_page is written on the insert branch but deliberately absent from $setClauses: on the
        // update branch the row already carries the right value, and rewriting it there could
        // re-identify an existing row into a different review stage. Omitting it from the INSERT left
        // genuine inserts with source_page = NULL, which (a) exempts them from
        // UNIQUE KEY job_pw_source_page, since MySQL treats every NULL as distinct, and (b) makes the
        // drift detector's `e.source_page = r.source_page` join match nothing, so the row reports as
        // drifted on every scan and no recount can ever clear it. No GREATEST(): it is an identity,
        // not a counter.
        $sql = "INSERT INTO
            qa_chunk_reviews ( id, id_job, id_project, password, review_password, source_page, penalty_points, reviewed_words_count, total_tte )
        VALUES(
            :id,
            :id_job,
            :id_project,
            :password,
            :review_password,
            :source_page,
            GREATEST( :penalty_points, 0 ),
            GREATEST( :reviewed_words_count, 0 ),
            GREATEST( :total_tte, 0 )
        ) ON DUPLICATE KEY UPDATE
        " . implode(",\n        ", $setClauses) . ";";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($sql);
        $penaltyPoints = empty($data['penalty_points']) ? 0 : $data['penalty_points'];

        $stmt->execute([
            'id' => $chunkReviewID,
            'id_job' => $chunkReview->id_job,
            'id_project' => $chunkReview->id_project,
            'review_password' => $chunkReview->review_password,
            'source_page' => $chunkReview->source_page,
            'password' => $chunkReview->password,
            'penalty_points' => $penaltyPoints,
            'reviewed_words_count' => $data['reviewed_words_count'],
            'total_tte' => $data['total_tte'],
            // same values again, unclamped, for the ON DUPLICATE KEY UPDATE deltas
            'penalty_points_delta' => $penaltyPoints,
            'reviewed_words_count_delta' => $data['reviewed_words_count'],
            'total_tte_delta' => $data['total_tte'],
        ]);
    }

}
