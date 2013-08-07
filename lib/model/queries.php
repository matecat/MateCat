<?php

include_once 'Database.class.php';

function getUserData($id) {

	$db = Database::obtain();

	$id= $db->escape($id);
	$query = "select * from users where email='$id'";

	$results = $db->query_first($query);

	return $results;
}

function randomString($maxlength = 15){
	//allowed alphabet
	$possible = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

	//init counter lenght
	$i = 0; 
	//init string
	$salt='';

	//add random characters to $password until $length is reached
	while ($i < $maxlength) { 
		//pick a random character from the possible ones
		$char = substr($possible, mt_rand(0, $maxlength-1), 1);
		//have we already used this character in $salt?
		if (!strstr($salt, $char)) { 
			//no, so it's OK to add it onto the end of whatever we've already got...
			$salt .= $char;
			//... and increase the counter by one
			$i++;
		}
	}
	return $salt;
}

function encryptPass($clear_pass,$salt){
	return sha1($clear_pass.$salt);
}

function mailer($toUser, $toMail, $fromUser, $fromMail, $subject, $message){
	//optional headerfields 
	$header = "From: ". $fromUser . " <" . $fromMail . ">\r\n"; 
	//mail command 
	mail($toMail, $subject, $message, $header); 
}

function sendResetLink($mail){

	//generate new random pass and unique salt
	$clear_pass=randomString(15);
	$newSalt=randomString(15);

	//hash pass
	$newPass=encryptPass($clear_pass,$newSalt);

	//get link
	$db = Database::obtain();

	//escape untrusted input
	$user=$db->escape($mail);

	//get data
	$q_get_name="select first_name, last_name from users where email='$mail'";
	$result=$db->query_first($q_get_name);
	if(2==count($result)){
		$toName=$result['first_name']." ".$result['last_name'];

		//query
		$q_new_credentials="update users set pass='$newPass', salt='$newSalt' where email='$mail'";
		$result=$db->query_first($q_new_credentials);

		$message="New pass for $mail is: $clear_pass";
		mailer($toName,$mail,"Matecat","noreply@matecat.com","Password reset",$message);

		$outcome=true;
	}else{
		$outcome=false;
	}
	return $outcome;
}

function checkLogin($user,$pass){
	//get link
	$db = Database::obtain();

	//escape untrusted input
	$user=$db->escape($user);

	//query
	$q_get_credentials="select pass,salt from users where email='$user'";
	$result=$db->query_first($q_get_credentials);

	//one way transform input pass
	$enc_string=encryptPass($pass,$result['salt']);

	//check
	return $enc_string==$result['pass'];
}

function insertUser($data){
	//random, unique to user salt
	@$data['salt']=randomString(15);

	//if no pass available, create a random one
	if(!isset($data['pass']) or empty($data['pass'])){
		@$data['pass']=randomString(15);
	}
	//now encrypt pass
	$clear_pass=$data['pass'];
	$encrypted_pass=encryptPass($clear_pass,$data['salt']);
	$data['pass']=$encrypted_pass;

	//creation data
	@$data['create_date']=date('Y-m-d H:i:s');

	//insert into db
	$db = Database::obtain();
	$results = $db->insert('users',$data,'email');
	return $results;
}

function tryInsertUserFromOAuth($data){
	//check if user exists
	$query="select email from users where email='".$data['email']."'";
	$db = Database::obtain();
	$results = $db->query_first($query);

	if(0==count($results) or false==$results){
		//new client
		$results=insertUser($data);
		//check outcome
		if($results){
			$cid=$data['email'];
		}else{
			$cid=false;
		}
	}else{
		$cid=$data['email'];
	}
	return $cid;
}

function getArrayOfSuggestionsJSON($id_segment){
	$query="select suggestions_array from segment_translations where id_segment=$id_segment";
	$db = Database::obtain();
	$results = $db->query_first($query);
	return $results['suggestions_array'];
}

/**
 * Get job data structure
 *
 * <pre>
 * $jobData = array(
 *      'source'        => 'it-IT',
 *      'target'        => 'en-US',
 *      'id_mt_engine'  => 1,
 *      'id_tms'        => 1,
 *      'id_translator' => '',
 *      'status'        => 'active',
 *      'password'      => 'GfgJ6h'
 * );
 * </pre>
 *
 * @param $id_job
 * @return array $jobData
 */
function getJobData($id_job) {
	$query = "select source, target, id_mt_engine, id_tms, id_translator, status_owner as status, password
		      from jobs
		      where id = %u";
    $query = sprintf( $query, $id_job );
	$db = Database::obtain();
	$results = $db->fetch_array($query);
	return $results[0];
}

function getEngineData($id) {
	if (is_array($id)) {
		$id = explode(",", $id);
	}
	$where_clause = " id IN ($id)";

	$query = "select * from engines where $where_clause";
                
	$db = Database::obtain();

	$results = $db->fetch_array($query);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}

	return $results[0];
}

function getSegment($id_segment){
	$db = Database::obtain();
	$id_segment = $db->escape($id_segment);
	$query = "select * from segments where id=$id_segment";
	$results = $db->query_first($query);
	return $results;
}

function getFirstSegmentOfFilesInJob($jid){
    $db = Database::obtain();
    $jid = intval($jid);
    $query = "select id_file, min( segments.id ) as first_segment
                from files_job
                join segments using( id_file )
                where files_job.id_job = $jid
                and segments.show_in_cattool = 1
                group by id_file";
    $results = $db->fetch_array($query);
    return $results;
}

function getWarning($jid){
	$db = Database::obtain();
	$jid = $db->escape($jid);

    $query = "SELECT total, id_segment, serialized_errors_list as warnings FROM (
                    SELECT Seg1.id_segment, Seg1.serialized_errors_list , SUM(CASE WHEN Seg1.warning != 0 THEN 1 ELSE 0 END) AS total
                    FROM segment_translations AS Seg1
                    WHERE Seg1.id_job = $jid
                    AND Seg1.warning != 0
                    GROUP BY Seg1.id_segment WITH ROLLUP
                ) AS Seg2 ORDER BY total DESC, id_segment ASC LIMIT 11"; //+1 for RollUp

	$results = $db->fetch_array($query);
	return $results;
}

