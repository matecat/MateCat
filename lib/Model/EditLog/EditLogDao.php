<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 05/10/15
 * Time: 11.33
 */
class EditLog_EditLogDao extends DataAccess_AbstractDao {

    const TABLE = "";

    const STRUCT_TYPE = "EditLog_EditLogSegmentStruct";

    protected static $NUM_SEGS = 10;

    /**
     * @param int $NUM_SEGS
     *
     * @return $this
     */
    public function setNumSegs( $NUM_SEGS = 10 ) {
        self::$NUM_SEGS = $NUM_SEGS;
        return $this;
    }

    /**
     * This method returns a set of 2*NUM_SEGS segments
     * having the middle segment's id equal to $ref_segment
     *
     * @param $job_id      int
     * @param $password    string
     * @param $ref_segment int
     *
     * @return EditLog_EditLogSegmentStruct|EditLog_EditLogSegmentStruct[]
     * @throws Exception
     */
    public function getSegments( $job_id, $password, $ref_segment ) {
        if ( empty( $job_id ) ) {
            throw new Exception( "Job id required" );
        }

        if ( empty( $password ) ) {
            throw new Exception( "Job password required" );
        }

        $querySegments = "
                    SELECT * FROM (
                        SELECT segments.id AS __sid
                        FROM segments
                        JOIN segment_translations st ON id = id_segment
                        JOIN jobs ON jobs.id = id_job
                        WHERE id_job = %d
                            AND password = '%s'
                            AND show_in_cattool = 1
                            AND segments.id >= %d
                            AND st.status not in( '%s', '%s' )
                        LIMIT %u
                    ) AS TT1
                    UNION
                    SELECT * from(
                            SELECT  segments.id AS __sid
                        FROM segments
                        JOIN segment_translations st ON id = id_segment
                        JOIN jobs ON jobs.id =  id_job
                        WHERE id_job = %d
                            AND password = '%s'
                            AND show_in_cattool = 1
                            AND segments.id < %d
                            AND st.status not in( '%s', '%s' )
                        ORDER BY __sid DESC
                        LIMIT %u
                    ) as TT2
                ";

        $query = "SELECT
            s.id,
            s.segment AS source,
            s.internal_id,
            st.translation AS translation,
            st.time_to_edit,
            st.suggestion,
            st.suggestions_array,
            st.suggestion_source,
            st.suggestion_match,
            st.suggestion_position,
            st.mt_qe,
            st.match_type,
            st.locked,
            ste.uid,
            j.id_translator,
            j.source AS job_source,
            j.target AS job_target,
            s.raw_word_count,
            p.name as proj_name,
            st.segment_hash
                FROM
                jobs j
                INNER JOIN segment_translations st ON j.id=st.id_job
                INNER JOIN segments s ON s.id = st.id_segment
                INNER JOIN projects p on p.id=j.id_project
                JOIN(
                  %s
                ) AS TEMP ON TEMP.__sid = s.id

                LEFT JOIN segment_translation_events ste on st.id_segment = ste.id_segment
                  AND st.version_number = ste.version_number

                WHERE
                st.id_job = %d AND
                j.password = '%s' AND
                translation IS NOT NULL AND
                st.status not in( '%s', '%s' )
                AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
                ORDER BY st.id_segment ASC";

        $querySegments = sprintf(
                $querySegments,
                $job_id,
                $password,
                $ref_segment,
                Constants_TranslationStatus::STATUS_NEW,
                Constants_TranslationStatus::STATUS_DRAFT,
                self::$NUM_SEGS,
                $job_id,
                $password,
                $ref_segment,
                Constants_TranslationStatus::STATUS_NEW,
                Constants_TranslationStatus::STATUS_DRAFT,
                self::$NUM_SEGS
        );

        $result = $this->_fetch_array(
                sprintf(
                        $query,
                        $querySegments,
                        $job_id,
                        $password,
                        Constants_TranslationStatus::STATUS_NEW,
                        Constants_TranslationStatus::STATUS_DRAFT
                )
        );

        $userDao = new Users_UserDao() ;
        $userDao->setCacheTTL( 60 * 60 * 24 * 30 ) ;

        foreach( $result as $key => $value ) {
            if ( !is_null( $result[ $key ] [ 'uid' ] ) ) {
                $result[ $key ] [ 'email' ] = $userDao->getByUid( $result [ $key ] [ 'uid' ] )->email  ;
            }
        }

        return $this->_buildResult( $result );
    }

    /**
     * @param $job_id   int
     * @param $password string
     *
     * @return bool
     */
    public function isEditLogEmpty( $job_id, $password ) {
        $query = "SELECT count(segments.id) as num_segs
                    FROM segments
                    JOIN segment_translations st ON id = id_segment
                    JOIN jobs ON jobs.id = id_job
                    WHERE id_job = %d
                        AND password = '%s'
                        AND show_in_cattool = 1
                        AND st.status not in( '%s', '%s' )";

        $result = $this->con->query_first(
                sprintf(
                        $query,
                        $job_id,
                        $password,
                        Constants_TranslationStatus::STATUS_NEW,
                        Constants_TranslationStatus::STATUS_DRAFT
                )
        );

        return (int)$result[ 'num_segs' ] == 0;
    }


