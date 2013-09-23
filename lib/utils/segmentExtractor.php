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

    $translations = array();

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
                    foreach ($xliff_trans_unit['seg-source'] as $position => $seg_source) {

                        $show_in_cattool = 1;
                        $tempSeg = strip_tags($seg_source['raw-content']);
                        $tempSeg = trim($tempSeg);

                        //init tags
                        $seg_source['mrk-ext-prec-tags'] = '';
                        $seg_source['mrk-ext-succ-tags'] = '';
                        if ( is_null($tempSeg) || $tempSeg === '' ) {
                            $show_in_cattool = 0;
                        } else {
                            $extract_external = strip_external($seg_source['raw-content']);
                            $seg_source['mrk-ext-prec-tags'] = $extract_external['prec'];
                            $seg_source['mrk-ext-succ-tags'] = $extract_external['succ'];
                            $seg_source['raw-content'] = $extract_external['seg'];

                            if( isset( $xliff_trans_unit['seg-target'][$position]['raw-content'] ) ){
                                $target_extract_external = strip_external( $xliff_trans_unit['seg-target'][$position]['raw-content'] );

                                //we don't want THE CONTENT OF TARGET TAG IF PRESENT and EQUAL TO SOURCE???
                                //AND IF IT IS ONLY A CHAR? like "*" ?
                                //we can't distinguish if it is translated or not
                                //this means that we lose the tags id inside the target if different from source
                                $src = strip_tags( html_entity_decode( $extract_external['seg'], ENT_QUOTES, 'UTF-8' ) );
                                $trg = strip_tags( html_entity_decode( $target_extract_external['seg'], ENT_QUOTES, 'UTF-8' ) );

                                if( $src != $trg ){

                                    Log::doLog( strip_tags( html_entity_decode( $extract_external['seg'], ENT_QUOTES, 'UTF-8' ) ) );
                                    Log::doLog( strip_tags( html_entity_decode( $target_extract_external['seg'], ENT_QUOTES, 'UTF-8' ) ) );

                                    $target = CatUtils::placeholdnbsp($target_extract_external['seg']);
                                    $target = mysql_real_escape_string($target);

                                    //add an empty string to avoid casting to int: 0001 -> 1
                                    //useful for idiom internal xliff id
                                    $translations[ "" . $xliff_trans_unit[ 'attr' ][ 'id' ] ][ ] = $target;

                                    //seg-source and target translation can have different mrk id
                                    //override the seg-source surrounding mrk-id with them of target
                                    $seg_source['mrk-ext-prec-tags'] = $target_extract_external['prec'];
                                    $seg_source['mrk-ext-succ-tags'] = $target_extract_external['succ'];

                                }

                            }

                        }

                        //Log::doLog( $xliff_trans_unit ); die();

                        $seg_source['raw-content'] = CatUtils::placeholdnbsp($seg_source['raw-content']);

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
                    $tempSeg = strip_tags( $xliff_trans_unit['source']['raw-content'] );
                    $tempSeg = trim($tempSeg);
                    $tempSeg = CatUtils::placeholdnbsp( $tempSeg );
                    $prec_tags = NULL;
                    $succ_tags = NULL;
                    if ( empty( $tempSeg ) || $tempSeg == NBSPPLACEHOLDER ) { //@see cat.class.php, don't show <x id=\"nbsp\"/>
                        $show_in_cattool = 0;
                    } else {
                        $extract_external                              = strip_external( $xliff_trans_unit[ 'source' ][ 'raw-content' ] );
                        $prec_tags                                     = empty( $extract_external[ 'prec' ] ) ? null : $extract_external[ 'prec' ];
                        $succ_tags                                     = empty( $extract_external[ 'succ' ] ) ? null : $extract_external[ 'succ' ];
                        $xliff_trans_unit[ 'source' ][ 'raw-content' ] = $extract_external[ 'seg' ];

                        if ( isset( $xliff_trans_unit[ 'target' ][ 'raw-content' ] ) ) {

                            $target_extract_external = strip_external( $xliff_trans_unit[ 'target' ][ 'raw-content' ] );

                            if ( $xliff_trans_unit[ 'source' ][ 'raw-content' ] != $target_extract_external[ 'seg' ] ) {

                                $target = CatUtils::placeholdnbsp( $target_extract_external[ 'seg' ] );
                                $target = mysql_real_escape_string( $target );

                                //add an empty string to avoid casting to int: 0001 -> 1
                                //useful for idiom internal xliff id
                                $translations[ "" . $xliff_trans_unit[ 'attr' ][ 'id' ] ][ ] = $target;

                            }

                        }
                    }

                    $source = CatUtils::placeholdnbsp( $xliff_trans_unit['source']['raw-content'] );

                    //we do the word count after the place-holding with <x id="nbsp"/>
                    //so &nbsp; are now not recognized as word and not counted as payable
                    $num_words = CatUtils::segment_raw_wordcount($source);

                    //applying escaping after raw count
                    $source = mysql_real_escape_string($source);

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

    // *NOTE*: PHP>=5.3 throws UnexpectedValueException, but PHP 5.2 throws ErrorException
    //use generic

    if (empty($query_segment)) {
        Log::doLog("Segment import - no segments found\n");
        throw new Exception( "Segment import - no segments found", -1 );
    }

    // Executing the Query
    $query_segment = "INSERT INTO segments (internal_id,id_file, segment, raw_word_count, xliff_mrk_id, xliff_ext_prec_tags, xliff_ext_succ_tags, show_in_cattool,xliff_mrk_ext_prec_tags,xliff_mrk_ext_succ_tags) 
		values " . join(",\n", $query_segment);

    $res = mysql_query($query_segment, $mysql_link);
    if( !empty( $translations ) ){

        $last_segments_query = "SELECT id, internal_id from segments WHERE id_file = %u";
        $last_segments_query = sprintf( $last_segments_query, $fid );

        $last_segments = mysql_query( $last_segments_query, $mysql_link );

        //assignment in condition is often dangerous, deprecated
        while ( ( $row = mysql_fetch_assoc( $last_segments ) ) != false ) {
            array_unshift( $translations[ "" . $row['internal_id'] ], "" . $row['internal_id'] );
            array_unshift( $translations[ "" . $row['internal_id'] ], $row['id'] );
        }

    }

    if (!$res) {
        Log::doLog("Segment import - DB Error: " . mysql_error() . " - $query_segment\n");
        throw new Exception( "Segment import - DB Error: " . mysql_error() . " - $query_segment", -2 );
    }

    return $translations;

}

