<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use TMS\TMSService;

/**
 * Created by PhpStorm.
 * User: roberto <roberto@translated.net>
 * Date: 10/12/14
 * Time: 12.13
 */

class exportTMXController extends downloadController {

    private $jobID;
    private $jobPass;
    private $tmx;
    private $type;
    private $fileName;

    protected $errors;

    public $jobInfo;

    public function __construct() {
        $filterArgs = array(
                'jid'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'jpass' => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ),
                'type'  => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                )
        );

        $this->errors = array();

        $getInput = filter_input_array( INPUT_GET, $filterArgs );

        $this->jobID   = $getInput[ 'jid' ];
        $this->jobPass = $getInput[ 'jpass' ];
        $this->type    = $getInput[ 'type' ];

        if ( $this->jobID == null || empty( $this->jobID ) ) {
            $this->errors [ ] = array(
                    'code'    => -1,
                    'message' => 'Job ID missing'
            );
        }

        if ( $this->jobPass == null || empty( $this->jobPass ) ) {
            $this->errors [ ] = array(
                    'code'    => -2,
                    'message' => 'Job password missing'
            );
        }

        $this->featureSet = new FeatureSet();

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     * @throws \Exceptions\NotFoundException
     */
    function doAction() {

        if ( count( $this->errors ) > 0 ) {
            return null;
        }

        //get job language and data
        //Fixed Bug: need a specific job, because we need The target Language
        //Removed from within the foreach cycle, the job is always the same...
        $jobData = $this->jobInfo = Chunks_ChunkDao::getByIdAndPassword( $this->jobID, $this->jobPass );
        $this->featureSet->loadForProject( $this->jobInfo->getProject() );

        $projectData = $this->jobInfo->getProject();

        $source = $jobData[ 'source' ];
        $target = $jobData[ 'target' ];

        $tmsService = new TMSService( $this->featureSet );

        switch( $this->type ){
            case 'csv':
                /**
                 * @var $tmx SplTempFileObject
                 */
                $this->tmx = $tmsService->exportJobAsCSV( $this->jobID, $this->jobPass, $source, $target );
                $this->fileName = $projectData[ 'name' ] . "-" . $this->jobID . ".csv";
                break;
            default:
                /**
                 * @var $tmx SplTempFileObject
                 */
                $this->tmx = $tmsService->exportJobAsTMX( $this->jobID, $this->jobPass, $source, $target );
                $this->fileName = $projectData[ 'name' ] . "-" . $this->jobID . ".tmx";
                break;
        }

        $this->_saveActivity();

    }

    protected function _saveActivity(){

        /**
         * Retrieve user information
         */
        $this->readLoginInfo();

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->jobID;
        $activity->id_project = $this->jobInfo['id_project'];
        $activity->action     = ActivityLogStruct::DOWNLOAD_JOB_TMX;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

    }

    /**
     * @Override
     */
    public function finalize() {

        $buffer = ob_get_contents();
        ob_get_clean();
        ob_start( "ob_gzhandler" );  // compress page before sending
        $this->nocache();
        header( "Content-Type: application/force-download" );
        header( "Content-Type: application/octet-stream" );
        header( "Content-Type: application/download" );

        // Enclose file name in double quotes in order to avoid duplicate header error.
        // Reference https://github.com/prior/prawnto/pull/16
        header( "Content-Disposition: attachment; filename=\"$this->fileName\"" );
        header( "Expires: 0" );
        header( "Connection: close" );

        //read file and output it
        foreach ( $this->tmx as $line ) {
            echo $line;
        }

        exit;
    }

}