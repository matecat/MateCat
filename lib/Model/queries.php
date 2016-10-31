<?php

function doSearchQuery( Array $queryParams ) {
    $db = Database::obtain();

    $key = $queryParams[ 'key' ]; //no escape: not related with Database

//    $src = preg_replace( array( "#'#", '#"#' ), array( '&apos;', '&quot;' ), $queryParams[ 'src' ] );
    $src = $queryParams[ 'src' ] ;

    // in the database at the target we have not html entities but normal quotes
    //so we have do not escape the translations
//    $trg = preg_replace( array( "#'#", '#"#' ), array( '&apos;', '&quot;' ), $queryParams[ 'trg' ] );
    $trg = $queryParams[ 'trg' ];

    $src = $db->escape( $src );
    $trg = $db->escape( $trg );

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
    $_regexpEscapedSrc = preg_replace( '#([\%\[\]\(\)\*\.\?\^\$\{\}\+\-\|\\\\])#', '\\\\$1', $queryParams[ 'src' ] );
//    $_regexpEscapedSrc = preg_replace( array( "#'#", '#"#' ), array( '&apos;', '&quot;' ), $_regexpEscapedSrc );
    $_regexpEscapedSrc = $db->escape( $_regexpEscapedSrc );

    $_regexpEscapedTrg = preg_replace( '#([\%\[\]\(\)\*\.\?\^\$\{\}\+\-\|\\\\])#', '\\\\$1', $queryParams[ 'trg' ] );
//    $_regexpEscapedTrg = preg_replace( array( "#'#", '#"#' ), array( '&apos;', '&quot;' ), $_regexpEscapedTrg );
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
			AND show_in_cattool = 1
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
    try {
        $results = $db->fetch_array($query);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
//    Log::doLog($results);

    if ( $key != 'coupled' && $key != 'status_only' ) { //there is the ROLLUP
        $rollup = array_pop( $results );
    }

    //    Log::doLog($results);

    $vector = array( 'sidlist' => array(), 'count' => '0' );
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
            $vector[ 'sidlist' ] = array();
            $vector[ 'count' ]   = 0;
        }
    }

    return $vector;
}

function doReplaceAll( Array $queryParams ) {

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
    $_regexpEscapedTrg = preg_replace( '#([\#\[\]\(\)\*\.\?\^\$\{\}\+\-\|\\\\])#', '\\\\$1', $trg );

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
        $trMod = preg_replace( "#({$Space_Left}){$_regexpEscapedTrg}{$Space_Right}#$modifier", '${1}' . $replacement, $tRow[ 'translation' ] );

        /**
         * Escape for database
         */
        $trMod      = $db->escape( $trMod );
        $sqlBatch[] = "({$tRow['id_segment']},{$tRow['id_job']},'{$trMod}')";
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

function getLanguageStats() {

    $db = Database::obtain();

    $query = "select source,target, date,total_post_editing_effort,job_count, total_word_count, pee_sigma
from language_stats
  where date=(select max(date) from language_stats)";

    $results = $db->fetch_array( $query );

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
            $cid[ 'email' ] = $data[ 'email' ];
            $cid[ 'uid' ]   = $results;
        } else {
            $cid = false;
        }
    } else {
        $cid[ 'email' ] = $data[ 'email' ];
        $cid[ 'uid' ]   = $results[ 'uid' ];

        // TODO: migrate this to an insert on duplicate key update
        $sql_update = "UPDATE users set oauth_access_token = ? WHERE uid = ?" ; 
        $conn = Database::obtain()->getConnection();
        $stmt = $conn->prepare( $sql_update ); 
        $stmt->execute( array( $data['oauth_access_token'], $cid['uid'] ) ); 
    }
    
    return $cid;
}

function getArrayOfSuggestionsJSON( $id_segment ) {
    $query   = "select suggestions_array from segment_translations where id_segment=$id_segment";
    $db      = Database::obtain();
    $results = $db->query_first( $query );

    return $results[ 'suggestions_array' ];
}

