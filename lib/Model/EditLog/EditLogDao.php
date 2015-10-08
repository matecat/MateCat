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

    const NUM_SEGS = 10;

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

        $queryCenter = "
                    SELECT segments.id AS __sid
                    FROM segments
                    JOIN segment_translations ON id = id_segment
                    JOIN jobs ON jobs.id = id_job
                    WHERE id_job = %d
                        AND password = '%s'
                        AND show_in_cattool = 1
                        AND segments.id >= %d
                    LIMIT %u
                    UNION
                    SELECT * from(
                            SELECT  segments.id AS __sid
                        FROM segments
                        JOIN segment_translations ON id = id_segment
                        JOIN jobs ON jobs.id =  id_job
                        WHERE id_job = %d
                            AND password = '%s'
                            AND show_in_cattool = 1
                            AND segments.id < %d
                        ORDER BY __sid DESC
                        LIMIT %u
                    ) as TT
                ";

        $query = "SELECT
            s.id,
            s.segment AS source,
            st.translation AS translation,
            st.time_to_edit,
            st.suggestion,
            st.suggestions_array,
            st.suggestion_source,
            st.suggestion_match,
            st.suggestion_position,
            st.mt_qe,
            j.id_translator,
            j.source AS job_source,
            j.target AS job_target,
            s.raw_word_count,
            p.name as proj_name
                FROM
                jobs j
                INNER JOIN segment_translations st ON j.id=st.id_job
                INNER JOIN segments s ON s.id = st.id_segment
                INNER JOIN projects p on p.id=j.id_project
                JOIN(
                  %s
                ) AS TEMP ON TEMP.__sid = s.id
                WHERE
                id_job = %d AND
                j.password = '%s' AND
                translation IS NOT NULL AND
                st.status<>'NEW'
                AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
                ORDER BY time_to_edit DESC";

        $queryCenter = sprintf(
                $queryCenter,
                $job_id,
                $password,
                $ref_segment,
                self::NUM_SEGS,
                $job_id,
                $password,
                $ref_segment,
                self::NUM_SEGS
        );

        $result = $this->_fetch_array(
                sprintf(
                        $query,
                        $queryCenter,
                        $job_id,
                        $password
                )
        );

        return $this->_buildResult( $result );
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
                            JOIN segment_translations ON id = id_segment
                            JOIN jobs ON jobs.id =  id_job
                            WHERE id_job = %d
                                AND password = '%s'
                                AND show_in_cattool = 1
                                AND segments.id < jobs.job_last_segment
                            ORDER BY __sid DESC
                            LIMIT %u
                      ) x
                      order by __sid ASC
                      limit 1";

        $result = $this->_fetch_array(
                sprintf(
                        $queryBefore,
                        $job_id,
                        $password, self::NUM_SEGS
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
                        JOIN segment_translations ON id = id_segment
                        JOIN jobs ON jobs.id =  id_job
                        WHERE id_job = %d
                            AND password = '%s'
                            AND show_in_cattool = 1
                            AND segments.id >= jobs.job_first_segment
                        ORDER BY __sid DESC";

        $result = $this->_fetch_array(
                sprintf(
                        $queryBefore,
                        $job_id,
                        $password, self::NUM_SEGS
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
			JOIN segment_translations ON id = id_segment
			JOIN jobs ON jobs.id =  id_job
	        JOIN ( SELECT @page:= -1 ) AS page
		  WHERE id_job = %d
            AND password = '%s'
            AND show_in_cattool = 1
			ORDER BY start_segment asc
        ) x
        group by 2;";

        $result = $this->_fetch_array(
                sprintf(
                        $queryBefore,
                        self::NUM_SEGS,
                        $job_id,
                        $password
                )
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