function getTranslatorPass($id_translator) {

	$db = Database::obtain();

	$id_translator = $db->escape($id_translator);
	$query = "select password from translators where username='$id_translator'";


	//$db->query_first($query);
	$results = $db->query_first($query);

	if ((is_array($results)) AND (array_key_exists("password", $results))) {
		return $results['password'];
	}
	return null;
}

function getTranslatorKey($id_translator) {

	$db = Database::obtain();

	$id_translator = $db->escape($id_translator);
	$query = "select mymemory_api_key from translators where username='$id_translator'";

	$db->query_first($query);
	$results = $db->query_first($query);

	if ((is_array($results)) AND (array_key_exists("mymemory_api_key", $results))) {
		return $results['mymemory_api_key'];
	}
	return null;
}

function getEngines($type = "MT") {
	$query = "select id,name from engines where type='$type'";

	$db = Database::obtain();
	$results = $db->fetch_array($query);
	return $results;
}

function getSegmentsDownload($jid, $password, $id_file, $no_status_new = 1) {
	if (!$no_status_new) {
		$select_translation = " st.translation ";
	} else {
		$select_translation = " if (st.status='NEW', '', st.translation) as translation ";
	}
	//$where_status ="";
	$query = "select                                 
		f.filename, f.mime_type, s.id as sid, s.segment, s.internal_id,
		s.xliff_mrk_id as mrk_id, s.xliff_ext_prec_tags as prev_tags, s.xliff_ext_succ_tags as succ_tags,
		s.xliff_mrk_ext_prec_tags as mrk_prev_tags, s.xliff_mrk_ext_succ_tags as mrk_succ_tags,
		$select_translation, st.status

			from jobs j 
			inner join projects p on p.id=j.id_project
			inner join files_job fj on fj.id_job=j.id
			inner join files f on f.id=fj.id_file
			inner join segments s on s.id_file=f.id
			left join segment_translations st on st.id_segment=s.id and st.id_job=j.id 
			where j.id=$jid and j.password='$password' and f.id=$id_file 

			";
	$db = Database::obtain();
	$results = $db->fetch_array($query);
	$err = $db->get_error();
	$errno = $err['error_code'];

	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}

	return $results;
}

function getSegmentsInfo($jid, $password) {

	$query = "select j.id as jid, j.id_project as pid,j.source,j.target, j.last_opened_segment, j.id_translator as tid,
		p.id_customer as cid, j.id_translator as tid, j.status_owner as status,  
		p.name as pname, p.create_date , fj.id_file, fj.id_segment_start, fj.id_segment_end, 
		f.filename, f.mime_type

			from jobs j 
			inner join projects p on p.id=j.id_project
			inner join files_job fj on fj.id_job=j.id
			inner join files f on f.id=fj.id_file
			where j.id=$jid and j.password='$password' ";

	$db = Database::obtain();
	$results = $db->fetch_array($query);

	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}

	return $results;
}

function getFirstSegmentId($jid, $password) {

	$query = "select s.id as sid
		from segments s
		inner join files_job fj on s.id_file = fj.id_file
		inner join jobs j on j.id=fj.id_job
		where fj.id_job=$jid and j.password='$password'
		and s.show_in_cattool=1
		order by s.id
		limit 1
		";
	$db = Database::obtain();
	$results = $db->fetch_array($query);

	return $results;
}

/*
   function getMoreSegments($jid,$password, $last_loaded_id, $step = 50, $central_segment = 0) {
   $start_point = ($central_segment)? ((float) $central_segment) - 100 : $last_loaded_id;

   $query = "select j.id as jid, j.id_project as pid,j.source,j.target, j.last_opened_segment, j.id_translator as tid,
   p.id_customer as cid, j.id_translator as tid,
   p.name as pname, p.create_date , fj.id_file, fj.id_segment_start, fj.id_segment_end,
   f.filename, f.mime_type, s.id as sid, s.segment, s.raw_word_count, s.internal_id,
   st.translation, st.status, st.time_to_edit

   from jobs j
   inner join projects p on p.id=j.id_project
   inner join files_job fj on fj.id_job=j.id
   inner join files f on f.id=fj.id_file
   inner join segments s on s.id_file=f.id
   left join segment_translations st on st.id_segment=s.id and st.id_job=j.id
   where j.id=$jid and j.password='$password' and s.id > $start_point
   order by s.id
   limit 0,$step
   ";

   $db = Database::obtain();
   $results = $db->fetch_array($query);

   return $results;
   }
 */

function getMoreSegments($jid, $password, $step = 50, $ref_segment, $where = 'after') {
	switch ($where) {
		case 'after':
			$ref_point = $ref_segment;
			break;
		case 'before':
			$ref_point = $ref_segment - ($step + 1);
			break;
		case 'center':
			$ref_point = ((float) $ref_segment) - 100;
			break;
	}

	//	$ref_point = ($where == 'center')? ((float) $ref_segment) - 100 : $ref_segment;

	$query = "select j.id as jid, j.id_project as pid,j.source,j.target, j.last_opened_segment, j.id_translator as tid,
		p.id_customer as cid, j.id_translator as tid,  
		p.name as pname, p.create_date , fj.id_file, fj.id_segment_start, fj.id_segment_end, 
		f.filename, f.mime_type, s.id as sid, s.segment, s.raw_word_count, s.internal_id,
		if (st.status='NEW',NULL,st.translation) as translation, st.status, IF(st.time_to_edit is NULL,0,st.time_to_edit) as time_to_edit, s.xliff_ext_prec_tags,s.xliff_ext_succ_tags, st.serialized_errors_list, st.warning

			from jobs j 
				inner join projects p on p.id=j.id_project
				inner join files_job fj on fj.id_job=j.id
				inner join files f on f.id=fj.id_file
				inner join segments s on s.id_file=f.id
				left join segment_translations st on st.id_segment=s.id and st.id_job=j.id
				where j.id=$jid and j.password='$password' and s.id > $ref_point and s.show_in_cattool=1 
				limit 0,$step
				";

	$db = Database::obtain();
	$results = $db->fetch_array($query);

	return $results;
}

