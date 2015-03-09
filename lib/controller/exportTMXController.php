<?php

/**
 * Created by PhpStorm.
 * User: roberto <roberto@translated.net>
 * Date: 10/12/14
 * Time: 12.13
 */
include_once INIT::$MODEL_ROOT . "/queries.php";

class exportTMXController extends downloadController {

    private $jobID;
    private $jobPass;
    private $tmx;
    private $fileName;

    protected $errors;

    public function __construct() {
        $filterArgs = array(
                'jid'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'jpass' => array(
                        'filter'  => FILTER_SANITIZE_STRING,
                        'flags' => FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW
                )
        );

        $this->errors = array();

        $getInput = filter_input_array( INPUT_GET, $filterArgs );

        $this->jobID   = $getInput[ 'jid' ];
        $this->jobPass = $getInput[ 'jpass' ];

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
    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {
        if ( count( $this->errors ) > 0 ) {
            return null;
        }

        //get job language and data
        //Fixed Bug: need a specific job, because we need The target Language
        //Removed from within the foreach cycle, the job is always the same...
        $jobData = $this->jobInfo = getJobData( $this->jobID, $this->jobPass );

        $pCheck = new AjaxPasswordCheck();

        //check for Password correctness
        if ( empty( $jobData ) || !$pCheck->grantJobAccessByJobData( $jobData, $this->jobPass ) ) {
            $msg = "Error : wrong password provided for download \n\n " . var_export( $_POST, true ) . "\n";
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            return null;
        }

        $projectData = getProject( $jobData[ 'id_project' ] );

        $source = $jobData[ 'source' ];
        $target = $jobData[ 'target' ];

        $tmsService = new TMSService();

        /**
         * @var $tmx SplTempFileObject
         */
        $this->tmx = $tmsService->exportJobAsTMX( $this->jobID, $this->jobPass, $source, $target );

        $this->fileName = $projectData[0][ 'name' ] . "-" . $this->jobID . ".tmx";

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