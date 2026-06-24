<?php

namespace Plugins\Features\SegmentFilter\Model;

use DivisionByZeroError;
use Exception;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\AbstractDao;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use PDOException;
use ReflectionException;
use Utils\Constants\TranslationStatus;

class SegmentFilterDao extends AbstractDao
{

    /**
     * @return ShapelessConcreteStruct[]
     * @throws ReflectionException
     * @throws PDOException
     * @throws Exception
     */
    public function findSegmentIdsBySimpleFilter(JobStruct $chunk, FilterDefinition $filter): array
    {
        $sql = "SELECT st.id_segment AS id
            FROM
            segment_translations st
            JOIN jobs ON jobs.id = st.id_job
               AND jobs.id = :id_job
               AND jobs.password = :password
               AND st.id_segment
               BETWEEN :job_first_segment AND :job_last_segment
               AND st.status = :status
            JOIN segments s ON s.id = st.id_segment AND s.show_in_cattool = 1";

        $data = [
            'id_job' => $chunk->id,
            'job_first_segment' => $chunk->job_first_segment,
            'job_last_segment' => $chunk->job_last_segment,
            'password' => $chunk->password,
            'status' => $filter->getSegmentStatus()
        ];

        $stmt = $this->_getStatementForQuery($sql);

        return $this->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, $data);
    }

    /**
     * @return ShapelessConcreteStruct[]
     * @throws Exception
     * @throws PDOException
     * @throws ReflectionException
     * @throws DivisionByZeroError
     */
    public function findSegmentIdsForSample(JobStruct $chunk, FilterDefinition $filter): array
    {
        $where = $this->getWhereFromFilter($filter);

        if ($filter->sampleSize() > 0) {
            $limit = $this->getLimit($chunk, $filter, $where);
        } else {
            $limit = ['limit' => 0, 'count' => 0, 'sample_size' => 0];
        }

        $data = $this->getData($chunk, $filter);

        $sql = match ($filter->sampleType()) {
            'segment_length_high_to_low' => $this->getSqlForSegmentLength($limit, $where, 'high_to_low'),
            'segment_length_low_to_high' => $this->getSqlForSegmentLength($limit, $where, 'low_to_high'),
            'edit_distance_high_to_low' => $this->getSqlForEditDistance($limit, $where, 'high_to_low'),
            'edit_distance_low_to_high' => $this->getSqlForEditDistance($limit, $where, 'low_to_high'),
            'regular_intervals' => $this->getSqlForRegularIntervals($limit, $where),
            'unlocked' => $this->getSqlForUnlocked($where),
            'ice' => $this->getSqlForIce($where),
            'modified_ice' => $this->getSqlForModifiedIce($where),
            'repetitions' => $this->getSqlForRepetition($where),
            'matches' => $this->getSqlForMatches($where),
            'mt', 'fuzzies_50_74', 'fuzzies_75_84', 'fuzzies_85_94', 'fuzzies_95_99' => $this->getSqlForMatchType($where),
            'todo' => $this->getSqlForToDo($where, $chunk->getIsReview(), $chunk->isSecondPassReview()),
            default => throw new Exception('Sample type is not valid: ' . $filter->sampleType()),
        };

        $stmt = $this->_getStatementForQuery($sql);

        return $this->_fetchObjectMap($stmt, ShapelessConcreteStruct::class, $data);
    }

    /**
     * @param array{limit: int, count: int, sample_size: int|float} $limit
     * @param array{sql: string, data: array<string, string>} $where
     *
     * @throws DivisionByZeroError
     */
    public function getSqlForRegularIntervals(array $limit, array $where): string
    {
        $ratio = (int)round($limit['count'] / $limit['limit']);

        return "SELECT id FROM (
            SELECT st.id_segment AS id,
            @curRow := @curRow + 1 AS rowNumber

          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.password = :password
           AND jobs.id = :id_job
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           JOIN segments s ON s.id = st.id_segment AND s.show_in_cattool = 1
           JOIN (SELECT @curRow := -1) r --  using -1 here makes the sample start from the first segment
           WHERE 1
           {$where['sql']}
           ORDER BY st.id_segment ASC
           ) sub WHERE `rowNumber` % $ratio = 0 ";
    }

    /**
     * @param array{limit: int, count: int, sample_size: int|float} $limit
     * @param array{sql: string, data: array<string, string>} $where
     */
    public function getSqlForEditDistance(array $limit, array $where, string $sort): string
    {
        $sqlSort = '';

        if ($sort === 'high_to_low') {
            $sqlSort = 'DESC';
        } elseif ($sort === 'low_to_high') {
            $sqlSort = 'ASC';
        }

        return "SELECT st.id_segment AS id
              FROM
               segment_translations st JOIN jobs
               ON jobs.id = st.id_job
               AND jobs.password = :password
               AND jobs.id = :id_job
               AND st.id_segment
               BETWEEN :job_first_segment AND :job_last_segment
               JOIN segments s ON s.id = st.id_segment AND s.show_in_cattool = 1
            WHERE 1
               {$where['sql']}
               ORDER BY st.edit_distance $sqlSort
               LIMIT {$limit['limit']} ;";
    }

    /**
     * @param array{limit: int, count: int, sample_size: int|float} $limit
     * @param array{sql: string, data: array<string, string>} $where
     */
    public function getSqlForSegmentLength(array $limit, array $where, string $sort): string
    {
        $sqlSort = '';

        if ($sort === 'high_to_low') {
            $sqlSort = 'DESC';
        } elseif ($sort === 'low_to_high') {
            $sqlSort = 'ASC';
        }

        return "SELECT st.id_segment AS id
          FROM
           segment_translations st
           JOIN jobs ON jobs.id = st.id_job
               AND jobs.password = :password
               AND jobs.id = :id_job
               AND st.id_segment
               BETWEEN :job_first_segment AND :job_last_segment
           JOIN segments s ON s.id = st.id_segment AND s.show_in_cattool = 1
           WHERE 1
           {$where['sql']}
           ORDER BY CHAR_LENGTH(s.segment) $sqlSort
           LIMIT {$limit['limit']}";
    }

    /**
     * @param array{sql: string, data: array<string, string>} $where
     */
    public function getSqlForUnlocked(array $where): string
    {
        return "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND st.locked = 0
           JOIN segments s ON s.id = st.id_segment AND s.show_in_cattool = 1
           WHERE 1
           {$where['sql']}
           ORDER BY st.id_segment
        ";
    }

    /**
     * @param array{sql: string, data: array<string, string>} $where
     */
    public function getSqlForMatchType(array $where): string
    {
        return "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND st.match_type = :match_type
           JOIN segments s ON s.id = st.id_segment AND s.show_in_cattool = 1
           WHERE 1
           {$where['sql']}
           ORDER BY st.id_segment
        ";
    }

    /**
     * @param array{sql: string, data: array<string, string>} $where
     */
    public function getSqlForIce(array $where): string
    {
        return "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment

           AND st.match_type = 'ICE'
           AND locked = 1
           AND version_number = 0
           JOIN segments s ON s.id = st.id_segment AND s.show_in_cattool = 1
           WHERE 1
           {$where['sql']}
           ORDER BY st.id_segment
        ";
    }

    /**
     * @param array{sql: string, data: array<string, string>} $where
     */
    public function getSqlForModifiedIce(array $where): string
    {
        return "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment

           AND st.match_type = 'ICE'
           AND locked = 1
           AND version_number > 0
           JOIN segments s ON s.id = st.id_segment AND s.show_in_cattool = 1
           WHERE 1
           {$where['sql']}
           ORDER BY st.id_segment
        ";
    }

    /**
     * @param array{sql: string, data: array<string, string>} $where
     */
    public function getSqlForRepetition(array $where): string
    {
        return "
            SELECT id_segment AS id, segment_hash FROM segment_translations JOIN(
                SELECT
                    GROUP_CONCAT( st.id_segment ) AS id,
                    st.segment_hash as hash
                FROM segment_translations st
                JOIN jobs
                        ON jobs.id = st.id_job
                        AND jobs.id = :id_job
                        AND jobs.password = :password
                        AND st.id_segment BETWEEN :job_first_segment AND :job_last_segment
                JOIN segments s ON s.id = st.id_segment AND s.show_in_cattool = 1
                WHERE 1

                        {$where['sql']}

                GROUP BY segment_hash, CONCAT( id_job, '-', password )
                HAVING COUNT( segment_hash ) > 1
            ) AS REPETITIONS ON REPETITIONS.hash = segment_translations.segment_hash AND FIND_IN_SET( id_segment, REPETITIONS.id )
            GROUP BY id_segment
        ";
    }

    /**
     * @param array{sql: string, data: array<string, string>} $where
     */
    public function getSqlForMatches(array $where): string
    {
        return "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND (st.match_type = :match_type_100_public
           OR st.match_type = :match_type_100)
           JOIN segments s ON s.id = st.id_segment AND s.show_in_cattool = 1
           WHERE 1
           {$where['sql']}
           ORDER BY st.id_segment
        ";
    }

    /**
     * @param array{sql: string, data: array<string, string>} $where
     */
    public function getSqlForToDo(array $where, bool $isReview = false, bool $isSecondPassReview = false): string
    {
        $sql_condition = "";

        if ($isReview) {
            $sql_condition = " OR st.status = :status_translated ";
        }

        if ($isSecondPassReview) {
            $sql_condition = " OR st.status = :status_translated OR st.status = :status_approved ";
        }

        return "
          SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.id = :id_job
           AND jobs.password = :password
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           AND (st.status = :status_new
           OR st.status = :status_draft " . $sql_condition . ")
           JOIN segments s ON s.id = st.id_segment AND s.show_in_cattool = 1
           WHERE 1
           {$where['sql']}
           ORDER BY st.id_segment
        ";
    }

    /**
     * @return array{sql: string, data: array<string, string>}
     */
    private function getWhereFromFilter(FilterDefinition $filter): array
    {
        $where = '';
        $where_data = [];

        if ($filter->isFiltered()) {
            $where = " AND st.status = :status ";
            $where_data = ['status' => $filter->getSegmentStatus()];
        }

        return ['sql' => $where, 'data' => $where_data];
    }

    /**
     * @return array<string, mixed>
     */
    private function getData(JobStruct $chunk, FilterDefinition $filter): array
    {
        $data = [
            'id_job' => $chunk->id,
            'job_first_segment' => $chunk->job_first_segment,
            'job_last_segment' => $chunk->job_last_segment,
            'password' => $chunk->password
        ];

        if ($filter->getSegmentStatus()) {
            $data = array_merge($data, [
                'status' => $filter->getSegmentStatus()
            ]);
        }

        if ($filter->sampleData()) {
            switch ($filter->sampleType()) {
                case 'mt':
                    $data = array_merge($data, [
                        'match_type' => InternalMatchesConstants::MT,
                    ]);
                    break;

                case 'matches':
                    $data = array_merge($data, [
                        'match_type_100_public' => InternalMatchesConstants::TM_100_PUBLIC,
                        'match_type_100' => InternalMatchesConstants::TM_100,
                    ]);
                    break;

                case 'fuzzies_50_74':
                    $data = array_merge($data, [
                        'match_type' => InternalMatchesConstants::TM_50_74,
                    ]);
                    break;

                case 'fuzzies_75_84':
                    $data = array_merge($data, [
                        'match_type' => InternalMatchesConstants::TM_75_84,
                    ]);
                    break;

                case 'fuzzies_85_94':
                    $data = array_merge($data, [
                        'match_type' => InternalMatchesConstants::TM_85_94,
                    ]);
                    break;

                case 'fuzzies_95_99':
                    $data = array_merge($data, [
                        'match_type' => InternalMatchesConstants::TM_95_99,
                    ]);
                    break;

                case 'todo':
                    $data = array_merge($data, [
                        'status_new' => TranslationStatus::STATUS_NEW,
                        'status_draft' => TranslationStatus::STATUS_DRAFT
                    ]);

                    if ($chunk->getIsReview()) {
                        $data = array_merge($data, [
                            'status_translated' => TranslationStatus::STATUS_TRANSLATED,
                        ]);
                    }

                    if ($chunk->isSecondPassReview()) {
                        $data = array_merge($data, [
                            'status_translated' => TranslationStatus::STATUS_TRANSLATED,
                            'status_approved' => TranslationStatus::STATUS_APPROVED,
                        ]);
                    }

                    break;
            }
        }

        return $data;
    }

    /**
     * @param array{sql: string, data: array<string, string>} $where
     *
     * @return array{limit: int, count: int, sample_size: int|float}
     * @throws PDOException
     */
    private function getLimit(JobStruct $chunk, FilterDefinition $filter, array $where): array
    {
        $countSql = "SELECT st.id_segment AS id
          FROM
           segment_translations st JOIN jobs
           ON jobs.id = st.id_job
           AND jobs.password = :password
           AND jobs.id = :id_job
           AND st.id_segment
           BETWEEN :job_first_segment AND :job_last_segment
           JOIN segments s ON s.id = st.id_segment AND s.show_in_cattool = 1
           WHERE 1
           {$where['sql']} ";

        $conn = $this->database->getConnection();
        $stmt = $conn->prepare($countSql);

        $data = $this->getData($chunk, $filter);

        if (!empty($where['data'])) {
            $data = array_merge($data, $where['data']);
        }

        $stmt->execute($data);
        $count = $stmt->rowCount();

        $limit = (int)round(($count / 100) * $filter->sampleSize());

        return [
            'limit' => $limit,
            'count' => $count,
            'sample_size' => $filter->sampleSize()
        ];
    }

}
