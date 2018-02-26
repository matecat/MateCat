<?php
use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;

/**
 * Description of catController
 *
 * @author antonio
 */
class editlogDownloadController extends downloadController {

    public function __construct() {

        $filterArgs = array(
                'jid'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_job    = $__postInput[ "jid" ];
        $this->password  = $__postInput[ "password" ];
        $this->setFilename( "Edit-log-export-" . $this->id_job . ".csv" );

    }

    public function doAction() {

        $this->model = new EditLog_EditLogModel( $this->id_job, $this->password );

        $this->outputContent = file_get_contents($this->model->genCSVTmpFile());

        /**
         * Retrieve user information
         */
        $this->checkLogin();

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->id_job;
        $activity->id_project = Projects_ProjectDao::findByJobId( $this->id_job, 60 * 60 )->id; //assume that all rows have the same project id
        $activity->action     = ActivityLogStruct::DOWNLOAD_EDIT_LOG;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );
        
    }

}