function getLastSegmentInNextFetchWindow($jid, $password, $step = 50, $ref_segment, $where = 'after') {
	switch ($where) {
		case 'after':
			$ref_point = $ref_segment;
			break;
		case 'before':
			$ref_point = $ref_segment - ($step + 1);
			break;
		case 'center':
			$ref_point = ((float) $ref_segment) - 100;
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


	$db = Database::obtain();
	$results = $db->query_first($query);


	return $results['max_id'];
}

function setTranslationUpdate($id_segment, $id_job, $status, $time_to_edit, $translation, $errors, $chosen_suggestion_index, $warning=0) {
	// need to use the plain update instead of library function because of the need to update an existent value in db (time_to_edit)
	$now = date("Y-m-d H:i:s");
	$db = Database::obtain();

	$translation = $db->escape($translation);
	$status = $db->escape($status);

	$q = "UPDATE segment_translations SET status='$status', suggestion_position='$chosen_suggestion_index', serialized_errors_list='$errors', time_to_edit=IF(time_to_edit is null,0,time_to_edit) + $time_to_edit, translation='$translation', translation_date='$now', warning=" . (int)$warning . " WHERE id_segment=$id_segment and id_job=$id_job";

	$db->query($q);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $db->affected_rows;
}

function setTranslationInsert($id_segment, $id_job, $status, $time_to_edit, $translation, $errors='', $chosen_suggestion_index, $warning=0) {
	$data = array();
	$data['id_job'] = $id_job;
	$data['status'] = $status;
	$data['time_to_edit'] = $time_to_edit;
	$data['translation'] = $translation;
	$data['translation_date'] = date("Y-m-d H:i:s");
	$data['id_segment'] = $id_segment;
	$data['id_job'] = $id_job;
	$data['serialized_errors_list']=$errors;
	$data['suggestion_position']=$chosen_suggestion_index;
	$data['warning'] = (int)$warning;
	$db = Database::obtain();
	$db->insert('segment_translations', $data);

	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		if($errno!=1062) log::doLog($err);
		return $errno * -1;
	}
	return $db->affected_rows;
}

function setSuggestionUpdate($id_segment, $id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source, $match_type, $eq_words, $standard_words, $translation, $tm_status_analysis,$warning, $err_json_list) {
	$data = array();
	$data['id_job'] = $id_job;
	$data['suggestions_array'] = $suggestions_json_array;
	$data['suggestion'] = $suggestion;
	$data['suggestion_match'] = $suggestion_match;
	$data['suggestion_source'] = $suggestion_source;
	$data['match_type'] = $match_type;
	$data['eq_word_count'] = $eq_words;
	$data['standard_word_count'] = $standard_words;
	$data['translation'] = $translation;
	$data['tm_analysis_status'] = $tm_status_analysis;

        $data['warning'] = $warning;
        $data['serialized_errors_list'] = $err_json_list;

	$and_sugg = "";
	if ($tm_status_analysis != 'DONE') {
		$and_sugg = "and suggestions_array is NULL";
	}

	$where = " id_segment=$id_segment and id_job=$id_job $and_sugg";

	$db = Database::obtain();
	$db->update('segment_translations', $data, $where);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);

		return $errno * -1;
	}
	return $db->affected_rows;
}

function setSuggestionInsert($id_segment, $id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source, $match_type, $eq_words, $standard_words, $translation, $tm_status_analysis, $warning, $err_json_list) {
	$data = array();
	$data['id_job'] = $id_job;
	$data['id_segment'] = $id_segment;
	$data['suggestions_array'] = $suggestions_json_array;
	$data['suggestion'] = $suggestion;
	$data['suggestion_match'] = $suggestion_match;
	$data['suggestion_source'] = $suggestion_source;
	$data['match_type'] = $match_type;
	$data['eq_word_count'] = $eq_words;
	$data['standard_word_count'] = $standard_words;
	$data['translation'] = $translation;
	$data['tm_analysis_status'] = $tm_status_analysis;

        $data['warning'] = $warning;
        $data['serialized_errors_list'] = $err_json_list;
        
	$db = Database::obtain();
	$db->insert('segment_translations', $data);

	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		if($errno!=1062) log::doLog($err);
		return $errno * -1;
	}
	return $db->affected_rows;
}

function setCurrentSegmentInsert($id_segment, $id_job) {
	$data = array();
	$data['last_opened_segment'] = $id_segment;

	$where = "id=$id_job";


	$db = Database::obtain();
	$db->update('jobs', $data, $where);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $db->affected_rows;
}

function getFilesForJob($id_job, $id_file) {
	$where_id_file = "";
	if (!empty($id_file)) {
		$where_id_file = " and id_file=$id_file";
	}
	$query = "select id_file, xliff_file, filename,mime_type from files_job fj
		inner join files f on f.id=fj.id_file
		where id_job=$id_job $where_id_file";

	$db = Database::obtain();
	$results = $db->fetch_array($query);

	return $results;
}

