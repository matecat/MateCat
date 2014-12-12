<?php

include_once 'Database.class.php';

function doSearchQuery( ArrayObject $queryParams ) {
    $db = Database::obtain();

    $key = $queryParams[ 'key' ]; //no escape: not related with Database
    $src = $db->escape( $queryParams[ 'src' ] );
    $trg = $db->escape( $queryParams[ 'trg' ] );

//	Log::doLog( $queryParams );
//	Log::doLog( $trg );

    $where_status = "";
    if ( $queryParams[ 'status' ] != 'all' ) {
        $status       = $queryParams[ 'status' ]; //no escape: hardcoded
        $where_status = " AND st.status = '$status'";
    }

    if ( $queryParams[ 'matchCase' ] ) {
        $SQL_CASE = "";
    } else {
        $SQL_CASE = "LOWER ";
        $src      = strtolower( $src );
        $trg      = strtolower( $trg );
    }

    if ( $queryParams[ 'exactMatch' ] ) {
        $LIKE = "";
    } else {
        $LIKE = "%";
    }

    /**
     * Escape Meta-characters to use in regular expression ( LIKE STATEMENT is treated inside MySQL as a Regexp pattern )
     *
     */
    $_regexpEscapedSrc = preg_replace( '#([\[\]\(\)\*\.\?\^\$\{\}\+\-\|\\\\])#', '\\\\$1', $queryParams[ 'src' ] );
    $_regexpEscapedSrc = $db->escape( $_regexpEscapedSrc );

    $_regexpEscapedTrg = preg_replace( '#([\[\]\(\)\*\.\?\^\$\{\}\+\-\|\\\\])#', '\\\\$1', $queryParams[ 'trg' ] );
    $_regexpEscapedTrg = $db->escape( $_regexpEscapedTrg );

    $query = "";
    if ( $key == "source" ) {

        $query = "SELECT s.id, sum(
			ROUND (
					( LENGTH( s.segment ) - LENGTH( REPLACE ( $SQL_CASE( segment ), $SQL_CASE( '$src' ), '') ) ) / LENGTH('$src') )
			) AS count
			FROM segments s
			INNER JOIN files_job fj on s.id_file=fj.id_file
			LEFT JOIN segment_translations st on st.id_segment = s.id AND st.id_job = fj.id_job
			WHERE fj.id_job = {$queryParams['job']}
		AND s.segment LIKE '" . $LIKE . $_regexpEscapedSrc . $LIKE . "'
			$where_status
			GROUP BY s.id WITH ROLLUP";

    } elseif ( $key == "target" ) {

        $query = "SELECT  st.id_segment as id, sum(
			ROUND (
					( LENGTH( st.translation ) - LENGTH( REPLACE ( $SQL_CASE( st.translation ), $SQL_CASE( '$trg' ), '') ) ) / LENGTH('$trg') )
			) AS count
			FROM segment_translations st
			WHERE st.id_job = {$queryParams['job']}
		AND st.translation LIKE '" . $LIKE . $_regexpEscapedTrg . $LIKE . "'
			AND st.status != 'NEW'
			$where_status
			AND ROUND (
					( LENGTH( st.translation ) - LENGTH( REPLACE ( $SQL_CASE ( st.translation ), $SQL_CASE ( '$trg' ), '') ) ) / LENGTH('$trg') )
			> 0
			GROUP BY st.id_segment WITH ROLLUP";

    } elseif ( $key == 'coupled' ) {

        $query = "SELECT st.id_segment as id
			FROM segment_translations as st
			JOIN segments as s on id = id_segment
			WHERE st.id_job = {$queryParams['job']}
		AND st.translation LIKE '" . $LIKE . $_regexpEscapedTrg . $LIKE . "'
			AND s.segment LIKE '" . $LIKE . $_regexpEscapedSrc . $LIKE . "'
			AND LENGTH( REPLACE ( $SQL_CASE( segment ), $SQL_CASE( '$src' ), '') ) != LENGTH( s.segment )
			AND LENGTH( REPLACE ( $SQL_CASE( st.translation ), $SQL_CASE( '$trg' ), '') ) != LENGTH( st.translation )
			AND st.status != 'NEW'
			$where_status ";

    } elseif ( $key = 'status_only' ) {

        $query = "SELECT st.id_segment as id
			FROM segment_translations as st
			WHERE st.id_job = {$queryParams['job']}
		$where_status ";

    }

//	Log::doLog($query);

    $results = $db->fetch_array( $query );
    $err     = $db->get_error();

//    Log::doLog($results);

    $errno = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        Log::doLog( $err );

        return $errno * -1;
    }

    if ( $key != 'coupled' && $key != 'status_only' ) { //there is the ROLLUP
        $rollup = array_pop( $results );
    }

    //    Log::doLog($results);

    $vector = array();
    foreach ( $results as $occurrence ) {
        $vector[ 'sidlist' ][ ] = $occurrence[ 'id' ];
    }

    $vector[ 'count' ] = @$rollup[ 'count' ]; //can be null, suppress warning

    //    Log::doLog($vector);

    if ( $key != 'coupled' && $key != 'status_only' ) { //there is the ROLLUP
        //there should be empty values because of Sensitive search
        //LIKE is case INSENSITIVE, REPLACE IS NOT
        //empty search values removed
        //ROLLUP counter rules!
        if ( $vector[ 'count' ] == 0 ) {
            $vector[ 'sidlist' ] = null;
            $vector[ 'count' ]   = null;
        }
    }

    return $vector;
}

