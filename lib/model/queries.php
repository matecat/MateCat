<?php

include_once 'Database.class.php';

function getSegments($pid, $start = 0) {
    /* $query = "select s.id,id_file, fj.id_job,segment, mimetype ,filename
      from segments s
      inner join files f on f.id=s.id_file
      inner_join files_jobs fj on fj.id_file=f.id
      inner join projects p on p.id = j.id_project

      where id_file =4
      limit $start,100 ";
     */

    $query = "select j.id as jid, j.id_project as pid, p.id_customer as cid, j.id_translator as tid,  
                p.name as pname, p.create_date , fj.id_file, fj.id_segment_start, fj.id_segment_end, 
                f.filename, f.mime_type, s.id as sid, s.segment, s.raw_word_count,
                st.translation, st.status

                from jobs j 
                inner join projects p on p.id=j.id_project
                inner join files_job fj on fj.id_job=j.id
                inner join files f on f.id=fj.id_file
                inner join segments s on s.id_file=f.id
                left join segment_translations st on st.id_segment=s.id and st.id_job=j.id
                where p.id=$pid 
                limit $start,1000
                
                
                
             ";
	if ($pid==2){
		//echo $query; exit;
	}
    //limit $start,100
    
    // log::doLog($query);

    $db = Database::obtain();
    $results = $db->fetch_array($query);
    // // log::doLog($results);
//    echo "<pre>";
//    echo count($results);
//    print_r ($results);exit;
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
    // log::doLog($db->affected_rows);
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

function getAllData($t, $am, $qof_go, $qof_id, $qof_pid, $qof_source, $qof_reqdate, $qof_tdelivdate, $qof_delivdate, $qof_cid, $qof_curroff, $qof_accoff, $qof_faxreq, $qof_status, $qof_am, $qof_limit) {

    $where = " ";
    $limit = " LIMIT 0,30 ";

    if (empty($qof_go)) {
        $orderBy = "status_new_score+status_sent_score+status_hold_score+important_score+status_non_assegnato DESC ,a.current_offer DESC";

        if (empty($t)) {
            $where_status = "a.status not in ('closed', 'accepted','refused','cancelled') ";
        } elseif ($t != 'all') {
            $where_status = "a.status='$t' ";
        } else {
            $where_status = "";
        }

        if (empty($am) or $am == "TUTTI") {
            $where_am = "";
        } else {
            $where_am = " a.am in ('$am', 'NON ASSEGNATO') ";
        }

        /* $query="SELECT max(co.id) as offer_id, cos.id_project , cos.id,cos.source_channel,cos.arrival_datetime, cos.response_date, cos.target_response_date,
          cos.id_customer, cos.requester_email, cos.requester_name,cos.words,cos.first_offer, cos.current_offer, COUNT(co.id) as num_version,
          MAX(co.creation_date) as max_creation_date, cos.fax_confirm_requested,  cos.fax_confirm_datetime,cos.status,cos.am as am,cos.id_auto_request
          FROM OFFER_STATS_TABLE  cos
          LEFT OUTER JOIN  customers_offers co on co.project_id=cos.id_project

          WHERE  cos.status!='closed'
          GROUP BY co.id
          ORDER BY cos.arrival_datetime desc
          ";
         */
        /* $query = "SELECT id, id_project, arrival_datetime, target_response_date,
          if (response_date = '0000-00-00 00:00:00', '', response_date) as response_date,
          id_customer,
          requester_name,requester_email,current_offer,fax_confirm_requested,
          fax_confirm_datetime,status,am,source_channel,
          IF(id_project IS NULL, NULL, (select max(id) from customers_offers where `project_id`=id_project)) as offer_id,
          IF(id_project IS NULL, NULL, (select max(creation_date) from customers_offers where `project_id`=id_project)) as offer_creation_date
          FROM OFFER_STATS_TABLE where status <>'closed' order by id desc limit 0,100"; //LEFT OUTER JOIN projects p on p.id=co.project_id
         *
         *
         */

        /* $query = "SELECT a.id, a.id_project, arrival_datetime, target_response_date,
          if (response_date = '0000-00-00 00:00:00', '', response_date) as response_date,
          id_customer,
          requester_name,requester_email,current_offer,fax_confirm_requested,
          fax_confirm_datetime,status,am,source_channel, max(j.id) as jid

          FROM OFFER_STATS_TABLE a
          LEFT OUTER JOIN jobs j on j.id_project=a.id_project
          WHERE a.status <>'closed'

          GROUP BY a.id
          ORDER BY id desc limit 0,100";
         */


        if (!empty($where_status)) {
            $where = " WHERE $where_status";
        }

        if (!empty($where_am)) {
            if (!empty($where)) {
                $where .=" AND $where_am";
            } else {
                $where = " WHERE  $where_am";
            }
        }
    } else {
        $orderBy = "a.id DESC";
        $where_arr = array();
        if (!empty($qof_id)) {
            $where_arr[] = "a.id=$qof_id";
        }
        if (!empty($qof_pid)) {
            $where_arr[] = "a.id_project=$qof_pid";
        }
        if (!empty($qof_source) and $qof_source != 'all') {
            $where_arr[] = "a.source_channel='$qof_source'";
        }

        if (!empty($qof_reqdate)) {
            $where_arr[] = "a.arrival_datetime like '%$qof_reqdate%'";
        }
        if (!empty($qof_tdelivdate)) {
            $where_arr[] = "a.target_response_date like  '%$qof_tdelivdate%'";
        }
        if (!empty($qof_delivdate)) {
            $where_arr[] = "a.response_date like '%$qof_delivdate%'";
        }

        if (!empty($qof_cid)) {
            $where_arr[] = "(a.id_customer like '%$qof_cid%' or a.requester_email like '%$qof_cid%')";
        }

        if ($qof_curroff != '') {  //qof_curroff is should not be considered empty value
            $where_arr[] = "a.current_offer='$qof_curroff'";
        }
        if ($qof_accoff != '') { //qof_accoff is should not be considered empty value
            $where_arr[] = "a.accepted_offer='$qof_accoff'";
        }

        if ($qof_faxreq > -1) {
            $where_arr[] = "a.fax_confirm_requested='$qof_faxreq'";
        }

        if (!empty($qof_status) AND $qof_status != 'all') {
            $where_arr[] = "a.status='$qof_status'";
        }

        if (!empty($qof_am) AND $qof_am != 'all') {
            $where_arr[] = "a.am='$qof_am'";
        }


        if (!empty($qof_limit)) {
            $limit = " limit $qof_limit";
        }


        if (!empty($where_arr)) {
            $where = ' WHERE ' . implode(" AND ", $where_arr);
        }
    }



    $query = "SELECT a.id, a.id_project, 
                        DATE_FORMAT(a.arrival_datetime, '%Y-%m-%d %H:%i' ) as arrival_datetime,
                        DATE_FORMAT(a.target_response_date, '%Y-%m-%d %H:%i' ) as target_response_date ,
                        if (response_date = '0000-00-00 00:00:00', '', DATE_FORMAT(response_date, '%Y-%m-%d %H:%i' ) ) as response_date,
                        a.id_customer,a.first_offer,a.suggested_translator
                        requester_name,requester_email,current_offer,accepted_offer,fax_confirm_requested,
                        a.fax_confirm_datetime,a.status,UCASE(am) as am,a.source_channel,a.accept_refuse_datetime,c.pass,
			
                        IF (id_project IS NULL,0,(SELECT SUM(customer_total) FROM jobs j WHERE j.`id_project`=a.id_project and status_customer NOT IN ('test', 'cancellato'))) as njobs,
                        IF (id_project IS NULL,0,(SELECT count(*) FROM jobs j WHERE j.`id_project`=a.id_project and status_customer NOT IN ('test', 'cancellato','offerta'))) as njobsInLavorazione,
                        IF(id_project IS NULL, NULL, (select max(id) from customers_offers where `project_id`=id_project)) as offer_id,
			IF(id_project IS NULL, NULL, (select max(creation_date) from customers_offers where `project_id`=id_project)) as offer_creation_date,
                        
                        IF(c.name='' OR c.addressline_1 ='' OR c.city='' OR c.country=''  OR (c.fiscal_code ='' AND c.piva='') OR (c.contact_email='' AND a.requester_email='') ,1,0) as customer_incomplete,                        
                        IF(c.name=''  ,1,0) as customer_incomplete_name,
                        IF(c.addressline_1=''  ,1,0) as customer_incomplete_address,
                        IF(c.city=''  ,1,0) as customer_incomplete_city,
                        IF(c.country=''  ,1,0) as customer_incomplete_country,
                        IF(c.fiscal_code ='' AND c.piva=''  ,1,0) as customer_incomplete_fiscal_vat,
                        IF(c.contact_email='' AND a.requester_email=''  ,1,0) as customer_incomplete_email,

                        IF (a.status='new',1000 ,0) as status_new_score,
                        IF (a.status='onhold',700 ,0) as status_hold_score,
                        IF (a.status='sent',300 ,0) as status_sent_score,
                        IF (a.important =1,30,0) as important_score,
                        IF (a.am='NON ASSEGNATO',30 ,0) as status_non_assegnato,

                        p.path,p.name as pname,c.lang, a.suggested_translator,c.country,                       
                        DATEDIFF(p.order_date , c.create_date) as  date_diff,
                        a.important,
                        
                        cm.message

                         FROM " . OFFER_STATS_TABLE . " a
                         LEFT OUTER JOIN customers c on c.id=a.id_customer
                         LEFT OUTER JOIN projects p on p.id=a.id_project
                         LEFT OUTER JOIN contact_module_website cm on cm.id=a.website_id_message
                         
                         $where 
                         
                         ORDER BY $orderBy
                         $limit";


//    log::doLog($query);
    //echo $query; exit;

    $db = Database::obtain();
    $results = $db->fetch_array($query);
    // log::doLog($results);
    //print_r ($results);exit;
    return $results;
}

function getCustomerData($id) {
    $query = "select * from  customers where id ='$id'";
    $db = Database::obtain();
    $results = $db->query_first($query);
    return $results;
}

function getOfferData($id) {
    $query = "SELECT project_id as pid,expiration_date,
			name,add1,add2,city,zipcode,country,
			contact,ref,subject,place_date,
			intro,offerta,modalities,conditions,responsabilities,end,per_translated,
			pm,lang 
			from customers_offers where id='$id'";
    //echo $query ; exit;


    $db = Database::obtain();
    $results = $db->query_first($query);
    return $results;
}

function inLavorazione($pid) {
    $data = array();
    $data['STATUS_CUSTOMER'] = 'IN LAVORAZIONE';
    $data['STATUS_TRANSLATOR'] = 'DOC VERIFIED';
    $data['USE_MPM'] = 1;

    $db = Database::obtain();
    $res = $db->update('jobs', $data, "id_project=$pid and STATUS_TRANSLATOR='OFFERTA' AND STATUS_CUSTOMER='OFFERTA'");
    if ($db->get_error_number() != 0) {
        // log::doLog($db->get_error());
        return $db->get_error();
    }
    // log::doLog($db->affected_rows);
    return $db->affected_rows;
}

function countJobsInStatus($pid, $status) {
    if (is_array($status)) {
        $status = implode("','", $status);
    }
    $status = "'$status'";

    $query = "select count(*) as num_jobs from jobs where status_customer in ($status) and id_project=$pid";
    $db = Database::obtain();
    $results = $db->query_first($query);
    if ($db->get_error_number() != 0) {
        // log::doLog($db->get_error());
        return $db->get_error();
    }

    // log::doLog("xxxxx");
    // log::doLog($results);
    // log::doLog("yyyy");
    return $results['num_jobs'];
}

function updateStatus($id_request, $new_status) {
    $data['status'] = $new_status;
    $where = "id=$id_request";


    $db = Database::obtain();
    $res = $db->update(OFFER_STATS_TABLE, $data, $where);
    if ($db->get_error_number() != 0) {
        // log::doLog($db->get_error());
        return $db->get_error();
    }
    // log::doLog($db->affected_rows);
    return $db->affected_rows;
}

function updateStat($id_request, $id_project, $am, $status, $fax_requested, $offer, $offerAccepted, $t_delivery_date, $request_date, $source, $update_accepted_refused, $update_timestamp = true, $update_delivery = false, $delivery_date = "", $important = "") {

    $data['am'] = $am;
    $data['status'] = $status;
    $data['fax_confirm_requested'] = $fax_requested;
    $data['target_response_date'] = $t_delivery_date;
    $data['arrival_datetime'] = $request_date;
    //$data['words']=$words;
    $data['current_offer'] = $offer;
    $data['accepted_offer'] = $offerAccepted;
    $data['source_channel'] = $source;

    if (!empty($update_accepted_refused)) {
        $data['accept_refuse_datetime'] = $update_accepted_refused;
    }

    if (!empty($important) or $important == 0) {
        $data['important'] = $important;
    }

    $now = date("Y-m-d H:i:s");
    if ($update_timestamp) {
        $data['am_fetch_datetime'] = $now;
    }

    if ($update_delivery) {
        if (empty($delivery_date)) {
            $delivery_date = $now;
        }
        $data['response_date'] = $delivery_date;
    }

    if (!empty($id_project) and $id_project != '0') {
        $data['id_project'] = $id_project;
    }

    $db = Database::obtain();
    $results = $db->update(OFFER_STATS_TABLE, $data, "id=$id_request");
    // // log::doLog($db->get_sql());
    return $results;
}

function getAm($id_request) {
    $query = "select am, am_fetch_datetime from " . OFFER_STATS_TABLE . "  where id=$id_request";
    $db = Database::obtain();
    $results = $db->query_first($query);
    return $results;
}

function getCustomerExists($id_customer) {
    $query = "select count(*) as customer_exists from customers where id ='$id_customer'";
    $db = Database::obtain();
    $results = $db->query_first($query);
    return $results;
}
