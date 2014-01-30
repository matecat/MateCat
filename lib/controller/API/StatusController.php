<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 30/01/14
 * Time: 16.36
 * 
 */

class StatusController extends getVolumeAnalysisController {

    public function __construct() {

        $this->disableSessions();

        $filterArgs = array(
                'id_project' => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'ppassword'  => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_project = $__postInput[ 'id_project' ];
        $this->ppassword  = $__postInput[ 'ppassword' ];

    }

} 