<?php

set_time_limit(180);
include_once INIT::$MODEL_ROOT."/queries.php";
include_once INIT::$UTILS_ROOT."/CatUtils.php";
include_once INIT::$UTILS_ROOT."/FileFormatConverter.php";
include_once(INIT::$UTILS_ROOT.'/XliffSAXTranslationReplacer.class.php');
include_once(INIT::$UTILS_ROOT.'/DetectProprietaryXliff.php');


class downloadFileController extends downloadController {

    private $id_job;
    private $password;
    private $fname;
    private $download_type;

    protected $JobInfo;

    protected $downloadToken;

    public function __construct() {

        INIT::sessionClose();
        parent::__construct();
        $filterArgs = array(
            'filename'      => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'id_file'       => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'id_job'        => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'download_type' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'password'      => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'downloadToken' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->fname         = $__postInput[ 'filename' ];
        $this->id_file       = $__postInput[ 'id_file' ];
        $this->id_job        = $__postInput[ 'id_job' ];
        $this->download_type = $__postInput[ 'download_type' ];
        $this->password      = $__postInput[ 'password' ];
        $this->downloadToken = $__postInput[ 'downloadToken' ];

        $this->filename      = $this->fname;

        if (empty($this->id_job)) {
            $this->id_job = "Unknown";
        }
    }

    public function doAction() {
        $debug=array();
        $debug['total'][]=time();

        //get job language and data
        //Fixed Bug: need a specific job, because we need The target Language
        //Removed from within the foreach cycle, the job is always the same....
        $jobData = $this->JobInfo = getJobData( $this->id_job, $this->password );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if ( empty( $jobData ) || !$pCheck->grantJobAccessByJobData( $jobData, $this->password ) ) {
            $msg = "Error : wrong password provided for download \n\n " .  var_export( $_POST ,true )."\n";
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );
            return null;
        }

        $debug['get_file'][]=time();
        $files_job = getFilesForJob( $this->id_job, $this->id_file );
        $debug['get_file'][]=time();
        $nonew = 0;
        $output_content = array();

        //get job language and data
        //Fixed Bug: need a specific job, because we need The target Language
        //Removed from within the foreach cycle, the job is always the same....
        $jobData = $this->JobInfo = getJobData( $this->id_job, $this->password );

        /*
        the procedure is now as follows:
        1)original file is loaded from DB into RAM and the flushed in a temp file on disk; a file handler is obtained
        2)RAM gets freed from original content
        3)the file is read chunk by chunk by a stream parser: for each tran-unit that is encountered,
            target is replaced (or added) with the corresponding translation among segments
            the current string in the buffe is flushed on standard output
        4)the temporary file is deleted by another process after some time
        */

        foreach ($files_job as $file) {

            $mime_type        = $file[ 'mime_type' ];
            $id_file          = $file[ 'id_file' ];
            $current_filename = $file[ 'filename' ];
            $original_xliff   = $file[ 'xliff_file' ];

            //flush file on disk
//            $original_xliff = str_replace( "\r\n", "\n", $original_xliff ); //dos2unix ??? why??

            //get path
            $path = INIT::$TMP_DOWNLOAD . '/' . $this->id_job . '/' . $id_file . '/' . $current_filename . '.sdlxliff';

            //make dir if doesn't exist
            if ( !file_exists( dirname( $path ) ) ) {

                Log::doLog( 'exec ("chmod 666 ' . escapeshellarg( $path ) . '");' );
                mkdir( dirname( $path ), 0777, true );
                exec( "chmod 666 " . escapeshellarg( $path ) );

            }

            //create file
            $fp = fopen( $path, 'w+' );

            //flush file to disk
            fwrite( $fp, $original_xliff );

            //free memory, as we can work with file on disk now
            unset( $original_xliff );


            $debug[ 'get_segments' ][ ] = time();
            $data                       = getSegmentsDownload( $this->id_job, $this->password, $id_file, $nonew );
            $debug[ 'get_segments' ][ ] = time();

            //create a secondary indexing mechanism on segments' array; this will be useful
            //prepend a string so non-trans unit id ( ex: numerical ) are not overwritten
            foreach ( $data as $i => $k ) {
                $data[ 'matecat|' . $k[ 'internal_id' ] ][ ] = $i;
            }

            $debug['replace'][] = time();

            //instatiate parser
            $xsp = new XliffSAXTranslationReplacer( $path, $data, Languages::getInstance()->getLangRegionCode($jobData['target']), $fp );

            //run parsing
            $xsp->replaceTranslation();
            unset( $xsp );

            $debug[ 'replace' ][ ] = time();

            $output_xliff = file_get_contents( $path . '.out.sdlxliff' );

            $output_content[ $id_file ][ 'content' ]  = $output_xliff;
            $output_content[ $id_file ][ 'filename' ] = $current_filename;

            //TODO set a flag in database when file uploaded to know if this file is a proprietary xlf converted
            //TODO so we can load from database the original file blob ONLY when needed
            /**
             * Conversion Enforce
             *
             */
            $convertBackToOriginal = true;
            try {

                //if it is a not converted file ( sdlxliff ) we have an empty field original_file
                //so we can simplify all the logic with:
                // is empty original_file? if it is, we don't need conversion back because
                // we already have an sdlxliff or an accepted file
                $file['original_file'] = @gzinflate( $file['original_file'] );

                if( !INIT::$CONVERSION_ENABLED || empty( $file['original_file'] ) ){
                    $convertBackToOriginal = false;
                    Log::doLog( "SDLXLIFF: {$file['filename']} --- " . var_export( $convertBackToOriginal , true ) );
                }
                else {
                    //dos2unix ??? why??
                    //force unix type files
//                    $output_content[$id_file]['content'] = CatUtils::dos2unix( $output_content[$id_file]['content'] );
                    Log::doLog( "NO SDLXLIFF, Conversion enforced: {$file['filename']} --- " . var_export( $convertBackToOriginal , true ) );
                }


            } catch ( Exception $e ) { Log::doLog( $e->getMessage() ); }

            if ( $convertBackToOriginal ) {

                $output_content[$id_file]['out_xliff_name'] = $path.'.out.sdlxliff';
                $output_content[$id_file]['source'] = $jobData['source'];
                $output_content[$id_file]['target'] = $jobData['target'];

                // specs for filename at the task https://app.asana.com/0/1096066951381/2263196383117
                $converter = new FileFormatConverter();
                $debug[ 'do_conversion' ][ ] = time();
                $convertResult = $converter->convertToOriginal( $output_content[ $id_file ], $chosen_machine = false );
                $output_content[ $id_file ][ 'content' ] = $convertResult[ 'documentContent' ];
                $debug[ 'do_conversion' ][ ] = time();

            }

        }

        //set the file Name
        $pathinfo       = pathinfo( $this->fname );
        $this->filename = $pathinfo['filename']  . "_" . $jobData[ 'target' ] . "." . $pathinfo['extension'];

        //qui prodest to check download type?
//        if ( $this->download_type == 'all' && count( $output_content ) > 1 ) {
        if ( count( $output_content ) > 1 ) {

            if ( $pathinfo[ 'extension' ] != 'zip' ) {
                $this->filename = $pathinfo[ 'basename' ] . ".zip";
            }

            $this->composeZip( $output_content, $jobData[ 'source' ] ); //add zip archive content here;

        } else {
            //always an array with 1 element, pop it, Ex: array( array() )
            $output_content = array_pop( $output_content );
            $this->setContent( $output_content );
        }

        $debug[ 'total' ][ ] = time();

        unlink( $path );
        unlink( $path . '.out.sdlxliff' );
        rmdir( INIT::$TMP_DOWNLOAD . '/' . $this->id_job . '/' . $id_file . '/' );
        rmdir( INIT::$TMP_DOWNLOAD . '/' . $this->id_job . '/' );

    }

