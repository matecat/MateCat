<?php
include INIT::$ROOT."/lib/utils/mymemory_queries_temp.php";
include_once INIT::$MODEL_ROOT . "/queries.php";

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
class getContributionController extends ajaxcontroller {

    private $id_segment;
    private $id_job;
    private $num_results;
    private $text;
        
    private $source;
    private $target;

    public function __construct() {
        parent::__construct();

        $this->id_segment = $this->get_from_get_post('id_segment');
        $this->id_job = $this->get_from_get_post('id_job');
        $this->num_results = $this->get_from_get_post('num_results');
        $this->text = $this->get_from_get_post('text');
        
        
    }

    public function doAction() {
        if (empty($this->id_segment)) {
            $this->result['error'][] = array("code" => -1, "message" => "missing id_segment");
        }
        if (empty($this->text)) {
            $this->result['error'][] = array("code" => -2, "message" => "missing text");
        }

        if (empty($this->num_results)) {
            $this->num_results = INIT::$DEFAULT_NUM_RESULTS_FROM_TM;
        }

        if (!empty($this->result['error'])) {
            return -1;
        }
        
        $st=getSourceTargetFromJob($this->id_job);
        
        $this->source=$st['source'];
        $this->target=$st['target'];


        // UNUSED
        $fake_matches = array();
        $fake_matches[] = array("segment" => $this->text, "translation" => "$this->text fake translation", "quality" => 74, "created_by" => "Vicky", "last_update_date" => "2011-08-21 14:30", "match" => 1);
        $fake_matches[] = array("segment" => $this->text, "translation" => "$this->text fake translation second result", "quality" => 84, "created_by" => "Don", "last_update_date" => "2010-06-21 14:30", "match" => 0.84);
        //$matches = $fake_matches;

        $matches=array();
        $matches = getFromMM($this->text, $this->source,$this->target);
        
        $matches=array_slice ($matches,0,INIT::$DEFAULT_NUM_RESULTS_FROM_TM);
      /*  foreach ($retMM as $r) {
            // Marco: antonio ma che è sta cosa con le posizione degli array invece che le chiavi?
            // Poi perché la stessa funzione sta in lib/utils/mymemory_queries_temp.php e viene effettivamente usata.
            
              $matches[] = @array("segment" => $r[5], "translation" => $r[0], "quality" => $r[1], "created_by" => $r[2], "last_update_date" => $r[3], "match" => $r[4]);
            
            if (count($matches) > INIT::$DEFAULT_NUM_RESULTS_FROM_TM) {
                break;
            }
        }
*/
        
        $this->result['data']['matches'] = $matches;
    }


}

?>
