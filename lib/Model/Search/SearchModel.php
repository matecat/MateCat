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

    public function __construct( SearchQueryParamsStruct $queryParams ){
        $this->queryParams = $queryParams;
        $this->db          = Database::obtain();
        $this->_loadParams();
    }

    /**
     * @throws Exception
     */
    public function replaceAll(){

        $sql = $this->_loadReplaceAllQuery();
        $resultSet = $this->_getQuery( $sql );

        $sqlBatch = [];
        $sqlValues = [];
        foreach ( $resultSet as $key => $tRow ) {

            //we get the spaces before needed string and re-apply before substitution because we can't know if there are
            //and how much they are
            $trMod = preg_replace( "#({$this->queryParams->exactMatch->Space_Left}){$this->queryParams->_regexpEscapedTrg}{$this->queryParams->exactMatch->Space_Right}#{$this->queryParams->matchCase->REGEXP_MODIFIER}",
                    '${1}' . $this->queryParams->replacement . '${2}',
                    $tRow[ 'translation' ]
            );

            /**
             * Escape for database
             */
            $sqlBatch[] = "(?,?,?)";
            $sqlValues[] = $tRow['id_segment'];
            $sqlValues[] = $tRow['id_job'];
            $sqlValues[] = $trMod;

        }

        //MySQL default max_allowed_packet is 16MB, this system surely need more
        //but we can assume that max translation length is more or less 2.5KB
        // so, for 100 translations of that size we can have 250KB + 20% char strings for query and id.
        // 300KB is a very low number compared to 16MB
        $sqlBatchChunk = array_chunk( $sqlBatch, 100 );
        $sqlValuesChunk = array_chunk( $sqlValues, 100 * 3 );

        foreach ( $sqlBatchChunk as $k => $batch ) {

            //WE USE INSERT STATEMENT for it's convenience ( update multiple fields in multiple rows in batch )
            //we try to insert these rows in a table wherein the primary key ( unique by definition )
            //is a coupled key ( id_segment, id_job ), but these values are already present ( duplicates )
            //so make an "ON DUPLICATE KEY UPDATE"
            $sqlInsert = "
            INSERT INTO segment_translations ( id_segment, id_job, translation )
			  VALUES " . implode( ",", $batch ) . "
			ON DUPLICATE KEY UPDATE translation = VALUES( translation )
			";

            try {

                $this->_insertQuery( $sqlInsert, $sqlValuesChunk[ $k ] );

            } catch ( Exception $e ){

                $msg = "\n\n Error ReplaceAll \n\n Integrity failure: \n\n
				- job id            : " . $this->queryParams->job . "
				- original data and failed query stored in log ReplaceAll_Failures.log\n\n
				";

                Log::$fileName = 'ReplaceAll_Failures.log';
                Log::doLog( $sql );
                Log::doLog( $resultSet );
                Log::doLog( $sqlInsert );
                Log::doLog( $msg );

                Utils::sendErrMailReport( $msg );

                throw new Exception( 'Update translations failure.' ); //bye bye translations....

            }

            //we must divide by 2 because Insert count as 1 but fails and duplicate key update count as 2
            //Log::doLog( "Replace ALL Batch " . ($k +1) . " - Affected Rows " . ( $db->affected_rows / 2 ) );

        }

    }

    /**
     * @return array
     * @throws Exception
     */
    public function search(){

        $sql = null;
        switch( $this->queryParams->key ){
            case 'source':
                $sql = $this->_loadSearchInSourceQuery();
                break;
            case 'target':
                $sql = $this->_loadSearchInTargetQuery();
                break;
            case 'coupled':
                $sql = $this->_loadSearchCoupledQuery();
                break;
            case 'status_only':
                $sql = $this->_loadSearchStatusOnlyQuery();
                break;
        }

        $results = $this->_getQuery( $sql );

        $vector = [ 'sid_list' => [], 'count' => '0' ];

        if ( $this->queryParams->key != 'coupled' && $this->queryParams->key != 'status_only' ) { //there is the ROLLUP

            $rollup = array_pop( $results );
            $vector[ 'count' ] = $rollup[ 'count' ]; //can be null, suppress warning

            foreach ( $results as $occurrence ) {
                $vector[ 'sid_list' ][] = $occurrence[ 'id' ];
            }

            //there should be empty values because of Sensitive search
            //LIKE is case INSENSITIVE, REPLACE IS NOT
            //empty search values removed
            //ROLLUP counter rules!
            if ( $vector[ 'count' ] == 0 ) {
                $vector[ 'sid_list' ] = [];
                $vector[ 'count' ]   = 0;
            }

        } else {

            foreach ( $results as $occurrence ) {
                $vector[ 'sid_list' ][] = $occurrence[ 'id' ];
            }

        }

        return $vector;

    }

    /**
     * @param $sql
     *
     * @return array
     * @throws Exception
     */
    protected function _getQuery( $sql ){

        try {
            $stmt = $this->db->getConnection()->prepare( $sql );
            $stmt->execute();
            $results = $stmt->fetchAll( PDO::FETCH_ASSOC );
        } catch ( PDOException $e ) {
            Log::doLog( $e->getMessage() );
            throw new \Exception( $e->getMessage(), $e->getCode() * -1, $e );
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
    protected function _insertQuery( $sql , $data ){

        try {
            $stmt = $this->db->getConnection()->prepare( $sql );
            $stmt->execute( $data );
        } catch ( PDOException $e ) {
            Log::doLog( $e->getMessage() );
            throw new \Exception( $e->getMessage(), $e->getCode() * -1, $e );
        }

        return $stmt->rowCount();

    }

    /**
     * Pay attention to possible SQL injection
     *
     */
    protected function _loadParams(){

        $this->queryParams->source = $this->db->escape( $this->queryParams->src );
        $this->queryParams->target = $this->db->escape( $this->queryParams->trg );

        $this->queryParams->where_status = "";
        if ( $this->queryParams->status != 'all' ) {
            $this->queryParams->status       = $this->db->escape( $this->queryParams->status ); //escape: hardcoded
            $this->queryParams->where_status = "AND st.status = '{$this->queryParams->status}'";
        }

        $matchCase = $this->queryParams->matchCase;

        $this->queryParams->matchCase = new \stdClass();
        if ( $matchCase ) {
            $this->queryParams->matchCase->SQL_REGEXP_CASE = "BINARY";
            $this->queryParams->matchCase->SQL_LENGHT_CASE = "";
            $this->queryParams->matchCase->REGEXP_MODIFIER = 'u';
        } else {
            $this->queryParams->matchCase->SQL_REGEXP_CASE = "";
            $this->queryParams->matchCase->SQL_LENGHT_CASE = "LOWER";
            $this->queryParams->matchCase->REGEXP_MODIFIER = 'iu';
        }

        $exactMatch = $this->queryParams->exactMatch;

        $this->queryParams->exactMatch = new \stdClass();
        if ( $exactMatch ) {
            $this->queryParams->exactMatch->Space_Left  = "[[:space:]]{0,}";
            $this->queryParams->exactMatch->Space_Right = "([[:space:]]|$)";
        } else {
            $this->queryParams->exactMatch->Space_Left = $this->queryParams->exactMatch->Space_Right = ""; // we want to search for all occurrences in a string: the word mod will take two matches: "mod" and "mod modifier"
        }

        /**
         * Escape Meta-characters to use in regular expression ( LIKE STATEMENT is treated inside MySQL as a Regexp pattern )
         *
         */
        $this->queryParams->_regexpNotEscapedSrc = preg_replace( '#([\#\[\]\(\)\*\.\?\^\$\{\}\+\-\|\\\\])#', '\\\\$1', $this->queryParams->src );
        $this->queryParams->regexpEscapedSrc     = $this->db->escape( $this->queryParams->_regexpNotEscapedSrc );

        $this->queryParams->_regexpEscapedTrg = preg_replace( '#([\#\[\]\(\)\*\.\?\^\$\{\}\+\-\|\\\\])#', '\\\\$1', $this->queryParams->trg );
        $this->queryParams->regexpEscapedTrg  = $this->db->escape( $this->queryParams->_regexpEscapedTrg );

    }

    protected function _loadSearchInTargetQuery(){

        $query = "
        SELECT  st.id_segment as id, sum(
			ROUND (
					( LENGTH( st.translation ) - LENGTH( 
                        REPLACE ( 
                          {$this->queryParams->matchCase->SQL_LENGHT_CASE}( st.translation ), 
                          {$this->queryParams->matchCase->SQL_LENGHT_CASE}( '{$this->queryParams->target}' ), ''
                        ) 
					) ) / LENGTH('{$this->queryParams->target}') )
			) AS count
			FROM segment_translations st
			WHERE st.id_job = {$this->queryParams->job}
		    AND st.translation REGEXP {$this->queryParams->matchCase->SQL_REGEXP_CASE} 
		          '{$this->queryParams->exactMatch->Space_Left}{$this->queryParams->regexpEscapedTrg}{$this->queryParams->exactMatch->Space_Right}'
			AND st.status != 'NEW'
			{$this->queryParams->where_status}
			AND ROUND (
                        ( LENGTH( st.translation ) - LENGTH( REPLACE ( 
                            {$this->queryParams->matchCase->SQL_LENGHT_CASE}( st.translation ), 
                            {$this->queryParams->matchCase->SQL_LENGHT_CASE}( '{$this->queryParams->target}' ), 
                            ''
                            ) ) 
                        ) / LENGTH('{{$this->queryParams->target}}') 
			) > 0
			GROUP BY st.id_segment WITH ROLLUP
		";

        return $query;

    }

    protected function _loadSearchInSourceQuery(){

        $query = "
        SELECT s.id, sum(
			ROUND (
					( LENGTH( s.segment ) - LENGTH( 
                        REPLACE ( 
                          {$this->queryParams->matchCase->SQL_LENGHT_CASE}( segment ), 
                          {$this->queryParams->matchCase->SQL_LENGHT_CASE}( '{$this->queryParams->source}' ), ''
                        ) 
					) ) / LENGTH('{$this->queryParams->source}') )
			) AS count
			FROM segments s
			INNER JOIN files_job fj on s.id_file=fj.id_file
			LEFT JOIN segment_translations st on st.id_segment = s.id AND st.id_job = fj.id_job
			WHERE fj.id_job = {$this->queryParams->job}
		    AND s.segment 
		        REGEXP {$this->queryParams->matchCase->SQL_REGEXP_CASE} 
		          '{$this->queryParams->exactMatch->Space_Left}{$this->queryParams->regexpEscapedSrc}{$this->queryParams->exactMatch->Space_Right}'
			{$this->queryParams->where_status}
			AND show_in_cattool = 1
			GROUP BY s.id WITH ROLLUP";

        return $query;

    }

    protected function _loadSearchCoupledQuery(){

        $query = "
        SELECT st.id_segment as id
			FROM segment_translations as st
			JOIN segments as s on id = id_segment
			WHERE st.id_job = {$this->queryParams->job}
		    AND st.translation 
		        REGEXP {$this->queryParams->matchCase->SQL_REGEXP_CASE} 
		          '{$this->queryParams->exactMatch->Space_Left}{$this->queryParams->regexpEscapedTrg}{$this->queryParams->exactMatch->Space_Right}'
			AND s.segment 
			    REGEXP {$this->queryParams->matchCase->SQL_REGEXP_CASE} 
			      '{$this->queryParams->exactMatch->Space_Left}{$this->queryParams->regexpEscapedSrc}{$this->queryParams->exactMatch->Space_Right}'
			AND LENGTH( 
			    REPLACE ( 
			      {$this->queryParams->matchCase->SQL_LENGHT_CASE}( segment ), 
			      {$this->queryParams->matchCase->SQL_LENGHT_CASE}( '{$this->queryParams->source}' ), 
			      ''
			    ) 
			) != LENGTH( s.segment )
			AND LENGTH( 
			    REPLACE ( 
			      {$this->queryParams->matchCase->SQL_LENGHT_CASE}( st.translation ), 
			      {$this->queryParams->matchCase->SQL_LENGHT_CASE}( '{$this->queryParams->target}' ), 
			      ''
			    ) 
			) != LENGTH( st.translation )
			AND st.status != 'NEW'
			{$this->queryParams->where_status}
		";

        return $query;

    }

    protected function _loadSearchStatusOnlyQuery(){

        $query = "
        SELECT st.id_segment as id
			FROM segment_translations as st
			WHERE st.id_job = {$this->queryParams->job}
		    {$this->queryParams->where_status}
		";

        return $query;

    }

    protected function _loadReplaceAllQuery(){

        $sql = "
        SELECT id_segment, id_job, translation
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

}