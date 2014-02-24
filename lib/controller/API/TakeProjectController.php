<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 20/02/14
 * Time: 12.27
 * 
 */

class TakeProjectController extends ajaxController {

    protected $api_output = array(
            'status' => 'OK'
    );

    public function __construct() {

        $this->disableSessions();

        $filterArgs = array(
                'id_project'    => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'project_pass'  => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
                'owner_email'   => array( 'filter' => FILTER_VALIDATE_EMAIL ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_project   = $__postInput[ 'id_project' ];
        $this->ppassword    = $__postInput[ 'project_pass' ];
        $this->owner_email  = $__postInput[ 'owner_email' ];

    }

    public function finalize() {
        $toJson = json_encode( $this->api_output );
        echo $toJson;
    }

    /**
     * This method does not offer debug when an user email is not found or it is wrong
     * It respond always OK
     *
     * Avoid brute forcing to find other user emails
     *
     */
    public function  doAction() {

        $user = getUserData( $this->owner_email );

        if( empty( $user ) ){
            $msg = "\n *** ERROR TakeProjectController Email not found \n
            - id project            : " . $this->id_project . "
            - project pass          : " . $this->ppassword . "
            - email                 : " . $this->owner_email ."\n";
            Log::doLog( $msg );

            Utils::sendErrMailReport( $msg );

            return; //No Debug we said OK. Avoid brute forcing to find other user emails
        }

        $project = getProjectJobData( $this->id_project );

        $pCheck = new AjaxPasswordCheck();
        if( empty( $project ) || !$pCheck->grantProjectAccess( $project, $this->ppassword ) ){

            $msg = "\n *** ERROR TakeProjectController Project not found \n
            - id project            : " . $this->id_project . "
            - project pass          : " . $this->ppassword . "
            - email                 : " . $this->owner_email ."\n";
            Log::doLog( $msg );

            Utils::sendErrMailReport( $msg );

            $this->api_output = array( 'status' => 'FAIL', 'message' => 'Project not found' );
            return;
        }

        foreach( $project as $value ){

            $res = updateJobOwner( $value['jid'], $this->owner_email );

            $msg = "\n TakeProjectController updateJobOwner \n
            - JOB ID                : " . $value['jid'] . "
            - RESULT                : " . var_export( $res, true ) . "

            - id project            : " . $this->id_project . "
            - project pass          : " . $this->ppassword . "
            - email                 : " . $this->owner_email ."\n";
            Log::doLog( $msg );

        }

    }

} 