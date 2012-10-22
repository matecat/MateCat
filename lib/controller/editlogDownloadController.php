<?php
include_once INIT::$UTILS_ROOT . "/cat.class.php";
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of catController
 *
 * @author antonio
 */
class editlogDownloadController extends downloadController {

    //put your code here
//    private $id_file="";
    private $jid = "";

    public function __construct() {
//		echo ".........\n";
        parent::__construct();
        $this->jid = $this->get_from_get_post("jid");
        $this->password = $this->get_from_get_post("password");
        //echo $this->jid; exit;
        $this->filename="Edit-log-export-".$this->jid.".csv";
        
    }


    public function doAction() {
   
  $csv="Job ID;Segment ID;Suggestion Source;Words;Match percentage;Time-to-edit;Post-editing effort;Segment;Suggestion;Translation;ID translator;Suggestion1-source;Suggestion1-translation;Suggestion1-match;Suggestion1-origin;Suggestion2-source;Suggestion2-translation;Suggestion2-match;Suggestion2-origin;Suggestion3-source;Suggestion3-translation;Suggestion3-match;Suggestion3-origin\n";
         $data = CatUtils::getEditingLogData($this->jid,$this->password);
//    	log::doLog('provalog');
         $data = $data[0];
//        	echo "<pre>"; print_r ($data); exit;

         foreach ($data as $d){
             $sid=$d['sid'];
             $rwc=$d['rwc'];
             $sugg_source=str_replace("';","\'\;",$d['ss']);
             $sugg_match=$d['sm'];
             $sugg_tte=$d['time_to_edit'];
             $pe_effort_perc=$d['pe_effort_perc'];
             $segment=str_replace("';","\'\;",$d['source']);
             $suggestion=str_replace("';","\'\;",$d['sug']);
             $translation=str_replace("';","\'\;",$d['translation']);
	     	 $id_translator=str_replace("';","\'\;",$d['tid']);
	     	 $sar=json_decode($d['sar']);
			 $s1_source=$sar[0]->segment;
			 $s2_source=$sar[1]->segment;
			 $s3_source=$sar[2]->segment;

			 $s1_translation=$sar[0]->translation;
			 $s2_translation=$sar[1]->translation;
			 $s3_translation=$sar[2]->translation;

			 $s1_match=$sar[0]->match;
			 $s2_match=$sar[1]->match;
			 $s3_match=$sar[2]->match;
			 
//			 $s1_origin=substr($sar[0]->created_by,0,2);
			 $s1_origin=(substr($sar[0]->created_by,0,2) == 'MT')?'MT':'TM';
			 $s2_origin=(substr($sar[1]->created_by,0,2) == 'MT')?'MT':'TM';
			 $s3_origin=(substr($sar[2]->created_by,0,2) == 'MT')?'MT':'TM';
             
			$csv.="$this->jid;$sid;\"$sugg_source\";$rwc;\"$sugg_match\";\"$sugg_tte\";\"$pe_effort_perc\";\"$segment\";\"$suggestion\";\"$translation\";\"$id_translator\";\"$s1_source\";\"$s1_translation\";\"$s1_match\";\"$s1_origin\";\"$s2_source\";\"$s2_translation\";\"$s2_match\";\"$s2_origin\";\"$s3_source\";\"$s3_translation\";\"$s3_match\";\"$s3_origin\"\n";
             
         }
      //   echo "<pre>$csv" ; exit;
         $this->content=$csv;
         

    } 

}

?>
