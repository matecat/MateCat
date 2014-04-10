<?php
include_once INIT::$MODEL_ROOT . "/queries.php";

class outsourceToTranslatedController extends ajaxController {
	
	private $id_project;

    public function __construct() {

        $this->disableSessions();
        parent::__construct();

		$this->id_project = $this->get_from_get_post('pid');
		if (empty($this->id_project)) {
			$this->id_project="Unknown";
		}
        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

 
//        $this->id_job     = (int)$__postInput[ 'id_job' ];
//        $this->password   = $__postInput[ 'password' ];

    }

    public function doAction() {

        $this->result[ 'code' ] = 1;
//		simulation valid for this analysis file: http://matecat.local/analyze/009-INDESIGN-Welcome_to_GDRIVE_mini.inx_en-US_de-DE.sdlxliff/15758-8ba30e8d72c9
        $this->result[ 'data' ] = '[{"id" : "19060-1", "price" : 20.12, "delivery_date": "2014-04-01 11:00"},{"id" : "19060-2", "price" : 30.12, "delivery_date": "2014-04-01 11:05"},{"id" : "19059-1", "price" : 10.54, "delivery_date": "2014-04-01 11:10"},{"id" : "19059-2", "price" : 38.15, "delivery_date": "2014-04-01 11:15"},{"id" : "19061-1", "price" : 50.72, "delivery_date": "2014-04-01 11:20"},{"id" : "19061-2", "price" : 8.40, "delivery_date": "2014-04-01 11:25"},{"id" : "19061-3", "price" : 3.42, "delivery_date": "2014-04-01 11:30"}]';

    }

}

?>
