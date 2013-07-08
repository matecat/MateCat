<?

include_once INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$UTILS_ROOT . "/xliff.parser.1.2.class.php";

function extractSegments($files_path, $file, $pid, $fid) {

    // Output
    // true = ok
    // -1   = Extension not supported
    // -2   = Parse Error
    // -3   = DB Error

    $mysql_hostname = INIT::$DB_SERVER;   // Database Server machine
    $mysql_database = INIT::$DB_DATABASE;     // Database Name
    $mysql_username = INIT::$DB_USER;   // Database User
    $mysql_password = INIT::$DB_PASS;

    $mysql_link = mysql_connect($mysql_hostname, $mysql_username, $mysql_password);
    mysql_select_db($mysql_database, $mysql_link);

    $query_segment = array();

    // Checking Extentions
    $info = pathinfo($file);
    if (($info['extension'] == 'xliff') || ($info['extension'] == 'sdlxliff') || ($info['extension'] == 'xlf')) {
        $content = file_get_contents("$files_path/$file");
    } else {
        return false;
    }

    $xliff_obj = new Xliff_Parser();
    $xliff = $xliff_obj->Xliff2Array($content);
    // Checking that parsing went well
    if (isset($xliff['parser-errors']) or !isset($xliff['files'])) {
        log::doLog("Xliff Import: Error parsing. " . join("\n", $xliff['parser-errors']));
        return false;
    }

    // Creating the Query
    foreach ($xliff['files'] as $xliff_file) {
        if (!array_key_exists('trans-units', $xliff_file)) {
            continue;
        }
        foreach ($xliff_file['trans-units'] as $xliff_trans_unit) {
            if (!isset($xliff_trans_unit['attr']['translate'])) {
                $xliff_trans_unit['attr']['translate'] = 'yes';
            }
            if ($xliff_trans_unit['attr']['translate'] == "no") {
            } else {
                // If the XLIFF is already segmented (has <seg-source>)
                if (isset($xliff_trans_unit['seg-source'])) {
                    foreach ($xliff_trans_unit['seg-source'] as $seg_source) {
                        $show_in_cattool = 1;
                        $tempSeg = strip_tags($seg_source['raw-content']);
                        $tempSeg = trim($tempSeg);

                        //init tags
                        $seg_source['mrk-ext-prec-tags'] = '';
                        $seg_source['mrk-ext-succ-tags'] = '';
                        if (empty($tempSeg)) {
                            $show_in_cattool = 0;
                        } else {
                            $extract_external = strip_external($seg_source['raw-content']);
                            $seg_source['mrk-ext-prec-tags'] = $extract_external['prec'];
                            $seg_source['mrk-ext-succ-tags'] = $extract_external['succ'];
                            $seg_source['raw-content'] = $extract_external['seg'];
                        }
                        $seg_source['raw-content']=  CatUtils::placeholdnbsp($seg_source['raw-content']);

                        $mid = mysql_real_escape_string($seg_source['mid']);
                        $ext_tags = mysql_real_escape_string($seg_source['ext-prec-tags']);
                        $source = mysql_real_escape_string($seg_source['raw-content']);
                        $ext_succ_tags = mysql_real_escape_string($seg_source['ext-succ-tags']);
                        $num_words = CatUtils::segment_raw_wordcount($seg_source['raw-content']);
                        $trans_unit_id = mysql_real_escape_string($xliff_trans_unit['attr']['id']);
                        $mrk_ext_prec_tags = mysql_real_escape_string($seg_source['mrk-ext-prec-tags']);
                        $mrk_ext_succ_tags = mysql_real_escape_string($seg_source['mrk-ext-succ-tags']);
                        $query_segment[] = "('$trans_unit_id',$fid,'$source',$num_words,'$mid','$ext_tags','$ext_succ_tags',$show_in_cattool,'$mrk_ext_prec_tags','$mrk_ext_succ_tags')";
                    }
                } else {
                    $show_in_cattool = 1;
                    //$tempSeg = stripTagsFromSource2($xliff_trans_unit['source']['raw-content']);
                    $tempSeg = strip_tags($xliff_trans_unit['source']['raw-content']);
                    $tempSeg = trim($tempSeg);
                    $prec_tags = NULL;
                    $succ_tags = NULL;
                    if (empty($tempSeg)) {
                        $show_in_cattool = 0;
                    } else {
                        $extract_external = strip_external($xliff_trans_unit['source']['raw-content']);
                        $prec_tags = empty($extract_external['prec']) ? NULL : $extract_external['prec'];
                        $succ_tags = empty($extract_external['succ']) ? NULL : $extract_external['succ'];
                        $xliff_trans_unit['source']['raw-content'] = $extract_external['seg'];
                    }
                    $source = mysql_real_escape_string($xliff_trans_unit['source']['raw-content']);
                    $source=  CatUtils::placeholdnbsp($source);
                    
                    $num_words = CatUtils::segment_raw_wordcount($xliff_trans_unit['source']['raw-content']);
                    $trans_unit_id = mysql_real_escape_string($xliff_trans_unit['attr']['id']);

                    if (!is_null($prec_tags)) {
                        $prec_tags = mysql_real_escape_string($prec_tags);
                    }
                    if (!is_null($succ_tags)) {
                        $succ_tags = mysql_real_escape_string($succ_tags);
                    }
                    $query_segment[] = "('$trans_unit_id',$fid,'$source',$num_words,NULL,'$prec_tags','$succ_tags',$show_in_cattool,NULL,NULL)";
                }
            }
        }
    }

    if (empty($query_segment)) {
        log::doLog("Segment import - no segments found\n");
        return -1;
    }

    // Executing the Query
    $query_segment = "INSERT INTO segments (internal_id,id_file, segment, raw_word_count, xliff_mrk_id, xliff_ext_prec_tags, xliff_ext_succ_tags, show_in_cattool,xliff_mrk_ext_prec_tags,xliff_mrk_ext_succ_tags) 
		values " . join(",\n", $query_segment);

    $res = mysql_query($query_segment, $mysql_link);
    if (!$res) {
        log::doLog("Segment import - DB Error: " . mysql_error() . " - $query_segment\n");
        return -2;
    }


    return 1;
}

