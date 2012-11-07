<?php

include_once INIT::$MODEL_ROOT . "/queries.php";

class downloadFileController extends downloadController {

    private $id_job;
    private $fname;
    private $download_type;

    public function __construct() {
        parent::__construct();

        $this->fname = $this->get_from_get_post('filename');
        $this->id_file = $this->get_from_get_post('id_file');
        $this->id_job = $this->get_from_get_post('id_job');
        $this->download_type = $this->get_from_get_post('download_type');
        if (empty($this->id_job)) {
            $this->id_job = "Unknown";
        }
    }

    public function doAction() {

        // specs for filename at the task https://app.asana.com/0/1096066951381/2263196383117
        $this->filename = $this->fname;
        if ($this->download_type == 'all') {
            //in this case fname contains the project name (see html)  
            $pathinfo = pathinfo($this->fname);
            //enable when will be supportoed the whole project with multiple file download
            //NOTICE: the file to be downloaded will have zip extension only if there are more than one 
            // file in te project        
            //if ($pathinfo['extension']!="zip"){
            //    $this->filename=$pathinfo['basename'].".zip"
            //}
            //disable when will be supportoed the whole project with multiple file download
            if ($pathinfo['extension'] != "xliff" and $pathinfo['extension'] != "sdlxliff" and $pathinfo['extension'] != "xlf") {
                $this->filename = $pathinfo['basename'] . ".xliff";
            } else {
                $this->filename = $this->fname;
            }
        }

        $files = getFilesForJob($this->id_job);
        $id_file = ($this->id_file == "") ? $files[0]['id_file'] : $this->id_file;
        $originalResult = getOriginalFile($id_file);
        $original = $originalResult[0]['original_file'];
        $working_copy = $original;

        $this->password = $this->get_from_get_post("password");
        $this->start_from = $this->get_from_get_post("start");
        if (is_null($this->start_from)) {
            $this->start_from = 0;
        }

        $data = getSegments($this->id_job, $this->password, $this->start_from);

        $transunit_translation = "";
        foreach ($data as $i => $seg) {
            $end_tags = "";
            $translation = empty($seg['translation']) ? $seg['segment'] : $seg['translation'];

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
            }

           
            if (!empty($seg['mrk_id'])) {
                $translation = "<mrk mtype=\"seg\" mid=\"" . $seg['mrk_id'] . "\">$translation</mrk>";
            }

            $transunit_translation.=$seg['prev_tags'] . $translation . $end_tags;

            if (isset($data[$i + 1]) and $seg['internal_id'] == $data[$i + 1]['internal_id']) {
                // current segment and subsequent has the same internal id --> 
                // they are two mrk of the same source segment  -->
                // the translation of the subsequentsegment will be queued to the current
                continue;
            }
            
            //this snipped could be temporary and cover the case if the segment is enclosed into a <g> tag
            // but the translation, due the tag stripping, does not contain it
            if (strpos($transunit_translation, "<g") === 0) { // I mean $transunit_translation began with <g tag
                $endsWith = substr($transunit_translation, -strlen("</g>")) == "</g>";

                if (!$endsWith) {
                    $transunit_translation.="</g>";
                }
            }
            $res_match_2 = false;
            $res_match_1 = false;

            $pattern = '|(<trans-unit id="' . $seg['internal_id'] . '".*?>.*?)(<source.*?>.*?</source>.*?)(<seg-source.*?>.*?</seg-source>.*?)?(<target.*?>).*?(</target>)(.*?)(</trans-unit>)|si';

            $res_match_1 = preg_match($pattern, $working_copy, $match_target);
            if (!$res_match_1) {
                $pattern = '|(<trans-unit id="' . $seg['internal_id'] . '".*?>.*?)(<source.*?>.*?</source>.*?)(<seg-source.*?>.*?</seg-source>.*?)?(.*?</trans-unit>)|si';
                $res_match_2 = preg_match($pattern, $working_copy, $match_target);
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
            $working_copy = preg_replace($pattern, $replacement, $working_copy);

            $transunit_translation = ""; // empty the translation before the end of the loop
        }
        $this->content = $working_copy;
    }

}

?>
