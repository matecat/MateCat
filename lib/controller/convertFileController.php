<?php

set_time_limit(0);

class convertFileController extends ajaxcontroller {

	public function __construct() {
		parent::__construct();

        $this->file_name = $this->get_from_get_post('file_name');
        $this->intDir = @INIT::$UPLOAD_REPOSITORY.'/' . $_COOKIE['upload_session'];

    }

	public function doAction() {
        $err = <<<EOE
            File conversion is not available in open source version.<br />
            But feel free to implement on your own.<br />
            In the meanwhile please, disable this functionality in config.inc.php
            <br />
            <br />
            self::\$CONVERSION_ENABLED = false;
EOE;

        $this->result['code'] = -100;
        $this->result['errors'][] = array("code" => -100, "message" => $err );

        @unlink( $this->intDir . '/' . $this->file_name );

    }

}