function getOriginalFilesForJob($id_job, $id_file, $password) {
	$where_id_file = "";
	if (!empty($id_file)) {
		$where_id_file = " and id_file=$id_file";
	}
	$query = "select id_file, if(original_file is null, xliff_file,original_file) as original_file, filename from files_job fj
		inner join files f on f.id=fj.id_file
		inner join jobs j on j.id=fj.id_job
		where id_job=$id_job $where_id_file and j.password='$password'";

	$db = Database::obtain();
	$results = $db->fetch_array($query);

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

function getStatsForMultipleJobs($_jids) {

	//transform array into comma separated string
	if(is_array($_jids)){
		$jids=implode(',',$_jids);
	}

	$query = "select SUM(IF(st.eq_word_count IS NULL, raw_word_count, st.eq_word_count)) as TOTAL, SUM(IF(st.status IS NULL OR st.status='DRAFT' OR st.status='NEW',IF(st.eq_word_count IS NULL, raw_word_count, st.eq_word_count),0)) as DRAFT, SUM(IF(st.status='REJECTED',IF(st.eq_word_count IS NULL, raw_word_count, st.eq_word_count),0)) as REJECTED, SUM(IF(st.status='TRANSLATED',IF(st.eq_word_count IS NULL, raw_word_count, st.eq_word_count),0)) as TRANSLATED, SUM(IF(st.status='APPROVED',IF(st.eq_word_count IS NULL, raw_word_count, st.eq_word_count),0)) as APPROVED, j.id 

		from jobs j 
		INNER JOIN files_job fj on j.id=fj.id_job 
		INNER join segments s on fj.id_file=s.id_file 
		LEFT join segment_translations st on s.id=st.id_segment and st.id_job=j.id


		WHERE j.id in ($jids)
		group by j.id";

	$db = Database::obtain();
	$jobs_stats = $db->fetch_array($query);

        //convert result to ID based index
        foreach ($jobs_stats as $job_stat) {
            $tmp_jobs_stats[$job_stat['id']] = $job_stat;
        }
        $jobs_stats = $tmp_jobs_stats;
        unset($tmp_jobs_stats);
        
        //cycle on results to ensure sanitization
        foreach ($_jids as $jid) {
            //if no stats for that job id
            if (!isset($jobs_stats[$jid])) {
                //add dummy empty stats
                $jobs_stats[$jid] = array('TOTAL' => 1.00, 'DRAFT' => 0.00, 'REJECTED' => 0.00, 'TRANSLATED' => 0.00, 'APPROVED' => 0.00, 'id' => $jid);
            }
        }
        
	return $jobs_stats;
}

function getStatsForJob($id_job) {


	// Old Raw-wordcount
	/*
	   $query = "select SUM(raw_word_count) as TOTAL, SUM(IF(status IS NULL OR status='DRAFT' OR status='NEW',raw_word_count,0)) as DRAFT, SUM(IF(status='REJECTED',raw_word_count,0)) as REJECTED, SUM(IF(status='TRANSLATED',raw_word_count,0)) as TRANSLATED, SUM(IF(status='APPROVED',raw_word_count,0)) as APPROVED from jobs j INNER JOIN files_job fj on j.id=fj.id_job INNER join segments s on fj.id_file=s.id_file LEFT join segment_translations st on s.id=st.id_segment WHERE j.id=" . $id_job;
	 */	

	$query = "
		select 
                j.id,
		SUM(
				IF(st.eq_word_count IS NULL, s.raw_word_count, st.eq_word_count)
		   ) as TOTAL, 
		SUM(
				IF(
					st.status IS NULL OR 
					st.status='DRAFT' OR 
					st.status='NEW',
					IF(st.eq_word_count IS NULL, s.raw_word_count, st.eq_word_count),0)
		   ) as DRAFT,
		SUM(
				IF(st.status='REJECTED',
					IF(st.eq_word_count IS NULL, s.raw_word_count, st.eq_word_count),0
				  )
		   ) as REJECTED, 
		SUM(
				IF(st.status='TRANSLATED',
					IF(st.eq_word_count IS NULL, s.raw_word_count, st.eq_word_count),0
				  )
		   ) as TRANSLATED, 
		SUM(
				IF(st.status='APPROVED',
					IF(st.eq_word_count IS NULL, s.raw_word_count, st.eq_word_count),0
				  )
		   ) as APPROVED 

			from jobs as j 
			INNER JOIN files_job as fj on j.id=fj.id_job 
			INNER join segments as s on fj.id_file=s.id_file 
			LEFT join segment_translations as st on s.id=st.id_segment and st.id_job=j.id


			WHERE j.id=$id_job";

	$db = Database::obtain();
	$results = $db->fetch_array($query);

	return $results;
}

function getStatsForFile($id_file) {
	$db = Database::obtain();
        
        //SQL Injection... cast to int 
        $id_file = intval($id_file);
        $id_file = $db->escape($id_file);
        
	// Old raw-wordcount
	/*
	   $query = "select SUM(raw_word_count) as TOTAL, SUM(IF(status IS NULL OR status='DRAFT' OR status='NEW',raw_word_count,0)) as DRAFT, SUM(IF(status='REJECTED',raw_word_count,0)) as REJECTED, SUM(IF(status='TRANSLATED',raw_word_count,0)) as TRANSLATED, SUM(IF(status='APPROVED',raw_word_count,0)) as APPROVED from jobs j INNER JOIN files_job fj on j.id=fj.id_job INNER join segments s on fj.id_file=s.id_file LEFT join segment_translations st on s.id=st.id_segment WHERE s.id_file=" . $id_file;
	 */
	$query = "SELECT SUM(IF(st.eq_word_count IS NULL, raw_word_count, st.eq_word_count) ) as TOTAL, 
                         SUM(IF(st.status IS NULL OR st.status='DRAFT' OR st.status='NEW',IF(st.eq_word_count IS NULL, raw_word_count, st.eq_word_count),0)) as DRAFT, 
                         SUM(IF(st.status='REJECTED',IF(st.eq_word_count IS NULL, raw_word_count, st.eq_word_count),0)) as REJECTED, 
                         SUM(IF(st.status='TRANSLATED',IF(st.eq_word_count IS NULL, raw_word_count, st.eq_word_count),0)) as TRANSLATED, 
                         SUM(IF(st.status='APPROVED',raw_word_count,0)) as APPROVED from jobs j 
                   INNER JOIN files_job fj on j.id=fj.id_job 
                   INNER join segments s on fj.id_file=s.id_file 
                   LEFT join segment_translations st on s.id=st.id_segment 
                   WHERE s.id_file=" . $id_file;

	$results = $db->fetch_array($query);

	return $results;
}

function getLastSegmentIDs($id_job) {

	$query = "SELECT group_concat(c.id_segment) as estimation_seg_ids from (SELECT id_segment from segment_translations WHERE id_job=$id_job AND status in ('TRANSLATED','APPROVED') ORDER by translation_date DESC LIMIT 0,10) as c";

	$db = Database::obtain();
	$results = $db->fetch_array($query);

	return $results;
}

function getEQWLastHour($id_job, $estimation_seg_ids) {


	// Old raw-wordcount
	/*
	   $query = "SELECT SUM(raw_word_count), MIN(translation_date), MAX(translation_date), 
	   IF(UNIX_TIMESTAMP(MAX(translation_date))-UNIX_TIMESTAMP(MIN(translation_date))>3600 OR count(*)<10,0,1) as data_validity, 
	   ROUND(SUM(raw_word_count)/(UNIX_TIMESTAMP(MAX(translation_date))-UNIX_TIMESTAMP(MIN(translation_date)))*3600) as words_per_hour, 
	   count(*) from segment_translations
	   INNER JOIN segments on id=segment_translations.id_segment WHERE status in ('TRANSLATED','APPROVED') and id_job=$id_job and id_segment in ($estimation_seg_ids)";
	 */

	$query = "SELECT SUM(IF(st.eq_word_count IS NULL, raw_word_count, st.eq_word_count)), MIN(translation_date), MAX(translation_date), 
		IF(UNIX_TIMESTAMP(MAX(translation_date))-UNIX_TIMESTAMP(MIN(translation_date))>3600 OR count(*)<10,0,1) as data_validity, 
		ROUND(SUM(IF(st.eq_word_count IS NULL, raw_word_count, st.eq_word_count))/(UNIX_TIMESTAMP(MAX(translation_date))-UNIX_TIMESTAMP(MIN(translation_date)))*3600) as words_per_hour, 
		count(*) from segment_translations st
			INNER JOIN segments on id=st.id_segment WHERE status in ('TRANSLATED','APPROVED') and id_job=$id_job and id_segment in ($estimation_seg_ids)";




	$db = Database::obtain();
	$results = $db->fetch_array($query);

	return $results;
}

function getOriginalFile($id_file) {

	$query = "select xliff_file from files where id=" . $id_file;

	$db = Database::obtain();
	$results = $db->fetch_array($query);

	return $results;
}

function getEditLog($jid, $pass) {

	$query = "SELECT
		s.id as sid,
		s.segment AS source,
		st.translation AS translation,
		st.time_to_edit AS tte,
		st.suggestion AS sug,
		st.suggestions_array AS sar,
		st.suggestion_source AS ss,
		st.suggestion_match AS sm,
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

	$db = Database::obtain();
	$results = $db->fetch_array($query);

	return $results;
}

function getNextUntranslatedSegment($sid, $jid) {

	// Warning this is a LEFT join a little slower...
	$query = "select s.id
		from segments s
		LEFT JOIN segment_translations st on st.id_segment = s.id
		INNER JOIN files_job fj on fj.id_file=s.id_file 
		INNER JOIN jobs j on j.id=fj.id_job 
		where fj.id_job=$jid AND 
		(st.status in ('NEW','DRAFT','REJECTED') OR st.status IS NULL) and s.id>$sid
		and s.show_in_cattool=1
		order by s.id
		limit 1
		";

	$db = Database::obtain();
	$results = $db->fetch_array($query);

	return $results;
}

function getNextSegmentId($sid, $jid, $status) {
	$rules = ($status == 'untranslated') ? "'NEW','DRAFT','REJECTED'" : "'$status'";
	$statusIsNull = ($status == 'untranslated') ? " OR st.status IS NULL" : "";
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

	$db = Database::obtain();
	$results = $db->query_first($query);
	return $results['sid'];
}

function insertProject($id_customer, $project_name, $analysis_status, $password, $ip='UNKNOWN') {
	$data = array();
	$data['id_customer'] = $id_customer;
	$data['name'] = $project_name;
	$data['create_date'] = date("Y-m-d H:i:s");
	$data['status_analysis'] = $analysis_status;
	$data['password'] = $password;
	$data['remote_ip_address'] = empty($ip)?'UNKNOWN':$ip;
	$query = "SELECT LAST_INSERT_ID() FROM projects";

	$db = Database::obtain();
	$db->insert('projects', $data);
	$results = $db->query_first($query);
	return $results['LAST_INSERT_ID()'];
}

function insertTranslator($user, $pass, $api_key, $email = '', $first_name = '', $last_name = '') {
	//get link
	$db = Database::obtain();
	//if this user already exists, return it without inserting again
	//this is because we allow to start a project with the bare key
	$query = "select username from translators where mymemory_api_key='" . $db->escape($api_key) . "'";
	$user_id = $db->query_first($query);
	$user_id = $user_id['username'];
	if (empty($user_id)) {
		$data = array();
		$data['username'] = $user;
		$data['email'] = $email;
		$data['password'] = $pass;
		$data['first_name'] = $first_name;
		$data['last_name'] = $last_name;
		$data['mymemory_api_key'] = $api_key;

		$db->insert('translators', $data);

		$user_id = $user;
	}
	return $user_id;
}

function insertJob($password, $id_project, $id_translator, $source_language, $target_language, $mt_engine, $tms_engine,$owner) {
	$data = array();
	$data['password'] = $password;
	$data['id_project'] = $id_project;
	$data['id_translator'] = $id_translator;
	$data['source'] = $source_language;
	$data['target'] = $target_language;
	$data['id_tms'] = $tms_engine;
	$data['id_mt_engine'] = $mt_engine;
	$data['create_date'] = date("Y-m-d H:i:s");
	$data['owner'] = $owner;

	$query = "SELECT LAST_INSERT_ID() FROM jobs";

	$db = Database::obtain();
	$db->insert('jobs', $data);
	$results = $db->query_first($query);
	return $results['LAST_INSERT_ID()'];
}

function insertFileIntoMap($sha1, $source, $target, $deflated_file, $deflated_xliff) {
	$db = Database::obtain();
	$data = array();
	$data['sha1'] = $sha1;
	$data['source'] = $source;
	$data['target'] = $target;
	$data['deflated_file'] = $deflated_file;
	$data['deflated_xliff'] = $deflated_xliff;
	$data['creation_date'] = date("Y-m-d");

	$db->insert('original_files_map', $data);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0 and $errno != 1062) {
		log::doLog($err);
		return $errno * -1;
	}
	return 1;
}

