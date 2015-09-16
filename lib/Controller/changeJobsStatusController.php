<?php

include_once INIT::$UTILS_ROOT . "/manage.class.php";

class changeJobsStatusController extends ajaxController {

    private $res_type;
    private $res_id;
    private $new_status;
    private $undo;

    public function __construct() {

        //SESSION START
        parent::sessionStart();
        parent::__construct();

        $filterArgs = array(
            'res'           => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'id'            => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
//            'project'       => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'     => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'new_status'    => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'status'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'page'          => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'step'          => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'only_if'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'undo'          => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
            'pn'            => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
            'source'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'target'        => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            //'filter'        => array( 'filter' => FILTER_VALIDATE_BOOLEAN ), // can be omitted in sanitization
            'onlycompleted' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),

        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );


        $this->res_type   = $__postInput[ 'res' ];
        $this->res_id     = $__postInput[ 'id' ];
        $this->new_status = $__postInput[ 'new_status' ];
        $this->undo       = $__postInput[ 'undo' ];

        // parameters to select the first item of the next page, to return
        if ( isset( $_POST[ 'page' ] ) ) {
            $this->page = ( $_POST[ 'page' ] == '' ) ? 1 : $__postInput[ 'page' ];
        } else {
            $this->page = 1;
        };

        if ( isset( $_POST[ 'step' ] ) ) {
            $this->step = $__postInput[ 'step' ];
        } else {
            $this->step = 100;
        };

//        if ( isset( $_POST[ 'project' ] ) ) {
//            $this->project_id = $__postInput[ 'project' ];
//        } else {
//            $this->project_id = false;
//        };

        if ( isset( $_POST[ 'password' ] ) ) {
            $this->job_password = $__postInput[ 'password' ];
        } else {
            $this->job_password = null;
        };

        if ( isset( $_POST[ 'only_if' ] ) ) {
            $this->only_if = $__postInput[ 'only_if' ];
        } else {
            $this->only_if = false;
        };

        if ( isset( $_POST[ 'filter' ] ) ) {
            $this->filter_enabled = true;
        } else {
            $this->filter_enabled = false;
        };

        if ( isset( $_POST[ 'pn' ] ) ) {
            $this->search_in_pname = $__postInput[ 'pn' ];
        } else {
            $this->search_in_pname = false;
        };

        if ( isset( $_POST[ 'source' ] ) ) {
            $this->search_source = $__postInput[ 'source' ];
        } else {
            $this->search_source = false;
        };

        if ( isset( $_POST[ 'target' ] ) ) {
            $this->search_target = $__postInput[ 'target' ];
        } else {
            $this->search_target = false;
        };

        if ( isset( $_POST[ 'status' ] ) ) {
            $this->search_status = $__postInput[ 'status' ];
        } else {
            $this->search_status = Constants_JobStatus::STATUS_ACTIVE;
        };

        if ( isset( $_POST[ 'onlycompleted' ] ) ) {
            $this->search_onlycompleted = $__postInput[ 'onlycompleted' ];
        } else {
            $this->search_onlycompleted = false;
        }
    }

    public function doAction() {

        if( empty( $_SESSION['cid'] ) ){
            //user not logged
            throw new Exception( "User Not Logged." );
        }

        if ( $this->res_type == "prj" ) {
            $old_status = getProjectJobData( $this->res_id );
            $strOld     = '';
            foreach ( $old_status as $item ) {
                $strOld .= $item[ 'id' ] . ':' . $item[ 'status_owner' ] . ',';
            }
            $strOld = trim( $strOld, ',' );

            $this->result[ 'old_status' ] = $strOld;

            updateJobsStatus( $this->res_type, $this->res_id, $this->new_status, $this->only_if, $this->undo );

            $start = ( ( $this->page - 1 ) * $this->step ) + $this->step - 1;

            $projects = ManageUtils::queryProjects( $start, 1, $this->search_in_pname, $this->search_source, $this->search_target, $this->search_status, $this->search_onlycompleted, $this->filter_enabled, null );

            $projnum = getProjectsNumber( $start, $this->step, $this->search_in_pname, $this->search_source, $this->search_target, $this->search_status, $this->search_onlycompleted, $this->filter_enabled );

            $this->result[ 'code' ]    = 1;
            $this->result[ 'data' ]    = "OK";
            $this->result[ 'status' ]  = $this->new_status;
            $this->result[ 'newItem' ] = $projects;
            $this->result[ 'page' ]    = $this->page;
            $this->result[ 'pnumber' ] = $projnum[ 0 ][ 'c' ];

        } else {

            updateJobsStatus( $this->res_type, $this->res_id, $this->new_status, $this->only_if, $this->undo, $this->job_password );

            $this->result[ 'code' ]   = 1;
            $this->result[ 'data' ]   = "OK";
            $this->result[ 'status' ] = $this->new_status;
        }
    }

}

?>