    /**
     * @param $job_id int
     *
     * @return array|mixed
     * @throws Exception
     */
    public function getTranslationMismatches( $job_id ) {
        if ( empty( $job_id ) ) {
            throw new Exception( "Job id required" );
        }

        $queryBefore = "select segment_hash,
                        COUNT( DISTINCT translation ) -1 AS translation_mismatch
                        FROM segment_translations
                        JOIN jobs ON id_job = id
                                  AND id_segment between jobs.job_first_segment AND jobs.job_last_segment
                        WHERE id_job = %d
                        AND segment_translations.status not in( '%s', '%s' )
                        GROUP BY segment_hash,
                                 CONCAT( id_job, '-', password )";

        $result = $this->_fetch_array(
                sprintf(
                        $queryBefore,
                        $job_id,
                        Constants_TranslationStatus::STATUS_NEW,
                        Constants_TranslationStatus::STATUS_DRAFT
                )
        );

        return $result;
    }

    /**
     * @param $job_id   int
     * @param $password string
     *
     * @return int
     * @throws Exception
     */
    public function getLastPage_firstID( $job_id, $password ) {

        if ( empty( $job_id ) ) {
            throw new Exception( "Job id required" );
        }

        if ( empty( $password ) ) {
            throw new Exception( "Job password required" );
        }

        $queryBefore = "select * from (
                            SELECT segments.id AS __sid
                            FROM segments
                            JOIN segment_translations st ON id = id_segment
                            JOIN jobs ON jobs.id =  id_job
                            WHERE id_job = %d
                                AND password = '%s'
                                AND show_in_cattool = 1
                                AND segments.id < jobs.job_last_segment
                                AND st.status not in( '%s', '%s' )
                            ORDER BY __sid DESC
                            LIMIT %u
                      ) x
                      order by __sid ASC
                      limit 1";

        $result = $this->_fetch_array(
                sprintf(
                        $queryBefore,
                        $job_id,
                        $password,
                        Constants_TranslationStatus::STATUS_NEW,
                        Constants_TranslationStatus::STATUS_DRAFT,
                        self::$NUM_SEGS
                )
        );

        return (int)$result[ 0 ][ '__sid' ];
    }

    /**
     * @param $job_id   int
     * @param $password string
     *
     * @return int
     * @throws Exception
     */
    public function getFirstPage_firstID( $job_id, $password ) {
        if ( empty( $job_id ) ) {
            throw new Exception( "Job id required" );
        }

        if ( empty( $password ) ) {
            throw new Exception( "Job password required" );
        }

        $queryBefore = "SELECT min(segments.id) AS __sid
                        FROM segments
                        JOIN segment_translations st ON id = id_segment
                        JOIN jobs ON jobs.id =  id_job
                        WHERE id_job = %d
                            AND password = '%s'
                            AND show_in_cattool = 1
                            AND segments.id >= jobs.job_first_segment
                            AND st.status not in ( '%s', '%s' )
                        ORDER BY __sid DESC";

        $result = $this->_fetch_array(
                sprintf(
                        $queryBefore,
                        $job_id,
                        $password,
                        Constants_TranslationStatus::STATUS_NEW,
                        Constants_TranslationStatus::STATUS_DRAFT,
                        self::$NUM_SEGS
                )
        );

        return (int)$result[ 0 ][ '__sid' ];
    }

    /**
     * @param $job_id   int
     * @param $password string
     *
     * @return array
     * @throws Exception
     */
    public function getPagination( $job_id, $password ) {
        if ( empty( $job_id ) ) {
            throw new Exception( "Job id required" );
        }

        if ( empty( $password ) ) {
            throw new Exception( "Job password required" );
        }

        $queryBefore = "
        select start_segment, floor(idx / %d ) +1 as page from (
          SELECT segments.id AS start_segment, @page := ( @page + 1 ) as idx
		  FROM segments
			JOIN segment_translations st ON id = id_segment
			JOIN jobs ON jobs.id =  id_job
	        JOIN ( SELECT @page:= -1 ) AS page
		  WHERE id_job = %d
            AND password = '%s'
            AND show_in_cattool = 1
            AND st.status not in ( '%s', '%s' )
			ORDER BY start_segment asc
        ) x
        group by 2;";

        $result = $this->_fetch_array(
                sprintf(
                        $queryBefore,
                        self::$NUM_SEGS,
                        $job_id,
                        $password,
                        Constants_TranslationStatus::STATUS_NEW,
                        Constants_TranslationStatus::STATUS_DRAFT
                )
        );

        return $result;

    }

    /**
     * @param $job_id   int
     * @param $password string
     *
     * @return array
     * @throws Exception
     */
    public function getGlobalStats( $job_id, $password ) {

        if ( empty( $job_id ) ) {
            throw new Exception( "Job id required" );
        }

        if ( empty( $password ) ) {
            throw new Exception( "Job password required" );
        }

        $resultValidSegs = ( new Jobs_JobDao() )->setCacheTTL( 60 * 15 )->getPeeStats( $job_id, $password );
        $resultAllSegs = ( new Jobs_JobDao() )->setCacheTTL( 60 * 15 )->getJobRawStats( $job_id, $password );

        $result = array(
                'tot_tte'       => $resultAllSegs->tot_tte,
                'raw_words'     => $resultAllSegs->raw_words,
                'secs_per_word' => $resultAllSegs->secs_per_word,
                'avg_pee'       => $resultValidSegs->avg_pee
        );

        return $result;
    }

    /**
     * @param $array_result array
     *
     * @return EditLog_EditLogSegmentStruct|EditLog_EditLogSegmentStruct[]
     */
    protected function _buildResult( $array_result ) {
        $return = array();

        if ( Utils::is_assoc( $array_result ) ) { //single result
            $return = new EditLog_EditLogSegmentStruct( $array_result );
        } else {
            foreach ( $array_result as $element ) {
                $return[] = new EditLog_EditLogSegmentStruct( $element );
            }
        }

        return $return;
    }


}