function getXliffBySHA1($sha1, $source, $target, $not_older_than_days = 0) {
	$db = Database::obtain();
	$where_creation_date = "";
	if ($not_older_than_days != 0) {
		$where_creation_date = " AND creation_date > DATE_SUB(NOW(), INTERVAL $not_older_than_days DAY)";
	}
	$query = "select deflated_xliff from original_files_map where sha1='$sha1' and source='$source' and target ='$target' $where_creation_date";
	$res = $db->query_first($query);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $res['deflated_xliff'];
}

function insertFile($id_project, $file_name, $source_language, $mime_type, $contents, $sha1_original = null,$original_file=null) {
	$data = array();
	$data['id_project'] = $id_project;
	$data['filename'] = $file_name;
	$data['source_language'] = $source_language;
	$data['mime_type'] = $mime_type;
	$data['xliff_file'] = $contents;
	if (!is_null($sha1_original)) {
		$data['sha1_original_file'] = $sha1_original;
	}

	if (!is_null($original_file) and !empty($original_file)) {
		$data['original_file'] = $original_file;
	}


	$query = "SELECT LAST_INSERT_ID() FROM files";

	$db = Database::obtain();

	$db->insert('files', $data);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno == 1153) {
		log::doLog("file too large for mysql packet: increase max_allowed_packed_size");

		$maxp = $db->query_first('SELECT @@global.max_allowed_packet');
		log::doLog($maxp);
		// to set the max_allowed_packet to 500MB
		$db->query('SET @@global.max_allowed_packet = ' . 500 * 1024 * 1024);
		$db->insert('files', $data);
		$db->query('SET @@global.max_allowed_packet = ' . 32 * 1024 * 1024); //restore to 32 MB
	}
	$results = $db->query_first($query);
	return $results['LAST_INSERT_ID()'];
}

