<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/04/18
 * Time: 13.57
 *
 */

namespace Search;

use Database;
use Exception;
use Log;
use Matecat\Finder\WholeTextFinder;
use Matecat\SubFiltering\MateCatFilter;
use PDO;
use PDOException;
use Utils;

class SearchModel {

    /**
     * @var SearchQueryParamsStruct
     */
    protected $queryParams;

    /**
     * @var Database
     */
    protected $db;

    /**
     * @var MateCatFilter
     */
    private $filters;

    /**
     * SearchModel constructor.
     *
     * @param SearchQueryParamsStruct $queryParams
     * @param MateCatFilter          $filters
     */
    public function __construct( SearchQueryParamsStruct $queryParams, MateCatFilter $filters ) {
        $this->queryParams = $queryParams;
        $this->db          = Database::obtain();
        $this->filters     = $filters;
        $this->_loadParams();
    }

    /**
     * @param bool $strictMode
     *
     * @return array
     * @throws Exception
     */
    public function search($strictMode = true) {

        switch ( $this->queryParams->key ) {
            case 'source':
                $results = $this->_getQuery( $this->_loadSearchInSourceQuery($strictMode) );
                break;
            case 'target':
                $results = $this->_getQuery( $this->_loadSearchInTargetQuery($strictMode) );
                break;
            case 'coupled':
                $rawResults = array_merge_recursive( $this->_getQuery( $this->_loadSearchInSourceQuery($strictMode) ), $this->_getQuery( $this->_loadSearchInTargetQuery($strictMode) ) );
                $results    = [];

                // in this case, $results is the merge of two queries results,
                // every segment id will possibly have 2 occurrences (source and target)
                foreach ( $rawResults as $rawResult ) {
                    $results[ $rawResult[ 'id' ] ][] = $rawResult[ 'text' ];
                }

                break;
            case 'status_only':
                $results = $this->_getQuery( $this->_loadSearchStatusOnlyQuery() );
                break;
        }

        $vector = [
                'sid_list' => [],
                'count'    => '0'
        ];

        if ( $this->queryParams->key === 'source' || $this->queryParams->key === 'target' ) {

            $searchTerm = ( false === empty( $this->queryParams->source ) ) ? $this->queryParams->source : $this->queryParams->target;

            foreach ( $results as $occurrence ) {
                $matches      = $this->find( $occurrence[ 'text' ], $searchTerm, $occurrence[ 'original_map' ] );
                $matchesCount = count( $matches );

                if ( $this->hasMatches( $matches ) ) {
                    $vector[ 'sid_list' ][] = $occurrence[ 'id' ];
                    $vector[ 'count' ]      = $vector[ 'count' ] + $matchesCount;
                }
            }

            if ( $vector[ 'count' ] == 0 ) {
                $vector[ 'sid_list' ] = [];
                $vector[ 'count' ]    = 0;
            }

        } elseif ( $this->queryParams->key === 'coupled' ) {

            foreach ( $results as $id => $occurrence ) {

                // check if exists match target
                if ( isset( $occurrence[ 1 ] ) ) {

                    // match source
                    $searchTermSource   = $this->queryParams->source;
                    $matchesSource      = $this->find( $occurrence[ 0 ], $searchTermSource );
                    $matchesSourceCount = count( $matchesSource );

                    $searchTermTarget   = $this->queryParams->target;
                    $matchesTarget      = $this->find( $occurrence[ 1 ], $searchTermTarget );
                    $matchesTargetCount = count( $matchesTarget );

                    if ( $this->hasMatches( $matchesSource ) and $this->hasMatches( $matchesTarget ) ) {
                        $vector[ 'sid_list' ][] = strval($id);
                        $vector[ 'count' ]      = $vector[ 'count' ] + $matchesTargetCount + $matchesSourceCount;
                    }
                }
            }

            if ( $vector[ 'count' ] == 0 ) {
                $vector[ 'sid_list' ] = [];
                $vector[ 'count' ]    = 0;
            }

        } else {

            foreach ( $results as $occurrence ) {
                $vector[ 'sid_list' ][] = $occurrence[ 'id' ];
            }

        }

        return $vector;
    }

