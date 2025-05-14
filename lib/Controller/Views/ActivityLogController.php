<?php

namespace Views;

use AbstractControllers\BaseKleinViewController;
use AbstractControllers\IController;
use ActivityLog\ActivityLogDao;
use ActivityLog\ActivityLogStruct;
use ActivityLogDecorator;
use AjaxPasswordCheck;
use Log;
use Projects_ProjectDao;
use Utils;

/**
 * User: gremorian
 * Date: 11/05/15
 * Time: 20.37
 *
 */
class ActivityLogController extends BaseKleinViewController implements IController {

    /**
     * @var array
     */
    public $project_data;

    /**
     * @var array
     */
    public $jobLanguageDefinition = [];

    /**
     * @var array
     */
    public $rawLogContent = [];

    /**
     * Variable to check if data must be downloaded or showed as View
     * @var bool
     */
    public $download = false;

    /**
     * @var string
     */
    public $download_type;  // switch flag, for now not important

    /**
     * @var string download file name
     */
    public $_filename;

    /**
     * @var string content download
     */
    public $content;

    /**
     * @var ActivityLogDecorator
     */
    protected $decorator;

    /**
     * @var int
     */
    protected $id_project;

    /**
     * @var string
     */
    protected $password;

    public function renderView() {

        $this->checkLoginRequiredAndRedirect();

        $request = $this->validateTheRequest();

        $projectData = Projects_ProjectDao::getProjectAndJobData( $request[ 'id_project' ] );

    }

    protected function validateTheRequest() {

        $filterArgs = [
                'id_project' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'   => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'download'   => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        return filter_var_array( $this->request->paramsNamed()->all(), $filterArgs );

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {

        $this->project_data = Projects_ProjectDao::getProjectAndJobData( $this->id_project );

        $pCheck = new AjaxPasswordCheck();
        $access = $pCheck->grantProjectAccess( $this->project_data, $this->password );

        //check for Password correctness
        if ( !$access ) {
            $msg = "Error : wrong password provided for Activity Log download \n\n " . var_export( $_POST, true ) . "\n";
            Log::doJsonLog( $msg );
            Utils::sendErrMailReport( $msg );

            return null;
        }

        $activityLogDao      = new ActivityLogDao();
        $this->rawLogContent = $activityLogDao->read(
                new ActivityLogStruct(),
                [ 'id_project' => $this->id_project ]
        );

        //NO ACTIVITY DATA FOR THIS PROJECT
        if ( empty( $this->rawLogContent ) ) {
            $this->finalizeEmptyActivity();
        }

        foreach ( $this->project_data as $val ) {
            $this->jobLanguageDefinition[ $val[ 'jid' ] ] = $val[ 'lang_pair' ];
        }

        parent::makeTemplate( 'activity_log.html' );
    }

    public function setTemplateVars() {
        $this->decorator = new ActivityLogDecorator( $this, $this->template );
        $this->decorator->decorate();
        $this->intOauthClients();
    }

    public function finalizeEmptyActivity() {
        parent::makeTemplate( "activity_log_not_found.html" );
        $this->nocache();
        $this->template->projectID = $this->id_project;
        header( 'Content-Type: text/html; charset=utf-8' );
        echo $this->template->execute();
        exit;

    }

    public function finalizeDownload() {
        try {

            $buffer = ob_get_contents();
            ob_get_clean();
            ob_start( "ob_gzhandler" );  // compress page before sending
            $this->nocache();
            header( "Content-Type: application/force-download" );
            header( "Content-Type: application/octet-stream" );
            header( "Content-Type: application/download" );
            header( "Content-Disposition: attachment; filename=\"$this->_filename\"" ); // enclose file name in double quotes in order to avoid duplicate header error. Reference https://github.com/prior/prawnto/pull/16
            header( "Expires: 0" );
            header( "Connection: close" );
            echo $this->content;
            exit;

        } catch ( Exception $e ) {
            echo "<pre>";
            print_r( $e );
            echo "\n\n\n";
            echo "</pre>";
            exit;
        }
    }

}