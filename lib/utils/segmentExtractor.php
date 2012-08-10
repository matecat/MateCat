<?

function extractSegments($files_path, $pid, $fid) {
	
	$mysql_hostname = "213.215.131.241";   // Database Server machine
	$mysql_database = "matecat_sandbox";     // Database Name
	$mysql_username = "translated";   // Database User
	$mysql_password = "azerty1";   // Database Password
	
	$mysql_link = mysql_connect($mysql_hostname, $mysql_username, $mysql_password);
	mysql_select_db($mysql_database, $mysql_link);
	
	
	$preg_file_html = '|<file original="(.*?)" source-language="(.*?)" datatype="(.*?)" target-language="(.*?)">|m';
	$preg_trans_unit = '|<trans-unit id="(.*?)"(.*?)>\s*<source>(.*?)</source>|m';
	
	$start_pid = 3;
	$lang_pid = array();
	
	$folder = $files_path;
	$dir = scandir($folder);

    foreach ($dir as $file) {
        if ($file == "." or $file == ".." or is_dir("$folder/$file")) {
            continue;
        }

        $info = pathinfo($file);

        if (($info['extension'] == 'xliff')||($info['extension'] == 'sdlxliff')) {
            $content = file_get_contents("$folder/$file");            
        }

        $res = array();
        preg_match_all($preg_file_html, $content, $res, PREG_SET_ORDER);

        if (!empty($res)) {
            $pathinfo = multiFSPathinfo($res[0][1]);
            $filename = mysql_real_escape_string($pathinfo['filename']);
            $extension = mysql_real_escape_string($pathinfo['extension']);
            $source_lang = mysql_real_escape_string($res[0][2]);
            $datatype = mysql_real_escape_string($res[0][3]);
            $target_lang = mysql_real_escape_string($res[0][4]);
            
//            echo "$filename %% $extension %% $source_lang %% $target_lang %% $datatype \n";
            $this_pid = $pid;
        }

//        echo "pid is $this_pid\n\n";
        
        $res2 = array();
        preg_match_all($preg_trans_unit, $content, $res2, PREG_SET_ORDER);
        foreach ($res2 as $trans_unit) {
            $id = $trans_unit[1];
            $no_translate = $trans_unit[2];
            if (!empty($no_translate) and $no_translate == 'translate="no"') {
                echo "no translatable segment -----$trans_unit[3]----- ...skipping\n";
                continue;
            }
            $source = mysql_real_escape_string($trans_unit[3]);
            $num_words = str_word_count($source);
//        	print_r (' ITEM:'.$id.'|'.$fid.'|'.$source.'|'.$num_words);
            $query_segment = "INSERT INTO segments (internal_id,id_file, segment, raw_word_count) values ('$id',$fid,'$source',$num_words)";
            $res = mysql_query($query_segment, $mysql_link);
            $errno = mysql_errno($mysql_link);
            if ($errno != 0) {
                $echo = "error-3 : " . mysql_error($mysql_link) . " - $query_segment\n";
                die($echo);
            }
        }
	}
	return true;
}

function multiFSPathinfo($s) {
    if (strpos($s, '/') !== false) {
        $d = '/';
    } elseif (strpos($s, '\\') !== false) {
        $d = '\\';
    } else {
        throw new Exception('Valid delimiter not found.');
    }

    $ret = explode($d, $s);
    $ret_ok = array();
    $ret_ok['filename'] = array_pop($ret);
    $ret_ok['extension'] = "";
    $ret_ok['path'] = implode($d, $ret);
    $parts = explode('.', $ret_ok['filename']);
    if (isset($parts[1])) {
        $ret_ok['extension'] = $parts[1];
    }

    return $ret_ok;
}

?>