function stripTagsFromSource2($text) {
    $pattern_g_o = '|(<.*?>)|';
    $pattern_g_c = '|(</.*?>)|';
    $pattern_x = '|(<.*?/>)|';

    $text = preg_replace($pattern_x, "", $text);

    $text = preg_replace($pattern_g_o, "", $text);
    //
	$text = preg_replace($pattern_g_c, "", $text);
    $text = str_replace("&nbsp;", " ", $text);
    return $text;
}

function strip_external($a) {
    
    $pattern_x_start = '/^(\s*<x .*?\/>)(.*)/mis';
    $pattern_x_end = '/(.*)(<x .*?\/>\s*)$/mis';
    $pattern_g = '/^(\s*<g [^>]*?>)([^<]*?)(<\/g>\s*)$/mis';
    $found = false;
    $prec = "";
    $succ = "";

    $c = 0;

    do {
        $c+=1;
        $found = false;
        do {
            $r = preg_match_all($pattern_x_start, $a, $res);
            if (isset($res[1][0])) {
                $prec.=$res[1][0];
                $a = $res[2][0];
                $found = true;
            }
        } while (isset($res[1][0]));
        do {
            $r = preg_match_all($pattern_x_end, $a, $res);
            if (isset($res[2][0])) {
                $succ = $res[2][0] . $succ; 
                $a = $res[1][0];
                
                $found = true;
            }
        } while (isset($res[2][0]));



        do {
            $r = preg_match_all($pattern_g, $a, $res);
            if (isset($res[1][0])) {
                $prec.=$res[1][0];
                $succ = $res[3][0] . $succ;
                $a = $res[2][0];
                $found = true;
            }
        } while (isset($res[1][0]));
    } while ($found);
    $r = array('prec' => $prec, 'seg' => $a, 'succ' => $succ);
   
    return $r;
}

?>
