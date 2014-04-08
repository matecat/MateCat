<?php

class outsourceToTranslated extends ajaxController {
	
	private $pid;

    public function __construct() {

        $this->disableSessions();
        parent::__construct();


        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->pid = (int)$__postInput[ 'pid' ];
//        $this->id_job     = (int)$__postInput[ 'id_job' ];
//        $this->password   = $__postInput[ 'password' ];

    }

    public function doAction() {

        $this->result[ 'code' ] = 1;
//        $this->result[ 'data' ] = '{"total" : 50}';


    }

}