function insertFilesJob($id_job, $id_file) {
	$data = array();
	$data['id_job'] = $id_job;
	$data['id_file'] = $id_file;

	$db = Database::obtain();
	$db->insert('files_job', $data);
}

function getPdata($pid) {
	$db = Database::obtain();
	$query = "select pid from projects where id =$pid";
	$res = $db->query_first($query);
	return $res['id'];
}

function getProjectData($pid, $password) {
	// per ora lasciamo disabilitata la verifica della password

	$query = "select p.name, j.id as jid, j.password as jpassword, j.source, j.target, f.id,f.filename, p.status_analysis,
		sum(s.raw_word_count) as file_raw_word_count, sum(st.eq_word_count) as file_eq_word_count, count(s.id) as total_segments,
		p.fast_analysis_wc,p.tm_analysis_wc, p.standard_analysis_wc

			from projects p 
			inner join jobs j on p.id=j.id_project
			inner join files f on p.id=f.id_project
			inner join segments s on s.id_file=f.id
			left join segment_translations st on st.id_segment=s.id and st.id_job=j.id

			where p.id= '$pid' and p.password='$password'

			group by 6,2 ";


	$db = Database::obtain();
	$results = $db->fetch_array($query);
	return $results;
}

function getProjects($start,$step,$search_in_pname,$search_source,$search_target,$search_status,$search_onlycompleted,$filtering,$project_id) {

	#session_start();

    $pn_query = ($search_in_pname)? " p.name like '%$search_in_pname%' and" : "";
	$ss_query = ($search_source)? " j.source='$search_source' and" : "";
	$st_query = ($search_target)? " j.target='$search_target' and" : "";
	$sst_query = ($search_status)? " j.status_owner='$search_status' and" : "";
	$oc_query = ($search_onlycompleted)? " j.completed=1 and" : "";
	$single_query = ($project_id)? " j.id_project=$project_id and" : "";
	$owner = $_SESSION['cid'];
    $owner_query = " j.owner='$owner' and"; 
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

	$filter_query = ($query_tail == '')? '': 'where ' . $query_tail;
	$filter_query = preg_replace('/( and)$/i','',$filter_query);

	$query = "select p.id as pid, p.name, p.password, p.id_engine_mt, p.id_engine_tm, p.tm_analysis_wc,
		group_concat(j.id,'##', j.source,'##',j.target,'##',j.create_date,'##',j.password,'##',e.name,'##',if (t.mymemory_api_key is NUll,'',t.mymemory_api_key),'##',j.status_owner) as job 

			from projects p
			inner join jobs j on j.id_project=p.id 
			inner join engines e on j.id_mt_engine=e.id 
			left join translators t on j.id_translator=t.username
			$filter_query
			group by 1
			order by pid desc, j.id
			limit $start,$step";				



			$db = Database::obtain();
	$results = $db->fetch_array($query);

	return $results;
}

function getProjectsNumber($start,$step,$search_in_pname,$search_source,$search_target,$search_status,$search_onlycompleted,$filtering) {

	//	$pn = ($search_in_pname)? "where p.name like '%$search_in_pname%'" : "";

	$pn_query = ($search_in_pname)? " p.name like '%$search_in_pname%' and" : "";
	$ss_query = ($search_source)? " j.source='$search_source' and" : "";
	$st_query = ($search_target)? " j.target='$search_target' and" : "";
	$sst_query = ($search_status)? " j.status_owner='$search_status' and" : "";
	$oc_query = ($search_onlycompleted)? " j.completed=1 and" : "";
	$owner = $_SESSION['cid'];
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
	$query_tail = $pn_query . $ss_query . $st_query. $sst_query . $oc_query . $owner_query ;
	$filter_query = ($query_tail == '')? '': 'where ' . $query_tail;
	$filter_query = preg_replace('/( and)$/i','',$filter_query);

	$query = "select count(*) as c 

		from projects p
		inner join jobs j on j.id_project=p.id 
		inner join engines e on j.id_mt_engine=e.id 
		left join translators t on j.id_translator=t.username
		$filter_query";				



		$db = Database::obtain();
	$results = $db->fetch_array($query);

	return $results;
}

function getProjectStatsVolumeAnalysis2($pid, $groupby = "job") {

	$db = Database::obtain();

	switch ($groupby) {
		case 'job':
			$first_column = "j.id";
			$groupby=" GROUP BY j.id";
			break;
		case 'file':
			$first_column = "fj.id_file,fj.id_job,";
			$groupby=" GROUP BY fj.id_file,fj.id_job";
			break;
		default:
			$first_column = "j.id";
			$groupby=" GROUP BY j.id";
	}

	$query = "select $first_column, 
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
	$results = $db->fetch_array($query);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $results;
}

function getProjectStatsVolumeAnalysis($pid) {


	$query="select st.id_job as jid,st.id_segment as sid, s.id_file, s.raw_word_count,
		st.suggestion_source, st.suggestion_match, st.eq_word_count, st.standard_word_count, st.match_type,
		p.status_analysis, p.fast_analysis_wc,p.tm_analysis_wc,p.standard_analysis_wc,
		st.tm_analysis_status as st_status_analysis
			from segment_translations as st 
			join segments as s on st.id_segment=s.id
			join jobs as j on j.id=st.id_job
			join projects as p on p.id=j.id_project
			where p.id=$pid and p.status_analysis in ('NEW', 'FAST_OK','DONE') and st.match_type<>''";

	$db = Database::obtain();
	$results = $db->fetch_array($query);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}

	return $results;
}

function getProjectForVolumeAnalysis($type, $limit = 1) {

	$query_limit = " limit $limit";

	$type = strtoupper($type);

	if ($type == 'FAST') {
		$status_search = "NEW";
	} else {
		$status_search = "FAST_OK";
	}
	$query = "select p.id, group_concat(j.id) as jid_list 
		from projects p
		inner join jobs j on j.id_project=p.id
		where status_analysis = '$status_search'
		group by 1
		order by id $query_limit
		";
	$db = Database::obtain();

	$results = $db->fetch_array($query);

	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $results;
}

