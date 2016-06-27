<?php
use ActivityLog\Activity;
use ActivityLog\ActivityLogDao;
use ActivityLog\ActivityLogStruct;

/**
 * User: gremorian
 * Date: 11/05/15
 * Time: 20.37
 * 
 */

class downloadActivityLogController extends viewController {

    protected $_filename;
    protected $content;


    /**
     * @var int
     */
    protected $id_project;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $download_type;  // switch flag, for now not important

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
            'id_project'    => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'password'      => array(
                'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            ),
            'download_type' => array(
                'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
            )
        );

        $__postInput = filter_var_array( $_REQUEST, $filterArgs );

        $this->id_project    = $__postInput[ 'id_project' ];
        $this->password      = $__postInput[ 'password' ];
        $this->download_type = $__postInput[ 'download_type' ]; // switch flag, for now not important

    }


    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {

        $_project_data = getProjectJobData( $this->id_project );

        $pCheck = new AjaxPasswordCheck();
        $access = $pCheck->grantProjectAccess( $_project_data, $this->password );

        //check for Password correctness
        if ( !$access ) {
            $msg = "Error : wrong password provided for Activity Log download \n\n " . var_export( $_POST, true ) . "\n";
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );
            return null;
        }

        $activityLogDao = new ActivityLogDao();
        $rawContent  = $activityLogDao->read(
                new ActivityLogStruct(
                        array(
                                'id_project' => $this->id_project
                        )
                )
        );

        if ( empty( $rawContent ) ){
            $this->emptyActivity();
        }

        $jobKeys = array();
        foreach ( $_project_data as $val ){
            $jobKeys[ $val[ 'jid' ] ] = $val[ 'lang_pair' ];
        }

        $outputContent = array();
        foreach( $rawContent as $k => $value ){

            if( empty( $value->email ) ) {
                $value->first_name = "Anonymous";
                $value->last_name = "User";
                $value->email = "Unknown";
            }

            $outputContent[ $value->id_job . "-" . $jobKeys[ $value->id_job ] ][ ] =
                    $value->ip . " - [" . $value->event_date . "]: " .
                    $value->first_name . " " . $value->last_name .
                    " <" . $value->email . "> - " . ActivityLogStruct::getAction( $value->action );
        }

        $this->content = $this->composeZip( $_project_data[0][ 'pname' ], $outputContent );
        $this->_filename = $_project_data[0][ 'pname' ] . ".zip";

    }

    protected static function composeZip( $projectName , $outputContent ) {

        $fileName = tempnam( "/tmp", "zipmat" );
        $zip  = new ZipArchive();
        $zip->open( $fileName, ZipArchive::OVERWRITE );

        // Staff with content
        foreach ( $outputContent as $jobName => $activityLog ) {
            if( $jobName == "-" ){
                $zip->addFromString( "Project-" . $projectName . ".txt", implode( "\n", $activityLog ) );
            } else {
                $zip->addFromString( "Job-" . $jobName . ".txt", implode( "\n", $activityLog ) );
            }
        }

        // Close and send to users
        $zip->close();

        $fileContent = file_get_contents( $fileName );
        unlink( $fileName );

        return $fileContent;

    }

    public function setTemplateVars() {
        // TODO: Implement setTemplateVars() method.
    }

    public function emptyActivity(){
        parent::makeTemplate("activity_not_found.html");
        parent::finalize();
        die();
    }

    public function finalize() {
        try {

            $buffer = ob_get_contents();
            ob_get_clean();
            ob_start("ob_gzhandler");  // compress page before sending
            $this->nocache();
            header("Content-Type: application/force-download");
            header("Content-Type: application/octet-stream");
            header("Content-Type: application/download");
            header("Content-Disposition: attachment; filename=\"$this->_filename\""); // enclose file name in double quotes in order to avoid duplicate header error. Reference https://github.com/prior/prawnto/pull/16
            header("Expires: 0");
            header("Connection: close");
            echo $this->content;
            exit;

        } catch (Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "\n\n\n";
            echo "</pre>";
            exit;
        }
    }

}