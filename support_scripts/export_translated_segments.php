<?php
function query_to_csv($db_conn, $query, $filename, $attachment = false, $headers = true) {
       
        if($attachment) {
            // send response headers to the browser
            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment;filename='.$filename);
            $fp = fopen('php://output', 'w');
        } else {
            $fp = fopen($filename, 'w');
        }
       
        $result = mysql_query($query, $db_conn) or die( mysql_error( $db_conn ) );
	echo mysql_error($db_conn);
       
        if($headers) {
            // output header row (if at least one row exists)
            $row = mysql_fetch_assoc($result);
		
            if($row) {
                fputcsv($fp, array_keys($row));
                // reset pointer back to beginning
                mysql_data_seek($result, 0);
            }
        }
       
        while($row = mysql_fetch_assoc($result)) {
            fputcsv($fp, $row);
        }
       
        fclose($fp);
	exit (0);
    }

    function extract_first_and_last($db_conn,$jid){

	$jid_parts=explode("-", $jid);
	$chunk="0";

	if (count($jid_parts)>1){
		$chunk=$jid_parts[1];
		$jid=$jid_parts[0];
	}
  
	$s="select job_first_segment, job_last_segment from jobs where id in ($jid) order by job_first_segment";

        $res=mysql_query($s,$db_conn);
	$num_found=mysql_num_rows($res);
	if ($num_found==0){
		return false;
	}

	$chunks=array();
	while ($row=mysql_fetch_assoc($res)){
		array_push($chunks, $row);
	}

	if ($chunk==0){
		$job_first_segment=$chunks[0]['job_first_segment'];
		$job_last_segment=$chunks[0]['job_last_segment'];
	}else{
		$job_first_segment=$chunks[$chunk-1]['job_first_segment'];
		$job_last_segment=$chunks[$chunk-1]['job_last_segment'];

		if (empty($job_first_segment) and empty($job_last_segment) ){
			$job_first_segment=$chunks[0]['job_first_segment'];
			$job_last_segment=$chunks[0]['job_last_segment'];
		}
	}
//print_r(array($jid,$job_first_segment,$job_last_segment));
	return array($jid,$job_first_segment,$job_last_segment);
    }

    $mysql_hostname  = "10.30.1.250";            // Database Server machine

    $mysql_database  = "matecat_sandbox";     // Database Name
    $mysql_username  = "matecat";		 // Database User
    $mysql_password  = "matecat01";		 // Database Password

    $mysql_link = mysql_connect($mysql_hostname, $mysql_username, $mysql_password);
    mysql_select_db($mysql_database, $mysql_link);

    $jid=$_POST['jid'];
    //$not_exist=false;
  
    if (!empty($jid)){

	$first_last=extract_first_and_last($mysql_link,$jid); 

	
	if (!$first_last){
		$not_exist=true;
	}

	if (!isset($not_exist)){
	$job_first_segment=$first_last[1];
	$job_last_segment=$first_last[2];
	$jid=$first_last[0];

	$jid_escape=mysql_real_escape_string($jid, $mysql_link);
	
	// Using the function
        $sql = "SELECT segments.segment as segment, segment_translations.translation as translation
	FROM segment_translations JOIN segments ON segments.id = segment_translations.id_segment
	WHERE segment_translations.id_job = '$jid_escape' and 
              segments.id BETWEEN $job_first_segment and $job_last_segment
    AND segment_translations.status = 'TRANSLATED'
	ORDER BY segments.id;";


	    // $db_conn should be a valid db handle


	    // output as an attachment
	    query_to_csv($mysql_link, $sql, "job_$jid.csv", true);

	    // output to file system
	    //query_to_csv($mysql_link, $sql, "test_$jid.csv", false);
	}
   }

?>


<html>
	<head>
		<style type="text/css">
			body {font-family: Calibri, Arial, sans-serif;}
			.error {color:red; font-weight="bold"}
		</style>
		
	</head>
	<body>
		<h1> Extract TRANSLATED segments from job (segment and translation) as CSV </h1>
		<p> Ex: 9871 , 9871-2 </p>
		<p> If you enter an id belonging to a splitted job without indicating the chunk number, the first chunk will be exported <br/>
		    For example, suppose job 9771 contains two chunks: entering 9771 equals to 9771-1
		</p>
		<form action="" method="post">
			<label for="jid">ID Job </label>
			<input type="text" name="jid" id="jid" value="<?=$jid?>"/> 
			<input type="submit" value="Go"/>
		</form>
		
		<?php if(isset($not_exist) and !is_null($not_exist)): ?>
			<p class="error"> The job "<?=$jid ?>" does not exist </p>
		<?php endif; ?>
	</body>
</html>
