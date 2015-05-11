<?php

class pauseResumeController extends ajaxController {

	private $id_project;
	private $act;

	public function __construct() {
		parent::__construct();

        $filterArgs = array(
                'pid' => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'act' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_project = (int)$__postInput[ 'pid' ];
        $this->act        = $__postInput[ 'act' ];

	}

	public function doAction() {

		if (empty($this->id_project)) {
			$this->result['errors'] = array(-1, "No id project provided");
			return -1;
		}
		$status = ( $this->act == 'cancel' )? 'CANCEL' : 'NEW';

		$res = changeProjectStatus($this->id_project,$status);

	}

}

?>
