<?php

include_once 'Database.class.php';

function doSearchQuery( ArrayObject $queryParams ) {
    $db = Database::obtain();
    
    $key = $queryParams['key'];                 //no escape: not related with Database
    $src = $db->escape( $queryParams['src'] );
    $trg = $db->escape( $queryParams['trg'] );

    $where_status = "";
    if ( $queryParams[ 'status' ] != 'all' ) {
        $status       = $queryParams[ 'status' ]; //no escape: hardcoded
        $where_status = " AND st.status = '$status'";
    }

    if( $queryParams['matchCase'] ) {
        $SQL_MOD = "";
    } else {
        $SQL_MOD = "LOWER ";
        $src = strtolower( $src );
        $trg = strtolower( $trg );
    }

    $query = "";
    if ( $key == "source" ) {

        $query = "SELECT s.id, sum(
                    ROUND (
                        ( LENGTH( s.segment ) - LENGTH( REPLACE ( $SQL_MOD( segment ), $SQL_MOD( '$src' ), '') ) ) / LENGTH('$src') )
                    ) AS count
                    FROM segments s
                    INNER JOIN files_job fj on s.id_file=fj.id_file
                    LEFT JOIN segment_translations st on st.id_segment = s.id AND st.id_job = fj.id_job
                    WHERE fj.id_job = {$queryParams['job']}
                    AND s.segment LIKE '%$src%'
                    $where_status
                    GROUP BY s.id WITH ROLLUP";

    } elseif ( $key == "target" ) {

        $query = "SELECT  st.id_segment as id, sum(
                    ROUND (
                      ( LENGTH( st.translation ) - LENGTH( REPLACE ( $SQL_MOD( st.translation ), $SQL_MOD( '$trg' ), '') ) ) / LENGTH('$trg') )
                    ) AS count
                    FROM segment_translations st
                    WHERE st.id_job = {$queryParams['job']}
                    AND st.translation like '%$trg%'
                    AND st.status != 'NEW'
                    $where_status
                    GROUP BY st.id_segment WITH ROLLUP";

    } elseif ( $key == 'coupled' ) {

        $query = "SELECT st.id_segment as id
                    FROM segment_translations as st
                    JOIN segments as s on id = id_segment
                    WHERE st.id_job = {$queryParams['job']}
                    AND st.translation LIKE '%$trg%'
                    AND s.segment LIKE '%$src%'
                    AND LENGTH( REPLACE ( $SQL_MOD( segment ), $SQL_MOD( '$src' ), '') ) != LENGTH( s.segment )
                    AND LENGTH( REPLACE ( $SQL_MOD( st.translation ), $SQL_MOD( '$trg' ), '') ) != LENGTH( st.translation )
                    AND st.status != 'NEW'
                    $where_status ";

    }

    //Log::doLog($query);

    $results = $db->fetch_array( $query );
    $err     = $db->get_error();

    $errno   = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        log::doLog( $err );
        return $errno * -1;
    }

    if( $key != 'coupled' ){ //there is the ROLLUP
        $rollup = array_pop( $results );
    }

    $vector = array();
    foreach($results as $occurrence ){
        $vector['sidlist'][] = $occurrence['id'];
    }

    $vector['count']   = @$rollup['count']; //can be null, suppress warning

    if( $key != 'coupled' ){ //there is the ROLLUP
        //there should be empty values because of Sensitive search
        //LIKE is case INSENSITIVE, REPLACE IS NOT
        //empty search values removed
        //ROLLUP counter rules!
        if ($vector['count'] == 0) {
            $vector[ 'sidlist' ] = null;
            $vector[ 'count' ]   = null;
        }
    }

    return $vector;
}

function getUserData($id) {

    $db = Database::obtain();

    $id = $db->escape($id);
    $query = "select * from users where email = '$id'";

    $results = $db->query_first( $query );

    return $results;
}

function randomString( $maxlength = 15 ) {
    //allowed alphabet
    $possible = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

    //init counter lenght
    $i = 0;
    //init string
    $salt = '';

    //add random characters to $password until $length is reached
    while ( $i < $maxlength ) {
        //pick a random character from the possible ones
        $char = substr( $possible, mt_rand( 0, $maxlength - 1 ), 1 );
        //have we already used this character in $salt?
        if ( !strstr( $salt, $char ) ) {
            //no, so it's OK to add it onto the end of whatever we've already got...
            $salt .= $char;
            //... and increase the counter by one
            $i++;
        }
    }

    return $salt;
}

function encryptPass( $clear_pass, $salt ) {
    return sha1( $clear_pass . $salt );
}

function mailer( $toUser, $toMail, $fromUser, $fromMail, $subject, $message ) {
    //optional headerfields
    $header = "From: " . $fromUser . " <" . $fromMail . ">\r\n";
    //mail command
    mail( $toMail, $subject, $message, $header );
}

function sendResetLink( $mail ) {

    //generate new random pass and unique salt
    $clear_pass = randomString( 15 );
    $newSalt    = randomString( 15 );

    //hash pass
    $newPass = encryptPass( $clear_pass, $newSalt );

    //get link
    $db = Database::obtain();

    //escape untrusted input
    $user = $db->escape( $mail );

    //get data
    $q_get_name = "select first_name, last_name from users where email='$mail'";
    $result     = $db->query_first( $q_get_name );
    if ( 2 == count( $result ) ) {
        $toName = $result[ 'first_name' ] . " " . $result[ 'last_name' ];

        //query
        $q_new_credentials = "update users set pass='$newPass', salt='$newSalt' where email='$mail'";
        $result            = $db->query_first( $q_new_credentials );

        $message = "New pass for $mail is: $clear_pass";
        mailer( $toName, $mail, "Matecat", "noreply@matecat.com", "Password reset", $message );

        $outcome = true;
    } else {
        $outcome = false;
    }

    return $outcome;
}

function checkLogin( $user, $pass ) {
    //get link
    $db = Database::obtain();

    //escape untrusted input
    $user = $db->escape( $user );

    //query
    $q_get_credentials = "select pass,salt from users where email='$user'";
    $result            = $db->query_first( $q_get_credentials );

    //one way transform input pass
    $enc_string = encryptPass( $pass, $result[ 'salt' ] );

    //check
    return $enc_string == $result[ 'pass' ];
}

function insertUser( $data ) {
    //random, unique to user salt
    @$data[ 'salt' ] = randomString( 15 );

    //if no pass available, create a random one
    if ( !isset( $data[ 'pass' ] ) or empty( $data[ 'pass' ] ) ) {
        @$data[ 'pass' ] = randomString( 15 );
    }
    //now encrypt pass
    $clear_pass     = $data[ 'pass' ];
    $encrypted_pass = encryptPass( $clear_pass, $data[ 'salt' ] );
    $data[ 'pass' ] = $encrypted_pass;

    //creation data
    @$data[ 'create_date' ] = date( 'Y-m-d H:i:s' );

    //insert into db
    $db      = Database::obtain();
    $results = $db->insert( 'users', $data, 'email' );

    return $results;
}

function tryInsertUserFromOAuth( $data ) {
    //check if user exists
    $query   = "select email from users where email='" . $data[ 'email' ] . "'";
    $db      = Database::obtain();
    $results = $db->query_first( $query );

    if ( 0 == count( $results ) or false == $results ) {
        //new client
        $results = insertUser( $data );
        //check outcome
        if ( $results ) {
            $cid = $data[ 'email' ];
        } else {
            $cid = false;
        }
    } else {
        $cid = $data[ 'email' ];
    }

    return $cid;
}

function getArrayOfSuggestionsJSON( $id_segment ) {
    $query   = "select suggestions_array from segment_translations where id_segment=$id_segment";
    $db      = Database::obtain();
    $results = $db->query_first( $query );

    return $results[ 'suggestions_array' ];
}