function doReplaceAll( ArrayObject $queryParams ) {

    $db          = Database::obtain();
    $trg         = $queryParams[ 'trg' ];
    $replacement = $queryParams[ 'replacement' ];

    $where_status = "";
    if ( $queryParams[ 'status' ] != 'all' && $queryParams[ 'status' ] != 'new' ) {
        $status       = $queryParams[ 'status' ]; //no escape: hardcoded
        $where_status = " AND st.status = '$status'";
    }

    if ( $queryParams[ 'matchCase' ] ) {
        $SQL_CASE = "BINARY ";
        $modifier = 'u';
    } else {
        $SQL_CASE = "";
        $modifier = 'iu';
    }

    if ( $queryParams[ 'exactMatch' ] ) {
        $Space_Left  = "[[:space:]]{0,}";
        $Space_Right = "[[:space:]]";
        $replacement = $replacement . " "; //add spaces to replace " a " with "b "
    } else {
        $Space_Left = $Space_Right = ""; // we also want to replace all occurrences in a string: replace "mod" with "dog" in "mod modifier" -> "dog dogifier"
    }

    // this doesn't works because of REPLACE IS ALWAYS CASE SENSITIVE, moreover, we can't perform UNDO
    //    $sql = "UPDATE segment_translations, jobs
    //                SET translation = REPLACE( translation, '{$LIKE}{$trg}{$LIKE}', '{$replacement}' )
    //                WHERE id_job = jobs.id
    //                AND id_job = {$queryParams['job']}
    //                AND jobs.password = '{$queryParams['password']}'
    //                AND id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
    //                AND segment_translations.status != 'NEW'
    //                AND locked != 1
    //                $where_status
    //            ";

    /**
     * Escape Meta-characters to use in regular expression
     *
     */
    $_regexpEscapedTrg = preg_replace( '#([\[\]\(\)\*\.\?\^\$\{\}\+\-\|\\\\])#', '\\\\$1', $trg );

    /**
     * Escape for database
     */
    $regexpEscapedTrg = $db->escape( $_regexpEscapedTrg );

//    Log::doLog( $regexpTrg );

    $sql = "SELECT id_segment, id_job, translation
                FROM segment_translations st
                JOIN jobs ON st.id_job = id AND password = '{$queryParams['password']}' AND id = {$queryParams['job']}
            WHERE id_job = {$queryParams['job']}
            AND id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
                AND st.status != 'NEW'
                AND locked != 1
                AND translation REGEXP $SQL_CASE'{$Space_Left}{$regexpEscapedTrg}{$Space_Right}'
                $where_status
                ";

    //use this for UNDO
    $resultSet = $db->fetch_array( $sql );

//	Log::doLog( $sql );
//	Log::doLog( $resultSet );
//	Log::doLog( "Replace ALL Total ResultSet " . count($resultSet) );

    $sqlBatch = array();
    foreach ( $resultSet as $key => $tRow ) {

//        Log::doLog( "#({$Space_Left}){$_regexpEscapedTrg}{$Space_Right}#$modifier" );
//        Log::doLog( '$1'.$replacement );

        //we get the spaces before needed string and re-apply before substitution because we can't know if there are
        //and how much they are
        $trMod = preg_replace( "#({$Space_Left}){$_regexpEscapedTrg}{$Space_Right}#$modifier", '$1' . $replacement, $tRow[ 'translation' ] );

        /**
         * Escape for database
         */
        $trMod       = $db->escape( $trMod );
        $sqlBatch[ ] = "({$tRow['id_segment']},{$tRow['id_job']},'{$trMod}')";
    }

    //MySQL default max_allowed_packet is 16MB, this system surely need more
    //but we can assume that max translation length is more or less 2.5KB
    // so, for 100 translations of that size we can have 250KB + 20% char strings for query and id.
    // 300KB is a very low number compared to 16MB
    $sqlBatchChunk = array_chunk( $sqlBatch, 100 );

    foreach ( $sqlBatchChunk as $k => $batch ) {

        //WE USE INSERT STATEMENT for it's convenience ( update multiple fields in multiple rows in batch )
        //we try to insert these rows in a table wherein the primary key ( unique by definition )
        //is a coupled key ( id_segment, id_job ), but these values are already present ( duplicates )
        //so make an "ON DUPLICATE KEY UPDATE"
        $sqlInsert = "INSERT INTO segment_translations ( id_segment, id_job, translation )
			VALUES %s
			ON DUPLICATE KEY UPDATE translation = VALUES( translation )";

        $sqlInsert = sprintf( $sqlInsert, implode( ",", $batch ) );

//        Log::doLog( $sqlInsert );

        $db->query( $sqlInsert );

        if ( !$db->affected_rows ) {

            $msg = "\n\n Error ReplaceAll \n\n Integrity failure: \n\n
				- job id            : " . $queryParams[ 'job' ] . "
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
//		Log::doLog( "Replace ALL Batch " . ($k +1) . " - Affected Rows " . ( $db->affected_rows / 2 ) );

    }
//	Log::doLog( "Replace ALL Done." );

}

function getReferenceSegment( $jid, $jpass, $sid, $binaries = null ) {

    $db = Database::obtain();

    $jpass = $db->escape( $jpass );
    $sid   = (int)$sid;
    $jid   = (int)$jid;

    if ( $binaries != null ) {
        $binaries = ', serialized_reference_binaries';
    }

    $query = "SELECT serialized_reference_meta $binaries
		FROM segments s
		JOIN files_job using ( id_file )
		JOIN jobs on files_job.id_job = jobs.id
		LEFT JOIN file_references fr ON s.id_file_part = fr.id
		WHERE s.id  = $sid
		AND jobs.id = $jid
		AND jobs.password = '$jpass'
		";

    return $db->query_first( $query );
}

function getUserData( $id ) {

    $db = Database::obtain();

    $id    = $db->escape( $id );
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
    $results = $db->insert( 'users', $data );

    return $results;
}

function tryInsertUserFromOAuth( $data ) {
    //check if user exists
    $db = Database::obtain();

    //avoid injection
    $data[ 'email' ] = $db->escape( $data[ 'email' ] );

    $query   = "SELECT uid, email FROM users WHERE email='" . $data[ 'email' ] . "'";
    $results = $db->query_first( $query );

    if ( 0 == count( $results ) or false == $results ) {
        //new client
        $results = insertUser( $data );
        //check outcome
        if ( $results ) {
            $cid['email'] = $data[ 'email' ];
            $cid['uid'] = $results;
        } else {
            $cid = false;
        }
    } else {
        $cid['email'] = $data[ 'email' ];
        $cid['uid'] = $results[ 'uid' ];
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
 *      'source'            => 'it-IT',
 *      'target'            => 'en-US',
 *      'id_mt_engine'      => 1,
 *      'id_tms'            => 1,
 *      'id_translator'     => '',
 *      'status'            => 'active',
 *      'password'          => 'UnDvBUXMiSBGNjSV',
 *      'job_first_segment' => '1234',
 *      'job_last_segment'  => '1456',
 * );
 * </pre>
 *
 * @param int         $id_job
 * @param null|string $password
 *
 * @return array $jobData
 */
function getJobData( $id_job, $password = null ) {

    $query = "SELECT id, source, target, id_mt_engine, id_tms, id_translator, tm_keys, status_owner AS status, password,
		job_first_segment, job_last_segment, create_date, owner,
		new_words, draft_words, translated_words, approved_words, rejected_words, id_project, subject
			FROM jobs
			WHERE id = %u";

    if ( !empty( $password ) ) {
        $query .= " and password = '%s' ";
    }

    $query   = sprintf( $query, $id_job, $password );
    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    if ( empty( $password ) ) {
        return $results;
    }

    return $results[ 0 ];
}

function getEngineData( $id ) {

    if ( is_array( $id ) ) {
        $id = explode( ",", $id );
    }

    $query = "select * from engines where  id IN ($id)";

    try {
        $memcacheHandler = MemcacheHandler::getInstance();
    } catch ( Exception $e ) {
        Log::doLog( $e->getMessage() );
        Log::doLog( "No Memcache server(s) configured." );
    }

    if ( isset( $memcacheHandler ) && !empty( $memcacheHandler ) ) {
        $_existingResult = $memcacheHandler->get( $query );
        if ( !empty( $_existingResult ) ) {
            return $_existingResult;
        }
    }

    $db = Database::obtain();

    $results = $db->fetch_array( $query );
    $err     = $db->get_error();
    $errno   = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );

        return $errno * -1;
    }

    if ( isset( $memcacheHandler ) && !empty( $memcacheHandler ) ) {
        $memcacheHandler->set( $query, @$results[ 0 ], 60 * 5 );
    }

    return @$results[ 0 ];
}

/**
 * @param $job_id       int The job ID
 * @param $job_password int The job password
 *
 * @return mixed If query was successful, this method returns the encoded tm keys string.<br/>
 *               Otherwise, it returns the query's error code
 */
function getJobTmKeys( $job_id, $job_password ) {
    $query = "SELECT tm_keys FROM jobs WHERE id = %d AND password = '%s' ";

    $db      = Database::obtain();
    $results = $db->fetch_array(
            sprintf( $query, $job_id, $job_password )
    );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );

        return $errno * -1;
    }

    return $results[ 0 ][ 'tm_keys' ];
}

/**
 * @param $job_id       int     The job id
 * @param $job_password string  The job password
 * @param $tmKeysString string  A json_encoded array of TmKeyManagement_TmKeyStruct objects
 *
 * @return int|null Returns null if everything went ok, otherwise it returns the mysql error code
 */
function setJobTmKeys( $job_id, $job_password, $tmKeysString ) {
    $query = "UPDATE jobs SET tm_keys = '%s' WHERE id = %d AND password = '%s'";

    $db = Database::obtain();
    $db->query( sprintf( $query, $db->escape($tmKeysString), (int)$job_id, $job_password ) );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );
        throw new Exception( $err, -$errno );
    }
}

