<?php
include_once INIT::$MODEL_ROOT . "/queries.php";

class getNextSegmentController extends ajaxcontroller {

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

        $this->status = $this->get_from_get_post('status');
        if (empty($this->status)) {
            $this->id_job="untranslated";
        }   

		$this->password=$this->get_from_get_post("password");
        $this->step = $this->get_from_get_post("step");
        $this->ref_segment = $this->get_from_get_post("ref_segment");
        $this->where = $this->get_from_get_post("where");
    }

    public function doAction() {

        if (empty($this->id_segment)) {
            $this->result['error'][] = array("code" => -1, "message" => "missing segment id");
        }


        $lastSegmentInNextWindow = getLastSegmentInNextFetchWindow($this->id_job, $this->password, $this->step, $this->ref_segment, $this->where);
	$nextSegmentId = getNextSegmentId($this->id_segment, $this->id_job, $this->status);

	
	$this->result['nextSegmentId']=$nextSegmentId;
	if ($nextSegmentId>$lastSegmentInNextWindow){
		$this->result['nextid_in_next_window']=0;
	}else{
		$this->result['nextid_in_next_window']=1;
	}
        $this->result['code'] = 1;
        $this->result['data'] = "OK";
        
        
	
        
    }


}

?>
