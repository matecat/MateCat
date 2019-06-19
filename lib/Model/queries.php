<?php

/**
 * @param $jid
 * @param $jPassword
 *
 * @return array
 */
function getTranslationsForTMXExport( $jid, $jPassword ) {

    $db        = Database::obtain();
    $jPassword = $db->escape( $jPassword );

    $sql = "
        SELECT
        id_segment,
        segment_translations.id_job,
        filename,
        segment,
        translation,
        translation_date,
        segment_translations.status
        FROM segment_translations
        JOIN segments ON id = id_segment

        JOIN files ON segments.id_file = files.id

        JOIN jobs ON jobs.id = segment_translations.id_job AND password = '" . $db->escape( $jPassword ) . "'

            WHERE segment_translations.id_job = " . (int)$jid . "
            AND show_in_cattool = 1
";

    try {
        $results = $db->fetch_array( $sql );
    } catch ( PDOException $e ) {
        Log::doJsonLog( $e->getMessage() );
        return $e->getCode() * -1;
    }

    return $results;

}

function getMTForTMXExport( $jid, $jPassword ) {
    //TODO: delete this function and put it in a DAO
    $db        = Database::obtain();
    $jPassword = $db->escape( $jPassword );

    $sql = "
        SELECT id_segment, st.id_job, '' as filename, segment, suggestion as translation,
        IF( st.status IN ('" . Constants_TranslationStatus::STATUS_TRANSLATED . "','" .
            Constants_TranslationStatus::STATUS_APPROVED . "'), translation_date, j.create_date ) as translation_date
        FROM segment_translations st
        JOIN segments ON id = id_segment
        JOIN jobs j ON j.id = st.id_job AND password = '" . $db->escape( $jPassword ) . "'

            WHERE st.id_job = " . (int)$jid . "
            AND show_in_cattool = 1
            AND suggestion_source in ('MT','MT-')
";
    try {
        $results = $db->fetch_array( $sql );
    } catch ( PDOException $e ) {
        Log::doJsonLog( $e->getMessage() );
        return $e->getCode() * -1;
    }

    return $results;
}

function getTMForTMXExport( $jid, $jPassword ) {
    //TODO: delete this function and put it in a DAO
    $db        = Database::obtain();
    $jPassword = $db->escape( $jPassword );

    $sql = "
        SELECT id_segment, st.id_job, '' as filename, segment, suggestion as translation,
        IF( st.status IN ('" . Constants_TranslationStatus::STATUS_TRANSLATED . "','" .
            Constants_TranslationStatus::STATUS_APPROVED . "'), translation_date, jobs.create_date ) as translation_date,
            st.status, suggestions_array, jobs.tm_keys, id_customer
        FROM segment_translations st
        JOIN segments ON id = id_segment
        JOIN jobs ON jobs.id = st.id_job AND password = '" . $db->escape( $jPassword ) . "'
        JOIN projects ON jobs.id_project = projects.id
            WHERE st.id_job = " . (int)$jid . "
            AND show_in_cattool = 1
            AND suggestion_source is not null
            AND ( suggestion_source = 'TM' OR suggestion_source not in ( 'MT', 'MT-' ) )
";

    try {
        $results = $db->fetch_array( $sql );
    } catch ( PDOException $e ) {
        Log::doJsonLog( $e->getMessage() );
        return $e->getCode() * -1;
    }

    foreach ( $results as $key => $value ) {

        //we already extracted a 100% match by definition
        if ( in_array( $value[ 'status' ], [
                        Constants_TranslationStatus::STATUS_TRANSLATED,
                        Constants_TranslationStatus::STATUS_APPROVED
                ]
        )
        ) {
            continue;
        }

        $suggestions_array = json_decode( $value[ 'suggestions_array' ] );
        foreach ( $suggestions_array as $_k => $_sugg ) {

            //we want the highest value of TM and we must exclude the MT
            if ( strpos( $_sugg->created_by, 'MT' ) !== false ) {
                continue;
            }

            //override the content of the result with the fuzzy matches
            $results[ $key ][ 'segment' ]     = $_sugg->segment;
            $results[ $key ][ 'translation' ] = $_sugg->translation;
            $results[ $key ][ '_created_by' ] = 'MateCat_OmegaT_Export';

            //stop, we found the first TM value in the list
            break;

        }

        //if no TM found unset the result
        if ( !isset( $results[ $key ][ '_created_by' ] ) ) {
            unset( $results[ $key ] );
        }

    }

    return $results;
}

/**
 * This function propagates the translation to every identical sources in the chunk/job
 *
 * @param $params
 * @param $job_data
 * @param $_idSegment
 *
 * @throws Exception
 * @return int
 */
function propagateTranslation( $params, $job_data, $_idSegment, Projects_ProjectStruct $project ) {

    $db = Database::obtain();

    if ( $project->getWordCountType() == Projects_MetadataDao::WORD_COUNT_RAW ) {
        $sum_sql = "SUM(segments.raw_word_count)";
    } else {
        $sum_sql = "SUM( IF( match_type != 'ICE', eq_word_count, segments.raw_word_count ) )";
    }

    /**
     * We want to avoid that a translation overrides a propagation,
     * so we have to set an additional status when the requested status to propagate is TRANSLATE
     */
    $additional_status = '';
    if ( $params[ 'status' ] == Constants_TranslationStatus::STATUS_TRANSLATED ) {
        $additional_status = "AND status != '" . Constants_TranslationStatus::STATUS_APPROVED . "'
";
    }

    /**
     * Sum the word count grouped by status, so that we can later update the count on jobs table.
     * We only count segments with status different than the current, because we don't need to update
     * the count for the same status.
     *
     */
    $queryTotals = "
           SELECT $sum_sql as total, COUNT(id_segment)as countSeg, status

           FROM segment_translations
              INNER JOIN  segments
              ON segments.id = segment_translations.id_segment
           WHERE id_job = {$params['id_job']}
           AND segment_translations.segment_hash = '" . $params[ 'segment_hash' ] . "'
           AND id_segment BETWEEN {$job_data['job_first_segment']} AND {$job_data['job_last_segment']}
           AND id_segment != $_idSegment
           AND status != '{$params['status']}'
           $additional_status
           GROUP BY status
    ";


    try {
        $totals = $db->fetch_array( $queryTotals );
    } catch ( PDOException $e ) {
        throw new Exception( "Error in counting total words for propagation: " . $e->getCode() . ": " . $e->getMessage()
                . "\n" . $queryTotals . "\n" . var_export( $params, true ),
                -$e->getCode() );
    }

    $dao = new Translations_SegmentTranslationDao();
    try {
        $segmentsForPropagation = $dao->getSegmentsForPropagation( [
                'id_segment'        => $_idSegment,
                'job_first_segment' => $job_data[ 'job_first_segment' ],
                'job_last_segment'  => $job_data[ 'job_last_segment' ],
                'segment_hash'      => $params[ 'segment_hash' ],
                'id_job'            => $params[ 'id_job' ]
        ], $params[ 'status' ] );
    } catch ( PDOException $e ) {
        throw new Exception(
                sprintf( "Error in querying segments for propagation: %s: %s ", $e->getCode(), $e->getMessage() ),
                -$e->getCode()
        );
    }

    $propagated_ids = [];

    if ( !empty( $segmentsForPropagation ) ) {

        $propagated_ids = array_map( function ( Translations_SegmentTranslationStruct $translation ) {
            return $translation->id_segment;
        }, $segmentsForPropagation );

        try {

            $place_holders_fields = [];
            foreach ( $params as $key => $value ) {
                $place_holders_fields[] = "$key = ?";
            }
            $place_holders_fields = implode( ",", $place_holders_fields );
            $place_holders_id     = implode( ',', array_fill( 0, count( $propagated_ids ), '?' ) );

            $values = array_merge(
                    array_values( $params ),
                    [ $params[ 'id_job' ] ],
                    $propagated_ids
            );

            $propagationSql = "
                  UPDATE segment_translations SET $place_holders_fields
                  WHERE id_job = ? AND id_segment IN ( $place_holders_id )
            ";

            $pdo  = $db->getConnection();
            $stmt = $pdo->prepare( $propagationSql );

            $stmt->execute( $values );

        } catch ( PDOException $e ) {
            throw new Exception( "Error in propagating Translation: " . $e->getCode() . ": " . $e->getMessage()
                    . "\n" .
                    $propagationSql
                    . "\n"
                    . var_export( $params, true )
                    . "\n"
                    . var_export( $propagated_ids, true )
                    . "\n",
                    -$e->getCode() );
        }

    }

    return [ 'totals' => $totals, 'propagated_ids' => $propagated_ids, 'propagated_segments' => $segmentsForPropagation ];
}

/**
 * Inefficient function for high traffic requests like setTranslation
 *
 * Leave untouched for getSegmentsController, split job recalculation
 * because of file level granularity in payable words
 *
 * @param      $id_job
 * @param null $id_file
 * @param null $jPassword
 *
 * @return array
 *
 */
function getStatsForJob( $id_job, $id_file = null, $jPassword = null ) {

    $query = "
        select
		j.id,
		SUM(
				IF( st.match_type = 'ICE' OR st.eq_word_count IS NULL, s.raw_word_count, st.eq_word_count )
		   ) as TOTAL,
		SUM(
				IF(
					st.status IS NULL OR
					st.status='NEW',
					IF( st.match_type = 'ICE' OR st.eq_word_count IS NULL, s.raw_word_count, st.eq_word_count ),0 )
		   ) as NEW,
		SUM(
				IF( 
					st.status IS NULL OR st.status='DRAFT' OR st.status='NEW',
					IF( st.match_type = 'ICE' OR st.eq_word_count IS NULL, s.raw_word_count, st.eq_word_count ),0 )
		   ) as DRAFT,
		SUM(
				IF( st.status='TRANSLATED', IF( st.match_type = 'ICE' OR st.eq_word_count IS NULL, s.raw_word_count, st.eq_word_count ),0 )
		   ) as TRANSLATED,
           
		SUM(
				IF(st.status='APPROVED', IF( st.match_type = 'ICE' OR st.eq_word_count IS NULL, s.raw_word_count, st.eq_word_count ),0 )
		   ) as APPROVED,
		SUM(
				IF(st.status='REJECTED', IF( st.match_type = 'ICE' OR st.eq_word_count IS NULL, s.raw_word_count, st.eq_word_count ),0 )
		   ) as REJECTED
			FROM jobs AS j
			INNER JOIN files_job as fj on j.id=fj.id_job
			INNER join segments as s on fj.id_file=s.id_file
			LEFT join segment_translations as st on s.id=st.id_segment and st.id_job=j.id
			WHERE j.id = $id_job
			AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
			";

    $db = Database::obtain();

    if ( !empty( $jPassword ) ) {
        $query .= " and j.password = '" . $db->escape( $jPassword ) . "'";
    }

    if ( !empty( $id_file ) ) {
        $query .= " and fj.id_file = " . intval( $id_file );
    }

    $results = $db->fetch_array( $query );

    return $results;
}

function getLastSegmentIDs( $id_job ) {

    // Force Index guarantee that the optimizer will not choose translation_date and scan the full table for new jobs.
    $query = "
		SELECT id_segment
		FROM segment_translations FORCE INDEX (id_job) 
		WHERE id_job = $id_job
		AND `status` IN ( 'TRANSLATED', 'APPROVED' )
		ORDER BY translation_date DESC LIMIT 10
		";

    $db = Database::obtain();
    try {
        //sometimes we can have broken projects in our Database that are not related to a job id
        //the query that extract the projects info returns a null job id for these projects, so skip the exception
        $results = $db->fetch_array( $query );
    } catch ( Exception $e ) {
        $results = null;
    }

    return $results;
}

function getEQWLastHour( $id_job, $estimation_seg_ids ) {

    /**
     * If the translator translated the last ten segments in less than 1 hour
     * In the cattool there will be the calculation of word per hour in the footer bar
     *
     */
    $query = "
            SELECT SUM(IF(Ifnull(st.eq_word_count, 0) = 0, raw_word_count,
                   st.eq_word_count)),
                   Min(translation_date),
                   Max(translation_date),
                   IF(Unix_timestamp(Max(translation_date)) - Unix_timestamp(Min(translation_date)) > 3600
                      OR Count(*) < 10, 0, 1) AS data_validity,

                   Round(SUM(IF(Ifnull(st.eq_word_count, 0) = 0, raw_word_count,
                         st.eq_word_count)) /
                               ( Unix_timestamp(Max(translation_date)) -
                                 Unix_timestamp(Min(translation_date)) ) * 3600) AS words_per_hour,
                   Count(*)
            FROM   segment_translations st
                   inner join segments ON id = st.id_segment
            WHERE  status IN ( 'TRANSLATED', 'APPROVED' )
                   AND id_job = $id_job
                   AND id_segment IN ( $estimation_seg_ids )
    ";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}


function insertFilesJob( $id_job, $id_file ) {
    $data              = [];
    $data[ 'id_job' ]  = (int)$id_job;
    $data[ 'id_file' ] = (int)$id_file;

    $db = Database::obtain();
    $db->insert( 'files_job', $data );
}

/**
 *
 * REALLY HEAVY
 *
 * @param $pid
 *
 * @return array|int|mixed
 */
function getProjectStatsVolumeAnalysis( $pid ) {

    $query = "SELECT
		st.id_job AS jid,
		j.password as jpassword,
		st.id_segment AS sid,
		s.id_file,
		f.filename,
		s.raw_word_count,
		st.suggestion_source,
		st.suggestion_match,
		st.eq_word_count,
		st.standard_word_count,
		st.match_type,
		p.status_analysis,
		p.fast_analysis_wc,
		p.tm_analysis_wc,
		p.standard_analysis_wc,
		st.tm_analysis_status AS st_status_analysis,
		st.locked as translated
			FROM
			segment_translations AS st
			JOIN
			segments AS s ON st.id_segment = s.id
			JOIN
			jobs AS j ON j.id = st.id_job
			JOIN
			projects AS p ON p.id = j.id_project
			JOIN
			files f ON s.id_file = f.id
			WHERE
			p.id = $pid
			AND p.status_analysis IN ('NEW' , 'FAST_OK', 'DONE')
			AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
			AND ( st.eq_word_count != 0  OR s.raw_word_count != 0 )
			ORDER BY j.id, j.job_last_segment
			";

    $db = Database::obtain();
    try {
        $results = $db->fetch_array( $query );
    } catch ( PDOException $e ) {
        Log::doJsonLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $results;
}

/**
 * This function is heavy, use but only if it is necessary
 *
 * TODO cached
 *
 * ( Used in TMAnalysisWorker and FastAnalysis )
 *
 * @param $pid
 *
 * @return array
 */
function getProjectSegmentsTranslationSummary( $pid ) {
    $db = Database::obtain();

    //TOTAL and eq_word should be equals BUT
    //tm Analysis can fail on some rows because of external service nature, so use TOTAL field instead of eq_word
    //to set the global word counter in job
    //Ref: jobs.new_words
    $query = "SELECT
		id_job,
		password,
		SUM(eq_word_count) AS eq_wc,
		SUM(standard_word_count) AS st_wc
        , SUM( IF( IFNULL( eq_word_count, -1 ) = -1, raw_word_count, eq_word_count) ) as TOTAL
        , COUNT( s.id ) AS project_segments,
        SUM(
            CASE
                WHEN st.standard_word_count != 0 THEN IF( st.tm_analysis_status = 'DONE', 1, 0 )
                WHEN st.standard_word_count = 0 THEN 1
            END
        ) AS num_analyzed
        FROM segment_translations st
        JOIN segments s ON s.id = id_segment
        INNER JOIN jobs j ON j.id=st.id_job
        WHERE j.id_project = $pid
        AND st.locked = 0
        AND match_type != 'ICE'
        GROUP BY id_job WITH ROLLUP";
    try {
        //Needed to address the query to the master database if exists
        \Database::obtain()->begin();

        $results = $db->fetch_array( $query );
        $db->getConnection()->commit();
    } catch ( PDOException $e ) {
        Log::doJsonLog( $e->getMessage() );

        return $e->getCode() * -1;
    }

    return $results;
}