function getSegment( $id_segment ) {
    $db         = Database::obtain();
    $id_segment = $db->escape( $id_segment );
    $query      = "select * from segments where id=$id_segment";
    $results    = $db->query_first( $query );

    return $results;
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
    $db  = Database::obtain();
    $jid = $db->escape( $jid );

    $query = "SELECT id_segment, serialized_errors_list
		FROM segment_translations
		JOIN jobs ON jobs.id = id_job AND id_segment BETWEEN jobs.job_first_segment AND jobs.job_last_segment
		WHERE jobs.id = $jid
		AND jobs.password = '$jpassword'
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

/**
 * @param $id_translator
 *
 * @return null
 *
 * @deprecated
 */
function getTranslatorKey( $id_translator ) {

    $db = Database::obtain();

    $id_translator = $db->escape( $id_translator );
    $query         = "select mymemory_api_key from translators where username='$id_translator'";

    $db->query_first( $query );
    $results = $db->query_first( $query );

    $res = null;
    if ( ( is_array( $results ) ) AND ( array_key_exists( "mymemory_api_key", $results ) ) ) {
        $res = $results[ 'mymemory_api_key' ];
    }

    return $res;
}

function getEngines( $type = "MT" ) {
    $query = "select id,name from engines where type='$type' and active=1";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

/**
 * @param $jid
 * @param $jPassword
 *
 * @return array
 */
function getTranslationsForTMXExport( $jid, $jPassword ){

    $db = Database::obtain();
    $jPassword = $db->escape( $jPassword );

    $sql = "
        SELECT id_segment, segment_translations.id_job, filename, segment, translation, translation_date
        FROM segment_translations
        JOIN segments ON id = id_segment

        JOIN files ON segments.id_file = files.id

        JOIN jobs ON jobs.id = segment_translations.id_job AND password = '" . $db->escape( $jPassword ) ."'

            WHERE segment_translations.id_job = " . (int)$jid . "
            AND segment_translations.status = '" . Constants_TranslationStatus::STATUS_TRANSLATED . "'
            AND show_in_cattool = 1
";


    $results = $db->fetch_array( $sql );

    $err     = $db->get_error();
    $errno   = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        Log::doLog( $err );
        return $errno * -1;
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
        Log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

function getSegmentsInfo( $jid, $password ) {

    $query = "select j.id as jid, j.id_project as pid,j.source,j.target,
		j.last_opened_segment, j.id_translator as tid, j.id_tms, j.id_mt_engine,
		p.id_customer as cid, j.id_translator as tid, j.status_owner as status,
		j.owner as job_owner, j.create_date, j.last_update, j.tm_keys,

		j.job_first_segment, j.job_last_segment,
		j.new_words, j.draft_words, j.translated_words, j.approved_words, j.rejected_words,

		p.name as pname, p.create_date , fj.id_file, p.status_analysis,
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
        Log::doLog( $err );

        return $errno * -1;
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

function getMoreSegments( $jid, $password, $step = 50, $ref_segment, $where = 'after' ) {

    switch ( $where ) {
        case 'after':
            $ref_point = $ref_segment;
            break;
        case 'before':
            $ref_point = $ref_segment - ( $step + 1 );
            break;
        case 'center':
            $ref_point = ( (float)$ref_segment ) - (int)( $step / 2 );
            break;
    }

    //	$ref_point = ($where == 'center')? ((float) $ref_segment) - 100 : $ref_segment;

    $query = "SELECT j.id AS jid, j.id_project AS pid,j.source,j.target, j.last_opened_segment, j.id_translator AS tid,
		p.id_customer AS cid, j.id_translator AS tid,
		p.name AS pname, p.create_date , fj.id_file,
		f.filename, f.mime_type, s.id AS sid, s.segment, s.segment_hash, s.raw_word_count, s.internal_id,
		IF (st.status='NEW',NULL,st.translation) AS translation,
		st.status, COALESCE( time_to_edit, 0 ) as time_to_edit,
		s.xliff_ext_prec_tags, s.xliff_ext_succ_tags, st.serialized_errors_list, st.warning,

		IF( ( s.id BETWEEN j.job_first_segment AND j.job_last_segment ) , 'false', 'true' ) AS readonly
		, COALESCE( autopropagated_from, 0 ) as autopropagated_from

			,IF( fr.id IS NULL, 'false', 'true' ) as has_reference

			FROM jobs j
			INNER JOIN projects p ON p.id=j.id_project
			INNER JOIN files_job fj ON fj.id_job=j.id
			INNER JOIN files f ON f.id=fj.id_file
			INNER JOIN segments s ON s.id_file=f.id
			LEFT JOIN segment_translations st ON st.id_segment=s.id AND st.id_job=j.id
			LEFT JOIN file_references fr ON s.id_file_part = fr.id
			WHERE j.id = $jid
			AND j.password = '$password'
			AND s.id > $ref_point AND s.show_in_cattool = 1
			LIMIT 0, $step ";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

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
        AND segment_translations.status = '" . Constants_TranslationStatus::STATUS_TRANSLATED . "'
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

        $query = sprintf( $queryForTranslationMismatch, $db->escape( $jpassword ), $sid, $jid, $sid );
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

        $query = sprintf( $queryForMismatchesInJob, $db->escape( $jpassword ), $jid );
    }

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

function addTranslation( array $_Translation ) {

    $db = Database::obtain();

    $query = "INSERT INTO `segment_translations` ";

    foreach ( $_Translation as $key => $val ) {

        if ( $key == 'translation' ) {
            $_Translation[ $key ] = "'" . $db->escape( $val ) . "'";
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
            $_Translation[ $key ] = "'" . $db->escape( $val ) . "'";
        }
    }

    $query .= "(" . implode( ", ", array_keys( $_Translation ) ) . ") VALUES (" . implode( ", ", array_values( $_Translation ) ) . ")";

    $query .= "
				ON DUPLICATE KEY UPDATE
				status = {$_Translation['status']},
                suggestion_position = {$_Translation['suggestion_position']},
                serialized_errors_list = {$_Translation['serialized_errors_list']},
                time_to_edit = time_to_edit + VALUES( time_to_edit ),
                translation = {$_Translation['translation']},
                translation_date = {$_Translation['translation_date']},
                warning = " . $_Translation[ 'warning' ];

    if ( isset( $_Translation[ 'autopropagated_from' ] ) ) {
        $query .= " , autopropagated_from = NULL";
    }

    if ( empty( $_Translation[ 'translation' ] ) && !is_numeric( $_Translation[ 'translation' ] ) ) {
        $msg = "\n\n Error setTranslationUpdate \n\n Empty translation found after DB Escape: \n\n " . var_export( array_merge( array( 'db_query' => $query ), $_POST ), true );
        Log::doLog( $msg );
        Utils::sendErrMailReport( $msg );

        return -1;
    }

    Log::doLog( $query );

    $db->query( $query );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];

//    Log::doLog( $err );
//    Log::doLog( $db->affected_rows );

    if ( $errno != 0 ) {
        Log::doLog( "$errno: " . var_export( $err, true ) );

        return $errno * -1;
    }

    return $db->affected_rows;
}

/**
 * This function propagates the translation to every identical sources in the chunk/job
 *
 * @param $params
 * @param $job_data
 * @param $propagateToTranslated
 * @param $_idSegment
 *
 * @throws Exception
 * @return int
 */
function propagateTranslation( $params, $job_data, $_idSegment, $propagateToTranslated = false ) {

    $db = Database::obtain();

    $st_translated = Constants_TranslationStatus::STATUS_TRANSLATED;
    $st_new        = Constants_TranslationStatus::STATUS_NEW;
    $st_draft      = Constants_TranslationStatus::STATUS_DRAFT;

    $q = array();
    foreach ( $params as $key => $value ) {
        if ( $key == 'status' ) {

            if ( $propagateToTranslated ) {
                $q[ ]      = $key . " = IF( status = '$st_translated' , '$st_translated', '" . $db->escape( $value ) . "' )";
                $andStatus = "AND status IN ( '$st_draft', '$st_new', '$st_translated' )";
            } else {
                $q[ ]      = $key . " = '" . $db->escape( $value ) . "'";
                $andStatus = "AND status IN ( '$st_draft', '$st_new' )";
            }
        } elseif ( is_bool( $value ) ) {
            $q[ ] = $key . " = " . var_export( (bool)$value, true );
        } elseif ( !is_numeric( $value ) ) {
            $q[ ] = $key . " = '" . $db->escape( $value ) . "'";
        } else {
            $q[ ] = $key . " = " . (float)$value;
        }
    }

    //the job password seems a better idea to search only the segments in the actual chunk,
    //but it needs an update in join on 2 tables.
    //job_first_segment and job_last_segment should do the same thing
    $TranslationPropagate = "
        UPDATE segment_translations
        SET " . implode( ", ", $q ) . "
        WHERE id_job = {$params['id_job']}
        AND segment_hash = '" . $params[ 'segment_hash' ] . "'
        $andStatus
        AND id_segment BETWEEN {$job_data['job_first_segment']} AND {$job_data['job_last_segment']}
        AND id_segment != $_idSegment
    ";

//    Log::doLog( $TranslationPropagate );

    $db->query( $TranslationPropagate );

    $err = $db->get_error();

    if ( $err[ 'error_code' ] != 0 ) {
        throw new Exception( "Error in propagating Translation: " . $err[ 'error_code' ] . ": " . $err[ 'error_description' ]
                . "\n" . $TranslationPropagate . "\n" . var_export( $params, true ),
                -$err[ 'error_code' ] );
    }

    return $db->affected_rows;
}

/*
function setTranslationUpdate( $id_segment, $id_job, $status, $time_to_edit, $translation, $errors, $chosen_suggestion_index, $warning = 0 ) {

	// need to use the plain update instead of library function because of the need to update an existent value in db (time_to_edit)
	$now = date( "Y-m-d H:i:s" );
	$db  = Database::obtain();

	$translation = $db->escape( $translation );
	$status      = $db->escape( $status );

	$q = "UPDATE segment_translations
	            SET status='$status',
	            suggestion_position='$chosen_suggestion_index',
	            serialized_errors_list='$errors',
	            time_to_edit = time_to_edit + $time_to_edit,
	            translation='$translation',
	            translation_date='$now',
	            warning=" . (int)$warning;

    //we put the patch here for convenience
    if( $status == 'TRANSLATED' ){
        $q .= ", autopropagated_from = NULL";
    }

	$q .= " WHERE id_segment=$id_segment and id_job=$id_job";

	if( empty( $translation ) && !is_numeric( $translation ) ){
		$msg = "\n\n Error setTranslationUpdate \n\n Empty translation found: \n\n " . var_export( array_merge( array( 'db_query' => $q ), $_POST ), true );
		Log::doLog( $msg );
		Utils::sendErrMailReport( $msg );
	}

	$db->query( $q );
	$err   = $db->get_error();
	$errno = $err[ 'error_code' ];

	if ( $errno != 0 ) {
		Log::doLog( "$errno: " . var_export( $err, true ) );
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

	if( empty( $translation ) && !is_numeric( $translation ) ){
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
			Log::doLog( "$errno: " . var_export( $err, true ) );
		}
		return $errno * -1;
	}

	return $db->affected_rows;
}
*/

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
        Log::doLog( $err );

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

    $data[ 'mt_qe' ] = $mt_qe;

    $db = Database::obtain();
    $db->insert( 'segment_translations', $data );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        if ( $errno != 1062 ) {
            Log::doLog( $err );
        }

        return $errno * -1;
    }

    return $db->affected_rows;
}

function setCurrentSegmentInsert( $id_segment, $id_job, $password ) {
    $data                          = array();
    $data[ 'last_opened_segment' ] = $id_segment;

    $where = "id = $id_job AND password = '$password'";

    $db = Database::obtain();
    $db->update( 'jobs', $data, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );

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

function getCurrentTranslationAndLock( $id_job, $id_segment ) {

    $query = "SELECT * FROM segment_translations WHERE id_segment = %u AND id_job = %u FOR UPDATE";
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
				IF( IFNULL( st.eq_word_count, -1 ) = -1, s.raw_word_count, st.eq_word_count)
		   ) as TOTAL,
		SUM(
				IF(
					st.status IS NULL OR
					st.status='NEW',
					IF( IFNULL( st.eq_word_count, -1 ) = -1 , s.raw_word_count, st.eq_word_count),0)
		   ) as NEW,
		SUM(
				IF(
					st.status IS NULL OR
					st.status='DRAFT' OR
					st.status='NEW',
					IF( IFNULL( st.eq_word_count, -1 ) = -1 , s.raw_word_count, st.eq_word_count),0)
		   ) as DRAFT,
		SUM(
				IF(st.status='REJECTED',
					IF( IFNULL( st.eq_word_count, -1 ) = -1 , s.raw_word_count, st.eq_word_count),0
				  )
		   ) as REJECTED,
		SUM(
				IF(st.status='TRANSLATED',
					IF( IFNULL( st.eq_word_count, -1 ) = -1 , s.raw_word_count, st.eq_word_count),0
				  )
		   ) as TRANSLATED,
		SUM(
				IF(st.status='APPROVED',
					IF( IFNULL( st.eq_word_count, -1 ) = -1, s.raw_word_count, st.eq_word_count),0
				  )
		   ) as APPROVED

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

function getOriginalFile( $id_file ) {

    $query = "SELECT xliff_file FROM files WHERE id=" . $id_file;

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

function getUpdatedTranslations( $timestamp, $first_segment, $last_segment ) {
    $query = "SELECT id_segment as sid, status,translation from segment_translations
		WHERE
		id_segment BETWEEN $first_segment AND $last_segment
		AND translation_date > FROM_UNIXTIME($timestamp)";

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
			AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
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
    $data[ 'id_customer' ]       = $projectStructure[ 'id_customer' ];
    $data[ 'name' ]              = $projectStructure[ 'project_name' ];
    $data[ 'create_date' ]       = date( "Y-m-d H:i:s" );
    $data[ 'status_analysis' ]   = $projectStructure[ 'status' ];
    $data[ 'password' ]          = $projectStructure[ 'ppassword' ];
    $data[ 'remote_ip_address' ] = empty( $projectStructure[ 'user_ip' ] ) ? 'UNKNOWN' : $projectStructure[ 'user_ip' ];
    $query                       = "SELECT LAST_INSERT_ID() FROM projects";

    $db = Database::obtain();
    $db->insert( 'projects', $data );
    $results = $db->query_first( $query );

    return $results[ 'LAST_INSERT_ID()' ];
}

function updateTranslatorJob( $id_job, stdClass $newUser ) {

    $data                       = array();
    $data[ 'username' ]         = $newUser->id;
    $data[ 'email' ]            = '';
    $data[ 'password' ]         = $newUser->pass;
    $data[ 'first_name' ]       = '';
    $data[ 'last_name' ]        = '';
    $data[ 'mymemory_api_key' ] = $newUser->key;

    $db = Database::obtain();

    $res = $db->insert( 'translators', $data ); //ignore errors on duplicate key

    $res = $db->update( 'jobs', array( 'id_translator' => $newUser->id ), ' id = ' . (int)$id_job );
}

//never used email , first_name and last_name
//function insertTranslator( $user, $pass, $api_key, $email = '', $first_name = '', $last_name = '' ) {
function insertTranslator( ArrayObject $projectStructure ) {
    //get link
    $db = Database::obtain();
    //if this user already exists, return without inserting again ( do nothing )
    //this is because we allow to start a project with the bare key

    $private_tm_key = ( is_array($projectStructure[ 'private_tm_key' ] ) ) ?
            $projectStructure[ 'private_tm_key' ][0]['key'] :
            $projectStructure[ 'private_tm_key' ];

    $query   = "SELECT username FROM translators WHERE mymemory_api_key='" . $db->escape( $private_tm_key ) . "'";
    $user_id = $db->query_first( $query );
    $user_id = $user_id[ 'username' ];

    if ( empty( $user_id ) ) {

        $data                       = array();
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
    $data[ 'subject' ]           = $projectStructure[ 'job_subject' ];
    $data[ 'owner' ]             = $owner;
    $data[ 'job_first_segment' ] = $job_segments[ 'job_first_segment' ];
    $data[ 'job_last_segment' ]  = $job_segments[ 'job_last_segment' ];
    $data[ 'tm_keys' ]           = $projectStructure[ 'tm_keys' ];
    $data[ 'payable_rates' ]     = json_encode($projectStructure[ 'payable_rates' ]);

    $query = "SELECT LAST_INSERT_ID() FROM jobs";

    $db = Database::obtain();
    $db->insert( 'jobs', $data );
    $results = $db->query_first( $query );

    return $results[ 'LAST_INSERT_ID()' ];
}

function insertFileIntoMap( $sha1, $source, $target, $deflated_file, $deflated_xliff, $segmentation_rule ) {
    $db                          = Database::obtain();
    $data                        = array();
    $data[ 'sha1' ]              = $sha1;
    $data[ 'source' ]            = $source;
    $data[ 'target' ]            = $target;
    $data[ 'deflated_file' ]     = $deflated_file;
    $data[ 'deflated_xliff' ]    = $deflated_xliff;
    $data[ 'creation_date' ]     = date( "Y-m-d" );
    $data[ 'segmentation_rule' ] = $segmentation_rule;

    $db->insert( 'original_files_map', $data );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 and $errno != 1062 ) {
        Log::doLog( $err );

        return $errno * -1;
    }

    return 1;
}

function getXliffBySHA1( $sha1, $source, $target, $not_older_than_days = 0, $segmentation_rule ) {
    $db                  = Database::obtain();
    $where_creation_date = "";
    if ( $not_older_than_days != 0 ) {
        $where_creation_date = " AND creation_date > DATE_SUB(NOW(), INTERVAL $not_older_than_days DAY)";
    }
    $query = "select deflated_xliff from original_files_map where sha1='$sha1' and source='$source' and target ='$target' and segmentation_rule='$segmentation_rule' $where_creation_date";
    $res   = $db->query_first( $query );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );

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
        Log::doLog( "file too large for mysql packet: increase max_allowed_packed_size" );

        $maxp = $db->query_first( 'SELECT @@global.max_allowed_packet' );
        Log::doLog( "max_allowed_packet: " . $maxp . " > try Upgrade to 500MB" );
        // to set the max_allowed_packet to 500MB
        //FIXME User matecat has no superuser privileges
        //ERROR 1227 (42000): Access denied; you need (at least one of) the SUPER privilege(s) for this operation
        $db->query( 'SET @@global.max_allowed_packet = ' . 500 * 1024 * 1024 );
        $db->insert( 'files', $data );

        $err   = $db->get_error();
        $errno = $err[ 'error_code' ];

        $db->query( 'SET @@global.max_allowed_packet = ' . 32 * 1024 * 1024 ); //restore to 32 MB

        if ( $errno > 0 ) {
            throw new Exception( "Database insert Large file error: $errno ", -$errno );
        }
    } elseif ( $errno > 0 ) {
        Log::doLog( "Database insert Large file error: $errno " );
        throw new Exception( "Database insert Large file error: $errno ", -$errno );
    }

    $results = $db->query_first( $query );

    return $results[ 'LAST_INSERT_ID()' ];
}

function insertFilesJob( $id_job, $id_file ) {
    $data              = array();
    $data[ 'id_job' ]  = (int)$id_job;
    $data[ 'id_file' ] = (int)$id_file;

    $db = Database::obtain();
    $db->insert( 'files_job', $data );
}

function updateJobOwner( $jid, $new_owner ) {

    $db = Database::obtain();

    $new_owner = $db->escape( $new_owner );
    $res       = $db->update( 'jobs', array( 'owner' => $new_owner ), ' id = ' . (int)$jid );

    return $res;
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
 * @param      $pid
 * @param null $project_password
 * @param null $jid
 * @param null $jpassword
 *
 * @return array
 */
function getProjectData( $pid, $project_password = null, $jid = null, $jpassword = null ) {

    $query = "
		SELECT p.name, j.id AS jid, j.password AS jpassword, j.source, j.target, j.payable_rates, f.id, f.id AS id_file,f.filename, p.status_analysis, j.subject,

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
				   AND s.id BETWEEN j.job_first_segment AND j.job_last_segment
				   %s
				   %s

				   GROUP BY f.id, j.id, j.password
				   ORDER BY j.create_date, j.job_first_segment
				   ";

    $and_1 = $and_2 = $and_3 = null;

    $db = Database::obtain();

    if ( !empty( $project_password ) ) {
        $and_1 = " and p.password = '" . $db->escape( $project_password ) . "' ";
    }

    if ( !empty( $jid ) ) {
        $and_2 = " and j.id = " . intval( $jid );
    }

    if ( !empty( $jpassword ) ) {
        $and_3 = " and j.password = '" . $db->escape( $jpassword ) . "' ";
    }

    $query = sprintf( $query, $and_1, $and_2, $and_3 );

    $results = $db->fetch_array( $query );

    //    echo "<pre>" .var_export( $results , true ) . "</pre>"; die();

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

    if ( !empty( $jid ) ) {
        $query = $query . " and j.id = " . intval( $jid );
    }

    $query = $query . " group by 6,2 ";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );

    return $results;
}

/**
 * @param $start                int
 * @param $step                 int
 * @param $search_in_pname      string
 * @param $search_source        string
 * @param $search_target        string
 * @param $search_status        string
 * @param $search_onlycompleted bool
 * @param $filtering
 * @param $project_id           int
 *
 * @return array|int|resource|void
 */
function getProjects( $start, $step, $search_in_pname, $search_source, $search_target, $search_status, $search_onlycompleted, $filtering, $project_id ) {

    $jobs_filter_query     = array();
    $projects_filter_query = array();

    if ( !is_null( $search_in_pname ) && !empty( $search_in_pname ) ) {
        $projects_filter_query[ ] = "p.name like '%" . $search_in_pname . "%'";
    }

    if ( !is_null( $search_source ) && !empty( $search_source ) ) {
        $jobs_filter_query[ ]     = "j.source = '" . $search_source . "'";
        $projects_filter_query[ ] = "j.source = '" . $search_source . "'";
    }

    if ( !is_null( $search_target ) && !empty( $search_target ) ) {
        $jobs_filter_query[ ]     = "j.target = '" . $search_target . "'";
        $projects_filter_query[ ] = "j.target = '" . $search_target . "'";
    }

    if ( !is_null( $search_status ) && !empty( $search_status ) ) {
        $jobs_filter_query[ ]     = "j.status_owner = '" . $search_status . "'";
        $projects_filter_query[ ] = "j.status_owner = '" . $search_status . "'";
    }

    if ( $search_onlycompleted ) {
        $jobs_filter_query[ ]     = "j.completed = 1";
        $projects_filter_query[ ] = "j.completed = 1";
    }

    if ( !is_null( $project_id ) && !empty( $project_id ) ) {
        $jobs_filter_query[ ]     = "j.id_project = " . $project_id;
        $projects_filter_query[ ] = "j.id_project = " . $project_id;
    }

    //FIXME: SESSION CALL SHOULD NOT BE THERE!!!
    $jobs_filter_query [ ]    = "j.owner = '" . $_SESSION[ 'cid' ] . "' and j.id_project in (%s)";
    $projects_filter_query[ ] = "j.owner = '" . $_SESSION[ 'cid' ] . "'";

    $projectsQuery =
            "SELECT p.id AS pid,
                            p.name,
                            p.password,
                            p.tm_analysis_wc
            FROM projects p
            INNER JOIN jobs j ON j.id_project=p.id
            WHERE %s
            GROUP BY 1
            ORDER BY 1 DESC
            LIMIT %d, %d";


    $where_query = 1;
    if ( count( $projects_filter_query ) ) {
        $where_query = implode( " and ", $projects_filter_query );
    }

    $query = sprintf( $projectsQuery, $where_query, $start, $step );

//    Log::doLog( $query );

    $db = Database::obtain();
//    $results = $db->query( "SET SESSION group_concat_max_len = 10000000;" );
    $results = $db->fetch_array( $query );

//    Log::doLog( $results );
    return $results;
}




function getJobsFromProjects(array $projectIDs, $search_source, $search_target, $search_status, $search_onlycompleted) {

    $jobs_filter_query = array();

    if ( !is_null( $search_source ) && !empty( $search_source ) ) {
        $jobs_filter_query[ ] = "j.source = '" . $search_source . "'";
    }

    if ( !is_null( $search_target ) && !empty( $search_target ) ) {
        $jobs_filter_query[ ] = "j.target = '" . $search_target . "'";
    }

    if ( !is_null( $search_status ) && !empty( $search_status ) ) {
        $jobs_filter_query[ ] = "j.status_owner = '" . $search_status . "'";
    }

    if ( $search_onlycompleted ) {
        $jobs_filter_query[ ] = "j.completed = 1";
    }

    //This will be always set. We don't need to check if array is empty.
    $jobs_filter_query [ ] = "j.owner = '" . $_SESSION[ 'cid' ] . "'";

    $where_query = implode( " and ", $jobs_filter_query );
    $ids         = implode( ", ", $projectIDs );

    if ( !count( $ids ) ) {
        $ids[ ] = 0;
    }

    $jobsQuery = "SELECT
                 j.id,
				 j.id_project,
				 j.source,
				 j.target,
				 j.create_date,
				 j.password,
				 j.tm_keys,
				 j.status_owner,
				 j.job_first_segment,
				 j.job_last_segment,
				 j.id_mt_engine,
				 j.id_tms,
				 j.subject,
				(draft_words + new_words) AS DRAFT,
				rejected_words AS REJECT,
				translated_words AS TRANSLATED,
				approved_words AS APPROVED,
                e.name
            FROM jobs j
            LEFT JOIN engines e ON j.id_mt_engine=e.id
            WHERE j.id_project IN (%s) AND %s
            ORDER BY j.id DESC,
                     j.job_first_segment ASC";

    $query = sprintf( $jobsQuery, $ids, $where_query );

//    Log::doLog( $query );

    $db = Database::obtain();
//    $results = $db->query( "SET SESSION group_concat_max_len = 10000000;" );
    $results = $db->fetch_array( $query );

//    Log::doLog( $results );
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

    //Log::doLog('OWNER QUERY:',$owner);

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
    Log::doLog('STATUS QUERY:',$status_query);

    //	$sa_query = ($search_showarchived)? " j.status='archived' and" : "";
    //	$sc_query = ($search_showcancelled)? " j.status='cancelled' and" : "";
     */
    $query_tail        = $pn_query . $ss_query . $st_query . $sst_query . $oc_query . $owner_query;
    $jobs_filter_query = ( $query_tail == '' ) ? '' : 'where ' . $query_tail;
    $jobs_filter_query = preg_replace( '/( and)$/i', '', $jobs_filter_query );

    $query = "select count(distinct id_project) as c

		from projects p
		inner join jobs j on j.id_project=p.id
		left join engines e on j.id_mt_engine=e.id
		left join translators t on j.id_translator=t.username
		$jobs_filter_query";

//    Log::doLog($query);

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
        Log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

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
			";

    $db      = Database::obtain();
    $results = $db->fetch_array( $query );
    $err     = $db->get_error();
    $errno   = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

function getProjectForVolumeAnalysis( $type, $limit = 1 ) {

    $query_limit = " limit $limit";

    $type = strtoupper( $type );

    if ( $type == 'FAST' ) {
        $status_search = Constants_ProjectStatus::STATUS_NEW;
    } else {
        $status_search = Constants_ProjectStatus::STATUS_FAST_OK;
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
        Log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

function getSegmentsForFastVolumeAnalysys( $pid ) {
    $query   = "select concat( s.id, '-', group_concat( distinct concat( j.id, ':' , j.password ) ) ) as jsid, s.segment, j.source, s.segment_hash, s.id as id
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
        Log::doLog( $err );

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
        Log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

function initializeWordCount( WordCount_Struct $wStruct ) {

    $db = Database::obtain();

    $data                       = array();
    $data[ 'new_words' ]        = $wStruct->getNewWords();
    $data[ 'draft_words' ]      = $wStruct->getDraftWords();
    $data[ 'translated_words' ] = $wStruct->getTranslatedWords();
    $data[ 'approved_words' ]   = $wStruct->getApprovedWords();
    $data[ 'rejected_words' ]   = $wStruct->getRejectedWords();

    $where = " id = " . (int)$wStruct->getIdJob() . " AND password = '" . $db->escape( $wStruct->getJobPassword() ) . "'";

    $db->update( 'jobs', $data, $where );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );

        return $errno * -1;
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

    $db->query( $query );

//	Log::doLog( $query . "\n" );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );

        return $errno * -1;
    }

    Log::doLog( "Affected: " . $db->affected_rows . "\n" );

    return $db->affected_rows;
}

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
        Log::doLog( $err );

        return $errno * -1;
    }

    return $db->affected_rows;
}

function insertFastAnalysis( $pid, $fastReport, $equivalentWordMapping, $perform_Tms_Analysis = true ) {

    $db   = Database::obtain();
    $data = array();

    $total_eq_wc       = 0;
    $total_standard_wc = 0;

    $data[ 'id_segment' ]          = null;
    $data[ 'id_job' ]              = null;
    $data[ 'segment_hash' ]        = null;
    $data[ 'match_type' ]          = null;
    $data[ 'eq_word_count' ]       = null;
    $data[ 'standard_word_count' ] = null;

    $data_queue[ 'id_job' ]      = null;
    $data_queue[ 'id_segment' ]  = null;
    $data_queue[ 'pid' ]         = null;
    $data_queue[ 'date_insert' ] = null;

    $segment_translations = "INSERT INTO `segment_translations` ( " . implode( ", ", array_keys( $data ) ) . " ) VALUES ";
    $st_values            = array();

    $segment_translations_queue = "INSERT IGNORE INTO matecat_analysis.segment_translations_analysis_queue ( " . implode( ", ", array_keys( $data_queue ) ) . " ) VALUES ";
    $st_queue_values            = array();

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

            list( $id_job, $job_pass ) = explode( ":", $id_job );

            $data[ 'id_segment' ]          = (int)$id_segment;
            $data[ 'id_job' ]              = (int)$id_job;
            $data[ 'segment_hash' ]        = $db->escape( $v[ 'segment_hash' ] );
            $data[ 'match_type' ]          = $db->escape( $type );
            $data[ 'eq_word_count' ]       = (float)$eq_word;
            $data[ 'standard_word_count' ] = (float)$standard_words;

            $st_values[ ] = " ( '" . implode( "', '", array_values( $data ) ) . "' )";

            if ( $data[ 'eq_word_count' ] > 0 && $perform_Tms_Analysis ) {

                $data_queue[ 'id_job' ] = (int)$id_job;;
                $data_queue[ 'id_segment' ]  = (int)$id_segment;
                $data_queue[ 'pid' ]         = (int)$pid;
                $data_queue[ 'date_insert' ] = date_create()->format( 'Y-m-d H:i:s' );

                $st_queue_values[ ] = " ( '" . implode( "', '", array_values( $data_queue ) ) . "' )";
            } else {
                Log::doLog( 'Skipped Fast Segment: ' . var_export( $data, true ) );
            }
        }
    }

    $chunks_st = array_chunk( $st_values, 500 );

