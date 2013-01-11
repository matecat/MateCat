<?php
include_once INIT::$MODEL_ROOT . "/queries.php";

class setCurrentSegmentController extends ajaxcontroller {

    private $id_segment;
    private $id_job;


    public function __construct() {
        parent::__construct();

        $this->id_segment = $this->get_from_get_post('id_segment');
        if (empty($this->id_segment)) {
            $this->id_segment="Unknown";
        }

        $this->id_job = $this->get_from_get_post('id_job');
        if (empty($this->id_job)) {
            $this->id_job="Unknown";
        }
   
    }

    public function doAction() {

        if (empty($this->id_segment)) {
            $this->result['error'][] = array("code" => -1, "message" => "missing segment id");
        }
        
        $insertRes = setCurrentSegmentInsert($this->id_segment, $this->id_job);
		
//		$nextSegmentId = getNextUntranslatedSegment($this->id_segment, $this->id_job);
		            
        $this->result['code'] = 1;
        $this->result['data'] = "OK";
        
//        $this->result['nextSegmentId'] = isset($nextSegmentId[0]['id'])?$nextSegmentId[0]['id']:'';
//		log::doLog('NEXTSEGMENTID: '.$nextSegmentId);
        
    }


}

?>