    private function setContent( $output_content ) {

        $this->filename = $this->sanitizeFileExtension( $output_content['filename'] );
        $this->content = $output_content['content'];

    }

    private function sanitizeFileExtension( $filename ){

        $pathinfo = pathinfo( $filename );

        if ( strtolower( $pathinfo[ 'extension' ] ) == 'pdf' ) {
            $filename = $pathinfo[ 'basename' ] . ".docx";
        }

        return $filename;

    }

    private function composeZip( $output_content, $sourceLang ) {

        $file = tempnam("/tmp", "zipmatecat");
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::OVERWRITE);

        // Staff with content
        foreach ($output_content as $f) {

            $f[ 'filename' ] = $this->sanitizeFileExtension( $f[ 'filename' ] );

            //Php Zip bug, utf-8 not supported
            $fName = preg_replace( '/[^0-9a-zA-Z_\.\-]/u', "_", $f['filename'] );
            $fName = preg_replace( '/[_]{2,}/', "_", $fName );
            $fName = str_replace( '_.', ".", $fName );

            $nFinfo = pathinfo($fName);
            $_name    = $nFinfo['filename'];
            if( strlen( $_name ) < 3 ){
                $fName = substr( uniqid(), -5 ) . "_" . $fName;
            }

            $zip->addFromString( $fName, $f['content'] );

        }

        // Close and send to users
        $zip->close();
        $zip_content = file_get_contents("$file");
        unlink($file);

        $this->content =  $zip_content;

    }

}
