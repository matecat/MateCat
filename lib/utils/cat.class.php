<?

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/MyMemory.copyrighted.php";
include_once INIT::$UTILS_ROOT . "/utils.class.php";

define("LTPLACEHOLDER", "##LESSTHAN##");
define("GTPLACEHOLDER", "##GREATERTHAN##");
define("AMPPLACEHOLDER", "##AMPPLACEHOLDER##");
define("NBSPPLACEHOLDER", "<x id=\"nbsp\"/>");

class CatUtils {

    //following functions are useful for manage the consistency of non braking spaces
    // chars coming, expecially,from MS Word
    // ref nbsp code https://en.wikipedia.org/wiki/Non-breaking_space
    public static function placeholdnbsp($s) {        
        $s = preg_replace("/\x{a0}/u", NBSPPLACEHOLDER, $s);
        return $s;
    }
    
     

    public static function restorenbsp($s) {
        $pattern="#".NBSPPLACEHOLDER."#";
        $s = preg_replace($pattern, Utils::unicode2chr(0Xa0), $s);
        return $s;
    }
    
    // ----------------------------------------------------------------
    
    public static function placeholdamp($s) {        
        $s = preg_replace("/\&/", AMPPLACEHOLDER, $s);
        return $s;
    }
    
      public static function restoreamp($s) {
        $pattern="#".AMPPLACEHOLDER."#";
        $s = preg_replace($pattern, Utils::unicode2chr("&"), $s);
        return $s;
    }

    //reconcile tag ids
    public static function ensureTagConsistency($q, $source_seg, $target_seg) {
        //TODO
    }

    //check for tag mismatches
    //TODO Rimuovere dopo il 1° agosto 2013
    //
//    public static function checkTagConsistency($source_seg, $target_seg) {
//        //ensure there are no entities
//        $source_seg = html_entity_decode($source_seg);
//        $target_seg = html_entity_decode($target_seg);
//
//        //get tags from words in source and target
//        preg_match_all('/<[^>]+>/', $source_seg, $source_tags);
//        preg_match_all('/<[^>]+>/', $target_seg, $target_tags);
//        $source_tags = $source_tags[0];
//        $target_tags = $target_tags[0];
//
//        //check equal tag count 
//        if (count($source_tags) != count($target_tags)) {
//            return array('outcome' => 1, 'debug' => 'tag count mismatch');
//        }
//
//        //check well formed xml (equal number of opening and closing tags inside each input segment)
//        $seg=self::placeholdamp($source_seg);
//        $tra=self::placeholdamp($target_seg);
//        @$xml_valid = @simplexml_load_string("<placeholder>$seg</placeholder>");
//
//        if ($xml_valid === FALSE) {
//            return array('outcome' => 2, 'debug' => 'bad source xml');
//        }
//        @$xml_valid = @simplexml_load_string("<placeholder>$tra</placeholder>");
//        if ($xml_valid === FALSE) {
//            return array('outcome' => 3, 'debug' => 'bad target xml');
//        }
//
//        //check for tags' id mismatching
//        //extract names
//        preg_match_all('/id="(.+?)"/', $source_seg, $source_tags_ids);
//        preg_match_all('/id="(.+?)"/', $target_seg, $target_tags_ids);
//        $source_tags_ids = $source_tags_ids[1];
//        $target_tags_ids = $target_tags_ids[1];
//        //set indexes for lookup purposes ('1' is just a dummy value to set the cell)
//        $tmp = array();
//        foreach ($source_tags_ids as $k => $src_tag)
//            $tmp[$src_tag] = 1;
//        $source_tags_ids = $tmp;
//        $tmp = array();
//        foreach ($target_tags_ids as $k => $trg_tag)
//            $tmp[$trg_tag] = 1;
//        $target_tags_ids = $tmp;
//        unset($tmp);
//        //for each tag in target
//        foreach ($target_tags_ids as $tag => $v) {
//            //if a tag in target is not present in source, error
//            if (!isset($source_tags_ids[$tag])) {
//                return array('outcome' => 4, 'debug' => 'tag id mismatch');
//            }
//        }
//
//        //all checks passed
//        return array('outcome' => 0, 'debug' => '');
//    }

