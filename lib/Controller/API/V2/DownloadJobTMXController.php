<?php

namespace Controller\API\V2;

use Controller\Abstracts\AbstractDownloadController;
use Exception;
use Model\ActivityLog\Activity;
use Model\ActivityLog\ActivityLogStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\ChunkDao;
use SplTempFileObject;
use Utils\TMS\TMSService;
use Utils\Tools\Utils;

class DownloadJobTMXController extends AbstractDownloadController {

    private $jobID;
    private $tmx;
    private $fileName;

    protected $errors;

    public $jobInfo;

    /**
     * @throws Exception
     */
    public function index() {
        $getInput = filter_var_array( $this->request->params(), [
                'id_job'   => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password' => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ],
                'type'     => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                ]
        ] );

        $this->errors = [];

        $this->jobID = $getInput[ 'id_job' ];
        $jobPass     = $getInput[ 'password' ];
        $type = $getInput[ 'type' ];

        if ( empty( $this->jobID ) ) {
            $this->errors [] = [
                    'code'    => -1,
                    'message' => 'Job ID missing'
            ];
        }

        if ( empty( $jobPass ) ) {
            $this->errors [] = [
                    'code'    => -2,
                    'message' => 'Job password missing'
            ];
        }

        $this->featureSet = new FeatureSet();

        if ( count( $this->errors ) > 0 ) {
            $this->response->status()->setCode( 500 );
            $this->response->json( $this->errors );

            exit();
        }

        //get job language and data
        //Fixed Bug: need a specific job, because we need The target Language
        //Removed from within the foreach cycle, the job is always the same...
        $jobData = $this->jobInfo = ChunkDao::getByIdAndPassword( $this->jobID, $jobPass );
        $this->featureSet->loadForProject( $this->jobInfo->getProject() );

        $projectData = $this->jobInfo->getProject();

        $source = $jobData[ 'source' ];
        $target = $jobData[ 'target' ];

        $tmsService = new TMSService( $this->featureSet );

        switch ( $type ) {
            case 'csv':
                /**
                 * @var $tmx SplTempFileObject
                 */
                $this->tmx      = $tmsService->exportJobAsCSV( $this->jobID, $jobPass, $source, $target );
                $this->fileName = $projectData[ 'name' ] . "-" . $this->jobID . ".csv";
                break;
            default:
                /**
                 * @var $tmx SplTempFileObject
                 */
                $this->tmx      = $tmsService->exportJobAsTMX( $this->jobID, $jobPass, $source, $target );
                $this->fileName = $projectData[ 'name' ] . "-" . $this->jobID . ".tmx";
                break;
        }

        $this->_saveActivity();
        $this->finalize();
    }

    protected function _saveActivity() {

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->jobID;
        $activity->id_project = $this->jobInfo[ 'id_project' ];
        $activity->action     = ActivityLogStruct::DOWNLOAD_JOB_TMX;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

    }

    /**
     * @Override
     */
    public function finalize( bool $forceXliff = false ) {

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