//	echo 'Insert Segment Translations: ' . count($st_values) . "\n";
    Log::doLog( 'Insert Segment Translations: ' . count( $st_values ) );

//	echo 'Queries: ' . count($chunks_st) . "\n";
    Log::doLog( 'Queries: ' . count( $chunks_st ) );

    //USE the MySQL InnoDB isolation Level to protect from thread high concurrency access
    $db->query( 'SET autocommit=0' );
    $db->query( 'START TRANSACTION' );

    foreach ( $chunks_st as $k => $chunk ) {

        $query_st = $segment_translations . implode( ", ", $chunk ) .
                " ON DUPLICATE KEY UPDATE
            match_type = VALUES( match_type ),
                       eq_word_count = VALUES( eq_word_count ),
                       standard_word_count = VALUES( standard_word_count )
                           ";

        $db->query( $query_st );

        //echo "Executed " . ( $k + 1 ) ."\n";
        //Log::doLog(  "Executed " . ( $k + 1 ) );

        $err = $db->get_error();
        if ( $err[ 'error_code' ] != 0 ) {
            Log::doLog( $err );

            return $err[ 'error_code' ] * -1;
        }
    }

    /*
     * IF NO TM ANALYSIS, upload the jobs global word count
     */
    if ( !$perform_Tms_Analysis ) {
        $_details = getProjectSegmentsTranslationSummary( $pid );

        echo "--- trying to initialize job total word count.\n";
        Log::doLog( "--- trying to initialize job total word count." );

        $project_details = array_pop( $_details ); //remove rollup

        foreach ( $_details as $job_info ) {
            $counter = new WordCount_Counter();
            $counter->initializeJobWordCount( $job_info[ 'id_job' ], $job_info[ 'password' ] );
        }
    }
    /* IF NO TM ANALYSIS, upload the jobs global word count */

    //echo "Done.\n";
    //Log::doLog( 'Done.' );

    if ( count( $st_queue_values ) ) {

        $chunks_st_queue = array_chunk( $st_queue_values, 500 );

//		echo 'Insert Segment Translations Queue: ' . count($st_queue_values) . "\n";
        Log::doLog( 'Insert Segment Translations Queue: ' . count( $st_queue_values ) );

//		echo 'Queries: ' . count($chunks_st_queue) . "\n";
        Log::doLog( 'Queries: ' . count( $chunks_st_queue ) );

        foreach ( $chunks_st_queue as $k => $queue_chunk ) {

            $query_st_queue = $segment_translations_queue . implode( ", ", $queue_chunk );

            $db->useDb( 'matecat_analysis' );
            $db->query( $query_st_queue );
            $db->useDb( INIT::$DB_DATABASE );

            //echo "Executed " . ( $k + 1 ) ."\n";
            //Log::doLog(  "Executed " . ( $k + 1 ) );

            $err = $db->get_error();
            if ( $err[ 'error_code' ] != 0 ) {
                $db->query( 'ROLLBACK' );
                $db->query( 'SET autocommit=1' );
                Log::doLog( $err );

                return $err[ 'error_code' ] * -1;
            }
        }

        //echo "Done.\n";
        //Log::doLog( 'Done.' );

    }

    $data2[ 'fast_analysis_wc' ]     = $total_eq_wc;