function getSegmentsForFastVolumeAnalysys($pid) {
	$query = "select concat(s.id,'-',group_concat(j.id)) as jsid,s.segment 
		from segments as s 
		inner join files_job as fj on fj.id_file=s.id_file
		inner join jobs as j on fj.id_job=j.id
		where j.id_project='$pid' 
		group by s.id
		order by s.id";
	$db = Database::obtain();
	$results = $db->fetch_array($query);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $results;
}

function getSegmentsForTMVolumeAnalysys($jid) {
	$query = "select s.id as sid ,segment ,raw_word_count,st.match_type from segments s 
		left join segment_translations st on st.id_segment=s.id

		where st.id_job='$jid' and st.match_type<>'' and st.tm_analysis_status='UNDONE' and s.raw_word_count>0
		limit 100";

	$db = Database::obtain();
	$results = $db->fetch_array($query);
	$err = $db->get_error();
	$errno = $err['error_code'];

	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $results;
}

function insertSegmentTMAnalysis($id_segment, $id_job, $suggestion, $suggestion_json, $suggestion_source, $match_type, $eq_word) {
	$db = Database::obtain();
	$data = array();
	//$data['id_segment'] = $id_segment;
	//$data['id_job'] = $id_job;
	$data['match_type'] = $match_type;
	$data['eq_word_count'] = $eq_word;
	$data['suggestion'] = $suggestion;
	$data['translation'] = $suggestion;
	$data['suggestions_array'] = $suggestion_json;
	$data['suggestion_source'] = $suggestion_source;


	$where = "  id_job=$id_job and id_segment=$id_segment ";
	$db->update('segment_translations', $data, $where);


	$db->insert('segment_translations', $data);
	$err = $db->get_error();
	$errno = $err['error_code'];

	if ($errno == 1062) {
		unset($data['id_job']);
		unset($data['id_segment']);
		$where = "  id_job=$id_job and id_segment=$id_segment ";
		$db->update('segment_translations', $data, $where);
		$err = $db->get_error();
		$errno = $err['error_code'];
	}

	if ($errno != 0) {
		if($errno!=1062) log::doLog($err);
		return $errno * -1;
	}

	$data2['fast_analysis_wc'] = $total_eq_wc;
	$where = " id = $pid";
	$db->update('projects', $data2, $where);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $db->affected_rows;
}

function changeTmWc($pid, $pid_eq_words, $pid_standard_words) {
	// query  da incorporare nella changeProjectStatus 
	$db = Database::obtain();
	$data = array();
	$data['tm_analysis_wc'] = $pid_eq_words;
	$data['standard_analysis_wc'] = $pid_standard_words;
	$where = " id =$pid";
	$db->update('projects', $data, $where);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $db->affected_rows;
}

function insertFastAnalysis($pid, $fastReport, $equivalentWordMapping) {
	$db = Database::obtain();
	$data = array();

	$total_eq_wc = 0;
	$total_standard_wc = 0;
	foreach ($fastReport as $k => $v) {
		$jid_fid = explode("-", $k);
		$id_segment = $jid_fid[0];
		$id_jobs = $jid_fid[1];

		$type = strtoupper($v['type']);

		if (array_key_exists($type, $equivalentWordMapping)) {
			$eq_word = ($v['wc'] * $equivalentWordMapping[$type] / 100);
			if ($type == "INTERNAL") {
			}
		} else {
			$eq_word = $v['wc'];
		}
		$total_eq_wc+=$eq_word;
		$standard_words = $eq_word;
		if ($type == "INTERNAL" or $type == "MT") {
			$standard_words = $v['wc'] * $equivalentWordMapping["NO_MATCH"] / 100;
		}
		$total_standard_wc+=$standard_words;

		$id_jobs=explode(',',$id_jobs);
		foreach($id_jobs as $id_job){
			$data['id_segment'] = $id_segment;
			$data['id_job'] = $id_job;
			$data['match_type'] = $type;
			$data['eq_word_count'] = $eq_word;
			$data['standard_word_count'] = $standard_words;

			// query for this data is after insert/update
			$data_innodb['id_job'] = $data['id_job'];
			$data_innodb['id_segment'] = $data['id_segment'];

			$db->insert('segment_translations', $data);
			$err = $db->get_error();
			$errno = $err['error_code'];

			if ($errno == 1062) {
				unset($data['id_job']);
				unset($data['id_segment']);
				$where = "  id_job=$id_job and id_segment=$id_segment ";
				$db->update('segment_translations', $data, $where);
				$err = $db->get_error();
				$errno = $err['error_code'];
			}



			if ($errno != 0) {
				if($errno!=1062) log::doLog($err);
				return $errno * -1;
			}

			if ($data['eq_word_count'] > 0) {
	//			$db->query("SET autocommit=0");
	//			$db->query("START TRANSACTION");
				$db->insert('segment_translations_analysis_queue', $data_innodb);
				$err = $db->get_error();
				$errno = $err['error_code'];
				if ($errno != 0 and $errno != 1062) {

					log::doLog($err);
	//				$db->query("ROLLBACK");
	//				$db->query("SET autocommit=1");
					return $errno * -1;
				}
	//			$db->query("COMMIT");
	//			$db->query("SET autocommit=1");
			}
		}
	}

	$data2['fast_analysis_wc'] = $total_eq_wc;
	$data2['standard_analysis_wc'] = $total_standard_wc;

	$where = " id = $pid";
	$db->update('projects', $data2, $where);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $db->affected_rows;
}

function changeProjectStatus($pid, $status, $if_status_not = array()) {


	$data['status_analysis'] = $status;
	$where = "id=$pid ";

	if (!empty($if_status_not)) {
		foreach ($if_status_not as $v) {
			$where.=" and status_analysis<>'$v' ";
		}
	}


	$db = Database::obtain();
	$db->update('projects', $data, $where);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $db->affected_rows;
}

function changePassword($res, $id, $password) {
	//    $new_password = 'changedpassword';
	$new_password = $password;

	if ($res == "prj") {
		$query = "update projects set password=\"$new_password\" where id=$id";
	} else {
		$query = "update jobs set password=\"$new_password\" where id=$id";
	}    

	$db = Database::obtain();
	$db->query($query);
	return $new_password;

}