    /**
     * @param array $matches
     *
     * @return bool
     */
    private function hasMatches( array $matches ) {

        return count( $matches ) > 0 and $matches[ 0 ][ 0 ] !== '';
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @param null   $originalMap
     *
     * @return array
     * @throws Exception
     */
    private function find( $haystack, $needle, $originalMap = null ) {

        $this->filters->fromLayer0ToLayer2( $haystack );
//        $haystack = StringTransformer::transform($haystack, $originalMap);

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
     * @param $sql
     *
     * @return array
     * @throws Exception
     */
    protected function _getQuery( $sql ) {

        try {
            $stmt = $this->db->getConnection()->prepare( $sql );
            $stmt->execute();
            $results = $stmt->fetchAll( PDO::FETCH_ASSOC );
        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );
            throw new Exception( $e->getMessage(), $e->getCode() * -1, $e );
        }

        return $results;
    }

    /**
     * @param $sql
     * @param $data
     *
     * @return mixed
     * @throws Exception
     */
    protected function _insertQuery( $sql, $data ) {

        try {
            $stmt = $this->db->getConnection()->prepare( $sql );
            $stmt->execute( $data );
        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );
            throw new Exception( $e->getMessage(), $e->getCode() * -1, $e );
        }

        return $stmt->rowCount();

    }

    /**
     * Pay attention to possible SQL injection
     */
    protected function _loadParams() {

        // bring the src and target from layer 2 (UI) to layer 0 (DB)
//        $this->queryParams->source = $this->filters->fromLayer2ToLayer0( $this->queryParams->src );
//        $this->queryParams->target = $this->filters->fromLayer2ToLayer0( $this->queryParams->trg );
        $this->queryParams->source = $this->queryParams->src;
        $this->queryParams->target = $this->queryParams->trg;

        $this->queryParams->where_status = "";
        if ( $this->queryParams->status != 'all' ) {
            $this->queryParams->status       = $this->db->escape( $this->queryParams->status ); //escape: hardcoded
            $this->queryParams->where_status = "AND st.status = '{$this->queryParams->status}'";
        }

        $this->queryParams->matchCase = new \stdClass();
        if ( $this->queryParams->isMatchCaseRequested ) {
            $this->queryParams->matchCase->SQL_REGEXP_CASE = "BINARY";
            $this->queryParams->matchCase->SQL_LENGHT_CASE = "";
            $this->queryParams->matchCase->REGEXP_MODIFIER = 'u';
        } else {
            $this->queryParams->matchCase->SQL_REGEXP_CASE = "";
            $this->queryParams->matchCase->SQL_LENGHT_CASE = "LOWER";
            $this->queryParams->matchCase->REGEXP_MODIFIER = 'iu';
        }

        $this->queryParams->exactMatch = new \stdClass();
        if ( $this->queryParams->isExactMatchRequested ) {
            $this->queryParams->exactMatch->Space_Left  = "[[:space:]]{0,}";
            $this->queryParams->exactMatch->Space_Right = "([[:space:]]|$)";
        } else {
            $this->queryParams->exactMatch->Space_Left = $this->queryParams->exactMatch->Space_Right = ""; // we want to search for all occurrences in a string: the word mod will take two matches: "mod" and "mod modifier"
        }

        /**
         * Escape Meta-characters to use in regular expression ( LIKE STATEMENT is treated inside MySQL as a Regexp pattern )
         *
         */
        $this->queryParams->_regexpNotEscapedSrc = preg_replace( '#([\#\[\]\(\)\*\.\?\^\$\{\}\+\-\|\\\\])#', '\\\\$1', $this->queryParams->source );
        $this->queryParams->regexpEscapedSrc     = $this->db->escape( $this->queryParams->_regexpNotEscapedSrc );

        $this->queryParams->_regexpEscapedTrg = preg_replace( '#([\#\[\]\(\)\*\.\?\^\$\{\}\+\-\|\\\\])#', '\\\\$1', $this->queryParams->target );
        $this->queryParams->regexpEscapedTrg  = $this->db->escape( $this->queryParams->_regexpEscapedTrg );

    }