//    $data2[ 'standard_analysis_wc' ] = $total_standard_wc;

    $where = " id = $pid";
    $db->update( 'projects', $data2, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {

        $db->query( 'ROLLBACK' );
        $db->query( 'SET autocommit=1' );
        Log::doLog( $err );

        return $errno * -1;
    }

    $db->query( 'COMMIT' );
    $db->query( 'SET autocommit=1' );

    return $db->affected_rows;
}

function changeProjectStatus( $pid, $status, $if_status_not = array() ) {

    $db = Database::obtain();

    $data[ 'status_analysis' ] = $db->escape( $status );
    $where                     = "id = " . (int)$pid;

    if ( !empty( $if_status_not ) ) {
        foreach ( $if_status_not as $v ) {
            $where .= " and status_analysis <> '" . $db->escape( $v ) . "' ";
        }
    }

    $db->update( 'projects', $data, $where );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );

        return $errno * -1;
    }

    return $db->affected_rows;
}

function changePassword( $res, $id, $password, $new_password ) {

    $db = Database::obtain();

    $query      = "UPDATE %s SET PASSWORD = '%s' WHERE id = %u AND PASSWORD = '%s' ";
    $sel_query  = "SELECT 1 FROM %s WHERE id = %u AND PASSWORD = '%s'";
    $row_exists = false;

    if ( $res == "prj" ) {

        $sel_query  = sprintf( $sel_query, 'projects', $id, $db->escape( $password ) );
        $res        = $db->fetch_array( $sel_query );
        $row_exists = @(bool)array_pop( $res[ 0 ] );

        $query = sprintf( $query, 'projects', $db->escape( $new_password ), $id, $db->escape( $password ) );
    } else {

        $sel_query  = sprintf( $sel_query, 'jobs', $id, $db->escape( $password ) );
        $res        = $db->fetch_array( $sel_query );
        $row_exists = @(bool)array_pop( $res[ 0 ] );

        $query = sprintf( $query, 'jobs', $db->escape( $new_password ), $id, $db->escape( $password ) );
    }

    $res   = $db->query( $query );
    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );

        return $errno * -1;
    }

    return ( $db->affected_rows | $row_exists );
}

