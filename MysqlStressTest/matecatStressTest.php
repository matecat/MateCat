<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 12/02/16
 * Time: 11.18
 *
 * for i in {1..70}; do  nohup php ../matecat/cattool/matecatStressTest.php > stressTest{$i}.log &  done
 *
 */
$maxIdJob        = 274993;

$getSegmentsProb = 100;
$getWarningProb  = 100;
$setSegmentProb  = 100;

$user = "matecat";
$pass = "matecat01";
$host = "52.29.195.208";
$db   = "matecat_sandbox";
$con  = new PDO( "mysql:host=" . $host . ";dbname=" . $db, $user, $pass );

while ( true ) {
    $sleepTime = rand(10000, 2000000);

    $idJob   = rand( $maxIdJob - 50000, $maxIdJob );
    $jobData = getJobData( $con, $idJob );
    $jobData = $jobData[ 0 ];

    $jobPass = $jobData[ 'password' ];

    logMsg( "Picked job " . $idJob . "-" . $jobPass );
    $segmentID = rand(
            intval( $jobData[ 'job_first_segment' ] ),
            intval( $jobData[ 'job_last_segment' ] )
    );

    logMsg( "Picked segment " . $segmentID );

    logMsg( "Starting getSegments.." );
    $segData = getMoreSegments( $con, $idJob, $jobPass, 50, $segmentID );
    logMsg( "Done getSegments" );

    if ( count( $segData ) ) {
        $segData     = $segData[ rand( 0, count( $segData ) - 1 ) ];
        $translation = $segData[ 'translation' ];
        $status      = $segData[ 'status' ];


        $dice = rand( 1, 100 );
        if ( $dice < $getWarningProb ) {
            logMsg( "Starting getWarning.. " );
            $getWarning = getTranslationsMismatches( $con, $idJob, $jobPass, $segmentID );
            logMsg( "Done getWarning.. " );
        }

        if ( $dice < $setSegmentProb ) {
            $translation = uniqid( '' );

            logMsg( "Starting setTranslation.. " );
            $setTranslation = addTranslation( $con, $segmentID, $idJob, $status, $translation );
            logMsg( "Done setTranslation." );
        }
    }

    logMsg( "Done\n" );

    logMsg("Sleeping for ".round(($sleepTime/1000))." millis");
    usleep( $sleepTime );
}


//getSegments
function getMoreSegments( PDO $db, $jid, $password, $step = 50, $ref_segment, $where = 'after' ) {

    $queryAfter = "
                    SELECT segments.id AS __sid
                    FROM segments
                    JOIN segment_translations ON id = id_segment
                    JOIN jobs ON jobs.id = id_job
                    WHERE id_job = $jid
                        AND password = '$password'
                        AND show_in_cattool = 1
                        AND segments.id > $ref_segment
                    LIMIT %u
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
                    ) as TT
                ";

    /*
     * This query is an union of the last two queries with only one difference:
     * the queryAfter parts differs for the equal sign.
     * Here is needed
     *
     */
    $queryCenter = "
                    SELECT segments.id AS __sid
                    FROM segments
                    JOIN segment_translations ON id = id_segment
                    JOIN jobs ON jobs.id = id_job
                    WHERE id_job = $jid
                        AND password = '$password'
                        AND show_in_cattool = 1
                        AND segments.id >= $ref_segment
                    LIMIT %u
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
                    ) as TT
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
                st.status,
                COALESCE(time_to_edit, 0) AS time_to_edit,
                s.xliff_ext_prec_tags,
                s.xliff_ext_succ_tags,
                st.serialized_errors_list,
                st.warning,
                sts.source_chunk_lengths,
                sts.target_chunk_lengths,
                IF( ( s.id BETWEEN j.job_first_segment AND j.job_last_segment ) , 'false', 'true' ) AS readonly
                , COALESCE( autopropagated_from, 0 ) as autopropagated_from
                ,( SELECT COUNT( segment_hash )
                          FROM segment_translations
                          WHERE segment_hash = s.segment_hash
                          AND id_job =  j.id
                ) repetitions_in_chunk
                ,IF( fr.id IS NULL, 'false', 'true' ) as has_reference
                FROM jobs j
                JOIN projects p ON p.id = j.id_project
                JOIN files_job fj ON fj.id_job = j.id
                JOIN files f ON f.id = fj.id_file
                JOIN segments s ON s.id_file = f.id
                LEFT JOIN segment_translations st ON st.id_segment = s.id AND st.id_job = j.id
                LEFT JOIN segment_translations_splits sts ON sts.id_segment = s.id AND sts.id_job = j.id
                LEFT JOIN file_references fr ON s.id_file_part = fr.id
                JOIN (

                  $subQuery

                ) AS TEMP ON TEMP.__sid = s.id

            WHERE j.id = $jid
            AND j.password = '$password'
            ORDER BY sid ASC
";

    try {

        $results = $db->query( $query )->fetchAll( PDO::FETCH_ASSOC );
    } catch ( PDOException $e ) {
        throw new Exception( __METHOD__ . " -> " . $e->getCode() . ": " . $e->getMessage() );
    }

    return $results;
}

