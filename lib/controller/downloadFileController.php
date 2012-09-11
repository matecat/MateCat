<?php
include_once INIT::$MODEL_ROOT . "/queries.php";

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class downloadFileController extends viewcontroller {

    private $id_job;


    public function __construct() {
        parent::__construct();

        $this->id_job = $this->get_from_get_post('id_job');
        if (empty($this->id_job)) {
            $this->id_job="Unknown";
        }
   
    }

    public function doAction() {
        echo "hghj";
		exit;
        $files = getFilesForJob($this->id_job);
		$id_file = $files[0]['id_file'];
		$originalResult = getOriginalFile($id_file);
		$original = $originalResult[0]['original_file'];
		$modified = $original;
/*
		$preg_trans_unit = '|<trans-unit id="(.*?)"(.*?)>\s*<source>(.*?)</source>.*?<target>(.*?)</target>|m';
        preg_match_all($preg_trans_unit, $modified, $res2, PREG_SET_ORDER);

        foreach ($res2 as $trans_unit) {
//            $id = $trans_unit[1];
//            $no_translate = $trans_unit[2];
        }
*/
//		$pattern = '|(<source>)(.*?)(</source>)|';
//		$modified = preg_replace($pattern, "$1$3", $modified);

		$this->password=$this->get_from_get_post("password");
        $this->start_from = $this->get_from_get_post("start");
        if (is_null($this->start_from)) {
            $this->start_from = 0;
        }
        if (is_null($this->password)) {
            $this->password = 'sldfjw322d';
        }
        $data = getSegments($this->id_job, $this->password, $this->start_from);
//		$stringa = "";

        foreach ($data as $i => $seg) {
/*
			$pattern = '|(<trans-unit id="'.$seg['internal_id'].'".*?>\s*<source>)(.*?)(</source>.*?<target>)(.*?)(</target>)|m';
			$replacement = '$1$2$3AAAA$5';
			$modified = preg_replace($pattern, $replacement, $modified);
*/
			$translation = ($seg['translation'] == '')? $seg['segment'] : $seg['translation'];
			$search = '|<trans-unit id="'.$seg['internal_id'].'".*?>\s*<source>.*?</source>.*?<target>.*?</target>.*?</trans-unit>|m';
			if(preg_match($search,$modified)) {
				$pattern = '|(<trans-unit id="'.$seg['internal_id'].'".*?>\s*<source>.*?</source><target>)(.*?)(</target>)|m';
				$replacement = '$1'.$translation.'$3';
				$modified = preg_replace($pattern, $replacement, $modified);				
			} else {
				$pattern = '|(<trans-unit id="'.$seg['internal_id'].'".*?>\s*<source>.*?</source>)|m';
				$replacement = '$1<target>'.$translation.'</target>';
				$modified = preg_replace($pattern, $replacement, $modified);
			}
		
//        	$stringa .= $i.'/'.$seg['sid'].', ';
//        	$stringa .= $i.'/'.$seg['internal_id'].', ';
			
         }
		$buffer = ob_get_contents();
		ob_clean();
 		header("Content-type: text/plain");
		header("Content-Disposition: attachment; filename=prova");
		header("Pragma: no-cache");
		header("Expires: 0");
		echo $modified;
		exit;
		
/*
		$buffer = ob_get_contents();
		ob_clean();
 		header("Content-type: text/plain");
		header("Content-Disposition: attachment; filename=prova");
		header("Pragma: no-cache");
		header("Expires: 0");
		echo 'prova';
		exit;
*/
		
//		return $original_file;

//        log::doLog($insertRes);

/*           
        $this->result['code'] = 1;
        $this->result['data'] = "OK";
        $this->result['original'] = $original;
        $this->result['modified'] = $modified;
*/

//        $this->result['stringa'] = $stringa;
        
    }


}

?>




