<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";

class getSearch_fakeController extends ajaxcontroller {

	private $job;
	private $token;
	private $password;
	private $source;
	private $target;
	private $status;
	private $replace;


	public function __construct() {
		parent::__construct();


		$this->function = $this->get_from_get_post('function');
		if (empty($this->function)) {
			$this->function="Unknown";
		}
         $this->job = $this->get_from_get_post('job');
		if (empty($this->job)) {
			$this->job="Unknown";
		}
		$this->token = $this->get_from_get_post('token');
		if (empty($this->token)) {
			$this->token="Unknown";
		}
		$this->source = $this->get_from_get_post('source');
		if (empty($this->source)) {
			$this->source="";
		}   
		$this->target = $this->get_from_get_post('target');
		if (empty($this->target)) {
			$this->target="";
		}   
        $this->status = $this->get_from_get_post('status');
		if (empty($this->status)) {
			$this->status="all";
		}   
        $this->replace = $this->get_from_get_post('replace');
		if (empty($this->replace)) {
			$this->replace="all";
		}   
		$this->password=$this->get_from_get_post("password");
	}

	public function doAction() {
		$this->result['token'] = $this->token;
		$this->result['total'] = 100;
  		$this->result['segments'] = array(100, 120, 150);      
        
/*
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
*/

	}
/*
    function getTotalItems() {

        $query = "select s.id as sid
                from segments s
                where j.id=$this->job and j.password='$this->password' ";

        $db      = Database::obtain();
        $results = $db->fetch_array( $query );

        $err   = $db->get_error();
        $errno = $err[ 'error_code' ];
        if ( $errno != 0 ) {
            log::doLog( $err );

            return $errno * -1;
        }

        return $results;
    }
*/
}