    /**
     * @param bool $strictMode
     *
     * @return string
     */
    protected function _loadSearchInTargetQuery($strictMode = false) {

        $this->_loadParams();
        $password_where = ($strictMode) ? ' AND st.id_segment between j.job_first_segment and j.job_last_segment AND j.password = "'.$this->queryParams->password.'"' : '';

        $query = "
        SELECT  st.id_segment as id, st.translation as text, od.map as original_map
			FROM segment_translations st
			INNER JOIN jobs j ON j.id = st.id_job
			LEFT JOIN segment_original_data od on od.id_segment = st.id_segment
			WHERE st.id_job = {$this->queryParams->job} 
			{$password_where}
			AND st.status != 'NEW'
			{$this->queryParams->where_status}
			GROUP BY st.id_segment";

        return $query;

    }

    /**
     * @param bool $strictMode
     *
     * @return string
     */
    protected function _loadSearchInSourceQuery($strictMode = false) {

        $this->_loadParams();
        $password_where = ($strictMode) ? ' AND s.id between j.job_first_segment and j.job_last_segment AND j.password = "'.$this->queryParams->password.'"' : '';

        $query = "
        SELECT s.id, s.segment as text, od.map as original_map
			FROM segments s
			INNER JOIN files_job fj on s.id_file=fj.id_file
			INNER JOIN jobs j ON j.id = fj.id_job
			LEFT JOIN segment_translations st on st.id_segment = s.id AND st.id_job = fj.id_job
			LEFT JOIN segment_original_data od on od.id_segment = s.id
			WHERE fj.id_job = {$this->queryParams->job}
			{$password_where}
			AND show_in_cattool = 1
			{$this->queryParams->where_status}
			GROUP BY s.id";

        return $query;

    }

    protected function _loadSearchStatusOnlyQuery() {

        $this->_loadParams();

        $query = "
        SELECT st.id_segment as id
			FROM segment_translations as st
			WHERE st.id_job = {$this->queryParams->job}
		    {$this->queryParams->where_status}
		";

        return $query;

    }

    public function _loadReplaceAllQuery() {

        $this->_loadParams();

        $sql = "
        SELECT st.id_segment, st.id_job, st.translation, st.status
            FROM segment_translations st
            JOIN jobs ON st.id_job = jobs.id AND password = '{$this->queryParams->password}' AND jobs.id = {$this->queryParams->job}
            JOIN segments as s ON st.id_segment = s.id 
            WHERE id_job = {$this->queryParams->job}
            AND id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
            AND st.status != 'NEW'
            AND translation 
            	REGEXP {$this->queryParams->matchCase->SQL_REGEXP_CASE} 
		          '{$this->queryParams->exactMatch->Space_Left}{$this->queryParams->regexpEscapedTrg}{$this->queryParams->exactMatch->Space_Right}'
            {$this->queryParams->where_status}
        ";

        if ( !empty( $this->queryParams->regexpEscapedSrc ) ) {
            $sql .= " AND s.segment REGEXP {$this->queryParams->matchCase->SQL_REGEXP_CASE} 
		          '{$this->queryParams->exactMatch->Space_Left}{$this->queryParams->regexpEscapedSrc}{$this->queryParams->exactMatch->Space_Right}' ";
        }

        return $sql;
    }

    /**
     * @return string
     */
    private function getSpacerForSearchQueries() {
        if ( $this->isExactMatchEnabled() ) {
            return ' ';
        }

        return '';
    }

    /**
     * @return bool
     */
    private function isExactMatchEnabled() {
        $exactMatch = $this->queryParams->exactMatch;

        return ( $exactMatch->Space_Left !== '' and $exactMatch->Space_Right !== '' );
    }

}