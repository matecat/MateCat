<?php
include INIT::$ROOT."/lib/utils/mymemory_queries_temp.php";

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class getContributionController extends ajaxcontroller {

    private $id_segment;
    private $num_results;
    private $text;

    public function __construct() {
        parent::__construct();

        $this->id_segment = $this->get_from_get_post('id_segment');
        $this->num_results = $this->get_from_get_post('num_results');
        $this->text = $this->get_from_get_post('text');
        
        $this->text=  str_replace("&nbsp;", " ", $this->text);
    }

    public function doAction() {
        if (empty($this->id_segment)) {
            $this->result['error'][] = array("code" => -1, "message" => "missing id_segment");
        }
        if (empty($this->id_segment)) {
            $this->result['error'][] = array("code" => -2, "message" => "missing text");
        }

        if (empty($this->num_results)) {
            $this->num_results = DEFAULT_NUM_RESULTS;
        }

        if (!empty($this->result['error'])) {
            return -1;
        }


        // UNUSED
        $fake_matches = array();
        $fake_matches[] = array("segment" => $this->text, "translation" => "$this->text fake translation", "quality" => 74, "created_by" => "Vicky", "last_update_date" => "2011-08-21 14:30", "match" => 1);
        $fake_matches[] = array("segment" => $this->text, "translation" => "$this->text fake translation second result", "quality" => 84, "created_by" => "Don", "last_update_date" => "2010-06-21 14:30", "match" => 0.84);
        //$matches = $fake_matches;

        $matches=array();
        $retMM = getFromMM($this->text);
        foreach ($retMM as $r) {
            // Marco: antonio ma che è sta cosa con le posizione degli array invece che le chiavi?
            // Poi perché la stessa funzione sta in lib/utils/mymemory_queries_temp.php e viene effettivamente usata.
            
              $matches[] = @array("segment" => $r[5], "translation" => $r[0], "quality" => $r[1], "created_by" => $r[2], "last_update_date" => $r[3], "match" => $r[4]);
            
            if (count($matches) > INIT::$DEFAULT_NUM_RESULTS_FROM_TM) {
                break;
            }
        }

        
        $this->result['data']['matches'] = $matches;
    }

    private function getFromMM() {
        $q = urlencode($this->text);
        $url = "http://mymemory.translated.net/api/get?q=$q&langpair=en|it";
        $res = file_get_contents($url);
        $res = json_decode($res, true);

        $ret = array();
        // print_r ($res['matches']);
        foreach ($res['matches'] as $match) {
            $ret[] = array($match['translation'], $match['quality'], $match['created-by'], $match['last-update-date'], $match['match'],  $match['segment']);
        }


        //print_r ($ret);
        return $ret;
    }

}

?>
