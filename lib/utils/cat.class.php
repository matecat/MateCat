<?
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/MyMemory.copyrighted.php";

define ("LTPLACEHOLDER", "##LESSTHAN##");
define ("GTPLACEHOLDER", "##GREATERTHAN##");



class CatUtils {

    private function parse_time_to_edit($ms) {
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
    
     private function stripTagesFromSource($text) {
        $pattern_g_o = '|(<.*?>)|';
        $pattern_g_c = '|(</.*?>)|';
        $pattern_x = '|(<.*?/>)|';

        $text = preg_replace($pattern_x, "", $text);

        $text = preg_replace($pattern_g_o, "", $text);
        $text = preg_replace($pattern_g_c, "", $text);
        return $text;
    }

    	private function placehold_xliff_tags ($segment){
		$segment=preg_replace('|<(g\s*.*?)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment); 
		$segment=preg_replace('|<(/g)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);     
		$segment=preg_replace('|<(x.*?/?)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment); 
		$segment=preg_replace('|<(bx.*?/?])>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(ex.*?/?)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(bpt\s*.*?)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(/bpt)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(ept\s*.*?)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(/ept)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(ph\s*.*?)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(/ph)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(it\s*.*?)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(/ph)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(it\s*.*?)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(/it)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(mrk\s*.*?)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
		$segment=preg_replace('|<(/mrk)>|si', LTPLACEHOLDER."$1".GTPLACEHOLDER,$segment);
	return $segment;
	}

	private function restore_xliff_tags($segment){
		$segment=str_replace(LTPLACEHOLDER,"<",$segment);
		$segment=str_replace(GTPLACEHOLDER,">",$segment);
		return $segment;
	}

	private function restore_xliff_tags_for_wiew($segment){
		$segment=str_replace(LTPLACEHOLDER,"&lt;",$segment);
		$segment=str_replace(GTPLACEHOLDER,"&gt;",$segment);
		return $segment;
	}

	public static function view2rawxliff($segment){
		// input : <g id="43">bang & olufsen < 3 </g> <x id="33"/>; --> valore della funzione .text() in cat.js su source, target, source suggestion,target suggestion
		// output : <g> bang &amp; olufsen are > 555 </g> <x/>
		// caso controverso <g id="4" x="&lt; dfsd &gt;"> 
		$segment=self::placehold_xliff_tags ($segment);
		$segment = htmlspecialchars($segment,ENT_NOQUOTES,'UTF-8',false);
		$segment=self::restore_xliff_tags($segment);	
		log::doLog(__FUNCTION__ . " $segment");
		return $segment;
	}


	public static function rawxliff2view($segment){
		// input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
		$segment=self::placehold_xliff_tags ($segment);
		$segment = html_entity_decode($segment, ENT_NOQUOTES,'UTF-8');
		// restore < e >
		$segment=str_replace ("<","&lt",$segment);
		$segment=str_replace (">","&gt",$segment);


		$segment= preg_replace('|<(.*?)>|si', "&lt;$1&gt;",$segment);
		$segment=self::restore_xliff_tags_for_wiew($segment);		
		return $segment;
	}

	public static function rawxliff2rawview($segment){
		// input : <g id="43">bang &amp; &lt; 3 olufsen </g>; <x id="33"/>
		$segment=self::placehold_xliff_tags ($segment);
		$segment = html_entity_decode($segment, ENT_NOQUOTES,'UTF-8');		
		$segment=self::restore_xliff_tags_for_wiew($segment);		
		return $segment;
	}


	// transform any segment format in raw xliff format: raw xliff will be used as starting format for any manipulation
	public static function toRawXliffNormalizer($segment){
	;
	}


    public static function getEditingLogData($jid,$password) {
        
        $data = getEditLog($jid,$password);	
                
        $slow_cut = 30;
        $fast_cut = 0.25;
        
        $stat_too_slow = array();
        $stat_too_fast = array();

        
        if(!$data) {
	        return false;
        }
        
        $stats['total-word-count'] = 0;
        $stat_mt = array();
        
        
        foreach ($data as &$seg) {
            //$seg['source'] = self::stripTagesFromSource($seg['source']);
            $seg['source'] = trim($seg['source']);
            $seg['sm'].="%";
            $seg['jid'] = $jid;
            $tte=self::parse_time_to_edit($seg['tte']);
            $seg['time_to_edit'] = "$tte[1]m:$tte[2]s";
            
            
            
            $stat_rwc[] = $seg['rwc'];
            
            // by definition we cannot have a 0 word sentence. It is probably a - or a tag, so we want to consider at least a word.
            if ($seg['rwc']<1) {
            $seg['rwc'] = 1;
            }
 
            $seg['secs-per-word'] = round($seg['tte']/1000/$seg['rwc'],1);
           
            if ( ($seg['secs-per-word']<$slow_cut) AND ($seg['secs-per-word']>$fast_cut) ) {
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
            if ($seg['secs-per-word']>=$slow_cut) {
	            $stat_too_slow[] = $seg['rwc'];
            }
            if ($seg['secs-per-word']<=$fast_cut) {
	            $stat_too_fast[] = $seg['rwc'];
            }
           
           
            $seg['pe_effort_perc'] = round((1 - MyMemory::TMS_MATCH($seg['sug'], $seg['translation'])) * 100);
            
            
            if ($seg['pe_effort_perc'] < 0) {
                $seg['pe_effort_perc'] = 0;
            }
            if ($seg['pe_effort_perc'] > 100) {
                $seg['pe_effort_perc'] = 100;
            }
            
            $stat_pee[] = $seg['pe_effort_perc']*$seg['rwc'];
            
            $seg['pe_effort_perc'] .= "%";
            $seg['sug_view']=html_entity_decode($seg['sug']);
            if ($seg['sug']<>$seg['translation']) { 
              $seg['diff'] = MyMemory::diff_html($seg['sug'], $seg['translation']);
            } else { $seg['diff']=''; }
                        
            // BUG: While suggestions source is not correctly set
            if ( ($seg['sm']=="85%") OR ($seg['sm']=="86%")) {
	            $seg['ss'] = 'Machine Translation';
	            $stat_mt[] = $seg['rwc'];
            } else { $seg['ss'] = 'Translation Memory'; }

           }
         
           $stats['edited-word-count'] = array_sum($stat_rwc);
           $stats['valid-word-count'] = array_sum($stat_valid_rwc);
           
           if ($stats['edited-word-count']>0) {
           	$stats['too-slow-words']    = round(array_sum($stat_too_slow)/$stats['edited-word-count'],2)*100;
           	$stats['too-fast-words']    = round(array_sum($stat_too_fast)/$stats['edited-word-count'],2)*100;
           	$stats['avg-pee']		    = round(array_sum($stat_pee)/array_sum($stat_rwc))."%";
           }
           
           $stats['mt-words']          = round(array_sum($stat_mt)/$stats['edited-word-count'],2)*100;
           $stats['tm-words']          = 100 - $stats['mt-words'];
           $stats['total-valid-tte']   = round(array_sum($stat_valid_tte)/1000);
           
           // Non weighted...
           // $stats['avg-secs-per-word'] = round(array_sum($stat_spw)/count($stat_spw),1);
           // Weighted
           $stats['avg-secs-per-word'] = round($stats['total-valid-tte']/$stats['valid-word-count'],1);
           $stats['est-words-per-day']     = number_format(round(3600*8/$stats['avg-secs-per-word']),0,'.',',');
           
           // Last minute formatting (after calculations)
           $temp=self::parse_time_to_edit(round(array_sum($stat_valid_tte)));
           $stats['total-valid-tte'] = "$temp[0]h:$temp[1]m:$temp[2]s";
          
           
                      
        return array($data,$stats);
    }

    public static function addSegmentTranslation($id_segment, $id_job, $status, $time_to_edit, $translation) {
        $insertRes = setTranslationInsert($id_segment, $id_job, $status, $time_to_edit, $translation);
	log::doLog("translation is $translation - encoded ". htmlentities($translation));
        if ($insertRes < 0 and $insertRes != -1062) {
            $result['error'][] = array("code" => -4, "message" => "error occurred during the storing (INSERT) of the translation for the segment $id_segment - $insertRes");
            return $result;
        }
        if ($insertRes == -1062) {
	
            $updateRes = setTranslationUpdate($id_segment, $id_job, $status, $time_to_edit, $translation);
	
            if ($updateRes < 0) {
                $result['error'][] = array("code" => -5, "message" => "error occurred during the storing (UPDATE) of the translation for the segment $id_segment");
                return $result;
            }
        }
        return 0;
    }

    public static function addTranslationSuggestion($id_segment, $id_job, $suggestions_json_array = "", $suggestion = "", $suggestion_match = "", $suggestion_source = "") {
	if (!empty($suggestion_source)){
		if (strpos($suggestion_source,"MT")===false){
			$suggestion_source='TM';
		}else{
			$suggestion_source='MT';
		}
	
	}
        $insertRes = setSuggestionInsert($id_segment, $id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source);
        if ($insertRes < 0 and $insertRes != -1062) {
            $result['error'][] = array("code" => -4, "message" => "error occurred during the storing (INSERT) of the suggestions for the segment $id_segment - $insertRes");
            return $result;
        }
        if ($insertRes == -1062) {
            // the translaion for this segment still exists : update it
            $updateRes = setSuggestionUpdate($id_segment, $id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source);
            // log::doLog("updateRes");
            // log::doLog($updateRes);
            if ($updateRes < 0) {
                $result['error'][] = array("code" => -5, "message" => "error occurred during the storing (UPDATE) of the suggestions for the segment $id_segment");
                return $result;
            }
        }
        return 0;
    }

    public static function getStatsForJob($jid) {
		$job_stats=getStatsForJob($jid);

		$job_stats=$job_stats[0];
		$job_stats['TOTAL_FORMATTED']=number_format($job_stats['TOTAL'],0,".",",");

		$job_stats['TRANSLATED_FORMATTED'] = number_format($job_stats['TRANSLATED'],0,".",",");
		$job_stats['APPROVED_FORMATTED']   = number_format($job_stats['APPROVED'],0,".",",");
		$job_stats['REJECTED_FORMATTED']   = number_format($job_stats['REJECTED'],0,".",",");
		$job_stats['DRAFT_FORMATTED']      = number_format($job_stats['DRAFT'],0,".",",");
		$job_stats['TODO_FORMATTED']       = number_format($job_stats['DRAFT']+$job_stats['REJECTED'],0,".",",");


		$job_stats['TRANSLATED_PERC']      = ($job_stats['TRANSLATED'])/$job_stats['TOTAL']*100;
		$job_stats['APPROVED_PERC']        = ($job_stats['APPROVED'])/$job_stats['TOTAL']*100;
		$job_stats['REJECTED_PERC']        = ($job_stats['REJECTED'])/$job_stats['TOTAL']*100;
		$job_stats['DRAFT_PERC']           = ($job_stats['DRAFT'])/$job_stats['TOTAL']*100;

		$job_stats['TRANSLATED_PERC_FORMATTED'] = number_format($job_stats['TRANSLATED_PERC'],1,".",",");
		$job_stats['APPROVED_PERC_FORMATTED']   = number_format($job_stats['APPROVED_PERC'],1,".",",");
		$job_stats['REJECTED_PERC_FORMATTED']   = number_format($job_stats['REJECTED_PERC'],1,".",",");
		$job_stats['DRAFT_PERC_FORMATTED']      = number_format($job_stats['DRAFT_PERC'],1,".",",");

    	$t = 'approved';
        if ($job_stats['TRANSLATED_FORMATTED'] > 0) $t = "translated";
        if ($job_stats['DRAFT_FORMATTED'] > 0) $t = "draft";
        if ($job_stats['REJECTED_FORMATTED'] > 0) $t = "draft";
		$job_stats['DOWNLOAD_STATUS']=$t;
		
		
		// Calculating words per hour and estimated completion
		$estimation_temp = getLastSegmentIDs($jid);
		$estimation_seg_ids = $estimation_temp[0]['estimation_seg_ids'];
		
		if ($estimation_seg_ids) { 
		
		$estimation_temp = getEQWLastHour($jid,$estimation_seg_ids);
			if ($estimation_temp[0]['data_validity']==1) {
				$job_stats['WORDS_PER_HOUR'] = number_format($estimation_temp[0]['words_per_hour'],0,'.',',');
				// 7.2 hours
				// $job_stats['ESTIMATED_COMPLETION'] = number_format( ($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour'],1);
				// 1 h 32 m
				// $job_stats['ESTIMATED_COMPLETION'] = date("G",($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour']*3600) . "h " . date("i",($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour']*3600) . "m";
				$job_stats['ESTIMATED_COMPLETION'] = date("G\h i\m",($job_stats['DRAFT']+$job_stats['REJECTED'])/$estimation_temp[0]['words_per_hour']*3600-3600);
			}
		}	
		
		        
		return $job_stats;
	
	}

	public static function getStatsForFile($fid){
	
	
		$file_stats=getStatsForFile($fid);

		$file_stats=$file_stats[0];
		$file_stats['ID_FILE']=$fid;
		$file_stats['TOTAL_FORMATTED']=number_format($file_stats['TOTAL'],0,".",",");
		$file_stats['REJECTED_FORMATTED']=number_format($file_stats['REJECTED'],0,".",",");
		$file_stats['DRAFT_FORMATTED']=number_format($file_stats['DRAFT'],0,".",",");
/*
		$file_stats['TRANSLATED_PERC']=($file_stats['TRANSLATED'])/$file_stats['TOTAL']*100;
		$file_stats['APPROVED_PERC']=($file_stats['APPROVED'])/$file_stats['TOTAL']*100;
		$file_stats['REJECTED_PERC']=($file_stats['REJECTED'])/$file_stats['TOTAL']*100;
		$file_stats['DRAFT_PERC']=($file_stats['DRAFT'])/$file_stats['TOTAL']*100;

		$file_stats['TRANSLATED_PERC_FORMATTED'] = number_format($file_stats['TRANSLATED_PERC'],1,".",",");
		$file_stats['APPROVED_PERC_FORMATTED']   = number_format($file_stats['APPROVED_PERC'],1,".",",");
		$file_stats['REJECTED_PERC_FORMATTED']   = number_format($file_stats['REJECTED_PERC'],1,".",",");
		$file_stats['DRAFT_PERC_FORMATTED']      = number_format($file_stats['DRAFT_PERC'],1,".",",");
*/
		// log::doLog($file_stats);		
		
		        
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


}

?>

