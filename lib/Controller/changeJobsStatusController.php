<?php

class changeJobsStatusController extends ajaxController {

    private $res_type;
    private $res_id;
    private $new_status = Constants_JobStatus::STATUS_ACTIVE;
    private $password = "fake wrong password";
    private $only_if = false;

    public function __construct() {

        //SESSION START
        parent::__construct();
        parent::checkLogin();

        $filterArgs = array(
                'res'           => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'id'            => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'      => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'new_status'    => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'only_if'       => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'pn'            => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],

        );

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        ( !empty( $postInput[ 'password' ] ) ? $this->password = $postInput[ 'password' ] : null );

        $this->res_type   = $postInput[ 'res' ];
        $this->res_id     = $postInput[ 'id' ];

        if ( Constants_JobStatus::isAllowedStatus( $postInput[ 'new_status' ] ) ) {
            $this->new_status = $postInput[ 'new_status' ];
        } else {
            throw new Exception( "Invalid Status" );
        }

        if ( !empty( $postInput[ 'only_if' ] ) ) {

            if ( Constants_JobStatus::isAllowedStatus( $postInput[ 'only_if' ] ) ) {
                $this->only_if = $postInput[ 'only_if' ];
            } else {
                throw new Exception( "Invalid Status" );
            }

        }

    }


    public function doAction() {

        if ( ! $this->userIsLogged ) {
            throw new Exception( "User Not Logged." );
        }

        $team = Users_UserDao::findDefaultTeam( $this->logged_user ) ;

        if ( $this->res_type == "prj" ) {

            $pCheck = new AjaxPasswordCheck();
            $projectData = getProjectJobData( $this->res_id );
            $access = $pCheck->grantProjectAccess( $projectData, $this->password );

            //check for Password correctness
            if ( !$access ) {
                $msg = "Error : wrong password provided for Change Project Status \n\n " . var_export( $_POST, true ) . "\n";
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
                return null;
            }

            $strOld     = '';
            foreach ( $projectData as $item ) {
                $strOld .= $item[ 'id' ] . ':' . $item[ 'status_owner' ] . ',';
            }
            $strOld = trim( $strOld, ',' );

            $this->result[ 'old_status' ] = $strOld;

            updateJobsStatus( $this->res_type, $this->res_id, $this->new_status, $this->only_if );

            $projects = ManageUtils::queryProjects( $this->logged_user,
                1, 1, false, false,
                false, false, false, null,
                $team
            );

            $projnum = getProjectsNumber(
                $this->logged_user,
                false, false,
                false, false, false,
                $team
            );

            $this->result[ 'code' ]    = 1;
            $this->result[ 'data' ]    = "OK";
            $this->result[ 'status' ]  = $this->new_status;
            $this->result[ 'newItem' ] = $projects;
            $this->result[ 'page' ]    = 1;
            $this->result[ 'pnumber' ] = $projnum[ 0 ][ 'c' ];

        } else {

            updateJobsStatus( $this->res_type, $this->res_id, $this->new_status, $this->only_if, $this->password );

            $this->result[ 'code' ]   = 1;
            $this->result[ 'data' ]   = "OK";
            $this->result[ 'status' ] = $this->new_status;
        }
    }

}
