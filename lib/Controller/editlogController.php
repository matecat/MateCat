<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;

/**
 * Description of catController
 *
 * @author antonio
 */
class editlogController extends viewController {

    public $project;
    private   $jid      = "";
    private   $password = "";
    private   $start_id;
    private   $sort_by;
    private   $thisUrl;


    public function __construct() {

        parent::__construct();
        parent::makeTemplate( "editlog.html" );

        $filterArgs = array(
                'jid'      => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'start'    => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'sortby'   => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )
        );

        $__postInput = filter_input_array( INPUT_GET, $filterArgs );

        $this->jid      = $__postInput[ "jid" ];
        $this->password = $__postInput[ "password" ];
        $this->start_id = $__postInput[ 'start' ];
        $this->sort_by  = $__postInput[ 'sortby' ];
        $this->thisUrl  = $_SERVER[ 'REQUEST_URI' ];

        $this->project    = Projects_ProjectDao::findByJobId( $this->jid );

        $this->featureSet->loadForProject( $this->project ) ;

    }

    public function doAction() {

        $this->featureSet->filter( 'beginDoAction', $this );

        $this->model = new EditLog_EditLogModel( $this->jid, $this->password, $this->featureSet );

        if ( isset( $this->start_id ) && !empty( $this->start_id ) ) {
            $this->model->setStartId( $this->start_id );
        }

        if ( isset( $this->sort_by ) && !empty( $this->sort_by ) ) {
            $this->model->setSortBy( $this->sort_by );
        }

        $this->model->controllerDoAction();

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->jid;
        $activity->id_project = $this->project->id;
        $activity->action     = ActivityLogStruct::ACCESS_EDITLOG_PAGE;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );
        
    }

    public function setTemplateVars() {
        //TODO: this could be put in the abstract viewController. It's generic. For the moments it replaces normal setTemplateVars
        $decorator = new EditLogDecorator( $this, $this->template );
        $decorator->decorate();
    }

    /**
     * @return string
     */
    public function getThisUrl() {
        return $this->thisUrl;
    }

}