function getJobData( $id_job, $password = null ) {

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
        'dqf_key', 
        'payable_rates', 
        'total_time_to_edit', 
        'avg_post_editing_effort'
    );

    $query = "SELECT " . implode(', ', $fields) . " FROM jobs WHERE id = %u";

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
 * @param $job_id       int The job ID
 * @param $job_password int The job password
 *
 * @return mixed If query was successful, this method returns the encoded tm keys string.<br/>
 *               Otherwise, it returns the query's error code
 */
function getJobTmKeys( $job_id, $job_password ) {
    $query = "SELECT tm_keys FROM jobs WHERE id = %d AND password = '%s' ";

    $db      = Database::obtain();
    try {
        $results = $db->fetch_array(
            sprintf($query, $job_id, $job_password)
        );
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $results[ 0 ][ 'tm_keys' ];
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
        $db->query(sprintf($query, $db->escape($tmKeysString), (int)$job_id, $job_password));
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        throw new Exception( $e->getMessage(), -$e->getCode() );
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
    $status_new = Constants_TranslationStatus::STATUS_NEW ;

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
        $results = $db->fetch_array($sql);
    } catch( PDOException $e ) {
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
        $results = $db->fetch_array($sql);
    } catch( PDOException $e ) {
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
            Constants_TranslationStatus::STATUS_APPROVED . "'), translation_date, j.create_date ) as translation_date,
            st.status, suggestions_array
        FROM segment_translations st
        JOIN segments ON id = id_segment
        JOIN jobs j ON j.id = st.id_job AND password = '" . $db->escape( $jPassword ) . "'

            WHERE st.id_job = " . (int)$jid . "
            AND show_in_cattool = 1
            AND suggestion_source is not null
            -- AND (suggestion_source = 'TM' or suggestion_source not in ('MT','MT-') )
";

    try {
        $results = $db->fetch_array($sql);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }

    foreach( $results as $key => $value ){

        //we already extracted a 100% match by definition
        if( in_array( $value['status'], array(
                    Constants_TranslationStatus::STATUS_TRANSLATED,
                    Constants_TranslationStatus::STATUS_APPROVED
                )
            )
        ) continue;

        $suggestions_array = json_decode( $value['suggestions_array'] );
        foreach( $suggestions_array as $_k => $_sugg ){

            //we want the highest value of TM and we must exclude the MT
            if( strpos( $_sugg->created_by, 'MT' ) !== false ) continue;

            //override the content of the result with the fuzzy matches
            $results[ $key ][ 'segment' ] = $_sugg->segment;
            $results[ $key ][ 'translation' ] = $_sugg->translation;
            $results[ $key ][ '_created_by' ] = 'MateCat_OmegaT_Export';

            //stop, we found the first TM value in the list
            break;

        }

        //if no TM found unset the result
        if( !isset( $results[ $key ][ '_created_by' ] ) ) unset( $results[ $key ] );

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
    try {
        $results = $db->fetch_array($query);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }

    return $results;
}

function getSegmentsInfo( $jid, $password ) {

    $query = "select j.id as jid, j.id_project as pid,j.source,j.target,
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

    $db      = Database::obtain();
    try {
        $results = $db->fetch_array($query);
    } catch( PDOException $e ) {
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
function getMoreSegments( $jid, $password, $step = 50, $ref_segment, $where = 'after', $options=array() ) {

    if ( $options['optional_fields'] ) {
        $optional_fields = ', ';
        $optional_fields .= implode(', ', $options['optional_fields']);
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
            $subQuery = sprintf( $queryAfter , $step * 2 );
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
                st.locked AS original_target_provied,
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
                ,IF( fr.id IS NULL, 'false', 'true' ) as has_reference

                $optional_fields

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

    $db      = Database::obtain();

    try {
        $results = $db->fetch_array($query);
    } catch( PDOException $e ) {
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
				AND segment_translations.status IN( '$st_translated' , '$st_approved' )
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
				AND segment_translations.status IN( '$st_translated' , '$st_approved' )
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
                warning = {$_Translation[ 'warning' ]}" ;

    if ( array_key_exists('version_number', $_Translation) ) {
        $query .= "\n, version_number = {$_Translation['version_number']}";
    }


    if ( isset( $_Translation[ 'autopropagated_from' ] ) ) {
        $query .= " , autopropagated_from = NULL";
    }

    if ( empty( $_Translation[ 'translation' ] ) && !is_numeric( $_Translation[ 'translation' ] ) ) {
        $msg = "\n\n Error setTranslationUpdate \n\n Empty translation found after DB Escape: \n\n " . var_export( array_merge( array( 'db_query' => $query ), $_POST ), true );
        Log::doLog( $msg );
        Utils::sendErrMailReport( $msg );
        throw new PDOException( $msg );
    }

//    Log::doLog( $query );

    try {
        $db->query($query);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        throw new PDOException( "Error occurred storing (UPDATE) the translation for the segment {$_Translation['id_segment']} - Error: {$e->getCode()}" );
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
     * Sum the word count grouped by status, so that we can later update the count on jobs table.
     * We only count segments with status different than the current, because we don't need to update
     * the count for the same status.
     *
     */
    $queryTotals = "
           SELECT $sum_sql as total, COUNT(id_segment)as countSeg, status

           FROM segment_translations
              -- JOIN for raw_word_count and ICE matches
              INNER JOIN  segments
              ON segments.id = segment_translations.id_segment
              -- JOIN for raw_word_count and ICE matches

           WHERE id_job = {$params['id_job']}
           AND segment_translations.segment_hash = '" . $params[ 'segment_hash' ] . "'
           AND id_segment BETWEEN {$job_data['job_first_segment']} AND {$job_data['job_last_segment']}
           AND id_segment != $_idSegment
           AND status != '{$params['status']}'
           GROUP BY status
    ";


    try {
        $totals = $db->fetch_array($queryTotals);
    } catch( PDOException $e ) {
        throw new Exception( "Error in counting total words for propagation: " . $e->getCode() . ": " . $e->getMessage()
                . "\n" . $queryTotals . "\n" . var_export( $params, true ),
                -$e->getCode() );
    }

    $dao = new Translations_SegmentTranslationDao();
    try {
        $segmentsForPropagation = $dao->getSegmentsForPropagation( array(
                'id_segment'        => $_idSegment,
                'job_first_segment' => $job_data[ 'job_first_segment' ],
                'job_last_segment'  => $job_data[ 'job_last_segment' ],
                'segment_hash'      => $params[ 'segment_hash' ],
                'id_job'            => $params[ 'id_job' ]
        ) );
    } catch( PDOException $e ) {
        throw new Exception(
                sprintf( "Error in querying segments for propagation: %s: %s ", $e->getCode(),  $e->getMessage() ),
                -$e->getCode()
        );
    }

    $propagated_ids = array();

    if ( !empty( $segmentsForPropagation ) ) {

        $propagated_ids = array_map(function( Translations_SegmentTranslationStruct $translation ) {
            return $translation->id_segment ;
        }, $segmentsForPropagation );

        try {

            $place_holders_fields = array();
            foreach ( $params as $key => $value ) {
                $place_holders_fields[ ] = "$key = ?";
            }
            $place_holders_fields = implode( ",", $place_holders_fields );
            $place_holders_id = implode( ',', array_fill( 0, count( $propagated_ids ), '?' ) );

            $values = array_merge(
                    array_values( $params ),
                    array( $params[ 'id_job' ] ),
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

    return array( 'totals' => $totals, 'propagated_ids' => $propagated_ids );
}

function setSuggestionUpdate( $data ) {

    $id_segment = (int)$data[ 'id_segment' ];
    $id_job     = (int)$data[ 'id_job' ];

    $where = " id_segment = $id_segment and id_job = $id_job";

    $db = Database::obtain();
    try {
        $affectedRows = $db->update('segment_translations', $data, $where);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $affectedRows;
}

function setSuggestionInsert( $id_segment, $id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source, $match_type, $eq_words, $standard_words, $translation, $tm_status_analysis, $warning, $err_json_list, $mt_qe, $segment_status = 'NEW' ) {
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
    $data[ 'status' ]              = $segment_status;

    $data[ 'warning' ]                = $warning;
    $data[ 'serialized_errors_list' ] = $err_json_list;

    $data[ 'mt_qe' ] = $mt_qe;

    $db = Database::obtain();

    try {
        $db->insert('segment_translations', $data);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $db->affected_rows;
}

function setCurrentSegmentInsert( $id_segment, $id_job, $password ) {
    $data                          = array();
    $data[ 'last_opened_segment' ] = $id_segment;

    $where = "id = $id_job AND password = '$password'";

    $db = Database::obtain();
    try {
        $affectedRows = $db->update('jobs', $data, $where);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $affectedRows;
}


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
    try {
        //sometimes we can have broken projects in our Database that are not related to a job id
        //the query that extract the projects info returns a null job id for these projects, so skip the exception
        $results = $db->fetch_array( $query );
    } catch( Exception $e ){ $results = null; }

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

function getOriginalFile( $id_file ) {

    $query = "SELECT xliff_file FROM files WHERE id=" . $id_file;

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

    $statusWeight = array(
            Constants_TranslationStatus::STATUS_NEW        => 10,
            Constants_TranslationStatus::STATUS_DRAFT      => 10,
            Constants_TranslationStatus::STATUS_REJECTED   => 10,
            Constants_TranslationStatus::STATUS_TRANSLATED => 40,
            Constants_TranslationStatus::STATUS_APPROVED   => 50
    );

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
        if (!$nSegment) {
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
    $data                        = array();
    $data[ 'id_customer' ]       = $projectStructure[ 'id_customer' ];
    $data[ 'name' ]              = $projectStructure[ 'project_name' ];
    $data[ 'create_date' ]       = $projectStructure[ 'create_date' ];
    $data[ 'status_analysis' ]   = $projectStructure[ 'status' ];
    $data[ 'password' ]          = $projectStructure[ 'ppassword' ];
    $data[ 'pretranslate_100' ]  = $projectStructure[ 'pretranslate_100' ];
    $data[ 'remote_ip_address' ] = empty( $projectStructure[ 'user_ip' ] ) ? 'UNKNOWN' : $projectStructure[ 'user_ip' ];
    $query                       = "SELECT LAST_INSERT_ID() FROM projects";

    $db = Database::obtain();
    $db->insert( 'projects', $data );
    $results = $db->query_first( $query );

    return $results[ 'LAST_INSERT_ID()' ];
}

function updateTranslatorJob( $id_job, Engines_Results_MyMemory_CreateUserResponse $newUser ) {

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

    $private_tm_key = ( is_array( $projectStructure[ 'private_tm_key' ] ) ) ?
            $projectStructure[ 'private_tm_key' ][ 0 ][ 'key' ] :
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
    $data[ 'id_translator' ]     = is_null($projectStructure[ 'private_tm_user' ]) ?  "" : $projectStructure[ 'private_tm_user' ] ;
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
    $data[ 'payable_rates' ]     = json_encode( $projectStructure[ 'payable_rates' ] );
    $data[ 'dqf_key' ]           = $projectStructure[ 'dqf_key' ];

    $query = "SELECT LAST_INSERT_ID() FROM jobs";

    $db = Database::obtain();
    $db->insert( 'jobs', $data );
    $results = $db->query_first( $query );

    return $results[ 'LAST_INSERT_ID()' ];
}

function insertFile( ArrayObject $projectStructure, $file_name, $mime_type, $fileDateSha1Path, $params=array() ) {
    $data                         = array();
    $data[ 'id_project' ]         = $projectStructure[ 'id_project' ];
    $data[ 'filename' ]           = $file_name;
    $data[ 'source_language' ]    = $projectStructure[ 'source_language' ];
    $data[ 'mime_type' ]          = $mime_type;
    $data[ 'sha1_original_file' ] = $fileDateSha1Path;

    $db = Database::obtain();

    try {
        $db->insert('files', $data);
    }
    catch (PDOException $e) {
        $errno = $e->getCode();
        if ( $errno == 1153 ) {
            Log::doLog( "file too large for mysql packet: increase max_allowed_packed_size" );
            throw new Exception( "Database insert Large file error: $errno ", -$errno );
        }
        else {
            Log::doLog( "Database insert error: $errno " );
            throw new Exception( "Database insert file error: $errno ", -$errno );
        }
    }
    $query   = "SELECT LAST_INSERT_ID() FROM files";

    try {
        $results = $db->query_first($query);
    } catch( PDOException $e ) {
        Log::doLog( "Database failure, failed to get last index. {$e->getMessage()}: {$e->getCode()} ", -$e->getCode() );
        throw new Exception( "Database failure, failed to get last index. {$e->getMessage()}: {$e->getCode()} ", -$e->getCode() );
    }
    $idFile = $results[ 'LAST_INSERT_ID()' ];

    return $idFile;
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
				   ORDER BY j.id,j.create_date, j.job_first_segment
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
                            SUM(draft_words + new_words+translated_words+rejected_words+approved_words) as tm_analysis_wc
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


function getJobsFromProjects( array $projectIDs, $search_source, $search_target, $search_status, $search_onlycompleted ) {

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
			";

    $db      = Database::obtain();
    try {
        $results = $db->fetch_array($query);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
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
    $query = "select p.id, id_tms, id_mt_engine, tm_keys , p.pretranslate_100, group_concat( distinct j.id ) as jid_list
		from projects p
		inner join jobs j on j.id_project=p.id
		where status_analysis = '$status_search'
		group by 1
		order by id $query_limit
		";
    $db    = Database::obtain();
    try {
        $results = $db->fetch_array($query);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
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
    try {
        $results = $db->fetch_array($query);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
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
    try {
        $db->update('jobs', $data, $where);
    } catch( PDOException $e ) {
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
        $db->query($query);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    $affectedRows = $db->affected_rows;
    Log::doLog( "Affected: " . $affectedRows . "\n" );
    return $affectedRows;
}

/**
 * @param $job_id int
 * @param $jobPass string
 * @param $segmentTimeToEdit int
 * @return int|mixed
 */
function updateTotalTimeToEdit( $job_id, $jobPass, $segmentTimeToEdit ){
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

    } catch( PDOException $e ) {
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
    $data                           = array();
    $data[ 'tm_analysis_wc' ]       = $pid_eq_words;
    $data[ 'standard_analysis_wc' ] = $pid_standard_words;
    $where                          = " id =$pid";
    try {
        $affectedRows = $db->update('projects', $data, $where);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $affectedRows;
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
    try {
        $affectedRows = $db->update('projects', $data, $where);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $affectedRows;
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
    try {
        $res = $db->query($query);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
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
                $cases .= " when id=" . $db->escape( $ss[ 0 ] ) . " then '" . $db->escape( $ss[ 1 ] ) . "'";
                $ids .= $db->escape( $ss[ 0 ] ) . ",";
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
    try {
        $affectedRows = $db->update('segment_translations', $data, $where);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $affectedRows;
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
    try {
        $results = $db->query_first($query);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
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
    try {
        $results = $db->fetch_array( $query );
    } catch ( PDOException $e ) {
        Log::doLog( $e->getMessage() );

        return $e->getCode() * -1;
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
    try {
        $results = $db->query_first($query);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $results;
}

function setJobCompleteness( $jid, $is_completed ) {
    $db    = Database::obtain();
    $query = "update jobs set completed=$is_completed where id=$jid";
    try {
        $result = $db->query_first($query);
    } catch( PDOException $e ) {
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

    try {
        $results = $db->fetch_array(
            sprintf(
                $query,
                implode(", ", $jobs)
            )
        );
    } catch( PDOException $e ) {
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
    try {
        $db->query($q_archive);
    } catch( PDOException $e ) {
        Log::doLog( $e->getMessage() );
        return $e->getCode() * -1;
    }
    return $db->affected_rows;
}
