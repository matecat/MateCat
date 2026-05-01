<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/04/18
 * Time: 13.57
 *
 */

namespace Model\Search;

use Exception;
use Matecat\Finder\WholeTextFinder;
use Matecat\SubFiltering\MateCatFilter;
use Model\DataAccess\Database;
use Model\DataAccess\IDatabase;
use PDO;
use PDOException;
use stdClass;
use Utils\Logger\LoggerFactory;

class SearchModel
{

    /**
     * @var SearchQueryParamsStruct
     */
    protected SearchQueryParamsStruct $queryParams;

    /**
     * @var Database
     */
    protected IDatabase $db;

    /**
     * @var MateCatFilter
     */
    private MateCatFilter $filters;

    /**
     * SearchModel constructor.
     *
     * @param SearchQueryParamsStruct $queryParams
     * @param MateCatFilter $filters
     */
    public function __construct(SearchQueryParamsStruct $queryParams, MateCatFilter $filters)
    {
        $this->queryParams = $queryParams;
        $this->db = Database::obtain();
        $this->filters = $filters;
        $this->_loadParams();
    }

    /**
     * @param bool $inCurrentChunkOnly
     *
     * @return array
     * @throws Exception
     */
    public function search(bool $inCurrentChunkOnly): array
    {
        switch ($this->queryParams->key) {
            case 'source':
                [$sql, $params] = $this->_loadSearchInSourceQuery($inCurrentChunkOnly);
                $results = $this->_getQuery($sql, $params);
                break;
            case 'target':
                [$sql, $params] = $this->_loadSearchInTargetQuery($inCurrentChunkOnly);
                $results = $this->_getQuery($sql, $params);
                break;
            case 'coupled':
                [$sqlSrc, $paramsSrc] = $this->_loadSearchInSourceQuery($inCurrentChunkOnly);
                [$sqlTrg, $paramsTrg] = $this->_loadSearchInTargetQuery($inCurrentChunkOnly);
                $rawResults = array_merge_recursive($this->_getQuery($sqlSrc, $paramsSrc), $this->_getQuery($sqlTrg, $paramsTrg));
                $results = [];

                // in this case, $results is the merge of two queries results,
                // every segment id will possibly have 2 occurrences (source and target)
                foreach ($rawResults as $rawResult) {
                    $results[$rawResult['id']][] = $rawResult['text'];
                }

                break;
            case 'status_only':
                [$sql, $params] = $this->_loadSearchStatusOnlyQuery();
                $results = $this->_getQuery($sql, $params);
                break;
            default:
                $results = [];
                break;
        }

        $vector = [
            'sid_list' => [],
            'count' => '0'
        ];

        if ($this->queryParams->key === 'source' || $this->queryParams->key === 'target') {
            $searchTerm = (false === empty($this->queryParams->source)) ? $this->queryParams->source : $this->queryParams->target;

            foreach ($results as $occurrence) {
                if($occurrence['text'] !== null){
                    $matches = $this->find($occurrence['text'], $searchTerm);
                    $matchesCount = count($matches);

                    if ($this->hasMatches($matches)) {
                        $vector[ 'sid_list' ][] = strval($occurrence[ 'id' ]);
                        $vector['count'] = $vector['count'] + $matchesCount;
                    }
                }
            }

            if ($vector['count'] == 0) {
                $vector['sid_list'] = [];
                $vector['count'] = 0;
            }
        } elseif ($this->queryParams->key === 'coupled') {
            foreach ($results as $id => $occurrence) {
                // check if exists match target
                if (isset($occurrence[1]) && $occurrence[1] !== null && $occurrence[0] !== null) {
                    // match source
                    $searchTermSource = $this->queryParams->source;
                    $matchesSource = $this->find($occurrence[0], $searchTermSource);
                    $matchesSourceCount = count($matchesSource);

                    $searchTermTarget = $this->queryParams->target;
                    $matchesTarget = $this->find($occurrence[1], $searchTermTarget);
                    $matchesTargetCount = count($matchesTarget);

                    if ($this->hasMatches($matchesSource) and $this->hasMatches($matchesTarget)) {
                        $vector['sid_list'][] = strval($id);
                        $vector['count'] = $vector['count'] + $matchesTargetCount + $matchesSourceCount;
                    }
                }
            }

            if ($vector['count'] == 0) {
                $vector['sid_list'] = [];
                $vector['count'] = 0;
            }
        } else {
            foreach ($results as $occurrence) {
                $vector[ 'sid_list' ][] = strval($occurrence[ 'id' ]);
            }
        }

        return $vector;
    }

    /**
     * @param array $matches
     *
     * @return bool
     */
    private function hasMatches(array $matches): bool
    {
        return count($matches) > 0 and $matches[0][0] !== '';
    }

    /**
     * @param string $haystack
     * @param string $needle
     *
     * @return array
     * @throws Exception
     */
    private function find(string $haystack, string $needle): array
    {
        $this->filters->fromLayer0ToLayer2($haystack);

        return WholeTextFinder::find(
            $haystack,
            $needle,
            true,
            $this->queryParams->isExactMatchRequested,
            $this->queryParams->isMatchCaseRequested,
            true
        );
    }

