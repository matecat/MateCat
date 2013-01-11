<?php
set_time_limit (180);
include_once INIT::$MODEL_ROOT . "/queries.php";

class downloadFileController extends downloadController {

    private $id_job;
    private $password;
    private $fname;
    private $download_type;
    
  
    public function __construct() {
        parent::__construct();
//echo "<pre>";print_r ($_POST);

        $this->fname = $this->get_from_get_post('filename');
        $this->id_file = $this->get_from_get_post('id_file');
        $this->id_job = $this->get_from_get_post('id_job');
        $this->download_type = $this->get_from_get_post('download_type');
        $this->filename = $this->fname;
        $this->password = $this->get_from_get_post("password");
        
        if (empty($this->id_job)) {
            $this->id_job = "Unknown";
        }
    }

    public function doAction() {

        // specs for filename at the task https://app.asana.com/0/1096066951381/2263196383117

        /*if ($this->download_type == 'all') {
            //in this case fname contains the project name (see html)  
            $pathinfo = pathinfo($this->fname);
            if ($pathinfo['extension'] != "xliff" and $pathinfo['extension'] != "sdlxliff" and $pathinfo['extension'] != "xlf" and $pathinfo['extension'] != "zip") {
                $this->filename = $pathinfo['basename'] . ".sdlxliff";
            } else {
                $this->filename = $this->fname;
            }
        }
        */

        $files_job = getFilesForJob($this->id_job,$this->id_file);
//print_r ($files_job); ;
        $output_content=array();
        foreach ($files_job as $file){
        	$id_file=$file['id_file'];
        	$current_filename=$file['filename'];
        	$original=$file['original_file'];
        	$data = getSegmentsDownload($this->id_job, $this->password, $id_file);	
        	//print_r ($data); exit;
        	$transunit_translation = "";
			//echo "<pre>";
			foreach ($data as $i => $seg) {
			//	echo $seg['internal_id']."\n";
				$end_tags = "";
				$translation = empty($seg['translation']) ? $seg['segment'] : $seg['translation'];
			//	echo "t1 : $translation\n";
	
				@$xml_valid = simplexml_load_string("<placeholder>$translation</placeholder>");
				if (!$xml_valid) {
					$temp = preg_split("|\<|si", $translation);
					$item = end($temp);
					if (preg_match('|/.*?>\W*$|si', $item)) {
						$end_tags.="<$item";
					}
					while ($item = prev($temp)) {
			if (preg_match('|/.*?>\W*$|si', $item)) {
							$end_tags = "<$item$end_tags"; //insert at the top of the string
						}
					}
					$translation = str_replace($end_tags, "", $translation);
					//echo "t2 : $translation\n";
				}
				
				if (!empty($seg['mrk_id'])) {
					$translation = "<mrk mtype=\"seg\" mid=\"" . $seg['mrk_id'] . "\">$translation</mrk>";
				}
	//echo "t3 : $translation\n";
//echo "\n\n";
				$transunit_translation.=$seg['prev_tags'] . $translation . $end_tags.$seg['succ_tags'];
	//echo "t4 :" .$seg['prev_tags'] . $translation . $end_tags.$seg['succ_tags']."\n";
				if (isset($data[$i + 1]) and $seg['internal_id'] == $data[$i + 1]['internal_id']) {
					// current segment and subsequent has the same internal id --> 
					// they are two mrk of the same source segment  -->
					// the translation of the subsequentsegment will be queued to the current
					continue;
				}
				
				//this snippet could be temporary and cover the case if the segment is enclosed into a <g> tag
				// but the translation, due the tag stripping, does not contain it
				// ANTONIO :  deleted because it's wrong !! if a segmemnt began by <g> tag its closure tag shoud be in the middle not only at the end of it. Instead we could check if the trans-unit is xml valid.
				/*if (strpos($transunit_translation, "<g") === 0) { // I mean $transunit_translation began with <g tag
					$endsWith = substr($transunit_translation, -strlen("</g>")) == "</g>";
	
					if (!$endsWith) {
						$transunit_translation.="</g>";
					}
				}*/
				$res_match_2 = false;
				$res_match_1 = false;
	
				$pattern = '|(<trans-unit id="' . $seg['internal_id'] . '".*?>.*?)(<source.*?>.*?</source>.*?)(<seg-source.*?>.*?</seg-source>.*?)?(<target.*?>).*?(</target>)(.*?)(</trans-unit>)|si';
	
				$res_match_1 = preg_match($pattern, $original, $match_target);
				if (!$res_match_1) {
					$pattern = '|(<trans-unit id="' . $seg['internal_id'] . '".*?>.*?)(<source.*?>.*?</source>.*?)(<seg-source.*?>.*?</seg-source>.*?)?(.*?</trans-unit>)|si';
					$res_match_2 = preg_match($pattern, $original, $match_target);
					if (!$res_match_2) {
						; // exception !!! see the segment format
					}
				}
	
	
				if ($res_match_1) { //target esiste
					$replacement = "$1$2$3$4" . $transunit_translation . "$5$6$7";
				}
				if ($res_match_2) { //target non esiste
					$replacement = "$1$2$3<target>$transunit_translation</target>$4";
				}
	
				if (!$res_match_1 and !$res_match_2) {
					continue; // none of pattern verify the file structure for current segmen t: go to next loop. In the worst case the procedure will return the original file
				}
				$original= preg_replace($pattern, $replacement, $original);
				$transunit_translation = ""; // empty the translation before the end of the loop
			}
			$output_content[$id_file]['content']=$original;
			$output_content[$id_file]['filename']=$current_filename;
        }
//	print_r ($output_content);
	//exit;
        $ext="";
        if ($this->download_type == 'all') {
            if (count($output_content)>1){
            	$this->filename = $this->fname;	            	
            	$pathinfo = pathinfo($this->fname);
                if ($pathinfo['extension'] != 'zip'){
                	$this->filename = $pathinfo['basename'] . ".zip";
                }
                $this->content=$this->composeZip($output_content) ; //add zip archive content here;
            }elseif (count($output_content)==1){
            	foreach ($output_content as $oc){
            		$pathinfo = pathinfo($oc['filename']);
			$this->filename=$oc['filename'];
				if (!in_array($pathinfo['extension'],array("xliff","sdlxliff","xlf"))){
					$this->filename = $pathinfo['basename'] . ".sdlxliff";
				}
				$this->content = $oc['content'];
		}
            }
        }else{
		foreach ($output_content as $oc){
            		$pathinfo = pathinfo($oc['filename']);
			$this->filename=$oc['filename'];
				if (!in_array($pathinfo['extension'],array("xliff","sdlxliff","xlf"))){
					$this->filename = $pathinfo['basename'] . ".sdlxliff";
				}
				$this->content = $oc['content'];
		}
        }   
        
	
    }
    
    
    private function composeZip($output_content){
    	$file = tempnam("/tmp", "zipmatecat");
		$zip = new ZipArchive();
		$zip->open($file, ZipArchive::OVERWRITE);

		// Staff with content
		foreach ($output_content as $f){
			$zip->addFromString($f['filename'], $f['content']);
		}

		// Close and send to users
		$zip->close();
		$zip_content=file_get_contents("$file");
		unlink ($file);
		return $zip_content;
    }

}

?>