/**
 * Get job data structure,
 * this can return a list of jobs if the job is split into chunks and
 * no password is provided for search
 *
 * <pre>
 * $jobData = array(
 *      'source'        => 'it-IT',
 *      'target'        => 'en-US',
 *      'id_mt_engine'  => 1,
 *      'id_tms'        => 1,
 *      'id_translator' => '',
 *      'status'        => 'active',
 *      'password'      => 'UnDvBUXMiSBGNjSV'
 * );
 * </pre>
 *
 * @param int $id_job
 * @param null|string $password
 *
 * @return array $jobData
 */
function getJobData( $id_job, $password = null ) {

    $query   = "select source, target, id_mt_engine, id_tms, id_translator, status_owner as status, password,
              job_first_segment, job_last_segment
		      from jobs
		      where id = %u";

    if( !empty( $password ) ){
        $query .= " and password = '%s' ";
    }

    $query   = sprintf( $query, $id_job, $password );
    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    if( empty( $password ) ){
        return $results;
    }

    return $results[0];
}

function getEngineData( $id ) {
    if ( is_array( $id ) ) {
        $id = explode( ",", $id );
    }
    $where_clause = " id IN ($id)";

    $query = "select * from engines where $where_clause";

    $db = Database::obtain();

    $results = $db->fetch_array( $query );
    $err     = $db->get_error();
    $errno   = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return @$results[ 0 ];
}

function getSegment( $id_segment ) {
    $db         = Database::obtain();
    $id_segment = $db->escape( $id_segment );
    $query      = "select * from segments where id=$id_segment";
    $results    = $db->query_first( $query );

    return $results;
}

function getFirstSegmentOfFilesInJob( $jid ) {
    $db      = Database::obtain();
    $jid     = intval( $jid );
    $query   = "select id_file, min( segments.id ) as first_segment, filename as file_name
                from files_job
                join segments using( id_file )
                join files on files.id = id_file
                where files_job.id_job = $jid
                and segments.show_in_cattool = 1
                group by id_file";
    $results = $db->fetch_array( $query );

    return $results;
}

function getWarning( $jid ) {
    $db  = Database::obtain();
    $jid = $db->escape( $jid );

    $query = "SELECT id_segment, serialized_errors_list
                FROM segment_translations
                WHERE id_job = $jid
                AND warning != 0";

    $results = $db->fetch_array( $query );

    return $results;
}

function getTranslatorPass( $id_translator ) {

    $db = Database::obtain();

    $id_translator = $db->escape( $id_translator );
    $query         = "select password from translators where username='$id_translator'";


    //$db->query_first($query);
    $results = $db->query_first( $query );

    if ( ( is_array( $results ) ) AND ( array_key_exists( "password", $results ) ) ) {
        return $results[ 'password' ];
    }

    return null;
}

function getTranslatorKey( $id_translator ) {

    $db = Database::obtain();

    $id_translator = $db->escape( $id_translator );
    $query         = "select mymemory_api_key from translators where username='$id_translator'";

    $db->query_first( $query );
    $results = $db->query_first( $query );

    if ( ( is_array( $results ) ) AND ( array_key_exists( "mymemory_api_key", $results ) ) ) {
        return $results[ 'mymemory_api_key' ];
    }

    return null;
}

