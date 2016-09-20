<?php

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;

set_time_limit( 180 );

class downloadOriginalController extends downloadController {

    protected $id_job;
    private $password;
    private $fname;
    private $download_type;
    private $id_file;
    private $id_project;

    public function __construct() {

        $filterArgs = array(
                'filename'      => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW
                ),
                'id_file'       => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'id_job'        => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'download_type' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'password'      => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )
        );

        $__postInput = filter_var_array( $_REQUEST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->_user_provided_filename         = $__postInput[ 'filename' ];
        $this->id_file       = $__postInput[ 'id_file' ];
        $this->id_job        = $__postInput[ 'id_job' ];
        $this->download_type = $__postInput[ 'download_type' ];
        $this->password      = $__postInput[ 'password' ];

    }

    public function doAction() {

        //get storage object
        $fs        = new FilesStorage();
        $files_job = $fs->getOriginalFilesForJob( $this->id_job, $this->id_file, $this->password );

        //take the project ID and creation date, array index zero is good, all id are equals
        $this->id_project   = $files_job[0]['id_project'];

        $this->project = Projects_ProjectDao::findById( $this->id_project );

        $output_content  = array();

        foreach ( $files_job as $file ) {

            $id_file = $file[ 'id_file' ];

            $zipPathInfo = ZipArchiveExtended::zipPathInfo( $file[ 'filename' ] );

            if ( is_array( $zipPathInfo ) ) {
                $output_content[ $id_file ][ 'output_filename' ] = $zipPathInfo[ 'zipfilename' ];
                $output_content[ $id_file ][ 'input_filename' ]  = $fs->getOriginalZipPath( $this->project->create_date, $this->id_project, $zipPathInfo[ 'zipfilename' ] );
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

        if ( $this->download_type == 'all' ) {

            if ( count( $output_content ) > 1 ) {

                $this->_filename = $this->_getDefaultFileName( $this->project );
                $pathInfo        = FilesStorage::pathinfo_fix( $this->_filename );

                if ( $pathInfo[ 'extension' ] != 'zip' ) {
                    $this->_filename = $pathInfo[ 'basename' ] . ".zip";
                }

                $this->content = self::composeZip( $output_content,null,true ); //add zip archive content here;

            } elseif ( count( $output_content ) == 1 ) {
                $this->setContent( $output_content );
            }

        } else {

            $this->setContent( $output_content );

        }

        /**
         * Retrieve user information
         */
        $this->checkLogin();

        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->id_job;
        $activity->id_project = $this->id_project;
        $activity->action     = ActivityLogStruct::DOWNLOAD_ORIGINAL;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );

    }

    /**
     * There is a foreach, but this should be always one element
     *
     * @param $output_content ZipContentObject[]
     */
    private function setContent( $output_content ) {
        foreach ( $output_content as $oc ) {
            $this->_filename = $oc->output_filename;
            $this->content   = $oc->getContent();
        }
    }

}
