<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use LQA\ChunkReviewDao;

set_time_limit( 180 );

class downloadOriginalController extends downloadController {

    public function __construct() {

        $filterArgs = [
                'filename'      => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW
                ],
                'id_file'       => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_job'        => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'download_type' => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
                'password'      => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ]
        ];

        $__postInput = filter_var_array( $_REQUEST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->_user_provided_filename = $__postInput[ 'filename' ];
        $this->id_job                  = $__postInput[ 'id_job' ];
        $this->password                = $__postInput[ 'password' ];
    }

    /**
     * @throws Exception
     */
    public function doAction() {

        // get Job Info, we need only a row of jobs ( split )
        $jobData = Jobs_JobDao::getByIdAndPassword( (int)$this->id_job, $this->password );

        // if no job was found, check if the provided password is a password_review
        if ( empty( $jobData ) ) {
            $chunkReviewStruct = ChunkReviewDao::findByReviewPasswordAndJobId( $this->password, (int)$this->id_job );
            $jobData           = $chunkReviewStruct->getChunk();
        }

        // check for Password correctness
        if ( empty( $jobData ) ) {
            $msg = "Error : wrong password provided for download \n\n " . var_export( $_POST, true ) . "\n";
            Log::doJsonLog( $msg );
            Utils::sendErrMailReport( $msg );

            return null;
        }

        //get storage object
        $fs        = FilesStorageFactory::create();
        $files_job = $fs->getFilesForJob( $this->id_job, false );

        //take the project ID and creation date, array index zero is good, all id are equals
        $id_project = $files_job[ 0 ][ 'id_project' ];

        $this->project = Projects_ProjectDao::findById( $id_project );

        $output_content = [];

        foreach ( $files_job as $file ) {

            $id_file = $file[ 'id_file' ];

            $zipPathInfo = ZipArchiveExtended::zipPathInfo( $file[ 'filename' ] );

            if ( is_array( $zipPathInfo ) ) {
                $output_content[ $id_file ][ 'output_filename' ] = $zipPathInfo[ 'zipfilename' ];
                $output_content[ $id_file ][ 'input_filename' ]  = $fs->getOriginalZipPath( $this->project->create_date, $id_project, $zipPathInfo[ 'zipfilename' ] );
            } else {
                $output_content[ $id_file ][ 'output_filename' ] = $file[ 'filename' ];
                $output_content[ $id_file ][ 'input_filename' ]  = $file[ 'originalFilePath' ];
            }

        }

        /*
         * get Unique file zip because there are more than one file in the zip
         * array_unique compares items using a string comparison.
         *
         * From the docs:
         * Note: Two elements are considered equal if and only if (string) $elem1 === (string) $elem2.
         * In words: when the string representation is the same. The first element will be used.
         */
        $output_content = array_map( 'unserialize', array_unique( array_map( 'serialize', $output_content ) ) );

        foreach ( $output_content as $key => $iFile ) {
            $output_content[ $key ] = new ZipContentObject( $iFile );
        }


        if ( count( $output_content ) > 1 ) {

            $this->setFilename( $this->getDefaultFileName( $this->project ) );
            $pathInfo = AbstractFilesStorage::pathinfo_fix( $this->_filename );

            if ( $pathInfo[ 'extension' ] != 'zip' ) {
                $this->setFilename( $pathInfo[ 'basename' ] . ".zip" );
            }

            $this->outputContent = self::composeZip( $output_content, null, true ); //add zip archive content here;
            $this->setMimeType();

        } elseif ( count( $output_content ) == 1 ) {
            $oContent = array_pop( $output_content );
            $this->setFilename( $oContent->output_filename );
            $this->setOutputContent( $oContent );
            $this->setMimeType();
        }


        /**
         * Retrieve user information
         */
        $this->readLoginInfo();

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->id_job;
        $activity->id_project = $id_project;
        $activity->action     = ActivityLogStruct::DOWNLOAD_ORIGINAL;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

    }

}