function getEngines( $type = "MT" ) {
    $query = "select id,name from engines where type='$type'";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getSegmentsDownload( $jid, $password, $id_file, $no_status_new = 1 ) {
    if ( !$no_status_new ) {
        $select_translation = " st.translation ";
    } else {
        $select_translation = " if (st.status='NEW', '', st.translation) as translation ";
    }
    //$where_status ="";
    $query   = "select
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
    $db      = Database::obtain();
    $results = $db->fetch_array( $query );
    $err     = $db->get_error();
    $errno   = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

function getSegmentsInfo( $jid, $password ) {

    $query = "select j.id as jid, j.id_project as pid,j.source,j.target, j.last_opened_segment, j.id_translator as tid,
		p.id_customer as cid, j.id_translator as tid, j.status_owner as status,  
		p.name as pname, p.create_date , fj.id_file,
		f.filename, f.mime_type

			from jobs j 
			inner join projects p on p.id=j.id_project
			inner join files_job fj on fj.id_job=j.id
			inner join files f on f.id=fj.id_file
			where j.id=$jid and j.password='$password' ";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

function getFirstSegmentId( $jid, $password ) {

    $query   = "select s.id as sid
		from segments s
		inner join files_job fj on s.id_file = fj.id_file
		inner join jobs j on j.id=fj.id_job
		where fj.id_job=$jid and j.password='$password'
		and s.show_in_cattool=1
		order by s.id
		limit 1
		";
    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getMoreSegments( $jid, $password, $step = 50, $ref_segment, $where = 'after' ) {

    switch ( $where ) {
        case 'after':
            $ref_point = $ref_segment;
            break;
        case 'before':
            $ref_point = $ref_segment - ( $step + 1 );
            break;
        case 'center':
            $ref_point = ( (float)$ref_segment ) - 100;
            break;
    }

    //	$ref_point = ($where == 'center')? ((float) $ref_segment) - 100 : $ref_segment;

    $query = "SELECT j.id AS jid, j.id_project AS pid,j.source,j.target, j.last_opened_segment, j.id_translator AS tid,
                p.id_customer AS cid, j.id_translator AS tid,
                p.name AS pname, p.create_date , fj.id_file,
                f.filename, f.mime_type, s.id AS sid, s.segment, s.raw_word_count, s.internal_id,
                IF (st.status='NEW',NULL,st.translation) AS translation,
                st.status, IF(st.time_to_edit IS NULL,0,st.time_to_edit) AS time_to_edit,
                s.xliff_ext_prec_tags, s.xliff_ext_succ_tags, st.serialized_errors_list, st.warning,

                IF( ( s.id BETWEEN j.job_first_segment AND j.job_last_segment ) , 'false', 'true' ) AS readonly

             FROM jobs j
                INNER JOIN projects p ON p.id=j.id_project
                INNER JOIN files_job fj ON fj.id_job=j.id
                INNER JOIN files f ON f.id=fj.id_file
                INNER JOIN segments s ON s.id_file=f.id
                LEFT JOIN segment_translations st ON st.id_segment=s.id AND st.id_job=j.id
                WHERE j.id = $jid
                AND j.password = '$password'
                AND s.id > $ref_point AND s.show_in_cattool = 1
                LIMIT 0, $step ";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getLastSegmentInNextFetchWindow( $jid, $password, $step = 50, $ref_segment, $where = 'after' ) {
    switch ( $where ) {
        case 'after':
            $ref_point = $ref_segment;
            break;
        case 'before':
            $ref_point = $ref_segment - ( $step + 1 );
            break;
        case 'center':
            $ref_point = ( (float)$ref_segment ) - 100;
            break;
    }

    //	$ref_point = ($where == 'center')? ((float) $ref_segment) - 100 : $ref_segment;

    $query = "select max(id) as max_id
		from (select s.id from  jobs j 
				inner join projects p on p.id=j.id_project
				inner join files_job fj on fj.id_job=j.id
				inner join files f on f.id=fj.id_file
				inner join segments s on s.id_file=f.id
				left join segment_translations st on st.id_segment=s.id and st.id_job=j.id
				where j.id=$jid and j.password='$password' and s.id > $ref_point and s.show_in_cattool=1 
				limit 0,$step) as id
		";


    $db      = Database::obtain();
    $results = $db->query_first( $query );


    return $results[ 'max_id' ];
}

function setTranslationUpdate( $id_segment, $id_job, $status, $time_to_edit, $translation, $errors, $chosen_suggestion_index, $warning = 0 ) {
    // need to use the plain update instead of library function because of the need to update an existent value in db (time_to_edit)
    $now = date( "Y-m-d H:i:s" );
    $db  = Database::obtain();

    $translation = $db->escape( $translation );
    $status      = $db->escape( $status );

    $q = "UPDATE segment_translations SET status='$status', suggestion_position='$chosen_suggestion_index', serialized_errors_list='$errors', time_to_edit=IF(time_to_edit is null,0,time_to_edit) + $time_to_edit, translation='$translation', translation_date='$now', warning=" . (int)$warning . " WHERE id_segment=$id_segment and id_job=$id_job";

    if( empty( $translation ) ){
        $msg = "\n\n Error setTranslationUpdate \n\n Empty translation found: \n\n " . var_export( array_merge( array( 'db_query' => $q ), $_POST ), true );
        Log::doLog( $msg );
        Utils::sendErrMailReport( $msg );
    }

    $db->query( $q );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( "$errno: $err" );

        return $errno * -1;
    }

    return $db->affected_rows;
}

function setTranslationInsert( $id_segment, $id_job, $status, $time_to_edit, $translation, $errors = '', $chosen_suggestion_index, $warning = 0 ) {
    $data                             = array();
    $data[ 'id_job' ]                 = $id_job;
    $data[ 'status' ]                 = $status;
    $data[ 'time_to_edit' ]           = $time_to_edit;
    $data[ 'translation' ]            = $translation;
    $data[ 'translation_date' ]       = date( "Y-m-d H:i:s" );
    $data[ 'id_segment' ]             = $id_segment;
    $data[ 'id_job' ]                 = $id_job;
    $data[ 'serialized_errors_list' ] = $errors;
    $data[ 'suggestion_position' ]    = $chosen_suggestion_index;
    $data[ 'warning' ]                = (int)$warning;

    if( empty( $translation ) ){
        $msg = "\n\n Error setTranslationUpdate \n\n Empty translation found: \n\n " . var_export( $_POST, true ) . " \n\n " . var_export( $data, true );
        Log::doLog( $msg );
        Utils::sendErrMailReport( $msg );
    }

    $db                               = Database::obtain();
    $db->insert( 'segment_translations', $data );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        if ( $errno != 1062 ) {
            log::doLog( "$errno: $err" );
        }

        return $errno * -1;
    }

    return $db->affected_rows;
}

function setSuggestionUpdate( $id_segment, $id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source, $match_type, $eq_words, $standard_words, $translation, $tm_status_analysis, $warning, $err_json_list, $mt_qe ) {
    $data                          = array();
    $data[ 'id_job' ]              = $id_job;
    $data[ 'suggestions_array' ]   = $suggestions_json_array;
    $data[ 'suggestion' ]          = $suggestion;
    $data[ 'suggestion_match' ]    = $suggestion_match;
    $data[ 'suggestion_source' ]   = $suggestion_source;
    $data[ 'match_type' ]          = $match_type;
    $data[ 'eq_word_count' ]       = $eq_words;
    $data[ 'standard_word_count' ] = $standard_words;
    $data[ 'mt_qe' ]               = $mt_qe;

    ( !empty( $translation ) ? $data[ 'translation' ] = $translation : null );
    ( $tm_status_analysis != 'UNDONE' ? $data[ 'tm_analysis_status' ] = $tm_status_analysis : null );

    $data[ 'warning' ]                = $warning;
    $data[ 'serialized_errors_list' ] = $err_json_list;

    $and_sugg = "";
    if ( $tm_status_analysis != 'DONE' ) {
        $and_sugg = "and suggestions_array is NULL";
    }

    $where = " id_segment=$id_segment and id_job=$id_job $and_sugg";

    $db = Database::obtain();
    $db->update( 'segment_translations', $data, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $db->affected_rows;
}

function setSuggestionInsert( $id_segment, $id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source, $match_type, $eq_words, $standard_words, $translation, $tm_status_analysis, $warning, $err_json_list, $mt_qe ) {
    $data                          = array();
    $data[ 'id_job' ]              = $id_job;
    $data[ 'id_segment' ]          = $id_segment;
    $data[ 'suggestions_array' ]   = $suggestions_json_array;
    $data[ 'suggestion' ]          = $suggestion;
    $data[ 'suggestion_match' ]    = $suggestion_match;
    $data[ 'suggestion_source' ]   = $suggestion_source;
    $data[ 'match_type' ]          = $match_type;
    $data[ 'eq_word_count' ]       = $eq_words;
    $data[ 'standard_word_count' ] = $standard_words;
    $data[ 'translation' ]         = $translation;
    $data[ 'tm_analysis_status' ]  = $tm_status_analysis;

    $data[ 'warning' ]                = $warning;
    $data[ 'serialized_errors_list' ] = $err_json_list;

    $data[ 'mt_qe' ]                  = $mt_qe;

    $db = Database::obtain();
    $db->insert( 'segment_translations', $data );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        if ( $errno != 1062 ) {
            log::doLog( $err );
        }

        return $errno * -1;
    }

    return $db->affected_rows;
}

function setCurrentSegmentInsert( $id_segment, $id_job, $password ) {
    $data                          = array();
    $data[ 'last_opened_segment' ] = $id_segment;

    $where = "id = $id_job AND password = '$password'" ;

    $db = Database::obtain();
    $db->update( 'jobs', $data, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $db->affected_rows;
}

function getFilesForJob( $id_job, $id_file ) {
    $where_id_file = "";

    if ( !empty( $id_file ) ) {
        $where_id_file = " and id_file=$id_file";
    }

    $query = "select id_file, xliff_file, original_file, filename,mime_type from files_job fj
        inner join files f on f.id=fj.id_file
        where id_job = $id_job $where_id_file";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getOriginalFilesForJob( $id_job, $id_file, $password ) {
    $where_id_file = "";
    if ( !empty( $id_file ) ) {
        $where_id_file = " and id_file=$id_file";
    }
    $query = "select id_file, if(original_file is null, xliff_file,original_file) as original_file, filename from files_job fj
		inner join files f on f.id=fj.id_file
		inner join jobs j on j.id=fj.id_job
		where id_job=$id_job $where_id_file and j.password='$password'";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

/*
   function getFilesForJob($id_job) {

   $query = "select id_file, xliff_file from files_job fj
   inner join files f on f.id=fj.id_file
   where id_job=".$id_job;

   $db = Database::obtain();
   $results = $db->fetch_array($query);

   return $results;
   }
 */

function getStatsForMultipleJobs( $_jids ) {

    //remove chunk jobs id
    $_jids = array_unique( $_jids );

    //transform array into comma separated string
    if ( is_array( $_jids ) ) {
        $jids = implode( ',', $_jids );
    }

    $query = "select SUM(IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count)) as TOTAL, SUM(IF(st.status IS NULL OR st.status='DRAFT' OR st.status='NEW',IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count),0)) as DRAFT, SUM(IF(st.status='REJECTED',IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count),0)) as REJECTED, SUM(IF(st.status='TRANSLATED',IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count),0)) as TRANSLATED, SUM(IF(st.status='APPROVED',IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count),0)) as APPROVED, j.id, j.password

		from jobs j
		INNER JOIN files_job fj on j.id=fj.id_job
		INNER join segments s on fj.id_file=s.id_file
		LEFT join segment_translations st on s.id=st.id_segment and st.id_job=j.id

		WHERE j.id in ($jids)

		AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
		group by j.id, j.password
		";

    $db         = Database::obtain();
    $jobs_stats = $db->fetch_array( $query );

    //convert result to ID based index
    foreach ( $jobs_stats as $job_stat ) {
        $tmp_jobs_stats[ $job_stat[ 'id' ] . "-" . $job_stat[ 'password' ] ] = $job_stat;
        $tmp_jobs_found[ $job_stat[ 'id' ] ] = true;
    }
    $jobs_stats = $tmp_jobs_stats;
    unset( $tmp_jobs_stats );

    return $jobs_stats;
}

function getStatsForJob( $id_job, $id_file = null, $jPassword = null ) {

    $query = "
		select
                j.id,
		SUM(
				IF( IFNULL( st.eq_word_count, 0 ) = 0, s.raw_word_count, st.eq_word_count)
		   ) as TOTAL,
		SUM(
				IF(
					st.status IS NULL OR
					st.status='DRAFT' OR
					st.status='NEW',
					IF( IFNULL( st.eq_word_count, 0 ) = 0 , s.raw_word_count, st.eq_word_count),0)
		   ) as DRAFT,
		SUM(
				IF(st.status='REJECTED',
					IF( IFNULL( st.eq_word_count, 0 ) = 0 , s.raw_word_count, st.eq_word_count),0
				  )
		   ) as REJECTED,
		SUM(
				IF(st.status='TRANSLATED',
					IF( IFNULL( st.eq_word_count, 0 ) = 0 , s.raw_word_count, st.eq_word_count),0
				  )
		   ) as TRANSLATED,
		SUM(
				IF(st.status='APPROVED',
					IF( IFNULL( st.eq_word_count, 0 ) = 0, s.raw_word_count, st.eq_word_count),0
				  )
		   ) as APPROVED

        FROM jobs AS j
        INNER JOIN files_job as fj on j.id=fj.id_job
        INNER join segments as s on fj.id_file=s.id_file
        LEFT join segment_translations as st on s.id=st.id_segment and st.id_job=j.id
        WHERE j.id = $id_job
        AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
        ";

    $db      = Database::obtain();

    if( !empty($jPassword) ){
        $query .= " and j.password = '" . $db->escape($jPassword) . "'";
    }

    if( !empty($id_file) ){
        $query .= " and fj.id_file = " . intval($id_file);
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
    $query = "SELECT SUM(IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count) ) as TOTAL,
                         SUM(IF(st.status IS NULL OR st.status='DRAFT' OR st.status='NEW',IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count),0)) as DRAFT,
                         SUM(IF(st.status='REJECTED',IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count),0)) as REJECTED,
                         SUM(IF(st.status='TRANSLATED',IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count),0)) as TRANSLATED,
                         SUM(IF(st.status='APPROVED',raw_word_count,0)) as APPROVED from jobs j
                   INNER JOIN files_job fj on j.id=fj.id_job
                   INNER join segments s on fj.id_file=s.id_file
                   LEFT join segment_translations st on s.id=st.id_segment
                   WHERE s.id_file=" . $id_file;

    $results = $db->fetch_array( $query );

    return $results;
}

function getLastSegmentIDs( $id_job ) {

    $query   = "SELECT group_concat(c.id_segment) as estimation_seg_ids from (SELECT id_segment from segment_translations WHERE id_job=$id_job AND status in ('TRANSLATED','APPROVED') ORDER by translation_date DESC LIMIT 0,10) as c";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getEQWLastHour( $id_job, $estimation_seg_ids ) {


    // Old raw-wordcount
    /*
       $query = "SELECT SUM(raw_word_count), MIN(translation_date), MAX(translation_date),
       IF(UNIX_TIMESTAMP(MAX(translation_date))-UNIX_TIMESTAMP(MIN(translation_date))>3600 OR count(*)<10,0,1) as data_validity,
       ROUND(SUM(raw_word_count)/(UNIX_TIMESTAMP(MAX(translation_date))-UNIX_TIMESTAMP(MIN(translation_date)))*3600) as words_per_hour,
       count(*) from segment_translations
       INNER JOIN segments on id=segment_translations.id_segment WHERE status in ('TRANSLATED','APPROVED') and id_job=$id_job and id_segment in ($estimation_seg_ids)";
     */

    $query = "SELECT SUM(IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count)), MIN(translation_date), MAX(translation_date),
		IF(UNIX_TIMESTAMP(MAX(translation_date))-UNIX_TIMESTAMP(MIN(translation_date))>3600 OR count(*)<10,0,1) as data_validity,
		ROUND(SUM(IF( IFNULL( st.eq_word_count, 0 ) = 0, raw_word_count, st.eq_word_count))/(UNIX_TIMESTAMP(MAX(translation_date))-UNIX_TIMESTAMP(MIN(translation_date)))*3600) as words_per_hour,
		count(*) from segment_translations st
			INNER JOIN segments on id=st.id_segment WHERE status in ('TRANSLATED','APPROVED') and id_job=$id_job and id_segment in ($estimation_seg_ids)";


    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getOriginalFile( $id_file ) {

    $query = "select xliff_file from files where id=" . $id_file;

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
		st.mt_qe,
		j.id_translator AS tid,
		j.source AS source_lang,
		j.target AS target_lang,
		s.raw_word_count rwc, 
		p.name as pname
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
			ORDER BY tte DESC
			LIMIT 5000";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getNextUntranslatedSegment( $sid, $jid, $password ) {

    $query = "SELECT s.id, st.`status`
                    FROM segments AS s
                    JOIN files_job fj USING (id_file)
                    JOIN jobs ON jobs.id = fj.id_job
                    JOIN files f ON f.id = fj.id_file
                    LEFT JOIN segment_translations st ON st.id_segment = s.id AND fj.id_job = st.id_job
                    WHERE jobs.id = $jid AND jobs.password = '$password'
                    AND ( st.status IN ( 'NEW', 'DRAFT', 'REJECTED' ) OR st.status IS NULL )
                    AND s.show_in_cattool = 1
                    AND s.id <> $sid
                    AND s.id BETWEEN jobs.job_first_segment AND jobs.job_last_segment
             ";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getNextSegmentId( $sid, $jid, $status ) {
    $rules        = ( $status == 'untranslated' ) ? "'NEW','DRAFT','REJECTED'" : "'$status'";
    $statusIsNull = ( $status == 'untranslated' ) ? " OR st.status IS NULL" : "";
    // Warning this is a LEFT join a little slower...
    $query = "select s.id as sid
		from segments s
		LEFT JOIN segment_translations st on st.id_segment = s.id
		INNER JOIN files_job fj on fj.id_file=s.id_file 
		INNER JOIN jobs j on j.id=fj.id_job 
		where fj.id_job=$jid AND 
		(st.status in ($rules)$statusIsNull) and s.id>$sid
		and s.show_in_cattool=1
		order by s.id
		limit 1
		";

    $db      = Database::obtain();
    $results = $db->query_first( $query );

    return $results[ 'sid' ];
}

//function insertProject( $id_customer, $project_name, $analysis_status, $password, $ip = 'UNKNOWN' ) {
function insertProject( ArrayObject $projectStructure ) {
    $data                        = array();
    $data[ 'id_customer' ]       = $projectStructure['id_customer'];
    $data[ 'name' ]              = $projectStructure['project_name'];
    $data[ 'create_date' ]       = date( "Y-m-d H:i:s" );
    $data[ 'status_analysis' ]   = $projectStructure['status'];
    $data[ 'password' ]          = $projectStructure['ppassword'];
    $data[ 'remote_ip_address' ] = empty( $projectStructure['user_ip'] ) ? 'UNKNOWN' : $projectStructure['user_ip'];
    $query                       = "SELECT LAST_INSERT_ID() FROM projects";

    $db = Database::obtain();
    $db->insert( 'projects', $data );
    $results = $db->query_first( $query );

    return $results[ 'LAST_INSERT_ID()' ];
}

//never used email , first_name and last_name
//function insertTranslator( $user, $pass, $api_key, $email = '', $first_name = '', $last_name = '' ) {
function insertTranslator( ArrayObject $projectStructure ) {
    //get link
    $db = Database::obtain();
    //if this user already exists, return without inserting again ( do nothing )
    //this is because we allow to start a project with the bare key
    $query   = "select username from translators where mymemory_api_key='" . $db->escape( $projectStructure['private_tm_key'] ) . "'";
    $user_id = $db->query_first( $query );
    $user_id = $user_id[ 'username' ];

    if ( empty( $user_id ) ) {
        $data                       = array();
        $data[ 'username' ]         = $projectStructure['private_tm_user'];
        $data[ 'email' ]            = '';
        $data[ 'password' ]         = $projectStructure['private_tm_pass'];
        $data[ 'first_name' ]       = '';
        $data[ 'last_name' ]        = '';
        $data[ 'mymemory_api_key' ] = $projectStructure['private_tm_key'];

        $db->insert( 'translators', $data );
    }

}

//function insertJob( $password, $id_project, $id_translator, $source_language, $target_language, $mt_engine, $tms_engine, $owner ) {
function insertJob( ArrayObject $projectStructure, $password, $target_language, $job_segments, $owner ) {
    $data                        = array();
    $data[ 'password' ]          = $password;
    $data[ 'id_project' ]        = $projectStructure[ 'id_project' ];
    $data[ 'id_translator' ]     = $projectStructure[ 'private_tm_user' ];
    $data[ 'source' ]            = $projectStructure[ 'source_language' ];
    $data[ 'target' ]            = $target_language;
    $data[ 'id_tms' ]            = $projectStructure[ 'tms_engine' ];
    $data[ 'id_mt_engine' ]      = $projectStructure[ 'mt_engine' ];
    $data[ 'create_date' ]       = date( "Y-m-d H:i:s" );
    $data[ 'owner' ]             = $owner;
    $data[ 'job_first_segment' ] = $job_segments[ 'job_first_segment' ];
    $data[ 'job_last_segment' ]  = $job_segments[ 'job_last_segment' ];

    $query = "SELECT LAST_INSERT_ID() FROM jobs";

    $db = Database::obtain();
    $db->insert( 'jobs', $data );
    $results = $db->query_first( $query );

    return $results[ 'LAST_INSERT_ID()' ];
}

function insertFileIntoMap( $sha1, $source, $target, $deflated_file, $deflated_xliff ) {
    $db                       = Database::obtain();
    $data                     = array();
    $data[ 'sha1' ]           = $sha1;
    $data[ 'source' ]         = $source;
    $data[ 'target' ]         = $target;
    $data[ 'deflated_file' ]  = $deflated_file;
    $data[ 'deflated_xliff' ] = $deflated_xliff;
    $data[ 'creation_date' ]  = date( "Y-m-d" );

    $db->insert( 'original_files_map', $data );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 and $errno != 1062 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return 1;
}

function getXliffBySHA1( $sha1, $source, $target, $not_older_than_days = 0 ) {
    $db                  = Database::obtain();
    $where_creation_date = "";
    if ( $not_older_than_days != 0 ) {
        $where_creation_date = " AND creation_date > DATE_SUB(NOW(), INTERVAL $not_older_than_days DAY)";
    }
    $query = "select deflated_xliff from original_files_map where sha1='$sha1' and source='$source' and target ='$target' $where_creation_date";
    $res   = $db->query_first( $query );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $res[ 'deflated_xliff' ];
}

//function insertFile( $id_project, $file_name, $source_language, $mime_type, $contents, $sha1_original = null, $original_file = null ) {
function insertFile( ArrayObject $projectStructure, $file_name, $mime_type, $contents, $sha1_original = null, $original_file = null ) {
    $data                      = array();
    $data[ 'id_project' ]      = $projectStructure[ 'id_project' ];
    $data[ 'filename' ]        = $file_name;
    $data[ 'source_language' ] = $projectStructure[ 'source_language' ];
    $data[ 'mime_type' ]       = $mime_type;
    $data[ 'xliff_file' ]      = $contents;
    if ( !is_null( $sha1_original ) ) {
        $data[ 'sha1_original_file' ] = $sha1_original;
    }

    if ( !is_null( $original_file ) and !empty( $original_file ) ) {
        $data[ 'original_file' ] = $original_file;
    }

    $query = "SELECT LAST_INSERT_ID() FROM files";

    $db = Database::obtain();

    $db->insert( 'files', $data );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno == 1153 ) {
        log::doLog( "file too large for mysql packet: increase max_allowed_packed_size" );

        $maxp = $db->query_first( 'SELECT @@global.max_allowed_packet' );
        log::doLog( "max_allowed_packet: " . $maxp . " > try Upgrade to 500MB" );
        // to set the max_allowed_packet to 500MB
        //FIXME User matecat han no superuser privileges
        //ERROR 1227 (42000): Access denied; you need (at least one of) the SUPER privilege(s) for this operation
        $db->query( 'SET @@global.max_allowed_packet = ' . 500 * 1024 * 1024 );
        $db->insert( 'files', $data );
        $db->query( 'SET @@global.max_allowed_packet = ' . 32 * 1024 * 1024 ); //restore to 32 MB
    } elseif ( $errno > 0 ) {
        log::doLog( "Database insert Large file error: $errno " );
        throw new Exception( "Database insert Large file error: $errno ", -$errno );
    }

    $results = $db->query_first( $query );

    return $results[ 'LAST_INSERT_ID()' ];
}

function insertFilesJob( $id_job, $id_file ) {
    $data              = array();
    $data[ 'id_job' ]  = $id_job;
    $data[ 'id_file' ] = $id_file;

    $db = Database::obtain();
    $db->insert( 'files_job', $data );
}

function getPdata( $pid ) {
    $db    = Database::obtain();
    $query = "select pid from projects where id =$pid";
    $res   = $db->query_first( $query );

    return $res[ 'id' ];
}

/**
 * @param      $pid
 * @param null $project_password
 * @param null $jid
 *
 * @return array
 */
function getProjectData( $pid, $project_password = null, $jid = null ) {

    $query = "
    SELECT * FROM (

            SELECT p.name, j.id AS jid, j.password AS jpassword, j.source, j.target, f.id, f.id AS id_file,f.filename, p.status_analysis,

                    SUM(s.raw_word_count) AS file_raw_word_count,
                    SUM(st.eq_word_count) AS file_eq_word_count,
                    COUNT(s.id) AS total_segments,

                    p.fast_analysis_wc,
                    p.tm_analysis_wc,
                    p.standard_analysis_wc

                    FROM projects p
                    INNER JOIN jobs j ON p.id=j.id_project
                    INNER JOIN files f ON p.id=f.id_project
                    INNER JOIN segments s ON s.id_file=f.id
                    LEFT JOIN segment_translations st ON st.id_segment=s.id AND st.id_job=j.id
                    WHERE p.id= '$pid'

                    %s
                    %s

                    GROUP BY f.id, j.id, j.password

    ) AS res GROUP BY jid, id_file";

    $and_1 = $and_2 = null;

    if( !empty( $project_password ) ){
        $and_1 = " and p.password = '$project_password' ";
    }

    if( !empty($jid) ){
        $and_2 = " and j.id = " . intval($jid);
    }

    $query = sprintf( $query, $and_1, $and_2 );

    Log::doLog( $query );

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

/**
 * @param      $pid
 * @param      $job_password
 * @param null $jid
 *
 * @return array
 */
function getJobAnalysisData( $pid, $job_password, $jid = null ) {

    $query = "select p.name, j.id as jid, j.password as jpassword, j.source, j.target, f.id,f.filename, p.status_analysis,
		sum(s.raw_word_count) as file_raw_word_count, sum(st.eq_word_count) as file_eq_word_count, count(s.id) as total_segments,
		p.fast_analysis_wc,p.tm_analysis_wc, p.standard_analysis_wc

			from projects p 
			inner join jobs j on p.id=j.id_project
			inner join files f on p.id=f.id_project
			inner join segments s on s.id_file=f.id
			left join segment_translations st on st.id_segment=s.id and st.id_job=j.id

			where p.id= '$pid' and j.password='$job_password' ";
    
    if( !empty($jid) ){
        $query = $query . " and j.id = " . intval($jid);
    }
    
	$query = $query ." group by 6,2 ";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getProjects( $start, $step, $search_in_pname, $search_source, $search_target, $search_status, $search_onlycompleted, $filtering, $project_id ) {

    #session_start();

    $pn_query     = ( $search_in_pname ) ? " p.name like '%$search_in_pname%' and" : "";
    $ss_query     = ( $search_source ) ? " j.source='$search_source' and" : "";
    $st_query     = ( $search_target ) ? " j.target='$search_target' and" : "";
    $sst_query    = ( $search_status ) ? " j.status_owner='$search_status' and" : "";
    $oc_query     = ( $search_onlycompleted ) ? " j.completed=1 and" : "";
    $single_query = ( $project_id ) ? " j.id_project=$project_id and" : "";
    $owner        = $_SESSION[ 'cid' ];
    $owner_query  = " j.owner='$owner' and";
//	$owner_query = "";

    /*
log::doLog('PN QUERY:',$pn_query);
log::doLog('SEARCH TARGET:',$search_target);

log::doLog('FILTERING:',$filtering);
log::doLog('SHOWARCHIVED:',$search_showarchived);
log::doLog('SHOWCANCELLED:',$search_showcancelled);

$status_query = " (j.status_owner='ongoing'";
//	if(!$search_showarchived && !$search_showcancelled) {
if($filtering) {
if($search_showarchived) $status_query .= " or j.status_owner='archived'";
if($search_showcancelled) $status_query .= " or j.status_owner='cancelled'";
$status_query .= ") and";
} else {
$status_query = " (j.status_owner='ongoing' or j.status_owner='cancelled' or j.status_owner='archived') and";
}
//	$status_query = (!$search_showarchived && !$search_showcancelled)? "j.status='ongoing' or j.status='cancelled' and" : "";
log::doLog('STATUS QUERY:',$status_query);

//	$sa_query = ($search_showarchived)? " j.status='archived' and" : "";
//	$sc_query = ($search_showcancelled)? " j.status='cancelled' and" : "";
*/
    $query_tail = $pn_query . $ss_query . $st_query . $sst_query . $oc_query . $single_query . $owner_query;

    $filter_query = ( $query_tail == '' ) ? '' : 'where ' . $query_tail;
    $filter_query = preg_replace( '/( and)$/i', '', $filter_query );

    $query = "select p.id as pid, p.name, p.password, j.id_mt_engine, j.id_tms, p.tm_analysis_wc,
		group_concat(j.id,'##', j.source,'##',j.target,'##',j.create_date,'##',j.password,'##',e.name,'##',if (t.mymemory_api_key is NUll,'',t.mymemory_api_key),'##',j.status_owner,'##',j.job_first_segment,'##',j.job_last_segment) as job

            , e.name as mt_engine_name

			from projects p
			inner join jobs j on j.id_project=p.id 
			inner join engines e on j.id_mt_engine=e.id 
			left join translators t on j.id_translator=t.username
			$filter_query
			group by 1
			order by pid desc, j.id
			limit $start,$step";


    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getProjectsNumber( $start, $step, $search_in_pname, $search_source, $search_target, $search_status, $search_onlycompleted, $filtering ) {

    //	$pn = ($search_in_pname)? "where p.name like '%$search_in_pname%'" : "";

    $pn_query    = ( $search_in_pname ) ? " p.name like '%$search_in_pname%' and" : "";
    $ss_query    = ( $search_source ) ? " j.source='$search_source' and" : "";
    $st_query    = ( $search_target ) ? " j.target='$search_target' and" : "";
    $sst_query   = ( $search_status ) ? " j.status_owner='$search_status' and" : "";
    $oc_query    = ( $search_onlycompleted ) ? " j.completed=1 and" : "";
    $owner       = $_SESSION[ 'cid' ];
    $owner_query = " j.owner='$owner' and";

    //log::doLog('OWNER QUERY:',$owner);		

//    $owner_query = $owner;
//	$owner_query = "";

    /*
       $status_query = " (j.status_owner='ongoing'";
    //	if(!$search_showarchived && !$search_showcancelled) {
    if($filtering) {
    if($search_showarchived) $status_query .= " or j.status_owner='archived'";
    if($search_showcancelled) $status_query .= " or j.status_owner='cancelled'";
    $status_query .= ") and";
    } else {
    $status_query = " (j.status_owner='ongoing' or j.status_owner='cancelled' or j.status_owner='archived') and";
    }
    //	$status_query = (!$search_showarchived && !$search_showcancelled)? "j.status='ongoing' or j.status='cancelled' and" : "";
    log::doLog('STATUS QUERY:',$status_query);

    //	$sa_query = ($search_showarchived)? " j.status='archived' and" : "";
    //	$sc_query = ($search_showcancelled)? " j.status='cancelled' and" : "";
     */
    $query_tail   = $pn_query . $ss_query . $st_query . $sst_query . $oc_query . $owner_query;
    $filter_query = ( $query_tail == '' ) ? '' : 'where ' . $query_tail;
    $filter_query = preg_replace( '/( and)$/i', '', $filter_query );

    $query = "select count(*) as c

		from projects p
		inner join jobs j on j.id_project=p.id 
		inner join engines e on j.id_mt_engine=e.id 
		left join translators t on j.id_translator=t.username
		$filter_query";


    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getProjectStatsVolumeAnalysis2( $pid, $groupby = "job" ) {

    $db = Database::obtain();

    switch ( $groupby ) {
        case 'job':
            $first_column = "j.id";
            $groupby      = " GROUP BY j.id";
            break;
        case 'file':
            $first_column = "fj.id_file,fj.id_job,";
            $groupby      = " GROUP BY fj.id_file,fj.id_job";
            break;
        default:
            $first_column = "j.id";
            $groupby      = " GROUP BY j.id";
    }

    $query   = "select $first_column,
		sum(if(st.match_type='INTERNAL' ,s.raw_word_count,0)) as INTERNAL_MATCHES,
		sum(if(st.match_type='MT' ,s.raw_word_count,0)) as MT,
		sum(if(st.match_type='NEW' ,s.raw_word_count,0)) as NEW,
		sum(if(st.match_type='NO_MATCH' ,s.raw_word_count,0)) as NO_MATCH,
		sum(if(st.match_type='100%' ,s.raw_word_count,0)) as `100%`,
		sum(if(st.match_type='75%-99%' ,s.raw_word_count,0)) as `75%-99%`,
		sum(if(st.match_type='50%-74%' ,s.raw_word_count,0)) as `50%-74%`,
		sum(if(st.match_type='REPETITIONS' ,s.raw_word_count,0)) as REPETITIONS
			from jobs j 
			inner join projects p on p.id=j.id_project
			inner join files_job fj on fj.id_job=j.id
			inner join segments s on s.id_file=fj.id_file
			left outer join segment_translations st on st.id_segment=s.id 

			where id_project=$pid  and p.status_analysis in ('NEW', 'FAST_OK','DONE') and st.match_type<>''
			group by 1
			";
    $results = $db->fetch_array( $query );
    $err     = $db->get_error();
    $errno   = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

function getProjectStatsVolumeAnalysis( $pid ) {

    $query = "select st.id_job as jid,st.id_segment as sid, s.id_file, s.raw_word_count,
		st.suggestion_source, st.suggestion_match, st.eq_word_count, st.standard_word_count, st.match_type,
		p.status_analysis, p.fast_analysis_wc,p.tm_analysis_wc,p.standard_analysis_wc,
		st.tm_analysis_status as st_status_analysis
			from segment_translations as st
			join segments as s on st.id_segment=s.id
			join jobs as j on j.id=st.id_job
			join projects as p on p.id=j.id_project
			where p.id=$pid and p.status_analysis in ('NEW', 'FAST_OK','DONE') and st.match_type<>''";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );
    $err     = $db->get_error();
    $errno   = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

function getProjectForVolumeAnalysis( $type, $limit = 1 ) {

    $query_limit = " limit $limit";

    $type = strtoupper( $type );

    if ( $type == 'FAST' ) {
        $status_search = "NEW";
    } else {
        $status_search = "FAST_OK";
    }
    $query = "select p.id, id_tms, id_mt_engine, group_concat( distinct j.id ) as jid_list
		from projects p
		inner join jobs j on j.id_project=p.id
		where status_analysis = '$status_search'
		group by 1
		order by id $query_limit
		";
    $db    = Database::obtain();

    $results = $db->fetch_array( $query );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

function getSegmentsForFastVolumeAnalysys( $pid ) {
    $query   = "select concat( s.id, '-', group_concat( distinct concat( j.id, ':' , j.password ) ) ) as jsid, s.segment
		from segments as s 
		inner join files_job as fj on fj.id_file=s.id_file
		inner join jobs as j on fj.id_job=j.id
		left join segment_translations as st on st.id_segment = s.id
		where j.id_project='$pid'
        and IFNULL( st.locked, 0 ) = 0
		group by s.id
		order by s.id";
    $db      = Database::obtain();
    $results = $db->fetch_array( $query );
    $err     = $db->get_error();
    $errno   = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

/**
 *
 * Not used
 *
 * @deprecated
 *
 * @param $jid
 *
 * @return array
 */
function getSegmentsForTMVolumeAnalysys( $jid ) {
    $query = "select s.id as sid ,segment ,raw_word_count,st.match_type from segments s
		left join segment_translations st on st.id_segment=s.id

		where st.id_job='$jid' and st.match_type<>'' and st.tm_analysis_status='UNDONE' and s.raw_word_count>0
		limit 100";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );
    $err     = $db->get_error();
    $errno   = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

//function insertSegmentTMAnalysis( $id_segment, $id_job, $suggestion, $suggestion_json, $suggestion_source, $match_type, $eq_word ) {
//    $db   = Database::obtain();
//    $data = array();
//    //$data['id_segment'] = $id_segment;
//    //$data['id_job'] = $id_job;
//    $data[ 'match_type' ]        = $match_type;
//    $data[ 'eq_word_count' ]     = $eq_word;
//    $data[ 'suggestion' ]        = $suggestion;
//    $data[ 'translation' ]       = $suggestion;
//    $data[ 'suggestions_array' ] = $suggestion_json;
//    $data[ 'suggestion_source' ] = $suggestion_source;
//
//
//    $where = "  id_job=$id_job and id_segment=$id_segment ";
//    $db->update( 'segment_translations', $data, $where );
//
//
//    $db->insert( 'segment_translations', $data );
//    $err   = $db->get_error();
//    $errno = $err[ 'error_code' ];
//
//    if ( $errno == 1062 ) {
//        unset( $data[ 'id_job' ] );
//        unset( $data[ 'id_segment' ] );
//        $where = "  id_job=$id_job and id_segment=$id_segment ";
//        $db->update( 'segment_translations', $data, $where );
//        $err   = $db->get_error();
//        $errno = $err[ 'error_code' ];
//    }
//
//    if ( $errno != 0 ) {
//        if ( $errno != 1062 ) {
//            log::doLog( $err );
//        }
//
//        return $errno * -1;
//    }
//
//    $data2[ 'fast_analysis_wc' ] = $total_eq_wc;
//    $where                       = " id = $pid";
//    $db->update( 'projects', $data2, $where );
//    $err   = $db->get_error();
//    $errno = $err[ 'error_code' ];
//    if ( $errno != 0 ) {
//        log::doLog( $err );
//
//        return $errno * -1;
//    }
//
//    return $db->affected_rows;
//}

function changeTmWc( $pid, $pid_eq_words, $pid_standard_words ) {
    // query  da incorporare nella changeProjectStatus
    $db                             = Database::obtain();
    $data                           = array();
    $data[ 'tm_analysis_wc' ]       = $pid_eq_words;
    $data[ 'standard_analysis_wc' ] = $pid_standard_words;
    $where                          = " id =$pid";
    $db->update( 'projects', $data, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $db->affected_rows;
}

function insertFastAnalysis( $pid, $fastReport, $equivalentWordMapping, $perform_Tms_Analysis = true ) {
    $db   = Database::obtain();
    $data = array();

    $total_eq_wc       = 0;
    $total_standard_wc = 0;
    foreach ( $fastReport as $k => $v ) {
        $jid_fid    = explode( "-", $k );
        $id_segment = $jid_fid[ 0 ];
        $id_jobs    = $jid_fid[ 1 ];

        $type = strtoupper( $v[ 'type' ] );

        if ( array_key_exists( $type, $equivalentWordMapping ) ) {
            $eq_word = ( $v[ 'wc' ] * $equivalentWordMapping[ $type ] / 100 );
            if ( $type == "INTERNAL" ) {
            }
        } else {
            $eq_word = $v[ 'wc' ];
        }
        $total_eq_wc += $eq_word;
        $standard_words = $eq_word;
        if ( $type == "INTERNAL" or $type == "MT" ) {
            $standard_words = $v[ 'wc' ] * $equivalentWordMapping[ "NO_MATCH" ] / 100;
        }
        $total_standard_wc += $standard_words;

        $id_jobs = explode( ',', $id_jobs );
        foreach ( $id_jobs as $id_job ) {
            $data[ 'id_segment' ]          = $id_segment;
            $data[ 'id_job' ]              = $id_job;
            $data[ 'match_type' ]          = $type;
            $data[ 'eq_word_count' ]       = $eq_word;
            $data[ 'standard_word_count' ] = $standard_words;

            // query for this data is after insert/update
            $data_innodb[ 'id_job' ]     = $data[ 'id_job' ];
            $data_innodb[ 'id_segment' ] = $data[ 'id_segment' ];

            $db->insert( 'segment_translations', $data );
            $err   = $db->get_error();
            $errno = $err[ 'error_code' ];

            if ( $errno == 1062 ) {
                unset( $data[ 'id_job' ] );
                unset( $data[ 'id_segment' ] );
                $where = "  id_job=$id_job and id_segment=$id_segment ";
                $db->update( 'segment_translations', $data, $where );
                $err   = $db->get_error();
                $errno = $err[ 'error_code' ];
            }


            if ( $errno != 0 ) {
                if ( $errno != 1062 ) {
                    log::doLog( $err );
                }

                return $errno * -1;
            }

            if ( $data[ 'eq_word_count' ] > 0 && $perform_Tms_Analysis ) {
                //			$db->query("SET autocommit=0");
                //			$db->query("START TRANSACTION");
                $db->insert( 'segment_translations_analysis_queue', $data_innodb );
                $err   = $db->get_error();
                $errno = $err[ 'error_code' ];
                if ( $errno != 0 and $errno != 1062 ) {

                    log::doLog( $err );

                    //				$db->query("ROLLBACK");
                    //				$db->query("SET autocommit=1");
                    return $errno * -1;
                }
                //			$db->query("COMMIT");
                //			$db->query("SET autocommit=1");
            }
        }
    }

    $data2[ 'fast_analysis_wc' ]     = $total_eq_wc;
    $data2[ 'standard_analysis_wc' ] = $total_standard_wc;

    $where = " id = $pid";
    $db->update( 'projects', $data2, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $db->affected_rows;
}

function changeProjectStatus( $pid, $status, $if_status_not = array() ) {


    $data[ 'status_analysis' ] = $status;
    $where                     = "id=$pid ";

    if ( !empty( $if_status_not ) ) {
        foreach ( $if_status_not as $v ) {
            $where .= " and status_analysis<>'$v' ";
        }
    }


    $db = Database::obtain();
    $db->update( 'projects', $data, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $db->affected_rows;
}

function changePassword( $res, $id, $password ) {
    //    $new_password = 'changedpassword';
    $new_password = $password;

    if ( $res == "prj" ) {
        $query = "update projects set password=\"$new_password\" where id=$id";
    } else {
        $query = "update jobs set password=\"$new_password\" where id=$id";
    }

    $db = Database::obtain();
    $db->query( $query );

    return $new_password;

}

function cancelJob( $res, $id ) {

    if ( $res == "prj" ) {
        $query = "update jobs set status_owner='cancelled' where id_project=$id";
    } else {
        $query = "update jobs set status_owner='cancelled' where id=$id";
    }
    /*
       if ($res == "prj") {
       $query = "update jobs set status='cancelled' where id_project=$id";
       } else {
       $query = "update jobs set status='cancelled' where id=$id";
       }
     */
    //    $query = "update jobs set disabled=1 where id=$id";

    $db = Database::obtain();
    $db->query( $query );

}

function archiveJob( $res, $id ) {

    if ( $res == "prj" ) {
        $query = "update jobs set status='archived' where id_project=$id";
    } else {
        $query = "update jobs set status='archived' where id=$id";
    }
    /*
       if ($res == "prj") {
       $query = "update jobs set disabled=1 where id_project=$id";
       } else {
       $query = "update jobs set disabled=1 where id=$id";
       }
     */
    //    $query = "update jobs set disabled=1 where id=$id";

    $db = Database::obtain();
    $db->query( $query );

}

function updateProjectOwner( $ownerEmail, $project_id ) {
    $db              = Database::obtain();
    $data            = array();
    $data[ 'owner' ] = $ownerEmail;
    $where           = sprintf( " id_project = %u ", $project_id );
    $result          = $db->update( 'jobs', $data, $where );

    return $result;
}

function updateJobsStatus( $res, $id, $status, $only_if, $undo ) {

    if ( $res == "prj" ) {
        $status_filter_query = ( $only_if ) ? " and status_owner='$only_if'" : "";
        $arStatus            = explode( ',', $status );

        $test = count( explode( ':', $arStatus[ 0 ] ) );
        if ( ( $test > 1 ) && ( $undo == 1 ) ) {
            $cases = '';
            $ids   = '';
            foreach ( $arStatus as $item ) {
                $ss = explode( ':', $item );
                $cases .= " when id=$ss[0] then '$ss[1]'";
                $ids .= "$ss[0],";
            }
            $ids   = trim( $ids, ',' );
            $query = "update jobs set status_owner= case $cases end where id in ($ids)" . $status_filter_query;

        } else {
            $query = "update jobs set status_owner='$status' where id_project=$id" . $status_filter_query;
        }


    } else {
        $query = "update jobs set status_owner='$status' where id=$id";
    }
    /*
       if ($res == "prj") {
       $query = "update jobs set status='cancelled' where id_project=$id";
       } else {
       $query = "update jobs set status='cancelled' where id=$id";
       }
     */
    //    $query = "update jobs set disabled=1 where id=$id";

    $db = Database::obtain();
    $db->query( $query );

}

function getCurrentJobsStatus( $pid ) {

    $query = "select id,status_owner from jobs where id_project=$pid";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;

}


function setSegmentTranslationError( $sid, $jid ) {


    $data[ 'tm_analysis_status' ] = "DONE"; // DONE . I don't want it remains in an incostistent state
    $where                        = " id_segment=$sid and id_job=$jid ";


    $db = Database::obtain();
    $db->update( 'segment_translations', $data, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $db->affected_rows;
}

// tm analysis threaded

function getNextSegmentAndLock() {

    //get link
    $db = Database::obtain();

    //declare statements
    //begin transaction
    $q1 = "SET autocommit=0";
    $q2 = "START TRANSACTION";
    //lock row
    $q3 = "select id_segment, id_job from segment_translations_analysis_queue where locked=0 for update";
    //end transaction
    $q4 = "ROLLBACK";
    $q5 = "COMMIT";
    $q6 = "SET autocommit=1";

    //execute statements
    //start locking
    $db->query( $q1 );
    $db->query( $q2 );
    //query
    $res = $db->query_first( $q3 );
    //if nothing useful
    if ( empty( $res ) ) {
        //empty result
        $res = "";
        $db->query( $q4 );
    } else {
        //else
        //take note of IDs
        $id_job     = $res[ 'id_job' ];
        $id_segment = $res[ 'id_segment' ];

        //set lock flag on row
        $data[ 'locked' ] = 1;
        $where            = " id_job=$id_job and id_segment=$id_segment ";
        //update segment
        $db->update( "segment_translations_analysis_queue", $data, $where );
        $err   = $db->get_error();
        $errno = $err[ 'error_code' ];
        //if something went wrong
        if ( $errno != 0 ) {
            log::doLog( $err );
            $db->query( $q4 );
            //return error code
            $res = -1;
        } else {
            //if everything went well, commit
            $db->query( $q5 );
        }
    }
    //release locks and end transaction
    $db->query( $q6 );

    //return stuff
    return $res;
}

function resetLockSegment() {
    $db               = Database::obtain();
    $data[ 'locked' ] = 0;
    $where            = " locked=1 ";
    $db->update( "segment_translations_analysis_queue", $data, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return -1;
    }

    return 0;
}

function deleteLockSegment( $id_segment, $id_job, $mode = "delete" ) {
    $db = Database::obtain();
    if ( $mode == "delete" ) {
        $q = "delete from segment_translations_analysis_queue where id_segment=$id_segment and id_job=$id_job";
    } else {
        $db->query( "SET autocommit=0" );
        $db->query( "START TRANSACTION" );
        $q = "update segment_translations_analysis_queue set locked=0 where id_segment=$id_segment and id_job=$id_job";
        $db->query( "COMMIT" );
        $db->query( "SET autocommit=1" );
    }
    $db->query( $q );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return -1;
    }

    return 0;
}

function getSegmentForTMVolumeAnalysys( $id_segment, $id_job ) {
    $query = "select s.id as sid ,s.segment ,raw_word_count,
		st.match_type, j.source, j.target, j.id as jid, j.id_translator,
		j.id_tms, j.id_mt_engine, p.id as pid
			from segments s
			inner join segment_translations st on st.id_segment=s.id
			inner join jobs j on j.id=st.id_job
			inner join projects p on p.id=j.id_project
			where
			p.status_analysis='FAST_OK' and
			st.id_segment=$id_segment and st.id_job=$id_job
			limit 1";

    $db      = Database::obtain();
    $results = $db->query_first( $query );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

/**
 * @deprecated Not Used Anywhere
 *
 * @return array|bool
 */
function getNextSegmentForTMVolumeAnalysys() {
    $query = "select s.id as sid ,s.segment ,raw_word_count,
		st.match_type, j.source, j.target, j.id as jid, j.id_translator,
		p.id_engine_mt,p.id as pid
			from segments s
			inner join segment_translations st on st.id_segment=s.id
			inner join jobs j on j.id=st.id_job
			inner join projects p on p.id=j.id_project

			where  
			st.tm_analysis_status='UNDONE' and 
			p.status_analysis='FAST_OK' and 
			s.raw_word_count>0   and
			locked=0
			order by s.id
			limit 1";

    $db      = Database::obtain();
    $results = $db->query_first( $query );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

function lockUnlockTable( $table, $lock_unlock = "unlock", $mode = "READ" ) {
    $db = Database::obtain();
    if ( $lock_unlock == "lock" ) {
        $query = "LOCK TABLES $table $mode";
    } else {
        $query = "UNLOCK TABLES";
    }

    $results = $db->query( $query );
    $err     = $db->get_error();
    $errno   = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

function lockUnlockSegment( $sid, $jid, $value ) {


    $data[ 'locked' ] = $value;
    $where            = "id_segment=$sid and id_job=$jid ";


    $db = Database::obtain();
    $db->update( 'segment_translations', $data, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $db->affected_rows;
}

function countSegments( $pid ) {
    $db = Database::obtain();

    $query = "select  count(s.id) as num_segments
		from segments s 
		inner join files_job fj on fj.id_file=s.id_file
		inner join jobs j on j.id= fj.id_job
		where id_project=$pid and raw_word_count>0
		";

    $results = $db->query_first( $query );


    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        log::doLog( $err );

        return $errno * -1;
    }

    return $results[ 'num_segments' ];
}

function countSegmentsTranslationAnalyzed( $pid ) {
    $db    = Database::obtain();
    $query = "select sum(if(st.tm_analysis_status='DONE',1,0)) as num_analyzed,
		sum(eq_word_count) as eq_wc ,
		sum(standard_word_count) as st_wc
			from segment_translations st
			inner join jobs j on j.id=st.id_job
			where j.id_project=$pid";

    $results = $db->query_first( $query );
    $err     = $db->get_error();
    $errno   = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        log::doLog( "$errno: $err" );

        return $errno * -1;
    }

    return $results;
}

function setJobCompleteness( $jid, $is_completed ) {
    $db    = Database::obtain();
    $query = "update jobs set completed=$is_completed where id=$jid";


    $results = $db->query_first( $query );
    $err     = $db->get_error();
    $errno   = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        log::doLog( "$errno: $err" );

        return $errno * -1;
    }

    return $db->affected_rows;
}

?>