/**
 * @param $SegmentTranslations mixed
 * @param $jid
 *
 * @throws Exception
 */
function insertPreTranslations( & $SegmentTranslations, $jid ) {

    $mysql_hostname = INIT::$DB_SERVER;   // Database Server machine
    $mysql_database = INIT::$DB_DATABASE;     // Database Name
    $mysql_username = INIT::$DB_USER;   // Database User
    $mysql_password = INIT::$DB_PASS;

    //terrific... another connection......
    $mysql_link = mysql_connect($mysql_hostname, $mysql_username, $mysql_password);
    mysql_select_db($mysql_database, $mysql_link);

    Log::doLog( array_shift( array_chunk( $SegmentTranslations, 5, true ) ) );

    foreach ( $SegmentTranslations as $internal_id => $struct ){

        if( empty($struct) ) {
            Log::doLog( $internal_id . " : " . var_export( $struct, true ) );
            continue;
        }

        //id_segment, id_job, status, translation, translation_date, tm_analysis_status, locked
        $query_translations[] = "( '{$struct[0]}', $jid, 'TRANSLATED', '{$struct[2]}', NOW(), 'DONE', 1 )";
    }

    // Executing the Query
    if( !empty($query_translations) ){

        $query_translations = "INSERT INTO segment_translations (id_segment, id_job, status, translation, translation_date, tm_analysis_status, locked)
            values " . join(",\n", $query_translations);

        Log::doLog( print_r( $query_translations,true ) );

        $res = mysql_query($query_translations, $mysql_link);

        if (!$res) {
            Log::doLog("Translation import - DB Error: " . mysql_error() . " - $query_translations\n");
            throw new Exception( "Translation import - DB Error: " . mysql_error() . " - $query_translations", -3 );
        }

    }

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
    $a=  str_replace("\n", " NL ", $a);
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
    $prec=  str_replace(" NL ", "\n", $prec);
    $succ=  str_replace(" NL ", "\n", $succ);
    $a =  str_replace(" NL ", "\n", $a);
    $r = array('prec' => $prec, 'seg' => $a, 'succ' => $succ);
    return $r;
}

?>
