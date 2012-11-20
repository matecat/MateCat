<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include INIT::$UTILS_ROOT . "/mymemory_queries_temp.php";
include INIT::$UTILS_ROOT . "/filetype.class.php";
include INIT::$UTILS_ROOT . "/cat.class.php";
include INIT::$UTILS_ROOT . "/langs/languages.inc.php";

/**
 * Description of catController
 *
 * @author antonio
 */
class catController extends viewcontroller {

    //put your code here    
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
	private $start_time=0.00;
    private $job_stats=array();
//    private $seg = '';

    public function __construct() {
		$this->start_time=microtime(1)*1000;    	
//    	log::doLog('provalog');
       // echo ".........\n";
        parent::__construct();
        parent::makeTemplate("index.html");
        $this->jid = $this->get_from_get_post("jid");
		$this->password=$this->get_from_get_post("password");
        $this->start_from = $this->get_from_get_post("start");
        $this->page = $this->get_from_get_post("page");

/*
		if (isset($_COOKIE['segment'])) {
			$this->open_segment = $_COOKIE['segment'];
			setcookie("segment", "", time()-3600);
		}
*/

		if (isset($_GET['step'])) { 
        	$this->step = $_GET['step'];
		} else {
        	$this->step = 1000;
		};

        if (is_null($this->page)) {
            $this->page = 1;
        }
		if (is_null($this->start_from)) {
            $this->start_from = ($this->page-1)*$this->step;
        }

	if (is_null($this->jid) and is_null($this->password)) {
            header("Location: /translatenew/esempio.xliff/en-fr/849-mcfmtvg8");
	    exit(0);
        }
    }
/*
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
*/
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
//	    $start = ($page-1)*$step;
        //$data = getSegments($this->jid, $this->password, $this->start_from, $this->step);
		$data = getSegmentsInfo($this->jid, $this->password);
		
//        echo "<pre>";
//        print_r ($data);
//        exit;
//        
        $first_not_translated_found = false;
//		log::doLog('DATA 0: '.$data[0]['last_opened_segment']);

        foreach ($data as $i => $seg) {
	  		// remove this when tag management enabled
        //	$seg['segment'] = $this->stripTagsFromSource($seg['segment']);
			
/*        	
//            $seg['segment'] = $this->stripTagsFromSource($seg['segment']);
//            $seg['segment'] = trim($seg['segment']);

            if (empty($seg['segment'])) {
                continue;
            }
*/
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
				$this->data["$id_file"]['last_opened_segment'] = $seg['last_opened_segment'];
                $this->data["$id_file"]['file_stats'] = $file_stats;		
		//$this->data["$id_file"]['segments'] = array();
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
	    	unset($seg['last_opened_segment']);

           // $seg['segment'] = $this->filetype_handler->parse($seg['segment']);
        //    $seg['parsed_time_to_edit']=  $this->parse_time_to_edit($seg['time_to_edit']); 
	    //$seg['time_to_edit']=explode(":", $seg['time_to_edit']); // from DB(time_to_sec function used) HH:MM:SS

         /*   if (!$first_not_translated_found and empty($seg['translation'])) { //get matches only for the first segment                
                $first_not_translated_found = true;
                $matches = array();
                $matches = getFromMM($seg['segment']);

                $matches = array_slice($matches, 0, INIT::$DEFAULT_NUM_RESULTS_FROM_TM);

                $seg['matches'] = $matches;

                //$seg['matches_no_mt']=0;
                //foreach ($matches as $m){
                //    if ($m['created-by']!='MT'){
                //        $seg['matches_no_mt']+=1;
                //    }
                //}
                $seg['css_loaded'] = "loaded";
            }
          * 
          * 
          */

            /*if (!empty($seg['translation'])) {
                $seg['css_loaded'] = "loaded";
            }*/

          //  $this->data["$id_file"]['segments'][] = $seg;
	  
						//print_r ($this->data); exit;

			//log::doLog('NUM SEGMENTS 2: '.count($this->data["$id_file"]['segments']));



        }

        if (empty($this->last_opened_segment)) {
            $this->last_opened_segment = getFirstSegmentId($this->jid, $this->password);
        	log::doLog($this->last_opened_segment);
        }
	        
        $this->job_stats = CatUtils::getStatsForJob($this->jid);

    //   echo "<pre>";
    //   print_r($this->data);
    //   exit;
    }

    public function setTemplateVars() {
        $this->template->jid = $this->jid;
        $this->template->password=$this->password;
        $this->template->cid = $this->cid;
        $this->template->create_date = $this->create_date;
        $this->template->pname = $this->pname;
		$this->template->pid=$this->pid;
        $this->template->tid=$this->tid;
		$this->template->source=$this->source;
		$this->template->target=$this->target;
		$this->template->cucu=$this->open_segment;
	
	
//		$this->template->stats=$stats[0]['TOTAL'];
		
		$this->template->source_code=$this->source_code;
		$this->template->target_code=$this->target_code;
		
		$this->template->last_opened_segment=$this->last_opened_segment;
		$this->template->data = $this->data;
	
		$this->template->job_stats=$this->job_stats;

		$end_time=microtime(true)*1000;
		$load_time=$end_time-$this->start_time;
		$this->template->load_time=$load_time;
		$this->template->time_to_edit_enabled = $TIME_TO_EDIT_ENABLED;

				log::doLog("TIME_TO_EDIT_ENABLED : $TIME_TO_EDIT_ENABLED"); 

       // echo "<pre>";
        //print_r ($this->template);
        //exit;

    }

}
?>