function cancelJob($res, $id) {

	if ($res == "prj") {
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
	$db->query($query);

}

function archiveJob($res, $id) {

	if ($res == "prj") {
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
	$db->query($query);

}

function updateProjectOwner( $ownerEmail, $project_id ){
    $db = Database::obtain();
    $data = array();
    $data['owner'] = $ownerEmail;
    $where = sprintf( " id_project = %u ", $project_id );
    $result = $db->update('jobs', $data, $where);
    return $result;
}

function updateJobsStatus($res, $id, $status, $only_if, $undo) {

	if ($res == "prj") {
		$status_filter_query = ($only_if)? " and status_owner='$only_if'" : "";
		$arStatus = explode(',',$status);

		$test = count(explode(':',$arStatus[0]));
		if(($test > 1) && ($undo == 1)) {
			$cases = '';
			$ids = '';
			foreach ($arStatus as $item) {
				$ss = explode(':',$item);
				$cases .= " when id=$ss[0] then '$ss[1]'";
				$ids .= "$ss[0],";
			}
			$ids = trim($ids,',');
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
	$db->query($query);

}

function getCurrentJobsStatus($pid) {

	$query = "select id,status_owner from jobs where id_project=$pid";

	$db = Database::obtain();
	$results = $db->fetch_array($query);
	return $results;

}


function setSegmentTranslationError($sid, $jid) {


	$data['tm_analysis_status'] = "DONE"; // DONE . I don't want it remains in an incostistent state
	$where = " id_segment=$sid and id_job=$jid ";


	$db = Database::obtain();
	$db->update('segment_translations', $data, $where);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
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
	$q4="ROLLBACK";
	$q5 = "COMMIT";
	$q6 = "SET autocommit=1";

	//execute statements
	//start locking
	$db->query($q1);
	$db->query($q2);
	//query
	$res = $db->query_first($q3);
	//if nothing useful
	if (empty($res)) {
		//empty result
		$res="";
		$db->query($q4);
	}else{
		//else
		//take note of IDs
		$id_job = $res['id_job'];
		$id_segment = $res['id_segment'];

		//set lock flag on row
		$data['locked'] = 1;
		$where = " id_job=$id_job and id_segment=$id_segment ";
		//update segment
		$db->update("segment_translations_analysis_queue", $data, $where);
		$err = $db->get_error();
		$errno = $err['error_code'];
		//if something went wrong
		if ($errno != 0) {
			log::doLog($err);
			$db->query($q4);
			//return error code
			$res=-1;
		}else{
			//if everything went well, commit
			$db->query($q5);
		}
	}
	//release locks and end transaction
	$db->query($q6);

	//return stuff
	return $res;
}

function resetLockSegment() {
	$db = Database::obtain();
	$data['locked'] = 0;
	$where = " locked=1 ";
	$db->update("segment_translations_analysis_queue", $data, $where);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return -1;
	}
	return 0;
}

function deleteLockSegment($id_segment, $id_job, $mode = "delete") {
	$db = Database::obtain();
	if ($mode == "delete") {
		$q = "delete from segment_translations_analysis_queue where id_segment=$id_segment and id_job=$id_job";
	} else {
		$db->query("SET autocommit=0");
		$db->query("START TRANSACTION");
		$q = "update segment_translations_analysis_queue set locked=0 where id_segment=$id_segment and id_job=$id_job";
		$db->query("COMMIT");
		$db->query("SET autocommit=1");
	}
	$db->query($q);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return -1;
	}
	return 0;
}

function getSegmentForTMVolumeAnalysys($id_segment, $id_job) {
	$query = "select s.id as sid ,s.segment ,raw_word_count,
		st.match_type, j.source, j.target, j.id as jid, j.id_translator,
		p.id_engine_mt,p.id as pid
			from segments s
			inner join segment_translations st on st.id_segment=s.id
			inner join jobs j on j.id=st.id_job
			inner join projects p on p.id=j.id_project

			where  

			p.status_analysis='FAST_OK' and

			st.id_segment=$id_segment and st.id_job=$id_job
			limit 1";

	$db = Database::obtain();
	$results = $db->query_first($query);

	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $results;
}

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

	$db = Database::obtain();
	$results = $db->query_first($query);

	$err = $db->get_error();
	$errno = $err['error_code'];

	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $results;
}

function lockUnlockTable($table, $lock_unlock = "unlock", $mode = "READ") {
	$db = Database::obtain();
	if ($lock_unlock == "lock") {
		$query = "LOCK TABLES $table $mode";
	} else {
		$query = "UNLOCK TABLES";
	}

	$results = $db->query($query);
	$err = $db->get_error();
	$errno = $err['error_code'];

	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $results;
}

function lockUnlockSegment($sid, $jid, $value) {


	$data['locked'] = $value;
	$where = "id_segment=$sid and id_job=$jid ";


	$db = Database::obtain();
	$db->update('segment_translations', $data, $where);
	$err = $db->get_error();
	$errno = $err['error_code'];
	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $db->affected_rows;
}

function countSegments($pid) {
	$db = Database::obtain();

	$query = "select  count(s.id) as num_segments
		from segments s 
		inner join files_job fj on fj.id_file=s.id_file
		inner join jobs j on j.id= fj.id_job
		where id_project=$pid and raw_word_count>0
		";

	$results = $db->query_first($query);


	$err = $db->get_error();
	$errno = $err['error_code'];

	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $results['num_segments'];
}

function countSegmentsTranslationAnalyzed($pid) {
	$db = Database::obtain();
	$query = "select sum(if(st.tm_analysis_status='DONE',1,0)) as num_analyzed,
		sum(eq_word_count) as eq_wc ,
		sum(standard_word_count) as st_wc
			from segment_translations st
			inner join jobs j on j.id=st.id_job
			where j.id_project=$pid";

	$results = $db->query_first($query);
	$err = $db->get_error();
	$errno = $err['error_code'];

	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $results;
}

function setJobCompleteness($jid,$is_completed) {
	$db = Database::obtain();
	$query = "update jobs set completed=$is_completed where id=$jid";


	$results = $db->query_first($query);
	$err = $db->get_error();
	$errno = $err['error_code'];

	if ($errno != 0) {
		log::doLog($err);
		return $errno * -1;
	}
	return $db->affected_rows;
}

?>
