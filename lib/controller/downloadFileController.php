<?php

set_time_limit(180);
include_once INIT::$MODEL_ROOT."/queries.php";
include_once INIT::$UTILS_ROOT."/CatUtils.php";
include_once INIT::$UTILS_ROOT."/fileFormatConverter.class.php";
include_once(INIT::$UTILS_ROOT.'/XliffSAXTranslationReplacer.class.php');
include_once(INIT::$UTILS_ROOT.'/DetectProprietaryXliff.php');


class downloadFileController extends downloadController {

    private $id_job;
    private $password;
    private $fname;
    private $download_type;

    public function __construct() {
        parent::__construct();

        $this->fname = $this->get_from_get_post('filename');
        $this->id_file = $this->get_from_get_post('id_file');
        $this->id_job = $this->get_from_get_post('id_job');
        $this->download_type = $this->get_from_get_post('download_type');
        $this->filename = $this->fname;
        $this->password = $this->get_from_get_post("password");

        $this->download_type = $this->get_from_get_post("download_type");

        if (empty($this->id_job)) {
            $this->id_job = "Unknown";
        }
    }

    public function doAction() {
        $debug=array();
        $debug['total'][]=time();

        $debug['get_file'][]=time();
        $files_job = getFilesForJob($this->id_job, $this->id_file);
        $debug['get_file'][]=time();
        $nonew = 0;
        $output_content = array();

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
            $mime_type = $file['mime_type'];
            $id_file = $file['id_file'];
            $current_filename = $file['filename'];
            $original = $file['xliff_file'];

            //flush file on disk
            $original=str_replace("\r\n","\n",$original);
            //get path
            $path=INIT::$TMP_DOWNLOAD.'/'.$this->id_job.'/'.$id_file.'/'.$current_filename.'.sdlxliff';
            //make dir if doesn't exist
            if(!file_exists(dirname($path))){
                mkdir(dirname($path), 0777, true);
                exec ("chmod 666 $path");
            }
            //create file
            $fp=fopen($path,'w+');
            //flush file to disk
            fwrite($fp,$original);
            //free memory, as we can work with file on disk now
            unset($original);

            $debug['get_segments'][]=time();
            $data = getSegmentsDownload($this->id_job, $this->password, $id_file, $nonew);

            //get job language and data
            //Fixed Bug: need a specific job, because we need The target Language
            $jobData = getJobData( $this->id_job, $this->password );

            $debug['get_segments'][]=time();
            //create a secondary indexing mechanism on segments' array; this will be useful
            //prepend a string so non-trans unit id ( ex: numerical ) are not overwritten
            foreach($data as $i=>$k){
                $data[ 'matecat|' . $k['internal_id'] ][]=$i;
            }
            $transunit_translation = "";
            $debug['replace'][] = time();
            //instatiate parser
            $xsp = new XliffSAXTranslationReplacer( $path, $data, $jobData['target'] );
            //run parsing
            $xsp->replaceTranslation();
            unset($xsp);
            $debug['replace'][] = time();

            /*
               TEMPORARY HACK
               read header of file (guess first 500B) and detect if it was an old file created on VM TradosAPI (10.30.1.247)
               if so, point the conversion explicitly to this VM and not to the cloud, otherwise conversion will fail
             */
            //get first 500B
            $header_of_file_for_hack=file_get_contents($path.'.out.sdlxliff',false,NULL,-1,500);

            //extract file tag
            preg_match('/<file .*?>/s',$header_of_file_for_hack,$res_header_of_file_for_hack);

            //make it a regular tag
            $file_tag=$res_header_of_file_for_hack[0].'</file>';

            //objectify
            $tag=simplexml_load_string($file_tag);

            //get "original" attribute
            $original_uri=trim($tag['original']);

            $chosen_machine=false;
            if(strpos($original_uri,'C:\automation')!==FALSE){
                $chosen_machine='10.30.1.247';
                log::doLog('Old project detected, falling back to old VM');
            }

            unset($header_of_file_for_hack,$file_tag,$tag,$original_uri);
            /*
               END OF HACK
             */

            $original=file_get_contents($path.'.out.sdlxliff');

            $output_content[$id_file]['content'] = $original;
            $output_content[$id_file]['filename'] = $current_filename;

            //TODO set a flag in database when file uploaded to know if this file is a proprietary xlf converted
            //TODO so we can load from database the original file blob ONLY when needed
            /**
             * Conversion Enforce
             *
             * Check Extentions no more sufficient, we want check content
             * if this is an idiom xlf file type, conversion are enforced
             * $enforcedConversion = true; //( if conversion is enabled )
             *
             * dos2unix must be enabled for xliff forced conversions
             *
             */
            $enforcedConversion = false;
            try {

                $file['original_file'] = @gzinflate($file['original_file']);

                $fileType = DetectProprietaryXliff::getInfoByStringData( $file['original_file'] );
                //Log::doLog( 'Proprietary detection: ' . var_export( $fileType, true ) );

                if( $fileType['proprietary'] == true  ){

                    if( INIT::$CONVERSION_ENABLED && $fileType['proprietary_name'] == 'idiom world server' ){
                        $enforcedConversion = true;

                        //force unix type files
                        $output_content[$id_file]['content'] = CatUtils::dos2unix( $output_content[$id_file]['content'] );

                        Log::doLog( 'Idiom found, conversion Enforced: ' . var_export( $enforcedConversion, true ) );

                    } else {
                        /**
                         * Application misconfiguration.
                         * upload should not be happened, but if we are here, raise an error.
                         * @see upload.class.php
                         * */
                        Log::doLog( "Application misconfiguration. Upload should not be happened, but if we are here, raise an error." );
                        return;
                        //stop execution
                    }
                }
            } catch ( Exception $e ) { Log::doLog( $e->getMessage() ); }


            if (!in_array($mime_type, array("xliff", "sdlxliff", "xlf")) || $enforcedConversion ) {

                $output_content[$id_file]['out_xliff_name'] = $path.'.out.sdlxliff';
                $output_content[$id_file]['source'] = $jobData['source'];
                $output_content[$id_file]['target'] = $jobData['target'];

                // specs for filename at the task https://app.asana.com/0/1096066951381/2263196383117
                $converter = new fileFormatConverter();
                $debug[ 'do_conversion' ][ ] = time();
                $convertResult = $converter->convertToOriginal( $output_content[ $id_file ], $chosen_machine );
                $output_content[ $id_file ][ 'content' ] = $convertResult[ 'documentContent' ];
                $debug[ 'do_conversion' ][ ] = time();

            }

        }