//getWarning
function getTranslationsMismatches( PDO $db, $jid, $jpassword, $sid = null ) {


    $st_translated = "TRANSLATED";
    $st_approved   = "APPROVED";

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
			COUNT(1) as TOT,
			GROUP_CONCAT( id_segment ) AS involved_id,
			IF( password = '%s', 1, 0 ) AS editable
				FROM segment_translations
				JOIN jobs ON id_job = id AND id_segment between jobs.job_first_segment AND jobs.job_last_segment
				WHERE segment_hash = (
					SELECT segment_hash FROM segments WHERE id = %u
				)
				AND segment_translations.status IN( '$st_translated' ) -- , '$st_approved' )
				AND id_job = %u
				AND id_segment != %u
				GROUP BY translation, CONCAT( id_job, '-', password )
		";

        $query = sprintf(
                $queryForTranslationMismatch,
                $jpassword,
                $sid,
                $jid,
                $sid
        );
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
			IF( password = '%s', MIN( id_segment ), NULL ) AS first_of_my_job
				FROM segment_translations
				JOIN jobs ON id_job = id AND id_segment between jobs.job_first_segment AND jobs.job_last_segment
				WHERE id_job = %u
				AND segment_translations.status IN( '$st_translated' ) -- , '$st_approved' )
				GROUP BY segment_hash, CONCAT( id_job, '-', password )
				HAVING translations_available > 1
		";

        $query = sprintf( $queryForMismatchesInJob,
                $jpassword,
                $jid
        );
    }

    $results = $db->query( $query )->fetchAll( PDO::FETCH_ASSOC );

    return $results;
}

//setTranslation
function addTranslation( PDO $db, $id_segment, $id_job, $status, $translation ) {

    $_Translation                       = array();
    $_Translation[ 'id_segment' ]       = $id_segment;
    $_Translation[ 'id_job' ]           = $id_job;
    $_Translation[ 'status' ]           = $status;
    $_Translation[ 'translation' ]      = preg_replace( '/[ \t\n\r\0\x0A\xA0]+$/u', '', $translation );
    $_Translation[ 'translation_date' ] = date( "Y-m-d H:i:s" );

    $query = "INSERT INTO `segment_translations` ";

    foreach ( $_Translation as $key => $val ) {

        if ( $key == 'translation' ) {
            $_Translation[ $key ] = $db->quote( $val );
            continue;
        }

        if ( strtolower( $val ) == 'now()' || strtolower( $val ) == 'current_timestamp()' || strtolower( $val ) == 'sysdate()' ) {
            $_Translation[ $key ] = "NOW()";
        } elseif ( is_numeric( $val ) ) {
            $_Translation[ $key ] = (float)$val;
        } elseif ( is_bool( $val ) ) {
            $_Translation[ $key ] = var_export( $val, true );
        } elseif ( strtolower( $val ) == 'null' || empty( $val ) ) {
            $_Translation[ $key ] = "NULL";
        } else {
            $_Translation[ $key ] = $db->quote( $val );
        }
    }

    $query .= "(" . implode( ", ", array_keys( $_Translation ) ) . ") VALUES (" . implode( ", ", array_values( $_Translation ) ) . ")";

    $query .= "
				ON DUPLICATE KEY UPDATE
				status = VALUES(status),
                suggestion_position = VALUES(suggestion_position),
                serialized_errors_list = VALUES(serialized_errors_list),
                time_to_edit = time_to_edit + VALUES( time_to_edit ),
                translation = {$_Translation['translation']},
                translation_date = {$_Translation['translation_date']}";

    if ( isset( $_Translation[ 'autopropagated_from' ] ) ) {
        $query .= " , autopropagated_from = NULL";
    }

    if ( empty( $_Translation[ 'translation' ] ) && !is_numeric( $_Translation[ 'translation' ] ) ) {
        $msg = "\n\n Error setTranslationUpdate \n\n Empty translation found after DB Escape: \n\n " . var_export( array_merge( array( 'db_query' => $query ), $_POST ), true );
        logMsg( $msg );
        Utils::sendErrMailReport( $msg );

        return -1;
    }

    logMsg( $query );
    try {
        $db->query( $query );
    } catch ( PDOException $e ) {
        logMsg( $e->getMessage() );

        return $e->getCode() * -1;
    }

    return;
}

function getJobData( PDO $db, $id_job, $password = null ) {

    $fields = array(
            'id',
            'id_project',
            'source',
            'target',
            'id_mt_engine',
            'id_tms',
            'id_translator',
            'tm_keys',
            'status_owner AS status',
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
            'dqf_key',
            'payable_rates',
            'total_time_to_edit',
            'avg_post_editing_effort'
    );

    $query = "SELECT " . implode( ', ', $fields ) . " FROM jobs WHERE id = %u";

    if ( !empty( $password ) ) {
        $query .= " AND password = '%s' ";
    }

    $query = sprintf( $query, $id_job, $password );

    $results = $db->query( $query )->fetchAll( PDO::FETCH_ASSOC );

    if ( empty( $password ) ) {
        return $results;
    }

    return $results[ 0 ];
}

function logMsg( $msg ) {
    $now            = date( 'Y-m-d H:i:s' );
    $stringDataInfo = "[$now] ";

    echo $stringDataInfo . $msg . "\n";
}