function cancelJob( $res, $id ) {

    if ( $res == "prj" ) {
        $query = "UPDATE jobs SET status_owner = '" . Constants_JobStatus::STATUS_CANCELLED . "' WHERE id_project=" . (int)$id;
    } else {
        $query = "UPDATE jobs SET status_owner = '" . Constants_JobStatus::STATUS_CANCELLED . "' WHERE id=" . (int)$id;
    }

    $db = Database::obtain();
    $db->query( $query );
}

function updateProjectOwner( $ownerEmail, $project_id ) {
    $db              = Database::obtain();
    $data            = array();
    $data[ 'owner' ] = $db->escape( $ownerEmail );
    $where           = sprintf( " id_project = %u ", $project_id );
    $result          = $db->update( 'jobs', $data, $where );

    return $result;
}

function updateJobsStatus( $res, $id, $status, $only_if, $undo, $jPassword = null ) {

    $db = Database::obtain();

    if ( $res == "prj" ) {
        $status_filter_query = ( $only_if ) ? " and status_owner='" . $db->escape( $only_if ) . "'" : "";
        $arStatus            = explode( ',', $status );

        $test = count( explode( ':', $arStatus[ 0 ] ) );
        if ( ( $test > 1 ) && ( $undo == 1 ) ) {
            $cases = '';
            $ids   = '';

            //help!!!
            foreach ( $arStatus as $item ) {
                $ss = explode( ':', $item );
                $cases .= " when id=" . $db->escape( $ss[0] ) . " then '" . $db->escape( $ss[1] ) . "'";
                $ids .= $db->escape( $ss[0] ) . ",";
            }
            $ids   = trim( $ids, ',' );
            $query = "update jobs set status_owner= case $cases end where id in ($ids)" . $status_filter_query;
            $db->query( $query );

        } else {

            $query = "update jobs set status_owner='" . $db->escape( $status ) . "' where id_project=" . (int)$id . $status_filter_query;

            $db->query( $query );

            //Works on the basis that MAX( id_segment ) is the same for ALL Jobs in the same Project
            // furthermore, we need a random ID so, don't worry about MySQL stupidity on random MAX
            //example: http://dev.mysql.com/doc/refman/5.0/en/example-maximum-column-group-row.html
            $select_max_id = "
                    SELECT max(id_segment) as id_segment
					    FROM segment_translations
						JOIN jobs ON id_job = id
						WHERE id_project = " . (int)$id;

            $_id_segment = $db->fetch_array( $select_max_id );
            $_id_segment = array_pop( $_id_segment );
            $id_segment  = $_id_segment[ 'id_segment' ];

            $query_for_translations = "
                UPDATE segment_translations
                    SET translation_date = NOW()
                WHERE id_segment = $id_segment";

            $db->query( $query_for_translations );
        }
    } else {

        $query = "update jobs set status_owner='" . $db->escape( $status ) . "' where id=" . (int)$id . " and password = '" . $db->escape( $jPassword ) . "' ";
        $db->query( $query );

        $select_max_id = "
                    SELECT max(id_segment) as id_segment
					    FROM segment_translations
						JOIN jobs ON id_job = id
						WHERE id = $id
						 AND password = '" . $db->escape( $jPassword ) . "'";

        $_id_segment = $db->fetch_array( $select_max_id );
        $_id_segment = array_pop( $_id_segment );
        $id_segment  = $_id_segment[ 'id_segment' ];

        $query_for_translations = "
                UPDATE segment_translations
                    SET translation_date = NOW()
                WHERE id_segment = $id_segment";

        $db->query( $query_for_translations );
    }
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
        Log::doLog( $err );

        return $errno * -1;
    }

    return $db->affected_rows;
}

