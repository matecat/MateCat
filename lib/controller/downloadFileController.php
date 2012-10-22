<?php
include_once INIT::$MODEL_ROOT . "/queries.php";

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class downloadFileController extends downloadController {

    private $id_job;


    public function __construct() {
        parent::__construct();

        $this->fname = $this->get_from_get_post('filename');
        $this->id_file = $this->get_from_get_post('id_file');
        $this->id_job = $this->get_from_get_post('id_job');
        if (empty($this->id_job)) {
            $this->id_job="Unknown";
        }
   
    }

    public function doAction() {
//		$this->filename = $this->fname.".xliff";
		$this->filename = $this->fname;
        $files = getFilesForJob($this->id_job);
		$id_file = ($this->id_file == "")?$files[0]['id_file']:$this->id_file;
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

//        $prova = "";
        foreach ($data as $i => $seg) {
/*
			$pattern = '|(<trans-unit id="'.$seg['internal_id'].'".*?>\s*<source>)(.*?)(</source>.*?<target>)(.*?)(</target>)|m';
			$replacement = '$1$2$3AAAA$5';
			$modified = preg_replace($pattern, $replacement, $modified);
*/
			$translation = ($seg['translation'] == '')? $seg['segment'] : $seg['translation'];
 			$search = '|<trans-unit id="'.$seg['internal_id'].'".*?>\s*<source>.*?</source>\s*<target>.*?</target>\s*</trans-unit>|sim';

			if(preg_match($search,$modified)) {
				$pattern = '|(<trans-unit id="'.$seg['internal_id'].'".*?>\s*<source>.*?</source>\s*<target>)(.*?)(</target>\s*)|sim';
				$replacement = '$1 '.$translation.'$3';
				$modified = preg_replace($pattern, $replacement, $modified);				
			} else {
			    // Modified to keep indentation as on the original file 
			    $pattern = '|(<trans-unit id="'.$seg['internal_id'].'".*?>)(\s*)(<source>\s*.*?\s*</source>)(\s*)|sim';
				$replacement = '$1$2$3$2<target>'.$translation.'</target>$4';
//				log::doLog('ECCO: '.$seg['internal_id'].' - '.$translation);
				$modified = preg_replace($pattern, $replacement, $modified);
				// log::doLog($modified);
			}
//			$GLOBALS['modified'] = $modified;
//		echo $modified."<br/>";

//        	$stringa .= $i.'/'.$seg['sid'].', ';
//        	$stringa .= $i.'/'.$seg['internal_id'].', ';
			
         }

		// log::doLog('MODIFIED: '.$modified);

		$this->content=$modified;
//		$this->content=$id_file;

		
//		$this->content=$prova;
		
		
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