    /**
     * @param string $sql
     * @param array $params
     *
     * @return array
     * @throws Exception
     */
    protected function _getQuery(string $sql, array $params = []): array
    {
        try {
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            LoggerFactory::doJsonLog($e->getMessage());
            throw new Exception($e->getMessage(), $e->getCode() * -1, $e);
        }

        return $results;
    }

    /**
     * Pay attention to possible SQL injection
     */
    protected function _loadParams(): void
    {
        // bring the src and target from layer 2 (UI) to layer 0 (DB)
//        $this->queryParams->source = $this->filters->fromLayer2ToLayer0( $this->queryParams->src );
//        $this->queryParams->target = $this->filters->fromLayer2ToLayer0( $this->queryParams->trg );
        $this->queryParams->source = $this->queryParams->src;
        $this->queryParams->target = $this->queryParams->trg;

        $this->queryParams->where_status = "";
        if ($this->queryParams->status != 'all') {
            $this->queryParams->where_status = "AND st.status = :status";
        }

        $this->queryParams->matchCase = new stdClass();
        if ($this->queryParams->isMatchCaseRequested) {
            $this->queryParams->matchCase->SQL_REGEXP_CASE = "BINARY";
            $this->queryParams->matchCase->SQL_LENGHT_CASE = "";
            $this->queryParams->matchCase->REGEXP_MODIFIER = 'u';
        } else {
            $this->queryParams->matchCase->SQL_REGEXP_CASE = "";
            $this->queryParams->matchCase->SQL_LENGHT_CASE = "LOWER";
            $this->queryParams->matchCase->REGEXP_MODIFIER = 'iu';
        }

        $this->queryParams->exactMatch = new stdClass();
        if ($this->queryParams->isExactMatchRequested) {
            $this->queryParams->exactMatch->Space_Left = "[[:space:]]{0,}";
            $this->queryParams->exactMatch->Space_Right = "([[:space:]]|$)";
        } else {
            $this->queryParams->exactMatch->Space_Left = $this->queryParams->exactMatch->Space_Right = ""; // we want to search for all occurrences in a string: the word mod will take two matches: "mod" and "mod modifier"
        }

    }

    /**
     * @param bool $inCurrentChunkOnly
     *
     * @return array
     */
    protected function _loadSearchInTargetQuery(bool $inCurrentChunkOnly = false): array
    {
        $this->_loadParams();
        $params = ['job' => $this->queryParams->job];
        $password_where = '';
        if ($inCurrentChunkOnly) {
            $password_where = ' AND st.id_segment BETWEEN j.job_first_segment AND j.job_last_segment AND j.password = :password';
            $params['password'] = $this->queryParams->password;
        }

        if ($this->queryParams->status != 'all') {
            $params['status'] = $this->queryParams->status;
        }

        $sql = "
        SELECT  st.id_segment as id, st.translation as text, od.map as original_map
			FROM segment_translations st
			INNER JOIN jobs j ON j.id = st.id_job
			LEFT JOIN segment_original_data od on od.id_segment = st.id_segment
			WHERE st.id_job = :job 
			{$password_where}
			AND st.status != 'NEW'
			{$this->queryParams->where_status}
			GROUP BY st.id_segment";

        return [$sql, $params];
    }

    /**
     * @param bool $inCurrentChunkOnly
     *
     * @return array
     */
    protected function _loadSearchInSourceQuery(?bool $inCurrentChunkOnly = false): array
    {
        $this->_loadParams();
        $params = ['job' => $this->queryParams->job];
        $password_where = '';
        if ($inCurrentChunkOnly) {
            $password_where = ' AND s.id BETWEEN j.job_first_segment AND j.job_last_segment AND j.password = :password';
            $params['password'] = $this->queryParams->password;
        }

        if ($this->queryParams->status != 'all') {
            $params['status'] = $this->queryParams->status;
        }

        $sql = "
        SELECT s.id, s.segment as text, od.map as original_map
			FROM segments s
			INNER JOIN files_job fj on s.id_file=fj.id_file
			INNER JOIN jobs j ON j.id = fj.id_job
			LEFT JOIN segment_translations st on st.id_segment = s.id AND st.id_job = fj.id_job
			LEFT JOIN segment_original_data od on od.id_segment = s.id
			WHERE fj.id_job = :job
			{$password_where}
			AND show_in_cattool = 1
			{$this->queryParams->where_status}
			GROUP BY s.id";

        return [$sql, $params];
    }

    protected function _loadSearchStatusOnlyQuery(): array
    {
        $this->_loadParams();
        $params = ['job' => $this->queryParams->job];

        if ($this->queryParams->status != 'all') {
            $params['status'] = $this->queryParams->status;
        }

        $sql = "
        SELECT st.id_segment as id
			FROM segment_translations as st
			WHERE st.id_job = :job
		    {$this->queryParams->where_status}
		";

        return [$sql, $params];
    }

}
