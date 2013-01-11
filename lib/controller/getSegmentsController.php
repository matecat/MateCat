<?php
include_once INIT::$MODEL_ROOT . "/queries.php";
include INIT::$UTILS_ROOT . "/mymemory_queries_temp.php";
include INIT::$UTILS_ROOT . "/filetype.class.php";
include INIT::$UTILS_ROOT . "/cat.class.php";
include INIT::$UTILS_ROOT . "/langs/languages.inc.php";
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class getSegmentsController extends ajaxcontroller {

    private $data = array();
    private $cid = "";
    private $jid = "";
    private $tid = "";
    private $password="";
    private $source="";
    private $pname = "";
    private $create_date = "";
    private $filetype_handler = null;
    private $start_from = 0;
    private $page = 0;

    public function __construct() {
        parent::__construct();

        $this->jid = $this->get_from_get_post("jid");
		$this->password=$this->get_from_get_post("password");
        $this->step = $this->get_from_get_post("step");
        $this->ref_segment = $this->get_from_get_post("segment");
        $this->where = $this->get_from_get_post("where");

//		    	log::doLog('LAST LOADED ID - MODIFIED: '.$this->last_loaded_id);
//		if($this->central_segment) log::doLog('CENTRAL SEGMENT: '.$this->central_segment);
    }

    private function stripTagsFromSource($text) {
        //       echo "<pre>";
        $pattern_g_o = '|(<.*?>)|';
        $pattern_g_c = '|(</.*?>)|';
        $pattern_x = '|(<.*?/>)|';

        // echo "first  -->  $text \n";
        $text = preg_replace($pattern_x, "", $text);
        // echo "after1  --> $text\n";

        $text = preg_replace($pattern_g_o, "", $text);
        //  echo "after2  -->  $text \n";
//
        $text = preg_replace($pattern_g_c, "", $text);
        $text= str_replace ("&nbsp;", " ", $text);
        return $text;
    }

	private function parse_time_to_edit($ms){
        if ($ms <= 0) {
            return array("00", "00", "00", "00");
        }
		
		$usec = $ms % 1000;
		$ms = floor($ms/ 1000);

		$seconds = str_pad($ms % 60,2,"0",STR_PAD_LEFT);
		$ms = floor($ms / 60);
		
		$minutes = str_pad($ms % 60,2 ,"0", STR_PAD_LEFT);
		$ms = floor($ms / 60); 
		
	        $hours = str_pad($ms % 60,2,"0",STR_PAD_LEFT);
                $ms = floor($ms / 60); 
		
		return array($hours,$minutes,$seconds,$usec);
	
	}

    public function doAction() {
        $lang_handler=languages::getInstance("en");       
//		log::doLog('REF SEGMENT: '.$this->ref_segment);    	


		    if ($this->ref_segment == '') {
				$this->ref_segment = 0;
	        }


        $data = getMoreSegments($this->jid, $this->password, $this->step, $this->ref_segment, $this->where);

        $first_not_translated_found = false;
		//log::doLog('REF SEGMENT: '.$this->ref_segment);    	
//		print_r($data); exit;
        foreach ($data as $i => $seg) {

	  		if($this->where == 'before') {
	  			if(((float) $seg['sid']) >= ((float) $this->ref_segment)) {break;}
			}

	  		// remove this when tag management enabled
//        	$seg['segment'] = $this->stripTagsFromSource($seg['segment']);

			
            if (empty($this->pname)) {
                $this->pname = $seg['pname'];
            }

            if (empty($this->last_opened_segment)) {
                $this->last_opened_segment = $seg['last_opened_segment'];
            }
			
            if (empty($this->cid)) {
                $this->cid = $seg['cid'];
            }

            if (empty($this->pid)) {
                $this->pid = $seg['pid'];
            }

            if (empty($this->tid)) {
                $this->tid = $seg['tid'];
            }

            if (empty($this->create_date)) {
                $this->create_date = $seg['create_date'];
            }

            if (empty($this->source_code)) {
	            $this->source_code = $seg['source'];
	        }

	    if (empty($this->target_code)) {
	        $this->target_code = $seg['target'];
	    }

	    if (empty($this->source)) {
		$s=explode("-", $seg['source']);
		$source=strtoupper($s[0]);
	        $this->source = $source;
	     }

		    if (empty($this->target)) {
			$t=explode("-", $seg['target']);
			$target=strtoupper($t[0]);
	            $this->target = $target;
	        }

            $id_file = $seg['id_file'];
			$file_stats =CatUtils::getStatsForFile($id_file);
            
			
            if (!isset($this->data["$id_file"])) {                
                $this->data["$id_file"]['jid'] = $seg['jid'];		
                $this->data["$id_file"]["filename"] = $seg['filename'];
                $this->data["$id_file"]["mime_type"] = $seg['mime_type'];
                $this->data["$id_file"]['id_segment_start'] = $seg['id_segment_start'];
                $this->data["$id_file"]['id_segment_end'] = $seg['id_segment_end'];                
                $this->data["$id_file"]['source']=$lang_handler->iso2Language($seg['source']);
                $this->data["$id_file"]['target']=$lang_handler->iso2Language($seg['target']);
                $this->data["$id_file"]['source_code']=$seg['source'];
                $this->data["$id_file"]['target_code']=$seg['target'];
                $this->data["$id_file"]['file_stats'] = $file_stats;		
				$this->data["$id_file"]['segments'] = array();
            }
            //if (count($this->data["$id_file"]['segments'])>100){continue;}
            $this->filetype_handler = new filetype($seg['mime_type']);



            unset($seg['id_file']);
	    	unset($seg['source']);
            unset($seg['target']);
	    	unset($seg['source_code']);
            unset($seg['target_code']);
            unset($seg['mime_type']);
            unset($seg['filename']);
            unset($seg['jid']);
            unset($seg['pid']);
            unset($seg['cid']);
            unset($seg['tid']);
            unset($seg['pname']);
            unset($seg['create_date']);
            unset($seg['id_segment_end']);
            unset($seg['id_segment_start']);
//		log::doLog('A');

            $seg['segment'] = $this->filetype_handler->parse($seg['segment']);

           // ASKED. MARCO CONFIRMED: in the web interface do not show xliff_ext_prec_tags and xliff_ext_succ_tags
	   // $seg['segment'] = $seg['xliff_ext_prec_tags'] . $seg['segment'].$seg['xliff_ext_succ_tags'] ;
	$seg['segment']=CatUtils::rawxliff2view($seg['segment']);
	$seg['translation']=CatUtils::rawxliff2view($seg['translation']);
		
            $seg['parsed_time_to_edit']=  $this->parse_time_to_edit($seg['time_to_edit']); 
		
            $this->data["$id_file"]['segments'][] = $seg;
	
        }

//log::doLog ($this->data);
		
		
        $this->result['data']['files'] = $this->data;
		
        $this->result['data']['where']=$this->where;
		
    }


    
}

?>



