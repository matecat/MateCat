<?php

function getArrayOfSuggestionsJSON( $id_segment ) {
    $query   = "select suggestions_array from segment_translations where id_segment=$id_segment";
    $db      = Database::obtain();
    $results = $db->query_first( $query );

    return $results[ 'suggestions_array' ];
}

/**
 * @param      $id_job
 * @param null $password
 *
 * @return array
 *
 * @deprecated Substitute this with Jobs_JobDao
 */
function getJobData( $id_job, $password = null ) {

    $fields = [
            'id',
            'id_project',
            'source',
            'target',
            'id_mt_engine',
            'id_tms',
            'id_translator',
            'tm_keys',
            'status_owner AS status',
            'status_owner',
            'password',
            'job_first_segment',
            'job_last_segment',
            'create_date',
            'owner',
            'new_words',
            'draft_words',
            'translated_words',
            'approved_words',
            'rejected_words',
            'subject',
            'payable_rates',
            'total_time_to_edit',
            'avg_post_editing_effort'
    ];

    $query = "SELECT " . implode( ', ', $fields ) . " FROM jobs WHERE id = %u";

    if ( !empty( $password ) ) {
        $query .= " AND password = '%s' ";
    }

    $query   = sprintf( $query, $id_job, $password );
    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    if ( empty( $password ) ) {
        return $results;
    }

    return $results[ 0 ];
}

/**
 * @param $job_id       int     The job id
 * @param $job_password string  The job password
 * @param $tmKeysString string  A json_encoded array of TmKeyManagement_TmKeyStruct objects
 *
 * @return int|null Returns null if everything went ok, otherwise it returns the mysql error code
 *
 * @throws Exception
 */
function setJobTmKeys( $job_id, $job_password, $tmKeysString ) {
    $query = "UPDATE jobs SET tm_keys = '%s' WHERE id = %d AND password = '%s'";

    $db = Database::obtain();
    try {
        $db->query( sprintf( $query, $db->escape( $tmKeysString ), (int)$job_id, $job_password ) );
    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        throw new Exception( $e->getMessage(), -$e->getCode() );
    }
}

function getFirstSegmentOfFilesInJob( $jid ) {
    $db    = Database::obtain();
    $jid   = intval( $jid );
    $query = "SELECT DISTINCT id_file, MIN( segments.id ) AS first_segment, filename AS file_name,
                    FORMAT(
                        SUM( IF( IFNULL( st.eq_word_count, -1 ) = -1, raw_word_count, st.eq_word_count) )
                        , 0
                    ) AS TOTAL_FORMATTED
                FROM files_job
                JOIN segments USING( id_file )
                JOIN files ON files.id = id_file
                JOIN jobs ON jobs.id = files_job.id_job
                LEFT JOIN segment_translations AS st ON segments.id = st.id_segment AND st.id_job = jobs.id
                WHERE files_job.id_job = $jid
                AND segments.show_in_cattool = 1
                GROUP BY id_file, jobs.id, jobs.password";

    $results = $db->fetch_array( $query );

    return $results;
}

function getWarning( $jid, $jpassword ) {
    $db         = Database::obtain();
    $jid        = $db->escape( $jid );
    $status_new = Constants_TranslationStatus::STATUS_NEW;

    $query = "SELECT id_segment, serialized_errors_list
		FROM segment_translations
		JOIN jobs ON jobs.id = id_job AND id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
		WHERE jobs.id = $jid
		AND jobs.password = '$jpassword'
		AND segment_translations.status != '$status_new' 
		-- following is a condition on bitmask to filter by severity ERROR
		AND warning & 1 = 1 ";

    $results = $db->fetch_array( $query );

    return $results;
}

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
            -- AND segment_translations.status in ( '" . Constants_TranslationStatus::STATUS_TRANSLATED . "', '" . Constants_TranslationStatus::STATUS_APPROVED . "')
            AND show_in_cattool = 1
