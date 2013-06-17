<?php

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";

class changeJobsStatusController extends ajaxcontroller {

      private $res_type;
      private $res_id;
      private $status;

      public function __construct() {
            parent::__construct();
            $this->res_type = $this->get_from_get_post('res');
            $this->res_id = $this->get_from_get_post('id');
            $this->status = $this->get_from_get_post('status');
      }

      public function doAction() {

			if ($this->res_type == "prj") {
				$old_status = getCurrentJobsStatus($this->res_id);
				$strOld = '';
				foreach ($old_status as $item) {
					$strOld .= $item['id'].':'.$item['status_owner'].',';
				}
				$strOld = trim($strOld,',');

				$this->result['old_status'] = $strOld;

			}

			$st = updateJobsStatus($this->res_type,$this->res_id,$this->status);

	        $this->result['code'] = 1;
	        $this->result['data'] = "OK";
	        $this->result['status'] = $this->status;
      }

}

?>