// tm analysis threaded

function getNextSegmentAndLock() {

    //get link
    $db = Database::obtain();
    $db->useDb( 'matecat_analysis' );

    //start locking
    $db->query( "SET autocommit=0" );
    $db->query( "START TRANSACTION" );
    //query

    //lock row
    $rnd = mt_rand( 0, 15 ); //rand num should be ( child_num / myMemory_sec_response_time )
    $q3  = "select id_segment, id_job, pid from matecat_analysis.segment_translations_analysis_queue where locked=0 limit $rnd,1 for update";
    //end transaction

    $res = $db->query_first( $q3 );

    //if nothing useful
    if ( empty( $res ) ) {
        //empty result
        $db->query( "ROLLBACK" );
        $res = null;
    } else {

        //DELETE
        $query = "DELETE FROM matecat_analysis.segment_translations_analysis_queue WHERE id_segment = %u AND id_job = %u";
        $query = sprintf( $query, $res[ 'id_segment' ], $res[ 'id_job' ] );

        $db->query( $query );
        $err = $db->get_error();

        $errno = $err[ 'error_code' ];

        if ( $errno != 0 || $db->affected_rows == 0 ) {
            Log::doLog( $err );
            $db->query( "ROLLBACK" );
            //return error code
            $res = null;
        } else {
            //if everything went well, commit
            $db->query( "COMMIT" );
        }
    }
    //release locks and end transaction
    $db->query( "SET autocommit=1" );

    $db->useDb( INIT::$DB_DATABASE );

    //return stuff
    return $res;
}