";

    try {
        $results = $db->fetch_array( $sql );
    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
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
        Log::doLog( $e->getMessage() );
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
        Log::doLog( $e->getMessage() );
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

function getSegmentsDownload( $jid, $password, $id_file, $no_status_new = 1 ) {
    if ( !$no_status_new ) {
        $select_translation = " st.translation ";
    } else {
        $select_translation = " if (st.status='NEW', '', st.translation) as translation ";
    }
    //$where_status ="";
    $query = "select
		f.filename, f.mime_type, s.id as sid, s.segment, s.internal_id,
		s.xliff_mrk_id as mrk_id, s.xliff_ext_prec_tags as prev_tags, s.xliff_ext_succ_tags as succ_tags,
		s.xliff_mrk_ext_prec_tags as mrk_prev_tags, s.xliff_mrk_ext_succ_tags as mrk_succ_tags,
		$select_translation, st.status, st.locked

			from jobs j 
			inner join projects p on p.id=j.id_project
			inner join files_job fj on fj.id_job=j.id
			inner join files f on f.id=fj.id_file
			inner join segments s on s.id_file=f.id
			left join segment_translations st on st.id_segment=s.id and st.id_job=j.id 
			where j.id=$jid and j.password='$password' and f.id=$id_file 

			";
    $db    = Database::obtain();
    try {
        $results = $db->fetch_array( $query );
    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }

    return $results;
}

function getSegmentsInfo( $jid, $password ) {

    $query = "select j.id as jid, j.password AS jpassword, j.id_project as pid,j.source,j.target,
		j.id_translator as tid, j.id_tms, j.id_mt_engine,
		p.id_customer as cid, j.id_translator as tid, j.status_owner as status,
		j.owner as job_owner, j.create_date, j.last_update, j.tm_keys,

		j.job_first_segment, j.job_last_segment,
		j.new_words, j.draft_words, j.translated_words, j.approved_words, j.rejected_words,

		p.create_date , fj.id_file, p.status_analysis,
		f.filename, f.mime_type

			from jobs j 
			inner join projects p on p.id=j.id_project
			inner join files_job fj on fj.id_job=j.id
			inner join files f on f.id=fj.id_file
			where j.id=$jid and j.password='$password' ";

    $db = Database::obtain();
    try {
        $results = $db->fetch_array( $query );
    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $results;
}

function getFirstSegmentId( $jid, $password ) {

    $query   = "SELECT s.id as sid
                FROM segments s
                INNER JOIN files_job fj ON s.id_file = fj.id_file
                INNER JOIN jobs j ON j.id = fj.id_job
                WHERE fj.id_job = $jid AND j.password = '$password'
                AND s.show_in_cattool=1
                ORDER BY s.id
                LIMIT 1
		";
    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

/**
 * @param        $jid
 * @param        $password
 * @param int    $step
 * @param        $ref_segment
 * @param string $where
 *
 * @return array
 * @throws Exception
 */
function getMoreSegments( $jid, $password, $step = 50, $ref_segment, $where = 'after', $options = [] ) {

    $optional_fields = null;
    if ( isset( $options[ 'optional_fields' ] ) ) {
        $optional_fields = ', ';
        $optional_fields .= implode( ', ', $options[ 'optional_fields' ] );
    }

    $queryAfter = "
                SELECT * FROM (
                    SELECT segments.id AS __sid
                    FROM segments
                    JOIN segment_translations ON id = id_segment
                    JOIN jobs ON jobs.id = id_job
                    WHERE id_job = $jid
                        AND password = '$password'
                        AND show_in_cattool = 1
                        AND segments.id > $ref_segment
                    LIMIT %u
                ) AS TT1
                ";

    $queryBefore = "
                SELECT * from(
                    SELECT  segments.id AS __sid
                    FROM segments
                    JOIN segment_translations ON id = id_segment
                    JOIN jobs ON jobs.id =  id_job
                    WHERE id_job = $jid
                        AND password = '$password'
                        AND show_in_cattool = 1
                        AND segments.id < $ref_segment
                    ORDER BY __sid DESC
                    LIMIT %u
                ) as TT2
                ";

    /*
     * This query is an union of the last two queries with only one difference:
     * the queryAfter parts differs for the equal sign.
     * Here is needed
     *
     */
    $queryCenter = "
                  SELECT * FROM ( 
                        SELECT segments.id AS __sid
                        FROM segments
                        JOIN segment_translations ON id = id_segment
                        JOIN jobs ON jobs.id = id_job
                        WHERE id_job = $jid
                            AND password = '$password'
                            AND show_in_cattool = 1
                            AND segments.id >= $ref_segment
                        LIMIT %u 
                  ) AS TT1
                  UNION
                  SELECT * from(
                        SELECT  segments.id AS __sid
                        FROM segments
                        JOIN segment_translations ON id = id_segment
                        JOIN jobs ON jobs.id =  id_job
                        WHERE id_job = $jid
                            AND password = '$password'
                            AND show_in_cattool = 1
                            AND segments.id < $ref_segment
                        ORDER BY __sid DESC
                        LIMIT %u
                  ) AS TT2
    ";

    switch ( $where ) {
        case 'after':
            $subQuery = sprintf( $queryAfter, $step * 2 );
            break;
        case 'before':
            $subQuery = sprintf( $queryBefore, $step * 2 );
            break;
        case 'center':
            $subQuery = sprintf( $queryCenter, $step, $step );
            break;
    }

    $query = "SELECT j.id AS jid,
                j.id_project AS pid,
                j.source,
                j.target,
                j.last_opened_segment,
                p.id_customer AS cid,
                j.id_translator AS tid,
                p.name AS pname,
                p.create_date,
                fj.id_file,
                f.filename,
                f.mime_type,
                s.id AS sid,
                s.segment,
                s.segment_hash,
                s.raw_word_count,
                s.internal_id,
                IF (st.status='NEW',NULL,st.translation) AS translation,
                UNIX_TIMESTAMP(st.translation_date) AS version,
                IF( st.locked AND match_type = 'ICE', 1, 0 ) AS ice_locked,
                st.status,
                COALESCE(time_to_edit, 0) AS time_to_edit,
                s.xliff_ext_prec_tags,
                s.xliff_ext_succ_tags,
                st.serialized_errors_list,
                st.warning,
                st.suggestion_match as suggestion_match,
                sts.source_chunk_lengths,
                sts.target_chunk_lengths,
                IF( ( s.id BETWEEN j.job_first_segment AND j.job_last_segment ) , 'false', 'true' ) AS readonly
                , COALESCE( autopropagated_from, 0 ) as autopropagated_from
                ,( SELECT COUNT( segment_hash )
                          FROM segment_translations
                          WHERE segment_hash = s.segment_hash
                          AND id_job =  j.id
                ) repetitions_in_chunk

                $optional_fields

                FROM jobs j
                JOIN projects p ON p.id = j.id_project
                JOIN files_job fj ON fj.id_job = j.id
                JOIN files f ON f.id = fj.id_file
                JOIN segments s ON s.id_file = f.id
                LEFT JOIN segment_translations st ON st.id_segment = s.id AND st.id_job = j.id
                LEFT JOIN segment_translations_splits sts ON sts.id_segment = s.id AND sts.id_job = j.id
                JOIN (

                  $subQuery

                ) AS TEMP ON TEMP.__sid = s.id

            WHERE j.id = $jid
            AND j.password = '$password'
            ORDER BY sid ASC
";

    $db = Database::obtain();

    try {
        $results = $db->fetch_array( $query );
    } catch ( PDOException $e ) {
        throw new Exception( __METHOD__ . " -> " . $e->getCode() . ": " . $e->getMessage() );
    }
    return $results;
}

function countThisTranslatedHashInJob( $jid, $jpassword, $sid ) {

    $db = Database::obtain();

    $isPropagationToAlreadyTranslatedAvailable = "
        SELECT COUNT(segment_hash) AS available
        FROM segment_translations
        JOIN jobs ON id_job = id AND id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
        WHERE segment_hash = (
            SELECT segment_hash FROM segments WHERE id = %u
        )
        AND id_job = %u
        AND id_segment != %u
        AND password = '%s'
        AND segment_translations.status IN( 
          '" . Constants_TranslationStatus::STATUS_TRANSLATED . "' , 
          '" . Constants_TranslationStatus::STATUS_APPROVED . "' 
        )
    ";

    $query = sprintf( $isPropagationToAlreadyTranslatedAvailable, $sid, $jid, $sid, $db->escape( $jpassword ) );

    $results = $db->query_first( $query );

//    Log::doLog($query);

    return $results;
}

function getTranslationsMismatches( $jid, $jpassword, $sid = null ) {

    $db = Database::obtain();

    $st_translated = Constants_TranslationStatus::STATUS_TRANSLATED;
    $st_approved   = Constants_TranslationStatus::STATUS_APPROVED;

    $jStructs   = Jobs_JobDao::getById( $jid );
    $filtered   = array_filter( $jStructs, function ( $item ) use ( $jpassword ) {
        return $item->password == $jpassword;
    } );
    $currentJob = array_pop( $filtered );

    if ( $sid != null ) {

        /**
         * Get all the available translations for this segment id,
         * the amount of equal translations,
         * a list of id,
         * and an editable boolean field identifying if jobs is mine or not
         *
         */
        $queryForTranslationMismatch = "
			SELECT
			translation,
			COUNT( distinct id_segment ) as TOT,
			GROUP_CONCAT( distinct id_segment ) AS involved_id,
			IF( password = '{$currentJob->password}' AND id_segment between job_first_segment AND job_last_segment, 1, 0 ) AS editable
				FROM segment_translations
				JOIN jobs ON id_job = id AND id_segment between {$jStructs[0]->job_first_segment} AND " . end( $jStructs )->job_last_segment . "
				WHERE segment_hash = (
					SELECT segment_hash FROM segments WHERE id = %u
				)
				AND segment_translations.status IN( '$st_translated' , '$st_approved' )
				AND id_job = {$jStructs[0]->id}
				AND id_segment != %u
				GROUP BY translation, id_job
		";

        $query = sprintf( $queryForTranslationMismatch, $sid, $sid );
    } else {

        /**
         * This query gets, for each hash with more than one translations available, the min id of the segments
         *
         * If we want also to check for mismatches against approved translations also,
         * we have to add the APPROVED status condition.
         *
         * But be careful, queries are much more heaviest.
         * ( Ca. 4X -> 0.01/0.02s for a job with 55k segments on a dev environment )
         *
         */
        $queryForMismatchesInJob = "
			SELECT
			COUNT( segment_hash ) AS total_sources,
			COUNT( DISTINCT translation ) AS translations_available,
			IF( password = '{$currentJob->password}', MIN( id_segment ), NULL ) AS first_of_my_job
				FROM segment_translations
				JOIN jobs ON id_job = id AND id_segment between {$currentJob->job_first_segment} AND {$currentJob->job_last_segment}
				WHERE id_job = {$currentJob->id}
				AND segment_translations.status IN( '$st_translated' , '$st_approved' )
				GROUP BY segment_hash, CONCAT( id_job, '-', password )
				HAVING translations_available > 1
		";

        $query = $queryForMismatchesInJob;
    }

    $results = $db->fetch_array( $query );

    return $results;
}

function addTranslation( Translations_SegmentTranslationStruct $translation_struct ) {

    $keys_fo_insert = [
            'id_segment', 'id_job', 'status', 'time_to_edit', 'translation', 'serialized_errors_list',
            'suggestion_position', 'warning', 'translation_date', 'version_number', 'autopropagated_from'
    ] ;

    $translation = $translation_struct->toArray( $keys_fo_insert ) ;

    $db = Database::obtain();

    $query = "INSERT INTO `segment_translations` ";

    foreach ( $translation as $key => $val ) {

        if ( $key == 'translation' ) {
            $translation[ $key ] = "'" . $db->escape( $val ) . "'";
            continue;
        }

        if ( strtolower( $val ) == 'now()' || strtolower( $val ) == 'current_timestamp()' || strtolower( $val ) == 'sysdate()' ) {
            $translation[ $key ] = "NOW()";
        } elseif ( is_numeric( $val ) ) {
            $translation[ $key ] = (float)$val;
        } elseif ( is_bool( $val ) ) {
            $translation[ $key ] = var_export( $val, true );
        } elseif ( strtolower( $val ) == 'null' || empty( $val ) ) {
            $translation[ $key ] = "NULL";
        } else {
            $translation[ $key ] = "'" . $db->escape( $val ) . "'";
        }
    }

    $query .= "(" . implode( ", ", array_keys( $translation ) ) . ") VALUES (" . implode( ", ", array_values( $translation ) ) . ")";

    $query .= "
				ON DUPLICATE KEY UPDATE
				status = {$translation['status']},
                suggestion_position = {$translation['suggestion_position']},
                serialized_errors_list = {$translation['serialized_errors_list']},
                time_to_edit = time_to_edit + VALUES( time_to_edit ),
                translation = {$translation['translation']},
                translation_date = {$translation['translation_date']},
                warning = {$translation[ 'warning' ]}";

    if ( array_key_exists( 'version_number', $translation ) ) {
        $query .= "\n, version_number = {$translation['version_number']}";
    }


    if ( isset( $translation[ 'autopropagated_from' ] ) ) {
        $query .= " , autopropagated_from = NULL";
    }

    if ( empty( $translation[ 'translation' ] ) && !is_numeric( $translation[ 'translation' ] ) ) {
        $msg = "\n\n Error setTranslationUpdate \n\n Empty translation found after DB Escape: \n\n " . var_export( array_merge( [ 'db_query' => $query ], $_POST ), true );
        Log::doLog( $msg );
        Utils::sendErrMailReport( $msg );
        throw new PDOException( $msg );
    }

//    Log::doLog( $query );

    try {
        $db->query( $query );
    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        throw new PDOException( "Error occurred storing (UPDATE) the translation for the segment {$translation['id_segment']} - Error: {$e->getCode()}" );
    }

    return $db->affected_rows;
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

    return [ 'totals' => $totals, 'propagated_ids' => $propagated_ids ];
}

function setCurrentSegmentInsert( $id_segment, $id_job, $password ) {
    $data                          = [];
    $data[ 'last_opened_segment' ] = $id_segment;

    $where = "id = $id_job AND password = '$password'";

    $db = Database::obtain();
    try {
        $affectedRows = $db->update( 'jobs', $data, $where );
    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $affectedRows;
}


/**
 * @deprecated remove this funciton and
 */

function getCurrentTranslation( $id_job, $id_segment ) {

    $query = "SELECT * FROM segment_translations WHERE id_segment = %u AND id_job = %u";
    $query = sprintf( $query, $id_segment, $id_job );

    $db      = Database::obtain();
    $results = $db->query_first( $query );

    return $results;
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

function getStatsForFile( $id_file ) {
    $db = Database::obtain();

    //SQL Injection... cast to int
    $id_file = intval( $id_file );
    $id_file = $db->escape( $id_file );

    // Old raw-wordcount
    /*
       $query = "select SUM(raw_word_count) as TOTAL, SUM(IF(status IS NULL OR status='DRAFT' OR status='NEW',raw_word_count,0)) as DRAFT, SUM(IF(status='REJECTED',raw_word_count,0)) as REJECTED, SUM(IF(status='TRANSLATED',raw_word_count,0)) as TRANSLATED, SUM(IF(status='APPROVED',raw_word_count,0)) as APPROVED from jobs j INNER JOIN files_job fj on j.id=fj.id_job INNER join segments s on fj.id_file=s.id_file LEFT join segment_translations st on s.id=st.id_segment WHERE s.id_file=" . $id_file;
     */
    $query = "SELECT SUM(IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count) ) AS TOTAL,
		SUM(IF(st.status IS NULL OR st.status='DRAFT' OR st.status='NEW',IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count),0)) AS DRAFT,
		SUM(IF(st.status='REJECTED',IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count),0)) AS REJECTED,
		SUM(IF(st.status='TRANSLATED',IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count),0)) AS TRANSLATED,
		SUM(IF(st.status='APPROVED',raw_word_count,0)) AS APPROVED FROM jobs j
			INNER JOIN files_job fj ON j.id=fj.id_job
			INNER JOIN segments s ON fj.id_file=s.id_file
			LEFT JOIN segment_translations st ON s.id=st.id_segment
			WHERE s.id_file=" . $id_file;

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

function getUpdatedTranslations( $timestamp, $first_segment, $last_segment, $id_job ) {

    $query = "SELECT id_segment as sid, status,translation from segment_translations
		WHERE
		id_segment BETWEEN $first_segment AND $last_segment
		AND translation_date > FROM_UNIXTIME($timestamp)
		AND id_job = $id_job";

    //Log::doLog($query);
    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getEditLog( $jid, $pass ) {

    $query = "SELECT
		s.id as sid,
		s.segment AS source,
		st.translation AS translation,
		st.time_to_edit AS tte,
		st.suggestion AS sug,
		st.suggestions_array AS sar,
		st.suggestion_source AS ss,
		st.suggestion_match AS sm,
		st.suggestion_position AS sp,
		st.mt_qe,
		j.id_translator AS tid,
		j.source AS source_lang,
		j.target AS target_lang,
		s.raw_word_count rwc,
		p.name as pname,
		p.id as id_project
			FROM
			jobs j 
			INNER JOIN segment_translations st ON j.id=st.id_job 
			INNER JOIN segments s ON s.id = st.id_segment
			INNER JOIN projects p on p.id=j.id_project
			WHERE
			id_job = $jid AND
			j.password = '$pass' AND
			translation IS NOT NULL AND
			st.status<>'NEW'
			AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
			ORDER BY tte DESC
			LIMIT 5000";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

/**
 * Used to get a resultset of segments id and statuses
 *
 * @param      $sid
 * @param      $jid
 * @param      $password
 * @param bool $getTranslatedInstead
 *
 * @return array
 */
function getNextSegment( $sid, $jid, $password = '', $getTranslatedInstead = false ) {

    $db = Database::obtain();

    $jid = (int)$jid;
    $sid = (int)$sid;

    $and_password = '';
    if ( !empty( $password ) ) {
        $password     = $db->escape( $password );
        $and_password = "AND jobs.password = '$password'";
    }

    if ( !$getTranslatedInstead ) {
        $translationStatus = " ( st.status IN (
                '" . Constants_TranslationStatus::STATUS_NEW . "',
                '" . Constants_TranslationStatus::STATUS_DRAFT . "',
                '" . Constants_TranslationStatus::STATUS_REJECTED . "'
            ) OR st.status IS NULL )"; //status NULL is not possible
    } else {
        $translationStatus = " st.status IN(
            '" . Constants_TranslationStatus::STATUS_TRANSLATED . "',
            '" . Constants_TranslationStatus::STATUS_APPROVED . "'
        )";
    }

    $query = "SELECT s.id, st.status
		FROM segments AS s
		JOIN files_job fj USING (id_file)
		JOIN jobs ON jobs.id = fj.id_job
		JOIN files f ON f.id = fj.id_file
		LEFT JOIN segment_translations st ON st.id_segment = s.id AND fj.id_job = st.id_job
		WHERE jobs.id = $jid AND jobs.password = '$password'
		AND $translationStatus
		$and_password
		AND s.show_in_cattool = 1
		AND s.id <> $sid
		AND s.id BETWEEN jobs.job_first_segment AND jobs.job_last_segment
		";

    $results = $db->fetch_array( $query );

    return $results;
}

/**
 * @param        $sid
 * @param        $results array The resultset from previous getNextSegment()
 * @param string $status
 *
 * @return null
 */

function fetchStatus( $sid, $results, $status = Constants_TranslationStatus::STATUS_NEW ) {

    $statusWeight = [
            Constants_TranslationStatus::STATUS_NEW        => 10,
            Constants_TranslationStatus::STATUS_DRAFT      => 10,
            Constants_TranslationStatus::STATUS_REJECTED   => 10,
            Constants_TranslationStatus::STATUS_TRANSLATED => 40,
            Constants_TranslationStatus::STATUS_APPROVED   => 50
    ];

    $nSegment = null;
    if ( isset( $results[ 0 ][ 'id' ] ) ) {
        //if there are results check for next id,
        //otherwise get the first one in the list
//        $nSegment = $results[ 0 ][ 'id' ];
        //Check if there is translated segment with $seg[ 'id' ] > $sid
        foreach ( $results as $seg ) {
            if ( $seg[ 'status' ] == null ) {
                $seg[ 'status' ] = Constants_TranslationStatus::STATUS_NEW;
            }
            if ( $seg[ 'id' ] > $sid && $statusWeight[ $seg[ 'status' ] ] == $statusWeight[ $status ] ) {
                $nSegment = $seg[ 'id' ];
                break;
            }
        }
        // If there aren't transleted segments in the next elements -> check starting from the first
        if ( !$nSegment ) {
            foreach ( $results as $seg ) {
                if ( $seg[ 'status' ] == null ) {
                    $seg[ 'status' ] = Constants_TranslationStatus::STATUS_NEW;
                }
                if ( $statusWeight[ $seg[ 'status' ] ] == $statusWeight[ $status ] ) {
                    $nSegment = $seg[ 'id' ];
                    break;
                }
            }
        }

    }

    return $nSegment;

}

//function insertProject( $id_customer, $project_name, $analysis_status, $password, $ip = 'UNKNOWN' ) {
function insertProject( ArrayObject $projectStructure ) {
    $data                        = [];
    $data[ 'id' ]                = $projectStructure[ 'id_project' ];
    $data[ 'id_customer' ]       = $projectStructure[ 'id_customer' ];
    $data[ 'id_team' ]           = $projectStructure[ 'id_team' ];
    $data[ 'name' ]              = $projectStructure[ 'project_name' ];
    $data[ 'create_date' ]       = $projectStructure[ 'create_date' ];
    $data[ 'status_analysis' ]   = $projectStructure[ 'status' ];
    $data[ 'password' ]          = $projectStructure[ 'ppassword' ];
    $data[ 'pretranslate_100' ]  = $projectStructure[ 'pretranslate_100' ];
    $data[ 'remote_ip_address' ] = empty( $projectStructure[ 'user_ip' ] ) ? 'UNKNOWN' : $projectStructure[ 'user_ip' ];
    $data[ 'id_assignee' ]       = $projectStructure[ 'id_assignee' ];
    $data[ 'instance_id' ]       = !is_null( $projectStructure[ 'instance_id' ] ) ? $projectStructure[ 'instance_id' ] : null;
    $data[ 'due_date' ]          = !is_null( $projectStructure[ 'due_date' ] ) ? $projectStructure[ 'due_date' ] : null;

    $db = Database::obtain();
    $db->begin();
    $projectId = $db->insert( 'projects', $data );
    $project   = Projects_ProjectDao::findById( $projectId );
    $db->commit();
    return $project;

}

//never used email , first_name and last_name
//function insertTranslator( $user, $pass, $api_key, $email = '', $first_name = '', $last_name = '' ) {
function insertTranslator( ArrayObject $projectStructure ) {
    //get link
    $db = Database::obtain();
    //if this user already exists, return without inserting again ( do nothing )
    //this is because we allow to start a project with the bare key

    $private_tm_key = ( is_array( $projectStructure[ 'private_tm_key' ] ) ) ?
            $projectStructure[ 'private_tm_key' ][ 0 ][ 'key' ] :
            $projectStructure[ 'private_tm_key' ];

    $query   = "SELECT username FROM translators WHERE mymemory_api_key='" . $db->escape( $private_tm_key ) . "'";
    $user_id = $db->query_first( $query );
    $user_id = $user_id[ 'username' ];

    if ( empty( $user_id ) ) {

        $data                       = [];
        $data[ 'username' ]         = $projectStructure[ 'private_tm_user' ];
        $data[ 'email' ]            = '';
        $data[ 'password' ]         = $projectStructure[ 'private_tm_pass' ];
        $data[ 'first_name' ]       = '';
        $data[ 'last_name' ]        = '';
        $data[ 'mymemory_api_key' ] = $private_tm_key;

        $db->insert( 'translators', $data );

        $user_id = $projectStructure[ 'private_tm_user' ];

    }

    $projectStructure[ 'private_tm_user' ] = $user_id;
}

function insertFile( ArrayObject $projectStructure, $file_name, $mime_type, $fileDateSha1Path ) {
    $data                         = [];
    $data[ 'id_project' ]         = $projectStructure[ 'id_project' ];
    $data[ 'filename' ]           = $file_name;
    $data[ 'source_language' ]    = $projectStructure[ 'source_language' ];
    $data[ 'mime_type' ]          = $mime_type;
    $data[ 'sha1_original_file' ] = $fileDateSha1Path;

    $db = Database::obtain();

    try {
        $idFile = $db->insert( 'files', $data );
    } catch ( PDOException $e ) {
        Log::doLog( "Database insert error: {$e->getMessage()} " );
        throw new Exception( "Database insert file error: {$e->getMessage()} ", -$e->getCode() );
    }

    return $idFile;
}

function insertFilesJob( $id_job, $id_file ) {
    $data              = [];
    $data[ 'id_job' ]  = (int)$id_job;
    $data[ 'id_file' ] = (int)$id_file;

    $db = Database::obtain();
    $db->insert( 'files_job', $data );
}


function getProject( $pid ) {
    $db    = Database::obtain();
    $query = "SELECT * FROM projects WHERE id = %u";
    $query = sprintf( $query, $pid );
    $res   = $db->fetch_array( $query );

    return $res;
}

function getProjectJobData( $pid ) {

    $db = Database::obtain();

    $query = "SELECT projects.id AS pid,
		projects.name AS pname,
		projects.password AS ppassword,
		projects.status_analysis,
		projects.standard_analysis_wc,
		projects.fast_analysis_wc,
		projects.tm_analysis_wc,
		projects.create_date,
		jobs.id AS jid,
		jobs.password AS jpassword,
		job_first_segment,
		job_last_segment,
		CONCAT( jobs.id , '-', jobs.password ) AS jid_jpassword,
		CONCAT( jobs.source, '|', jobs.target ) AS lang_pair,
		CONCAT( projects.name, '/', jobs.source, '-', jobs.target, '/', jobs.id , '-', jobs.password ) AS job_url,
		status_owner
			FROM jobs
			JOIN projects ON jobs.id_project = projects.id
			WHERE projects.id = %u
			ORDER BY jid, job_last_segment
			";

    $query = sprintf( $query, $pid );
    $res   = $db->fetch_array( $query );

    return $res;
}

/**
 *
 * Very bound to the query SQL which is used to retrieve project jobs or just the count
 * of records for the pagination and other stuff in manage page.
 *
 * @param $search_in_pname
 * @param $search_source
 * @param $search_target
 * @param $search_status
 * @param $search_only_completed
 *
 * @return array
 */
function conditionsForProjectsQuery(
        $search_in_pname, $search_source, $search_target,
        $search_status, $search_only_completed
) {
    $conditions = [];
    $data       = [];

    if ( $search_in_pname ) {
        $conditions[]           = " p.name LIKE :project_name ";
        $data[ 'project_name' ] = "%$search_in_pname%";
    }

    if ( $search_source ) {
        $conditions[]     = " j.source = :source ";
        $data[ 'source' ] = $search_source;
    }

    if ( $search_target ) {
        $conditions[]     = " j.target = :target  ";
        $data[ 'target' ] = $search_target;
    }

    if ( $search_status ) {
        $conditions[]           = " j.status_owner = :owner_status ";
        $data[ 'owner_status' ] = $search_status;
    }

    if ( $search_only_completed ) {
        $conditions[] = " j.completed = 1 ";
    }


    return [ $conditions, $data ];
}

/**
 * @param Users_UserStruct  $user
 * @param                   $start                int
 * @param                   $step                 int
 * @param                   $search_in_pname      string
 * @param                   $search_source        string
 * @param                   $search_target        string
 * @param                   $search_status        string
 * @param                   $search_onlycompleted bool
 * @param                   $project_id           int
 *
 * @param \Teams\TeamStruct $team
 *
 * @return array
 */
function getProjects( Users_UserStruct $user, $start, $step,
                      $search_in_pname, $search_source, $search_target,
                      $search_status, $search_only_completed,
                      $project_id,
                      \Teams\TeamStruct $team = null,
                      Users_UserStruct $assignee = null,
                      $no_assignee

) {

    list( $conditions, $data ) = conditionsForProjectsQuery(
            $search_in_pname, $search_source, $search_target,
            $search_status, $search_only_completed
    );

    if ( $project_id ) {
        $conditions[]         = " p.id = :project_id ";
        $data[ 'project_id' ] = $project_id;
    }

    if ( !is_null( $team ) ) {
        $conditions[]       = " p.id_team = :id_team ";
        $data [ 'id_team' ] = $team->id;
    }

    if ( $no_assignee ) {
        $conditions[] = " p.id_assignee IS NULL ";
    } elseif ( !is_null( $assignee ) ) {
        $conditions[]           = " p.id_assignee = :id_assignee ";
        $data [ 'id_assignee' ] = $assignee->uid;
    }

    $where_query = implode( " AND ", $conditions );

    $projectsQuery =
            "SELECT p.id
                FROM projects p
                INNER JOIN jobs j ON j.id_project = p.id
                WHERE $where_query
                GROUP BY 1
                ORDER BY 1 DESC
                LIMIT $start, $step 
            ";

    $stmt = Database::obtain()->getConnection()->prepare( $projectsQuery );
    $stmt->execute( $data );

    return array_map( function ( $d ) {
        return $d[ 'id' ];
    }, $stmt->fetchAll( PDO::FETCH_ASSOC ) );

}

function getProjectsNumber( Users_UserStruct $user, $search_in_pname, $search_source, $search_target, $search_status,
                            $search_only_completed,
                            \Teams\TeamStruct $team = null,
                            Users_UserStruct $assignee = null,
                            $no_assignee = false
) {

    list( $conditions, $data ) = conditionsForProjectsQuery(
            $search_in_pname, $search_source, $search_target,
            $search_status, $search_only_completed
    );


    $query = " SELECT COUNT( distinct id_project ) AS c
    FROM projects p
    INNER JOIN jobs j ON j.id_project = p.id

    ";


    if ( !is_null( $team ) ) {
        $conditions[]       = " p.id_team = :id_team ";
        $data [ 'id_team' ] = $team->id;
    }

    if ( $no_assignee ) {
        $conditions[] = " p.id_assignee IS NULL ";
    } elseif ( !is_null( $assignee ) ) {
        $conditions[]           = " p.id_assignee = :id_assignee ";
        $data [ 'id_assignee' ] = $assignee->uid;
    }

    if ( count( $conditions ) ) {
        $query = $query . " AND " . implode( " AND ", $conditions );
    }

    $stmt = Database::obtain()->getConnection()->prepare( $query );
    $stmt->execute( $data );

    return $stmt->fetchAll();
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
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $results;
}

function initializeWordCount( WordCount_Struct $wStruct ) {

    $db = Database::obtain();

    $data                       = [];
    $data[ 'new_words' ]        = $wStruct->getNewWords();
    $data[ 'draft_words' ]      = $wStruct->getDraftWords();
    $data[ 'translated_words' ] = $wStruct->getTranslatedWords();
    $data[ 'approved_words' ]   = $wStruct->getApprovedWords();
    $data[ 'rejected_words' ]   = $wStruct->getRejectedWords();

    $where = " id = " . (int)$wStruct->getIdJob() . " AND password = '" . $db->escape( $wStruct->getJobPassword() ) . "'";
    try {
        $db->update( 'jobs', $data, $where );
    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $db->affected_rows;
}

/**
 * Update the word count for the job
 *
 * We perform an update in join with jobs table
 * because we want to update the word count only for the current chunk
 *
 * Update the status of segment_translation is needed to avoid duplicated calls
 * ( The second call fails for status condition )
 *
 * @param WordCount_Struct $wStruct
 *
 * @return int
 */
function updateWordCount( WordCount_Struct $wStruct ) {

    $db = Database::obtain();

    //Update in Transaction
    $query = "UPDATE jobs AS j SET
                new_words = new_words + " . $wStruct->getNewWords() . ",
                draft_words = draft_words + " . $wStruct->getDraftWords() . ",
                translated_words = translated_words + " . $wStruct->getTranslatedWords() . ",
                approved_words = approved_words + " . $wStruct->getApprovedWords() . ",
                rejected_words = rejected_words + " . $wStruct->getRejectedWords() . "
                  WHERE j.id = " . (int)$wStruct->getIdJob() . "
                  AND j.password = '" . $db->escape( $wStruct->getJobPassword() ) . "'";

    try {
        $db->query( $query );
    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    $affectedRows = $db->affected_rows;
    Log::doLog( "Affected: " . $affectedRows . "\n" );
    return $affectedRows;
}

/**
 * @param $job_id            int
 * @param $jobPass           string
 * @param $segmentTimeToEdit int
 *
 * @return int|mixed
 */
function updateTotalTimeToEdit( $job_id, $jobPass, $segmentTimeToEdit ) {
    $db = Database::obtain();

    //Update in Transaction
    $query = "UPDATE jobs AS j SET
                  total_time_to_edit = coalesce( total_time_to_edit, 0 ) + %d
               WHERE j.id = %d
               AND j.password = '%s'";

    try {
        $db->query(
                sprintf(
                        $query,
                        (int)$segmentTimeToEdit,
                        (int)$job_id,
                        $jobPass
                )
        );

    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    $affectedRows = $db->affected_rows;
    Log::doLog( "Affected: " . $affectedRows );
    return $affectedRows;
}

function changeTmWc( $pid, $pid_eq_words, $pid_standard_words ) {
    // query  da incorporare nella changeProjectStatus
    $db                             = Database::obtain();
    $data                           = [];
    $data[ 'tm_analysis_wc' ]       = $pid_eq_words;
    $data[ 'standard_analysis_wc' ] = $pid_standard_words;
    $where                          = " id =$pid";
    try {
        $affectedRows = $db->update( 'projects', $data, $where );
    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $affectedRows;
}

function changeProjectStatus( $pid, $status, $if_status_not = [] ) {

    $db = Database::obtain();

    $data[ 'status_analysis' ] = $db->escape( $status );
    $where                     = "id = " . (int)$pid;

    if ( !empty( $if_status_not ) ) {
        foreach ( $if_status_not as $v ) {
            $where .= " and status_analysis <> '" . $db->escape( $v ) . "' ";
        }
    }
    try {

        $affectedRows = $db->update( 'projects', $data, $where );
        Projects_ProjectDao::destroyCacheById( $pid );

    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $affectedRows;
}

/**
 * @param      $res
 * @param      $id
 * @param      $status
 * @param null $jPassword
 *
 * @deprecated
 *
 */
function updateJobsStatus( $res, $id, $status, $jPassword = null ) {

    $conn = Database::obtain()->getConnection();

    if ( $res == "prj" ) {

        $query = "UPDATE jobs SET status_owner = :status WHERE id_project = :id_project";
        $stmt = $conn->prepare( $query );
        $stmt->execute( ['status' => $status, 'id_project' => $id] );


        //Works on the basis that MAX( id_segment ) is the same for ALL Jobs in the same Project
        // furthermore, we need a random ID so, don't worry about MySQL stupidity on random MAX
        //example: http://dev.mysql.com/doc/refman/5.0/en/example-maximum-column-group-row.html
        $select_max_id = "
				SELECT max(id_segment) AS id_segment
				FROM segment_translations
				JOIN jobs ON id_job = id
				WHERE id_project = :id_project";

        $stmt = $conn->prepare( $select_max_id );
        $stmt->execute( ['id_project' => $id] );
        $project_id = $id;

    } else {

        $query = "update jobs set status_owner = :status where id = :id_job and password = :password ";
        $stmt = $conn->prepare( $query );
        $stmt->execute( ['status' => $status, 'id_job' => $id, 'password' => $jPassword] );

        $select_max_id = "
			SELECT max(id_segment) as id_segment
			FROM segment_translations
			JOIN jobs ON id_job = id
			WHERE id = :id_job
			AND password = :password";

        $stmt = $conn->prepare( $select_max_id );
        $stmt->execute( ['id_job' => $id, 'password' => $jPassword] );

        $job = Jobs_JobDao::getById($id)[0];

        $project_id = $job->id_project;
    }

    ( new Jobs_JobDao )->destroyCacheByProjectId($project_id);


    $id_segment  = $stmt->fetchColumn();

    $query_for_translations = "
			UPDATE segment_translations
			SET translation_date = NOW()
			WHERE id_segment = :id_segment";

    $stmt = $conn->prepare( $query_for_translations );
    $stmt->execute( ['id_segment' => $id_segment] );

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
        Log::doLog( $e->getMessage() );

        return $e->getCode() * -1;
    }

    return $results;
}


function setJobCompleteness( $jid, $is_completed ) {
    $db    = Database::obtain();
    $query = "update jobs set completed=$is_completed where id=$jid";
    try {
        $result = $db->query_first( $query );
    } catch ( PDOException $e ) {
        Log::doLog( $query );
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $result;
}

/**
 * Given an array of job IDs, this function returns the IDs of archivable jobs
 *
 * USE IN CRON
 *
 * @param array $jobs
 *
 * @return array
 */
function getArchivableJobs( $jobs = [] ) {
    $db    = Database::obtain();
    $query =
            "
        SELECT j.id, j.password , SBS.translation_date
            FROM jobs j
            JOIN (
                SELECT MAX( translation_date ) AS translation_date, id_job
                FROM segment_translations
                    WHERE id_job IN( %s )
                    GROUP BY id_job
                ) AS SBS
                ON SBS.id_job = j.id
                AND IFNULL( SBS.translation_date, DATE( '1970-01-01' ) ) < ( curdate() - INTERVAL " . INIT::JOB_ARCHIVABILITY_THRESHOLD . " DAY  )
           WHERE
                j.status_owner = '" . Constants_JobStatus::STATUS_ACTIVE . "'
                AND j.create_date < ( curdate() - INTERVAL " . INIT::JOB_ARCHIVABILITY_THRESHOLD . " DAY )
                AND j.status = '" . Constants_JobStatus::STATUS_ACTIVE . "'
           GROUP BY j.id, j.password";

    try {
        $results = $db->fetch_array(
                sprintf(
                        $query,
                        implode( ", ", $jobs )
                )
        );
    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }

    return $results;
}

function getLastTranslationDate( $jid ) {
    $query = "SELECT
                IFNULL( MAX(translation_date), DATE('1970-01-01') ) AS last_translation_date
                FROM segment_translations
                WHERE id_job = %u";
    $db    = Database::obtain();
    $res   = $db->query_first( sprintf( $query, $jid ) );

    return $res[ 'last_translation_date' ];
}

function getMaxJobUntilDaysAgo( $days = INIT::JOB_ARCHIVABILITY_THRESHOLD ) {

    $last_id_query = "
            SELECT
                MAX(id) AS max
            FROM jobs
            WHERE create_date < ( curdate() - INTERVAL " . INIT::JOB_ARCHIVABILITY_THRESHOLD . " DAY )
            AND status_owner = '" . Constants_JobStatus::STATUS_ACTIVE . "'";

    $db      = Database::obtain();
    $last_id = $db->query_first( $last_id_query );
    $last_id = (int)$last_id[ 'max' ];

    return $last_id;
}

function batchArchiveJobs( $jobs = [], $days = INIT::JOB_ARCHIVABILITY_THRESHOLD ) {

    $query_archive_jobs = "
        UPDATE jobs
            SET status_owner = '" . Constants_JobStatus::STATUS_ARCHIVED . "'
            WHERE %s
            AND last_update < ( curdate() - INTERVAL %u DAY )";

    $tuple_of_double_indexes = [];
    foreach ( $jobs as $job ) {
        $tuple_of_double_indexes[] = sprintf( "( id = %u AND password = '%s' )", $job[ 'id' ], $job[ 'password' ] );
    }

    $q_archive = sprintf(
            $query_archive_jobs,
            implode( " OR ", $tuple_of_double_indexes ),
            $days
    );

    $db = Database::obtain();
    try {
        $db->query( $q_archive );
    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $db->affected_rows;
}
