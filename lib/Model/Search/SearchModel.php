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
use Search_ReplaceHistory;
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
     * @var Search_ReplaceHistory
     */
    private $eventHistory;

    /**
     * SearchModel constructor.
     *
     * @param SearchQueryParamsStruct $queryParams
     *
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     */
    public function __construct( SearchQueryParamsStruct $queryParams ) {
        $this->queryParams = $queryParams;
        $this->db          = Database::obtain();
        //$this->_loadParams();
        $this->eventHistory = new Search_ReplaceHistory(
                $this->queryParams[ 'job' ],
                new \Search_RedisReplaceEventDAO(),
                new \Search_RedisReplaceEventIndexDAO()
        );
    }

    /**
     * @param $resultSet
     *
     * @throws Exception
     */
    public function replaceAll($resultSet) {

        $sqlBatch  = [];
        $sqlValues = [];
        foreach ( $resultSet as $key => $tRow ) {

            //we get the spaces before needed string and re-apply before substitution because we can't know if there are
            //and how much they are
            $trMod = $this->_getReplacedSegmentTranslation( $tRow[ 'translation' ] );

            /**
             * Escape for database
             */
            $sqlBatch[]  = "(?,?,?)";
            $sqlValues[] = $tRow[ 'id_segment' ];
            $sqlValues[] = $tRow[ 'id_job' ];
            $sqlValues[] = $trMod;

        }

        //MySQL default max_allowed_packet is 16MB, this system surely need more
        //but we can assume that max translation length is more or less 2.5KB
        // so, for 100 translations of that size we can have 250KB + 20% char strings for query and id.
        // 300KB is a very low number compared to 16MB
        $sqlBatchChunk  = array_chunk( $sqlBatch, 100 );
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

                // save replace event
                $versionToMove = ( $this->eventHistory->getCursor() + 1 );

                foreach ( $resultSet as $key => $tRow ) {
                    $this->_saveReplacementEvent( $versionToMove, $tRow );
                }

            } catch ( Exception $e ) {

                $msg = "\n\n Error ReplaceAll \n\n Integrity failure: \n\n
				- job id            : " . $this->queryParams->job . "
				- original data and failed query stored in log ReplaceAll_Failures.log\n\n
				";

                Log::$fileName = 'ReplaceAll_Failures.log';
                Log::doJsonLog( $sql );
                Log::doJsonLog( $resultSet );
                Log::doJsonLog( $sqlInsert );
                Log::doJsonLog( $msg );

                Utils::sendErrMailReport( $msg );

                throw new Exception( 'Update translations failure.' ); //bye bye translations....

            }

            //we must divide by 2 because Insert count as 1 but fails and duplicate key update count as 2
            //Log::doJsonLog( "Replace ALL Batch " . ($k +1) . " - Affected Rows " . ( $db->affected_rows / 2 ) );

        }

    }

    /**
     * @param $translation
     *
     * @return string|string[]|null
     */
    private function _getReplacedSegmentTranslation( $translation ) {
        return preg_replace( "#({$this->queryParams->exactMatch->Space_Left}){$this->queryParams->_regexpEscapedTrg}{$this->queryParams->exactMatch->Space_Right}#{$this->queryParams->matchCase->REGEXP_MODIFIER}",
                '${1}' . $this->queryParams->replacement . '${2}',
                $translation
        );
    }

    /**
     * @param $tRow
     */
    private function _saveReplacementEvent( $bulk_version, $tRow ) {
        $event                                 = new ReplaceEventStruct();
        $event->bulk_version                   = $bulk_version;
        $event->id_segment                     = $tRow[ 'id_segment' ];
        $event->id_job                         = $this->queryParams[ 'job' ];
        $event->job_password                   = $this->queryParams[ 'password' ];
        $event->source                         = $this->queryParams[ 'source' ];
        $event->target                         = $this->queryParams[ 'target' ];
        $event->replacement                    = $this->queryParams[ 'replacement' ];
        $event->translation_before_replacement = $tRow[ 'translation' ];
        $event->translation_after_replacement  = $this->_getReplacedSegmentTranslation( $tRow[ 'translation' ] );

        $this->eventHistory->save( $event );
        $this->eventHistory->updateIndex( $bulk_version );

        Log::doJsonLog( 'Replacement event for segment #' . $tRow[ 'id_segment' ] . ' correctly saved.' );
    }

    /**
     * Undo a ReplaceAll
     */
    public function undoReplaceAll() {
        $this->eventHistory->undo();

        Log::doJsonLog( 'Undo replacement for job #' . $this->queryParams[ 'job' ] . ' correctly done.' );
    }

    /**
     * Redo a ReplaceAll
     */
    public function redoReplaceAll() {
        $this->eventHistory->redo();

        Log::doJsonLog( 'Redo replacement for job #' . $this->queryParams[ 'job' ] . ' correctly done.' );
    }

    /**
     * @return array
     * @throws Exception
     */
    public function search() {

        $sql = null;
        switch ( $this->queryParams->key ) {
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

            $rollup            = array_pop( $results );
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
    protected function _insertQuery( $sql, $data ) {

        try {
            $stmt = $this->db->getConnection()->prepare( $sql );
            $stmt->execute( $data );
        } catch ( PDOException $e ) {
            Log::doJsonLog( $e->getMessage() );
            throw new \Exception( $e->getMessage(), $e->getCode() * -1, $e );
        }

        return $stmt->rowCount();

    }

    /**
     * Pay attention to possible SQL injection
     *
     */
    protected function _loadParams() {

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

    protected function _loadSearchInTargetQuery() {
        $this->_loadParams();
        $ste_join  = $this->_SteJoinInSegments( 'st.id_segment' );
        $ste_where = $this->_SteWhere();

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
			$ste_join
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
                        ) / LENGTH('{$this->queryParams->target}') 
			) > 0
			$ste_where
			GROUP BY st.id_segment WITH ROLLUP
		";

        return $query;

    }

    protected function _loadSearchInSourceQuery() {
        $this->_loadParams();
        $ste_join  = $this->_SteJoinInSegments();
        $ste_where = $this->_SteWhere();

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
            $ste_join
			WHERE fj.id_job = {$this->queryParams->job}
			$ste_where
		    AND s.segment 
		        REGEXP {$this->queryParams->matchCase->SQL_REGEXP_CASE} 
		          '{$this->queryParams->exactMatch->Space_Left}{$this->queryParams->regexpEscapedSrc}{$this->queryParams->exactMatch->Space_Right}'
			{$this->queryParams->where_status}
			AND show_in_cattool = 1
			GROUP BY s.id WITH ROLLUP";

        return $query;

    }

    protected function _loadSearchCoupledQuery() {
        $this->_loadParams();
        $ste_join  = $this->_SteJoinInSegments();
        $ste_where = $this->_SteWhere();

        $query = "
        SELECT st.id_segment as id
			FROM segment_translations as st
			JOIN segments as s on id = id_segment
			$ste_join
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
			$ste_where
		";

        return $query;

    }

    protected function _loadSearchStatusOnlyQuery() {
        $this->_loadParams();
        $ste_join  = $this->_SteJoinInSegments( 'st.id_segment' );
        $ste_where = $this->_SteWhere();

        $query = "
        SELECT st.id_segment as id
			FROM segment_translations as st
			$ste_join
			WHERE st.id_job = {$this->queryParams->job}
			$ste_where
		    {$this->queryParams->where_status}
		";

        return $query;

    }

    public function loadReplaceAllQuery() {
        $this->_loadParams();
        $ste_join  = $this->_SteJoinInSegments();
        $ste_where = $this->_SteWhere();

        $sql = "
        SELECT id_segment, id_job, translation
            FROM segment_translations st
            JOIN jobs ON st.id_job = jobs.id AND password = '{$this->queryParams->password}' AND jobs.id = {$this->queryParams->job}
            JOIN segments as s ON st.id_segment = s.id 

            $ste_join

            WHERE id_job = {$this->queryParams->job}
            AND id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
            AND st.status != 'NEW'
            AND translation 
            	REGEXP {$this->queryParams->matchCase->SQL_REGEXP_CASE} 
		          '{$this->queryParams->exactMatch->Space_Left}{$this->queryParams->regexpEscapedTrg}{$this->queryParams->exactMatch->Space_Right}'
            {$this->queryParams->where_status}

            $ste_where

        ";

        if ( !empty( $this->queryParams->regexpEscapedSrc ) ) {
            $sql .= " AND s.segment REGEXP {$this->queryParams->matchCase->SQL_REGEXP_CASE} 
		          '{$this->queryParams->exactMatch->Space_Left}{$this->queryParams->regexpEscapedSrc}{$this->queryParams->exactMatch->Space_Right}' ";
        }

        return $sql;
    }

    /**
     * Sometimes queries make use of s alias or segments, sometimes they make use of st for segment_translations.
     *
     * @param string $joined_field
     *
     * @return string
     */
    protected function _SteJoinInSegments( $joined_field = 's.id' ) {
        if ( !$this->queryParams->sourcePage ) {
            return '';
        }

        return "
            LEFT JOIN (
                SELECT id_segment as ste_id_segment, source_page FROM segment_translation_events WHERE id IN (
                SELECT max(id) FROM segment_translation_events
                    WHERE id_job = {$this->queryParams->job}
                    GROUP BY id_segment ) ORDER BY id_segment
            ) ste ON ste.ste_id_segment = $joined_field ";
    }

    /**
     * @return string
     */
    protected function _SteWhere() {
        if ( !$this->queryParams->sourcePage ) {
            return '';
        }

        /**
         * This variable $first_revision_source_code is necessary because in case of first revision
         * segment_translations_events may not have records
         * for APPROVED segments ( in case of ICE match ). While in second pass reviews or later this should not happen.
         */
        $first_revision_source_code = \Constants::SOURCE_PAGE_REVISION;

        return " AND ( ste.source_page = {$this->queryParams->sourcePage}
                    OR ( {$this->queryParams->sourcePage} = $first_revision_source_code AND ste.source_page = null )
               ) ";
    }

}