        $ext = "";
        if ($this->download_type == 'all') {
            if (count($output_content) > 1) {
                $this->filename = $this->fname;
                $pathinfo = pathinfo($this->fname);
                if ($pathinfo['extension'] != 'zip') {
                    $this->filename = $pathinfo['basename'] . ".zip";
                }
                $this->content = $this->composeZip($output_content); //add zip archive content here;
            } elseif (count($output_content) == 1) {
                $this->setContent($output_content);
            }
        } else {
            $this->setContent($output_content);
        }
        $debug['total'][]=time();

        unlink($path);
        unlink($path.'.out.sdlxliff');
        rmdir(INIT::$TMP_DOWNLOAD.'/'.$this->id_job.'/'.$id_file.'/');
        rmdir(INIT::$TMP_DOWNLOAD.'/'.$this->id_job.'/');

    }

    private function setContent($output_content) {
        foreach ($output_content as $oc) {
            $pathinfo = pathinfo($oc['filename']);
            $ext = $pathinfo['extension'];
            $this->filename = $oc['filename'];

            if ($ext == 'pdf' or $ext == "PDF") {
                $this->filename = $pathinfo['basename'] . ".docx";
            }
            $this->content = $oc['content'];
        }
    }

    private function composeZip($output_content) {
        $file = tempnam("/tmp", "zipmatecat");
        $zip = new ZipArchive();
        $zip->open($file, ZipArchive::OVERWRITE);

        // Staff with content
        foreach ($output_content as $f) {
            $pathinfo = pathinfo($f['filename']);
            $ext = $pathinfo['extension'];
            if ($ext == 'pdf' or $ext == "PDF") {
                $f['filename'] = $pathinfo['basename'] . ".docx";
            }

            //Php Zip bug, utf-8 not supported
            $zip->addFromString( iconv( "UTF-8", 'ASCII//TRANSLIT//IGNORE', $f['filename'] ), $f['content']);
        }

        // Close and send to users
        $zip->close();
        $zip_content = file_get_contents("$file");
        unlink($file);
        return $zip_content;
    }

    private function convertToOriginalFormat() {

    }

}