function resetLockSegment() {
    $db = Database::obtain();
    $db->useDb( 'matecat_analysis' );
    $db->query( "UPDATE matecat_analysis.segment_translations_analysis_queue SET locked = 0 WHERE locked = 1" );
    $err = $db->get_error();
    $db->useDb( INIT::$DB_DATABASE );
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );

        return -1;
    }

    return 0;
}

function deleteLockSegment( $id_segment, $id_job, $mode = "delete" ) {
    //set memcache

    $db = Database::obtain();
    $db->useDb( 'matecat_analysis' );
    if ( $mode == "delete" ) {
        $q = "delete from matecat_analysis.segment_translations_analysis_queue where id_segment=$id_segment and id_job=$id_job";
    } else {
        $db->query( "SET autocommit=0" );
        $db->query( "START TRANSACTION" );
        $q = "update matecat_analysis.segment_translations_analysis_queue set locked=0 where id_segment=$id_segment and id_job=$id_job";
        $db->query( "COMMIT" );
        $db->query( "SET autocommit=1" );
    }
    $db->query( $q );
    $err = $db->get_error();
    $db->useDb( INIT::$DB_DATABASE );
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );

        return -1;
    }

    return 0;
}

function getSegmentForTMVolumeAnalysys( $id_segment, $id_job ) {
    $query = "select s.id as sid ,s.segment ,raw_word_count,
		st.match_type, j.source, j.target, j.id as jid, j.id_translator, tm_keys,
		j.id_tms, j.id_mt_engine, j.payable_rates, p.id as pid
			from segments s
			inner join segment_translations st on st.id_segment=s.id
			inner join jobs j on j.id=st.id_job
			inner join projects p on p.id=j.id_project
			where
			p.status_analysis='FAST_OK' and
			st.id_segment=$id_segment and st.id_job=$id_job
			and st.locked = 0
			limit 1";

    $db      = Database::obtain();
    $results = $db->query_first( $query );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    if ( $errno != 0 ) {
        Log::doLog( $err );

        return $errno * -1;
    }

    return $results;
}

function getNumSegmentsInQueue( $currentPid ) {
    $query = "select count(*) as num_segments from matecat_analysis.segment_translations_analysis_queue where pid < $currentPid ";

    $db = Database::obtain();
    $db->useDb( 'matecat_analysis' );
    $results = $db->query_first( $query );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];
    $db->useDb( INIT::$DB_DATABASE );
    if ( $errno != 0 ) {
        Log::doLog( $err );

        return $errno * -1;
    }
    $num_segments = 0;
    if ( (int)$results[ 'num_segments' ] > 0 ) {
        $num_segments = (int)$results[ 'num_segments' ];
    }

    return $num_segments;
}

/**
 * @deprecated Not Used Anywhere
 *
 * @return array|bool
 */
function getNextSegmentForTMVolumeAnalysys() {
    $query = "SELECT s.id AS sid ,s.segment ,raw_word_count,
		st.match_type, j.source, j.target, j.id AS jid, j.id_translator,
		p.id_engine_mt,p.id AS pid
			FROM segments s
			INNER JOIN segment_translations st ON st.id_segment=s.id
			INNER JOIN jobs j ON j.id=st.id_job
			INNER JOIN projects p ON p.id=j.id_project

			WHERE
			st.tm_analysis_status='UNDONE' AND
			p.status_analysis='FAST_OK' AND
			s.raw_word_count>0   AND
			locked=0
			ORDER BY s.id
			LIMIT 1";

    $db      = Database::obtain();
    $results = $db->query_first( $query );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        Log::doLog( $err );

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
        Log::doLog( $err );

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
        Log::doLog( $err );

        return $errno * -1;
    }

    return $db->affected_rows;
}

/**
 * @param $pid
 *
 * @return mixed
 *
 */
function countSegments( $pid ) {
    $db = Database::obtain();

    $query = "SELECT  COUNT(s.id) AS num_segments
		FROM segments s
		INNER JOIN files_job fj ON fj.id_file=s.id_file
		INNER JOIN jobs j ON j.id= fj.id_job
		WHERE id_project = $pid
		";

    //-- and raw_word_count>0 -- removed, count ALL segments

    $results = $db->query_first( $query );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        Log::doLog( $err );

        return $errno * -1;
    }

    return $results[ 'num_segments' ];
}

/**
 * This function are pretty the same as
 * countSegmentsTranslationAnalyzed, but it is more heavy
 * so use after the preceding but only if it is necessary
 *
 * TODO cached
 *
 * ( Used in tmVolumeAnalysisThread with concurrency 100 )
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
        GROUP BY id_job WITH ROLLUP";

    $results = $db->fetch_array( $query );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        Log::doLog( "$errno: " . var_export( $err, true ) );

        return $errno * -1;
    }

    return $results;
}

/**
 *
 * @param $pid
 *
 * @return array
 */
function countSegmentsTranslationAnalyzed( $pid ) {
    $db = Database::obtain();

    $query = "SELECT
            COUNT( s.id ) AS project_segments,
            SUM(
                CASE
                    WHEN ( st.standard_word_count != 0 OR st.standard_word_count IS NULL ) THEN IF( st.tm_analysis_status = 'DONE', 1, 0 )
                    WHEN st.standard_word_count = 0 THEN 1
                END
            ) AS num_analyzed,
            SUM(eq_word_count) AS eq_wc ,
            SUM(standard_word_count) AS st_wc
            FROM segments s
            JOIN segment_translations st ON s.id = st.id_segment
            INNER JOIN jobs j ON j.id = st.id_job
            WHERE j.id_project = $pid";

    $results = $db->query_first( $query );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        Log::doLog( "$errno: " . var_export( $err, true ) );

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
        Log::doLog( "$errno: " . var_export( $err, true ) );

        return $errno * -1;
    }

    return $db->affected_rows;
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
function getArchivableJobs( $jobs = array() ) {
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

    $results = $db->fetch_array(
            sprintf(
                    $query,
                    implode( ", ", $jobs )
            )
    );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        Log::doLog( "$errno: " . var_export( $err, true ) );

        return $errno * -1;
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

function batchArchiveJobs( $jobs = array(), $days = INIT::JOB_ARCHIVABILITY_THRESHOLD ) {

    $query_archive_jobs = "
        UPDATE jobs
            SET status_owner = '" . Constants_JobStatus::STATUS_ARCHIVED . "'
            WHERE %s
            AND last_update < ( curdate() - INTERVAL %u DAY )";

    $tuple_of_double_indexes = array();
    foreach ( $jobs as $job ) {
        $tuple_of_double_indexes[ ] = sprintf( "( id = %u AND password = '%s' )", $job[ 'id' ], $job[ 'password' ] );
    }

    $q_archive = sprintf(
            $query_archive_jobs,
            implode( " OR ", $tuple_of_double_indexes ),
            $days
    );

    $db = Database::obtain();
    $db->query( $q_archive );

    $err   = $db->get_error();
    $errno = $err[ 'error_code' ];

    if ( $errno != 0 ) {
        Log::doLog( "$errno: " . var_export( $err, true ) );

        return $errno * -1;
    }

    return $db->affected_rows;
}