    private static function parse_time_to_edit($ms) {
        if ($ms <= 0) {
            return array("00", "00", "00", "00");
        }

        $usec = $ms % 1000;
        $ms = floor($ms / 1000);

        $seconds = str_pad($ms % 60, 2, "0", STR_PAD_LEFT);
        $ms = floor($ms / 60);

        $minutes = str_pad($ms % 60, 2, "0", STR_PAD_LEFT);
        $ms = floor($ms / 60);

        $hours = str_pad($ms % 60, 2, "0", STR_PAD_LEFT);
        $ms = floor($ms / 60);

        return array($hours, $minutes, $seconds, $usec);
    }

    private static function placehold_xliff_tags($segment) {
        //$segment=preg_replace('|<(g\s*.*?)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
        $segment = preg_replace('|<(g\s*id=["\']+.*?["\']+\s*[^<>]*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);

        $segment = preg_replace('|<(/g)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(x.*?/?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(bx.*?/?])>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(ex.*?/?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(bpt\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/bpt)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(ept\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/ept)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(ph\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/ph)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(it\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/ph)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(it\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/it)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(mrk\s*.*?)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        $segment = preg_replace('|<(/mrk)>|si', LTPLACEHOLDER . "$1" . GTPLACEHOLDER, $segment);
        return $segment;
    }

    private static function restore_xliff_tags($segment) {
        $segment = str_replace(LTPLACEHOLDER, "<", $segment);
        $segment = str_replace(GTPLACEHOLDER, ">", $segment);
        return $segment;
    }

    private static function restore_xliff_tags_for_wiew($segment) {
        $segment = str_replace(LTPLACEHOLDER, "&lt;", $segment);
        $segment = str_replace(GTPLACEHOLDER, "&gt;", $segment);
        return $segment;
    }

    public static function stripTags($text) {
        $pattern_g_o = '|(<.*?>)|';
        $pattern_g_c = '|(</.*?>)|';
        $pattern_x = '|(<.*?/>)|';

        $text = preg_replace($pattern_x, "", $text);

        $text = preg_replace($pattern_g_o, "", $text);
        $text = preg_replace($pattern_g_c, "", $text);
        return $text;
    }

    public static function view2rawxliff($segment) {
        // input : <g id="43">bang & olufsen < 3 </g> <x id="33"/>; --> valore della funzione .text() in cat.js su source, target, source suggestion,target suggestion
        // output : <g> bang &amp; olufsen are > 555 </g> <x/>
        // caso controverso <g id="4" x="&lt; dfsd &gt;"> 
        $segment = self::placehold_xliff_tags($segment);
        $segment = htmlspecialchars($segment, ENT_NOQUOTES, 'UTF-8', false);
        $segment = self::restore_xliff_tags($segment);
        return $segment;
    }

    public static function rawxliff2view($segment) {
        // input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
        $segment = self::placehold_xliff_tags($segment);
        
        $segment = html_entity_decode($segment, ENT_NOQUOTES, 'UTF-8');
        // restore < e >
        $segment = str_replace("<", "&lt", $segment);
        $segment = str_replace(">", "&gt", $segment);


        $segment = preg_replace('|<(.*?)>|si', "&lt;$1&gt;", $segment);
        $segment = self::restore_xliff_tags_for_wiew($segment);
        $segment = str_replace("&nbsp;", "++", $segment);
        return $segment;
    }

    public static function rawxliff2rawview($segment) {
        // input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
        $segment = self::placehold_xliff_tags($segment);
        $segment = html_entity_decode($segment, ENT_NOQUOTES, 'UTF-8');
        $segment = self::restore_xliff_tags_for_wiew($segment);
        return $segment;
    }

    // transform any segment format in raw xliff format: raw xliff will be used as starting format for any manipulation
    public static function toRawXliffNormalizer($segment) {
        ;
    }

    public static function getEditingLogData($jid, $password) {

        $data = getEditLog($jid, $password);

        $slow_cut = 30;
        $fast_cut = 0.25;

        $stat_too_slow = array();
        $stat_too_fast = array();


        if (!$data) {
            return false;
        }

        $stats['total-word-count'] = 0;
        $stat_mt = array();


        foreach ($data as &$seg) {
            //$seg['source'] = self::stripTagesFromSource($seg['source']);
            $seg['source'] = trim(htmlspecialchars_decode( $seg['source'], ENT_QUOTES ));
            $seg['translation'] = trim(htmlspecialchars_decode( $seg['source'], ENT_QUOTES ));

            $seg['sm'].="%";
            $seg['jid'] = $jid;
            $tte = self::parse_time_to_edit($seg['tte']);
            $seg['time_to_edit'] = "$tte[1]m:$tte[2]s";



            $stat_rwc[] = $seg['rwc'];

            // by definition we cannot have a 0 word sentence. It is probably a - or a tag, so we want to consider at least a word.
            if ($seg['rwc'] < 1) {
                $seg['rwc'] = 1;
            }

            $seg['secs-per-word'] = round($seg['tte'] / 1000 / $seg['rwc'], 1);

            if (($seg['secs-per-word'] < $slow_cut) AND ($seg['secs-per-word'] > $fast_cut)) {
                $seg['stats-valid'] = 'Yes';
                $seg['stats-valid-color'] = '';
                $seg['stats-valid-style'] = '';

                $stat_valid_rwc[] = $seg['rwc'];
                $stat_valid_tte[] = $seg['tte'];
                $stat_spw[] = $seg['secs-per-word'];
            } else {
                $seg['stats-valid'] = 'No';
                $seg['stats-valid-color'] = '#ee6633';
                $seg['stats-valid-style'] = 'border:2px solid #EE6633';
            }


            // Stats
            if ($seg['secs-per-word'] >= $slow_cut) {
                $stat_too_slow[] = $seg['rwc'];
            }
            if ($seg['secs-per-word'] <= $fast_cut) {
                $stat_too_fast[] = $seg['rwc'];
            }


            $seg['pe_effort_perc'] = round((1 - MyMemory::TMS_MATCH($seg['sug'], $seg['translation'])) * 100);


            if ($seg['pe_effort_perc'] < 0) {
                $seg['pe_effort_perc'] = 0;
            }
            if ($seg['pe_effort_perc'] > 100) {
                $seg['pe_effort_perc'] = 100;
            }

            $stat_pee[] = $seg['pe_effort_perc'] * $seg['rwc'];

            $seg['pe_effort_perc'] .= "%";
            $seg['sug_view'] = html_entity_decode($seg['sug']);
            
            
            if ($seg['sug'] <> $seg['translation']) {
                $sug_for_diff=self::placehold_xliff_tags($seg['sug']);
                $tra_for_diff=self::placehold_xliff_tags($seg['translation']);
                $seg['diff'] = MyMemory::diff_tercpp($sug_for_diff, $tra_for_diff);
                if (!$seg['diff']) {
                    // TER NOT WORKING : fallback to old diff viewer
                    $seg['diff'] = MyMemory::diff_html($sug_for_diff, $tra_for_diff);
                }
            } else {
                $seg['diff'] = '';
            }
            $seg['diff_view']= self::restore_xliff_tags_for_wiew($seg['diff']);
            //$seg['diff_view']= CatUtils::rawxliff2rawview($seg['diff']);

            // BUG: While suggestions source is not correctly set
            if (($seg['sm'] == "85%") OR ($seg['sm'] == "86%")) {
                $seg['ss'] = 'Machine Translation';
                $stat_mt[] = $seg['rwc'];
            } else {
                $seg['ss'] = 'Translation Memory';
            }
        }

        $stats['edited-word-count'] = array_sum($stat_rwc);
        $stats['valid-word-count'] = array_sum($stat_valid_rwc);

        if ($stats['edited-word-count'] > 0) {
            $stats['too-slow-words'] = round(array_sum($stat_too_slow) / $stats['edited-word-count'], 2) * 100;
            $stats['too-fast-words'] = round(array_sum($stat_too_fast) / $stats['edited-word-count'], 2) * 100;
            $stats['avg-pee'] = round(array_sum($stat_pee) / array_sum($stat_rwc)) . "%";
        }

        $stats['mt-words'] = round(array_sum($stat_mt) / $stats['edited-word-count'], 2) * 100;
        $stats['tm-words'] = 100 - $stats['mt-words'];
        $stats['total-valid-tte'] = round(array_sum($stat_valid_tte) / 1000);

        // Non weighted...
        // $stats['avg-secs-per-word'] = round(array_sum($stat_spw)/count($stat_spw),1);
        // Weighted
        $stats['avg-secs-per-word'] = round($stats['total-valid-tte'] / $stats['valid-word-count'], 1);
        $stats['est-words-per-day'] = number_format(round(3600 * 8 / $stats['avg-secs-per-word']), 0, '.', ',');

        // Last minute formatting (after calculations)
        $temp = self::parse_time_to_edit(round(array_sum($stat_valid_tte)));
        $stats['total-valid-tte'] = "$temp[0]h:$temp[1]m:$temp[2]s";

        //$seg['translation'] = trim(htmlspecialchars_decode( $seg['translation'], ENT_QUOTES ));



        return array($data, $stats);
    }

    public static function addSegmentTranslation($id_segment, $id_job, $status, $time_to_edit, $translation, $errors, $chosen_suggestion_index, $warning = 0) {


        $insertRes = setTranslationInsert($id_segment, $id_job, $status, $time_to_edit, $translation, $errors, $chosen_suggestion_index, $warning);
        if ($insertRes < 0 and $insertRes != -1062) {
            $result['error'][] = array("code" => -4, "message" => "error occurred during the storing (INSERT) of the translation for the segment $id_segment - $insertRes");
            return $result;
        }
        if ($insertRes == -1062) {

            $updateRes = setTranslationUpdate($id_segment, $id_job, $status, $time_to_edit, $translation, $errors, $chosen_suggestion_index, $warning);

            if ($updateRes < 0) {
                $result['error'][] = array("code" => -5, "message" => "error occurred during the storing (UPDATE) of the translation for the segment $id_segment");
                return $result;
            }
        }
        return 0;
    }

    public static function addTranslationSuggestion($id_segment, $id_job, $suggestions_json_array = "", $suggestion = "", $suggestion_match = "", $suggestion_source = "", $match_type = "", $eq_words = 0, $standard_words = 0, $translation = "", $tm_status_analysis = "UNDONE", $warning = 0, $err_json = '' ) {
        if (!empty($suggestion_source)) {
            if (strpos($suggestion_source, "MT") === false) {
                $suggestion_source = 'TM';
            } else {
                $suggestion_source = 'MT';
            }
        }

        $insertRes = setSuggestionInsert($id_segment, $id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source, $match_type, $eq_words, $standard_words, $translation, $tm_status_analysis, $warning, $err_json);
        if ($insertRes < 0 and $insertRes != -1062) {
            $result['error'][] = array("code" => -4, "message" => "error occurred during the storing (INSERT) of the suggestions for the segment $id_segment - $insertRes");
            return $result;
        }
        if ($insertRes == -1062) {
            // the translaion for this segment still exists : update it
            $updateRes = setSuggestionUpdate($id_segment, $id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source, $match_type, $eq_words, $standard_words, $translation, $tm_status_analysis, $warning, $err_json);
            if ($updateRes < 0) {
                $result['error'][] = array("code" => -5, "message" => "error occurred during the storing (UPDATE) of the suggestions for the segment $id_segment");
                return $result;
            }
        }
        return 0;
    }

    /**
     * Public interface to multiple Job Stats Info
     * 
     * @param array $jids
     * @param bool $estimate_performance
     * @return mixed
     * <pre>
     *   $res_job_stats = array(
     *      (int)id => 
     *          array(
     *              'id'                           => (int),
     *              'TOTAL'                        => (int),
     *              'TRANSLATED'                   => (int),
     *              'APPROVED'                     => (int),
     *              'REJECTED'                     => (int),
     *              'DRAFT'                        => (int),
     *              'ESTIMATED_COMPLETION'         => (int),
     *              'WORDS_PER_HOUR'               => (int),
     *          )
     *   );
     * </pre>
     * 
     */
    public static function getStatsForMultipleJobs( array $jids, $estimate_performance = false) {

        //get stats for all jids
        $jobs_stats = getStatsForMultipleJobs($jids);

        //init results
        $res_job_stats = array();
        foreach ($jobs_stats as $job_stat) {
            // this prevent division by zero error when the jobs contains only segment having untranslatable content	
            if ($job_stat['TOTAL'] == 0) {
                $job_stat['TOTAL'] = 1;
            }
            
            $job_stat = self::_getStatsForJob($job_stat, $estimate_performance);
            if ($estimate_performance){
                $job_stat = self::_performanceEstimationTime($job_stat);
            }
            
            $jid = $job_stat['id'];
            unset($job_stat['id']);
            $res_job_stats[$jid] = $job_stat;
            unset($jid);
        }
        
        return $res_job_stats;
        
    }
    
    /**
     * 
     * Find significant digits from float num.
     * 
     * Accepted range are between 0 and 2 ( max approximation )
     * 
     * @param float $floatNum
     * @return int
     */
    protected static function _getSignificantDigits( $floatNum ){
        if( $floatNum == 0 ){
            return 0;
        }
        $decimalNumbers = ceil( log10( $floatNum ) );
        $decimalNumbers = ( $decimalNumbers >= 0 ? 0 : abs($decimalNumbers) +1 );
        return ( $decimalNumbers < 2 ? $decimalNumbers : 2 ); //force max to 2 decimal number
    }
    
    /**
     * Make an estimation on performance
     * 
     * @param mixed $job_stats
     * @return mixed
     */
    protected static function _performanceEstimationTime( array $job_stats ){
        
        $estimation_temp = getLastSegmentIDs($job_stats['id']);
        $estimation_seg_ids = $estimation_temp[0]['estimation_seg_ids'];

        if ($estimation_seg_ids) {
            //perform check on performance if single segment are set to check or globally Forced
            // Calculating words per hour and estimated completion
            $estimation_temp = getEQWLastHour($job_stats['id'], $estimation_seg_ids);
            if ($estimation_temp[0]['data_validity'] == 1) {
                $job_stats['WORDS_PER_HOUR'] = number_format($estimation_temp[0]['words_per_hour'], 0, '.', ',');
                // 7.2 hours
                // $job_stats['ESTIMATED_COMPLETION'] = number_format( ($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour'],1);
                // 1 h 32 m
                // $job_stats['ESTIMATED_COMPLETION'] = date("G",($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour']*3600) . "h " . date("i",($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour']*3600) . "m";
                $job_stats['ESTIMATED_COMPLETION'] = date("G\h i\m", ($job_stats['DRAFT'] + $job_stats['REJECTED']) / $estimation_temp[0]['words_per_hour'] * 3600 - 3600);
            }
        }
        
        return $job_stats;
        
    }
    
    /**
     * Perform analysis on single Job
     *  
     * <pre>
     *      $job_stats = array(
     *          'id'                           => (int),
     *          'TOTAL'                        => (int),
     *          'TRANSLATED'                   => (int),
     *          'APPROVED'                     => (int),
     *          'REJECTED'                     => (int),
     *          'DRAFT'                        => (int),
     *          'ESTIMATED_COMPLETION'         => (int),
     *          'WORDS_PER_HOUR'               => (int),
     *      );
     * </pre>
     *  
     * @param mixed $job_stats
     * @param bool $estimate_performance
     * @return mixed $job_stats
     */
    protected static function _getStatsForJob( $job_stats ) {
        
        $job_stats['PROGRESS'] = ( $job_stats['TRANSLATED'] + $job_stats['APPROVED'] );                   

        $job_stats['TOTAL_FORMATTED'] = number_format($job_stats['TOTAL'], 0, ".", ",");
        $job_stats['PROGRESS_FORMATTED'] = number_format( $job_stats['TRANSLATED'] + $job_stats['APPROVED'], 0, ".", "," );                   
        $job_stats['APPROVED_FORMATTED'] = number_format($job_stats['APPROVED'], 0, ".", ",");
        $job_stats['REJECTED_FORMATTED'] = number_format($job_stats['REJECTED'], 0, ".", ",");
        $job_stats['TODO_FORMATTED'] = number_format($job_stats['DRAFT'] + $job_stats['REJECTED'], 0, ".", ",");
        $job_stats['DRAFT_FORMATTED'] = number_format($job_stats['DRAFT'], 0, ".", ",");
        $job_stats['TRANSLATED_FORMATTED'] = number_format($job_stats['TRANSLATED'], 0, ".", ",");

        $job_stats['APPROVED_PERC'] = ($job_stats['APPROVED']) / $job_stats['TOTAL'] * 100;
        $job_stats['REJECTED_PERC'] = ($job_stats['REJECTED']) / $job_stats['TOTAL'] * 100;
        $job_stats['DRAFT_PERC'] = ( $job_stats['DRAFT'] / $job_stats['TOTAL'] * 100 );
        $job_stats['TRANSLATED_PERC'] = ( $job_stats['TRANSLATED'] / $job_stats['TOTAL'] * 100 );
        $job_stats['PROGRESS_PERC'] = ( $job_stats['PROGRESS'] / $job_stats['TOTAL'] ) * 100;

        $significantDigits = array();
        $significantDigits[] = self::_getSignificantDigits($job_stats['TRANSLATED_PERC']);
        $significantDigits[] = self::_getSignificantDigits($job_stats['DRAFT_PERC']);
        $significantDigits[] = self::_getSignificantDigits($job_stats['APPROVED_PERC']);
        $significantDigits[] = self::_getSignificantDigits($job_stats['REJECTED_PERC']);
        $significantDigits = max($significantDigits);
                
        $job_stats['TRANSLATED_PERC_FORMATTED'] = round($job_stats['TRANSLATED_PERC'], $significantDigits ) ;
        $job_stats['DRAFT_PERC_FORMATTED'] = round($job_stats['DRAFT_PERC'], $significantDigits ) ;
        $job_stats['APPROVED_PERC_FORMATTED'] = round($job_stats['APPROVED_PERC'], $significantDigits );
        $job_stats['REJECTED_PERC_FORMATTED'] = round($job_stats['REJECTED_PERC'], $significantDigits );
        $job_stats['PROGRESS_PERC_FORMATTED'] = round( $job_stats['PROGRESS_PERC'], $significantDigits ) ;
        
        $t = 'approved';
        if ($job_stats['TRANSLATED_FORMATTED'] > 0)
            $t = "translated";
        if ($job_stats['DRAFT_FORMATTED'] > 0)
            $t = "draft";
        if ($job_stats['REJECTED_FORMATTED'] > 0)
            $t = "draft";
        $job_stats['DOWNLOAD_STATUS'] = $t;            

        return $job_stats;
        
    }
    
    /**
     * Public interface to single Job Stats Info
     * 
     * 
     * @param int $jid
     * 
     * @return $job_stats
     * <pre>
     *      $job_stats = array(
     *          'id'                           => (int),
     *          'TOTAL'                        => (int),
     *          'TRANSLATED'                   => (int),
     *          'APPROVED'                     => (int),
     *          'REJECTED'                     => (int),
     *          'DRAFT'                        => (int),
     *          'ESTIMATED_COMPLETION'         => (int),
     *          'WORDS_PER_HOUR'               => (int),
     *      );
     * </pre>
     * 
     */
    public static function getStatsForJob( $jid ) {
        
        $job_stats = getStatsForJob($jid);
        $job_stats = $job_stats[0];

        $job_stats = self::_getStatsForJob($job_stats, true); //true set estimation check if present
        return self::_performanceEstimationTime($job_stats);
        
    }

    public static function getStatsForFile($fid) {


        $file_stats = getStatsForFile($fid);

        $file_stats = $file_stats[0];
        $file_stats['ID_FILE'] = $fid;
        $file_stats['TOTAL_FORMATTED'] = number_format($file_stats['TOTAL'], 0, ".", ",");
        $file_stats['REJECTED_FORMATTED'] = number_format($file_stats['REJECTED'], 0, ".", ",");
        $file_stats['DRAFT_FORMATTED'] = number_format($file_stats['DRAFT'], 0, ".", ",");


        return $file_stats;
    }

    //CONTA LE PAROLE IN UNA STRINGA
    public static function segment_raw_wordcount($string) {

        $app = trim($string);
        $n = strlen($app);
        if ($app == "") {
            return 0;
        }

        $res = 0;
        $temp = array();

        $string = preg_replace("#<.*?" . ">#si", "", $string);
        $string = preg_replace("#<\/.*?" . ">#si", "", $string);

        $string = str_replace(":", "", $string);
        $string = str_replace(";", "", $string);
        $string = str_replace("[", "", $string);
        $string = str_replace("]", "", $string);
        $string = str_replace("?", "", $string);
        $string = str_replace("!", "", $string);
        $string = str_replace("{", "", $string);
        $string = str_replace("}", "", $string);
        $string = str_replace("(", "", $string);
        $string = str_replace(")", "", $string);
        $string = str_replace("/", "", $string);
        $string = str_replace("\\", "", $string);
        $string = str_replace("|", "", $string);
        $string = str_replace("£", "", $string);
        $string = str_replace("$", "", $string);
        $string = str_replace("%", "", $string);
        $string = str_replace("-", "", $string);
        $string = str_replace("_", "", $string);
        $string = str_replace("#", "", $string);
        $string = str_replace("§", "", $string);
        $string = str_replace("^", "", $string);
        $string = str_replace("â€???", "", $string);
        $string = str_replace("&", "", $string);

        // 08/02/2011 CONCORDATO CON MARCO : sostituire tutti i numeri con un segnaposto, in modo che il conteggio
        // parole consideri i segmenti che differiscono per soli numeri some ripetizioni (come TRADOS)
        $string = preg_replace("/[0-9]+([\.,][0-9]+)*/", "<TRANSLATED_NUMBER>", $string);

        $string = str_replace(" ", "<sep>", $string);
        $string = str_replace(", ", "<sep>", $string);
        $string = str_replace(". ", "<sep>", $string);
        $string = str_replace("' ", "<sep>", $string);
        $string = str_replace(".", "<sep>", $string);
        $string = str_replace("\"", "<sep>", $string);
        $string = str_replace("\'", "<sep>", $string);


        $app = explode("<sep>", $string);
        foreach ($app as $a) {
            $a = trim($a);
            if ($a != "") {
                //voglio contare anche i numeri:
                //if(!is_number($a)) {
                $temp[] = $a;
                //}
            }
        }

        $res = count($temp);
        return $res;
    }

    public static function generate_password($length = 8) {


        // Random
        $pool = "abcdefghkmnpqrstuvwxyz23456789"; // skipping iljo01 because not easy to distinguish
        $pool_lenght = strlen($pool);
        $pwd = "";
        for ($index = 0; $index < $length; $index++) {
            $pwd .= substr($pool, (rand() % ($pool_lenght)), 1);
        }

        return $pwd;
    }

}

?>
