<?php

include_once 'Database.class.php';

function getJobData($id_job) {
    $query = "select source, target,id_mt_engine,id_tms
                from jobs where id=$id_job";
   
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
    return $results[0];
}

function getTranslatorPass($id_translator){
   
    $db = Database::obtain();   
       
    $id_translator=  $db->escape($id_translator);
    $query = "select password from translators where username='$id_translator'";
    //log::doLog($query);
    
    $db->query_first($query);
    $results = $db->query_first($query);
    
    if ( (is_array($results)) AND (array_key_exists("password", $results)) ) {
        return $results['password'];
    }
    return null;
}

function getTranslatorKey($id_translator){

    $db = Database::obtain();

    $id_translator=  $db->escape($id_translator);
    $query = "select mymemory_api_key from translators where username='$id_translator'";
    //log::doLog($query);

    $db->query_first($query);
    $results = $db->query_first($query);

    if ( (is_array($results)) AND (array_key_exists("mymemory_api_key", $results)) ) {
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

function getSegments($jid,$password, $start = 0, $step = 200) {
	if ($start<0){
		$start=0;	
	}

	if (empty($step)){$step=200;}

	
	
    $query = "select j.id as jid, j.id_project as pid,j.source,j.target, j.last_opened_segment, j.id_translator as tid,
                p.id_customer as cid, j.id_translator as tid,  
                p.name as pname, p.create_date , fj.id_file, fj.id_segment_start, fj.id_segment_end, 
                f.filename, f.mime_type, s.id as sid, s.segment, s.raw_word_count, s.internal_id,
		s.xliff_mrk_id as mrk_id, s.xliff_ext_prec_tags as prev_tags, 
                st.translation, st.status, st.time_to_edit

                from jobs j 
                inner join projects p on p.id=j.id_project
                inner join files_job fj on fj.id_job=j.id
                inner join files f on f.id=fj.id_file
                inner join segments s on s.id_file=f.id
                left join segment_translations st on st.id_segment=s.id and st.id_job=j.id
                where j.id=$jid and j.password='$password'
                limit $start,$step
                
                
                
             ";

    $db = Database::obtain();
    $results = $db->fetch_array($query);

    return $results;
}


function getSegmentsInfo($jid,$password) {
	
    $query = "select j.id as jid, j.id_project as pid,j.source,j.target, j.last_opened_segment, j.id_translator as tid,
                p.id_customer as cid, j.id_translator as tid,  
                p.name as pname, p.create_date , fj.id_file, fj.id_segment_start, fj.id_segment_end, 
                f.filename, f.mime_type

                from jobs j 
                inner join projects p on p.id=j.id_project
                inner join files_job fj on fj.id_job=j.id
                inner join files f on f.id=fj.id_file
                where j.id=$jid and j.password='$password'
                
                
                
                
             ";
    $db = Database::obtain();
    $results = $db->fetch_array($query);

    return $results;
}

function getMoreSegments($jid,$password, $last_loaded_id, $step = 50) {

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
                where j.id=$jid and j.password='$password' and s.id > $last_loaded_id
                order by s.id
                limit 0,$step
             ";

    $db = Database::obtain();
    $results = $db->fetch_array($query);

    return $results;
}

function setTranslationUpdate($id_segment, $id_job, $status, $time_to_edit, $translation, $match_type = 'unknown') {
    $data = array();
    $data['id_job'] = $id_job;
    $data['status'] = $status;
    $data['time_to_edit'] = $time_to_edit;
    $data['translation'] = $translation;
    $data['translation_date'] = date("Y-m-d H:i:s");
    $data['match_type'] = $match_type;

    $where = "id_segment=$id_segment and id_job=$id_job";


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

function setTranslationInsert($id_segment, $id_job, $status, $time_to_edit, $translation, $match_type = 'unknown') {
    $data = array();
    $data['id_job'] = $id_job;
    $data['status'] = $status;
    $data['time_to_edit'] = $time_to_edit;
    $data['translation'] = $translation;
    $data['translation_date'] = date("Y-m-d H:i:s");
    $data['match_type'] = $match_type;
    $data['id_segment'] = $id_segment;
    $data['id_job'] = $id_job;
    $db = Database::obtain();
    $db->insert('segment_translations', $data);

    $err = $db->get_error();
    $errno = $err['error_code'];
    if ($errno != 0) {
        log::doLog($err);
        return $errno * -1;
    }
    return $db->affected_rows;
}

function setSuggestionUpdate($id_segment, $id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source) {
    $data = array();
    $data['id_job'] = $id_job;
    $data['suggestions_array'] = $suggestions_json_array;
    $data['suggestion'] = $suggestion;
    $data['suggestion_match'] = $suggestion_match;
    $data['suggestion_source'] = $suggestion_source;


    $where = "id_segment=$id_segment and id_job=$id_job and suggestions_array is NULL";


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

function setSuggestionInsert($id_segment, $id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source) {
    $data = array();
    $data['id_job'] = $id_job;
    $data['id_segment'] = $id_segment;
    $data['suggestions_array'] = $suggestions_json_array;
    $data['suggestion'] = $suggestion;
    $data['suggestion_match'] = $suggestion_match;
    $data['suggestion_source'] = $suggestion_source;

    $db = Database::obtain();
    $db->insert('segment_translations', $data);

    $err = $db->get_error();
    $errno = $err['error_code'];
    if ($errno != 0) {
        log::doLog($err);
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
    // log::doLog($db->affected_rows);
    return $db->affected_rows;
}

function getFilesForJob($id_job) {

    $query = "select id_file from files_job where id_job=".$id_job;

    $db = Database::obtain();
    $results = $db->fetch_array($query);

    return $results;
}

function getStatsForJob($id_job) {

    $query = "select SUM(raw_word_count) as TOTAL, SUM(IF(status IS NULL OR status='DRAFT' OR status='NEW',raw_word_count,0)) as DRAFT, SUM(IF(status='REJECTED',raw_word_count,0)) as REJECTED, SUM(IF(status='TRANSLATED',raw_word_count,0)) as TRANSLATED, SUM(IF(status='APPROVED',raw_word_count,0)) as APPROVED from jobs j INNER JOIN files_job fj on j.id=fj.id_job INNER join segments s on fj.id_file=s.id_file LEFT join segment_translations st on s.id=st.id_segment WHERE j.id=".$id_job;

    $db = Database::obtain();
    $results = $db->fetch_array($query);

    return $results;
}

function getStatsForFile($id_file) {

    $query = "select SUM(raw_word_count) as TOTAL, SUM(IF(status IS NULL OR status='DRAFT' OR status='NEW',raw_word_count,0)) as DRAFT, SUM(IF(status='REJECTED',raw_word_count,0)) as REJECTED, SUM(IF(status='TRANSLATED',raw_word_count,0)) as TRANSLATED, SUM(IF(status='APPROVED',raw_word_count,0)) as APPROVED from jobs j INNER JOIN files_job fj on j.id=fj.id_job INNER join segments s on fj.id_file=s.id_file LEFT join segment_translations st on s.id=st.id_segment WHERE s.id_file=".$id_file;

    $db = Database::obtain();
    $results = $db->fetch_array($query);

    return $results;
}


function getLastSegmentIDs($id_job) {

    $query = "SELECT group_concat(c.id_segment) as estimation_seg_ids from (SELECT id_segment from segment_translations WHERE id_job=$id_job AND status in ('TRANSLATED','APPROVED') ORDER by translation_date DESC LIMIT 0,10) as c";

    $db = Database::obtain();
    $results = $db->fetch_array($query);

    return $results;
}


function getEQWLastHour($id_job,$estimation_seg_ids) {

    $query = "SELECT SUM(raw_word_count), MIN(translation_date), MAX(translation_date), 
    		  IF(UNIX_TIMESTAMP(MAX(translation_date))-UNIX_TIMESTAMP(MIN(translation_date))>3600 OR count(*)<10,0,1) as data_validity, 
    		  ROUND(SUM(raw_word_count)/(UNIX_TIMESTAMP(MAX(translation_date))-UNIX_TIMESTAMP(MIN(translation_date)))*3600) as words_per_hour, 
    	      count(*) from segment_translations
    	      INNER JOIN segments on id=segment_translations.id_segment WHERE status in ('TRANSLATED','APPROVED') and id_job=$id_job and id_segment in ($estimation_seg_ids)";

    $db = Database::obtain();
    $results = $db->fetch_array($query);

    return $results;
}

function getOriginalFile($id_file) {

    $query = "select original_file from files where id=".$id_file;

    $db = Database::obtain();
    $results = $db->fetch_array($query);

    return $results;
}

function getEditLog($jid,$pass) {

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
	    s.raw_word_count rwc
		FROM
		    jobs j 
                    INNER JOIN segment_translations st ON j.id=st.id_job 
                    INNER JOIN segments s ON s.id = st.id_segment
		WHERE
		    id_job = $jid AND
		    password = '$pass' AND
		    translation IS NOT NULL 
		ORDER BY tte DESC
		LIMIT 5000";

    $db = Database::obtain();
    $results = $db->fetch_array($query);

    return $results;
}

function getNextUntranslatedSegment($sid,$jid) {

	$query = "select id_segment
				from segment_translations
				where id_job=$jid and status in ('NEW','DRAFT','REJECTED') and id_segment>$sid
				order by id_segment
				limit 1
			";
             
    $db = Database::obtain();
    $results = $db->fetch_array($query);

    return $results;
}


function insertProject($id_customer, $project_name) {
    $data = array();
    $data['id_customer'] = $id_customer;
    $data['name'] = $project_name;
    $data['create_date'] = date("Y-m-d H:i:s");
	$query = "SELECT LAST_INSERT_ID() FROM projects";
	
    $db = Database::obtain();
    $db->insert('projects', $data);
    $results = $db->query_first($query);
    return $results['LAST_INSERT_ID()'];
}

function insertJob($password, $id_project, $id_translator, $source_language, $target_language, $mt_engine, $tms_engine) {
    $data = array();
    $data['password'] = $password;
    $data['id_project'] = $id_project;
    $data['id_translator'] = $id_translator;
    $data['source'] = $source_language;
    $data['target'] = $target_language;
    $data['id_tms'] = $tms_engine;
    $data['id_mt_engine'] = $mt_engine;

    $query = "SELECT LAST_INSERT_ID() FROM jobs";
	
    $db = Database::obtain();
    $db->insert('jobs', $data);
    $results = $db->query_first($query);
    return $results['LAST_INSERT_ID()'];	
}

function insertFile($id_project, $file_name, $source_language, $mime_type, $contents) {
    $data = array();
    $data['id_project'] = $id_project;
    $data['filename'] = $file_name;
    $data['source_language'] = $source_language;
    $data['mime_type'] = $mime_type;
    $data['original_file'] = $contents;
    $query = "SELECT LAST_INSERT_ID() FROM files";
	
    $db = Database::obtain();
    $db->insert('files', $data);
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
