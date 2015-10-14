<?php

set_time_limit( 180 );

class downloadFileController extends downloadController {

    protected $id_job;
    protected $password;
    protected $fname;
    protected $download_type;
    protected $jobInfo;
    protected $forceXliff;
    protected $downloadToken;

    const FILES_CHUNK_SIZE = 3;

    public function __construct() {

        $filterArgs = array(
                'filename'      => array(
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'id_file'       => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'id_job'        => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'download_type' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'password'      => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'downloadToken' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'forceXliff'    => array()
        );

        $__postInput = filter_var_array( $_REQUEST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->fname         = $__postInput[ 'filename' ];
        $this->id_file       = $__postInput[ 'id_file' ];
        $this->id_job        = $__postInput[ 'id_job' ];
        $this->download_type = $__postInput[ 'download_type' ];
        $this->password      = $__postInput[ 'password' ];
        $this->downloadToken = $__postInput[ 'downloadToken' ];


        $this->forceXliff = ( isset( $__postInput[ 'forceXliff' ] ) && !empty( $__postInput[ 'forceXliff' ] ) && $__postInput[ 'forceXliff' ] == 1 );

        if ( empty( $this->id_job ) ) {
            $this->id_job = "Unknown";
        }
    }

    public function doAction() {
        $debug              = array();
        $debug[ 'total' ][] = time();

        //get job language and data
        //Fixed Bug: need a specific job, because we need The target Language
        //Removed from within the foreach cycle, the job is always the same....
        $jobData = $this->jobInfo = getJobData( $this->id_job, $this->password );

        $pCheck = new AjaxPasswordCheck();

        //check for Password correctness
        if ( empty( $jobData ) || !$pCheck->grantJobAccessByJobData( $jobData, $this->password ) ) {
            $msg = "Error : wrong password provided for download \n\n " . var_export( $_POST, true ) . "\n";
            Log::doLog( $msg );
            Utils::sendErrMailReport( $msg );

            return null;
        }

        $debug[ 'get_file' ][] = time();

        //get storage object
        $fs        = new FilesStorage();
        $files_job = $fs->getFilesForJob( $this->id_job, $this->id_file );

        $debug[ 'get_file' ][] = time();
        $nonew                 = 0;
        $output_content        = array();
        $thereIsAZipFile       = false;
        /*
           the procedure:
           1)original xliff file is read directly from disk; a file handler is obtained
           2)the file is read chunk by chunk by a stream parser: for each trans-unit that is encountered, target is replaced (or added) with the corresponding translation obtained from the DB
           3)the parsed portion of xliff in the buffer is flushed on temporary file
           4)the temporary file is sent to the converter and an original file is obtained
           5)the temporary file is deleted
         */

        // Detect which type of converter was used to create this project, just
        // checking the XLIFF type of the project's first file.
        // Is it possible for a project to have some files converted with SDL
        // and others with the new MateCAT converter? No. Because all the files
        // of the new projects are converted with the new MateCAT converter,
        // while all the old projects were converted with SDL Trados Studio.
        // It's not possible to have a project with files converted using both
        // converters.
        $fileType = DetectProprietaryXliff::getInfo($files_job[0]['xliffFilePath']);
        if ($fileType[ 'proprietary_short_name' ] == 'matecat_converter') {
            $useLegacyConverters = false;
        } else {
            // Use SDL Trados Studio in case of SDLXLIFF and GlobalSight
            $useLegacyConverters = true;
        }

        //file array is chuncked. Each chunk will be used for a parallel conversion request.
        $files_job = array_chunk( $files_job, self::FILES_CHUNK_SIZE );
        foreach ( $files_job as $chunk ) {

            $converter = new FileFormatConverter($useLegacyConverters);

            $files_to_be_converted = array();

            foreach ( $chunk as $file ) {

                $mime_type        = $file[ 'mime_type' ];
                $fileID           = $file[ 'id_file' ];
                $current_filename = $file[ 'filename' ];

                //get path for the output file converted to know it's right extension
                $_fileName  = explode( DIRECTORY_SEPARATOR, $file[ 'xliffFilePath' ] );
                $outputPath = INIT::$TMP_DOWNLOAD . '/' . $this->id_job . '/' . $fileID . '/' . uniqid( '', true ) . "_.out." . array_pop( $_fileName );

                //make dir if doesn't exist
                if ( !file_exists( dirname( $outputPath ) ) ) {

                    Log::doLog( 'Create Directory ' . escapeshellarg( dirname( $outputPath ) ) . '' );
                    mkdir( dirname( $outputPath ), 0775, true );

                }

                $debug[ 'get_segments' ][] = time();
                $data                      = getSegmentsDownload( $this->id_job, $this->password, $fileID, $nonew );
                $debug[ 'get_segments' ][] = time();

                //prepare regexp for nest step
                $regexpEntity = '/&#x(0[0-8BCEF]|1[0-9A-F]|7F);/u';
                $regexpAscii  = '/([\x{00}-\x{1F}\x{7F}]{1})/u';

                foreach ( $data as $i => $k ) {
                    //create a secondary indexing mechanism on segments' array; this will be useful
                    //prepend a string so non-trans unit id ( ex: numerical ) are not overwritten
                    $data[ 'matecat|' . $k[ 'internal_id' ] ][] = $i;

                    //FIXME: temporary patch
                    $data[ $i ][ 'translation' ] = str_replace( '<x id="nbsp"/>', '&#xA0;', $data[ $i ][ 'translation' ] );
                    $data[ $i ][ 'segment' ]     = str_replace( '<x id="nbsp"/>', '&#xA0;', $data[ $i ][ 'segment' ] );

                    //remove binary chars in some xliff files
                    $sanitized_src = preg_replace( $regexpAscii, '', $data[ $i ][ 'segment' ] );
                    $sanitized_trg = preg_replace( $regexpAscii, '', $data[ $i ][ 'translation' ] );

                    //clean invalid xml entities ( charactes with ascii < 32 and different from 0A, 0D and 09
                    $sanitized_src = preg_replace( $regexpEntity, '', $sanitized_src );
                    $sanitized_trg = preg_replace( $regexpEntity, '', $sanitized_trg );
                    if ( $sanitized_src != null ) {
                        $data[ $i ][ 'segment' ] = $sanitized_src;
                    }
                    if ( $sanitized_trg != null ) {
                        $data[ $i ][ 'translation' ] = $sanitized_trg;
                    }

                }

                $debug[ 'replace' ][] = time();

                //instatiate parser
                $xsp = new SdlXliffSAXTranslationReplacer( $file[ 'xliffFilePath' ], $data, Langs_Languages::getInstance()->getLangRegionCode( $jobData[ 'target' ] ), $outputPath );

                if ( $this->download_type == 'omegat' ) {
                    $xsp->setSourceInTarget( true );
                }

                //run parsing
                Log::doLog( "work on " . $fileID . " " . $current_filename );
                $xsp->replaceTranslation();

                //free memory
                unset( $xsp );
                unset( $data );

                $debug[ 'replace' ][] = time();

                $output_content[ $fileID ][ 'document_content' ] = file_get_contents( $outputPath );
                $output_content[ $fileID ][ 'output_filename' ]  = $current_filename;

                if ( $this->forceXliff ) {
                    $file_info_details                              = FilesStorage::pathinfo_fix( $output_content[ $fileID ][ 'output_filename' ] );

                    //clean the output filename by removing
                    // the unique hash identifier 55e5739b467109.05614837_.out.Test_English.doc.sdlxliff
                    $output_content[ $fileID ][ 'output_filename' ] = preg_replace( '#[0-9a-f]+\.[0-9_]+\.out\.#i', '', FilesStorage::basename_fix( $outputPath ) );
                }

                /**
                 * Conversion Enforce
                 */
                $convertBackToOriginal = true;
                try {


                    //if it is a not converted file ( sdlxliff ) we have originalFile equals to xliffFile (it has just been copied)
                    $file[ 'original_file' ] = file_get_contents( $file[ 'originalFilePath' ] );

                    $fileType = DetectProprietaryXliff::getInfo($file[ 'xliffFilePath' ]);
                    // When the 'proprietary' flag is set to false, the xliff
                    // is not passed to any converter, because is handled
                    // directly inside MateCAT.
                    $xliffWasNotConverted = ($fileType['proprietary'] === false);

                    if ( !INIT::$CONVERSION_ENABLED || ( $file[ 'originalFilePath' ] == $file[ 'xliffFilePath' ] and $xliffWasNotConverted ) or $this->forceXliff ) {
                        $convertBackToOriginal = false;
                        Log::doLog( "SDLXLIFF: {$file['filename']} --- " . var_export( $convertBackToOriginal, true ) );
                    } else {
                        //TODO: dos2unix ??? why??
                        //force unix type files
                        Log::doLog( "NO SDLXLIFF, Conversion enforced: {$file['filename']} --- " . var_export( $convertBackToOriginal, true ) );
                    }


                } catch ( Exception $e ) {
                    Log::doLog( $e->getMessage() );
                }

                if ( $convertBackToOriginal ) {

                    $output_content[ $fileID ][ 'out_xliff_name' ] = $outputPath;
                    $output_content[ $fileID ][ 'source' ]         = $jobData[ 'source' ];
                    $output_content[ $fileID ][ 'target' ]         = $jobData[ 'target' ];

                    $files_to_be_converted [ $fileID ] = $output_content[ $fileID ];

                } elseif ( $this->forceXliff ) {

                    $this->cleanFilePath( $output_content[ $fileID ][ 'document_content' ] );

                }

            }

            $debug[ 'do_conversion' ][] = time();
            $convertResult              = $converter->multiConvertToOriginal( $files_to_be_converted, $chosen_machine = false );

            foreach ( array_keys( $files_to_be_converted ) as $fileID ) {

                $output_content[ $fileID ][ 'document_content' ] = $this->ifGlobalSightXliffRemoveTargetMarks( $convertResult[ $fileID ] [ 'document_content' ], $files_to_be_converted[ $fileID ][ 'output_filename' ] );

                //in case of .strings, they are required to be in UTF-16
                //get extension to perform file detection
                $extension = FilesStorage::pathinfo_fix( $output_content[ $fileID ][ 'output_filename' ], PATHINFO_EXTENSION );
                if ( strtoupper( $extension ) == 'STRINGS' ) {
                    //use this function to convert stuff
                    $encodingConvertedFile = CatUtils::convertEncoding( 'UTF-16', $output_content[ $fileID ][ 'document_content' ] );


                    //strip previously added BOM
                    $encodingConvertedFile[ 1 ] = $converter->stripBOM( $encodingConvertedFile[ 1 ], 16 );

                    //store new content
                    $output_content[ $fileID ][ 'document_content' ] = $encodingConvertedFile[ 1 ];

                    //trash temporary data
                    unset( $encodingConvertedFile );
                }


            }
            //            $output_content[ $fileID ][ 'document_content' ] = $convertResult[ 'document_content' ];
            unset( $convertResult );
            $debug[ 'do_conversion' ][] = time();
        }

        foreach ( $output_content as $idFile => $fileInformations ) {
            $zipPathInfo = ZipArchiveExtended::zipPathInfo( $output_content[ $idFile ][ 'output_filename' ] );
            if ( is_array( $zipPathInfo ) ) {
                $thereIsAZipFile                                = true;
                $output_content[ $idFile ][ 'zipfilename' ]     = $zipPathInfo[ 'zipfilename' ];
                $output_content[ $idFile ][ 'zipinternalPath' ] = $zipPathInfo[ 'dirname' ];
                $output_content[ $idFile ][ 'output_filename' ] = $zipPathInfo[ 'basename' ];
            }
        }

        //set the file Name
        $pathinfo        = FilesStorage::pathinfo_fix( $this->fname );
        $this->_filename = $pathinfo[ 'filename' ] . "_" . $jobData[ 'target' ] . "." . $pathinfo[ 'extension' ];

        //qui prodest to check download type?
        if ( $this->download_type == 'omegat' ) {

            $this->_filename .= ".zip";

            $tmsService = new TMSService();
            $tmsService->setOutputType( 'tm' );

            /**
             * @var $tmFile SplTempFileObject
             */
            $tmFile = $tmsService->exportJobAsTMX( $this->id_job, $this->password, $jobData[ 'source' ], $jobData[ 'target' ] );

            $tmsService->setOutputType( 'mt' );

            /**
             * @var $mtFile SplTempFileObject
             */
            $mtFile = $tmsService->exportJobAsTMX( $this->id_job, $this->password, $jobData[ 'source' ], $jobData[ 'target' ] );

            $tm_id                    = uniqid( 'tm' );
            $mt_id                    = uniqid( 'mt' );
            $output_content[ $tm_id ] = array(
                    'document_content' => '',
                    'output_filename'  => $pathinfo[ 'filename' ] . "_" . $jobData[ 'target' ] . "_TM . tmx"
            );

            foreach ( $tmFile as $lineNumber => $content ) {
                $output_content[ $tm_id ][ 'document_content' ] .= $content;
            }

            $output_content[ $mt_id ] = array(
                    'document_content' => '',
                    'output_filename'  => $pathinfo[ 'filename' ] . "_" . $jobData[ 'target' ] . "_MT . tmx"
            );

            foreach ( $mtFile as $lineNumber => $content ) {
                $output_content[ $mt_id ][ 'document_content' ] .= $content;
            }

            $this->createOmegaTZip( $output_content, $jobData[ 'source' ], $jobData[ 'target' ] ); //add zip archive content here;

        }
        else {

            try {

                $output_content = $this->getOutputContentsWithZipFiles( $output_content );

                if ( count( $output_content ) > 1 ) {

                    //cast $output_content elements to ZipContentObject
                    foreach ( $output_content as $key => $__output_content_elem ) {
                        $output_content[ $key ] = new ZipContentObject( $__output_content_elem );
                    }

                    if ( $pathinfo[ 'extension' ] != 'zip' ) {
                        if ( $this->forceXliff ) {
                            $this->_filename = $this->id_job . ".zip";
                        } else {
                            $this->_filename = $pathinfo[ 'basename' ] . ".zip";
                        }
                    }

                    $this->content = self::composeZip( $output_content ); //add zip archive content here;

                } else {
                    //always an array with 1 element, pop it, Ex: array( array() )
                    $output_content = array_pop( $output_content );
                    $this->setContent( $output_content );
                }
            }
            catch ( Exception $e ){

                $msg = "\n\n Error retrieving file content, Conversion failed??? \n\n Error: {$e->getMessage()} \n\n" . var_export( $e->getTraceAsString(), true );
                $msg .= "\n\n Request: " . var_export( $_REQUEST, true );
                Log::$fileName = 'fatal_errors.txt';
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
                $this->unlockToken(
                    array(
                            "code" => -110,
                            "message" => "Download failed. Please contact " . INIT::$SUPPORT_MAIL
                    )
                );
                throw $e; // avoid sent Headers and empty file content with finalize method

            }

        }

        $debug[ 'total' ][] = time();

        try {
            Utils::deleteDir( INIT::$TMP_DOWNLOAD . '/' . $this->id_job . '/' );
        }
        catch(Exception $e){
            Log::doLog('Failed to delete dir:'.$e->getMessage());
        }
    }

    /**
     * @param ZipContentObject $output_content
     *
     * @throws Exception
     */
    protected function setContent( ZipContentObject $output_content ) {

        $this->_filename = self::sanitizeFileExtension( $output_content->output_filename );
        $this->content   = $output_content->getContent();

    }

    protected function createOmegaTZip( $output_content, $sourceLang, $targetLang ) {
        $file = tempnam( "/tmp", "zipmatecat" );

        $zip = new ZipArchive();
        $zip->open( $file, ZipArchive::OVERWRITE );

        $zip_baseDir   = $this->jobInfo[ 'id' ] . "/";
        $zip_fileDir   = $zip_baseDir . "inbox/";
        $zip_tm_mt_Dir = $zip_baseDir . "tm/";

        $a[] = $zip->addEmptyDir( $zip_baseDir );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "glossary" );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "inbox" );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "omegat" );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "target" );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "terminology" );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "tm" );
        $a[] = $zip->addEmptyDir( $zip_baseDir . "tm/auto" );

        $rev_index_name = array();

        // Staff with content
        foreach ( $output_content as $key => $f ) {

            $f[ 'output_filename' ] = self::sanitizeFileExtension( $f[ 'output_filename' ] );

            //Php Zip bug, utf-8 not supported
            $fName = preg_replace( '/[^0-9a-zA-Z_\.\-]/u', "_", $f[ 'output_filename' ] );
            $fName = preg_replace( '/[_]{2,}/', "_", $fName );
            $fName = str_replace( '_.', ".", $fName );
            $fName = str_replace( '._', ".", $fName );
            $fName = str_replace( ".out.sdlxliff", ".sdlxliff", $fName );

            $nFinfo = FilesStorage::pathinfo_fix( $fName );
            $_name  = $nFinfo[ 'filename' ];
            if ( strlen( $_name ) < 3 ) {
                $fName = substr( uniqid(), -5 ) . "_" . $fName;
            }

            if ( array_key_exists( $fName, $rev_index_name ) ) {
                $fName = uniqid() . $fName;
            }

            $rev_index_name[ $fName ] = $fName;

            if ( substr( $key, 0, 2 ) == 'tm' || substr( $key, 0, 2 ) == 'mt' ) {
                $path = $zip_tm_mt_Dir;
            } else {
                $path = $zip_fileDir;
            }

            $zip->addFromString( $path . $fName, $f[ 'document_content' ] );

        }

        $zip_prjFile = $this->getOmegatProjectFile( $sourceLang, $targetLang );
        $zip->addFromString( $zip_baseDir . "omegat.project", $zip_prjFile );

        // Close and send to users
        $zip->close();
        $zip_content = file_get_contents( "$file" );
        unlink( $file );

        $this->content = $zip_content;
    }

    private function getOmegatProjectFile( $source, $target ) {
        $source           = strtoupper( $source );
        $target           = strtoupper( $target );
        $defaultTokenizer = "LuceneEnglishTokenizer";

        $omegatFile = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
			<omegat>
			<project version="1.0">
			<source_dir>inbox</source_dir>
			<source_dir_excludes>
			<mask>**/.svn/**</mask>
			<mask>**/CSV/**</mask>
			<mask>**/.cvs/**</mask>
			<mask>**/desktop.ini</mask>
			<mask>**/Thumbs.db</mask>
			</source_dir_excludes>
			<target_dir>__DEFAULT__</target_dir>
			<tm_dir>__DEFAULT__</tm_dir>
			<glossary_dir>terminology</glossary_dir>
			<glossary_file>terminology/new-glossary.txt</glossary_file>
			<dictionary_dir>__DEFAULT__</dictionary_dir>
			<source_lang>@@@SOURCE@@@</source_lang>
			<target_lang>@@@TARGET@@@</target_lang>
			<source_tok>org.omegat.tokenizer.@@@TOK_SOURCE@@@</source_tok>
			<target_tok>org.omegat.tokenizer.@@@TOK_TARGET@@@</target_tok>
			<sentence_seg>false</sentence_seg>
			<support_default_translations>true</support_default_translations>
			<remove_tags>false</remove_tags>
			</project>
			</omegat>';

        $omegatTokenizerMap = array(
                "AR" => "LuceneArabicTokenizer",
                "HY" => "LuceneArmenianTokenizer",
                "EU" => "LuceneBasqueTokenizer",
                "BG" => "LuceneBulgarianTokenizer",
                "CA" => "LuceneCatalanTokenizer",
                "ZH" => "LuceneSmartChineseTokenizer",
                "CZ" => "LuceneCzechTokenizer",
                "DK" => "LuceneDanishTokenizer",
                "NL" => "LuceneDutchTokenizer",
                "EN" => "LuceneEnglishTokenizer",
                "FI" => "LuceneFinnishTokenizer",
                "FR" => "LuceneFrenchTokenizer",
                "GL" => "LuceneGalicianTokenizer",
                "DE" => "LuceneGermanTokenizer",
                "GR" => "LuceneGreekTokenizer",
                "IN" => "LuceneHindiTokenizer",
                "HU" => "LuceneHungarianTokenizer",
                "ID" => "LuceneIndonesianTokenizer",
                "IE" => "LuceneIrishTokenizer",
                "IT" => "LuceneItalianTokenizer",
                "JA" => "LuceneJapaneseTokenizer",
                "KO" => "LuceneKoreanTokenizer",
                "LV" => "LuceneLatvianTokenizer",
                "NO" => "LuceneNorwegianTokenizer",
                "FA" => "LucenePersianTokenizer",
                "PT" => "LucenePortugueseTokenizer",
                "RO" => "LuceneRomanianTokenizer",
                "RU" => "LuceneRussianTokenizer",
                "ES" => "LuceneSpanishTokenizer",
                "SE" => "LuceneSwedishTokenizer",
                "TH" => "LuceneThaiTokenizer",
                "TR" => "LuceneTurkishTokenizer"

        );

        $source_lang     = substr( $source, 0, 2 );
        $target_lang     = substr( $target, 0, 2 );
        $sourceTokenizer = $omegatTokenizerMap[ $source_lang ];
        $targetTokenizer = $omegatTokenizerMap[ $target_lang ];

        if ( $sourceTokenizer == null ) {
            $sourceTokenizer = $defaultTokenizer;
        }
        if ( $targetTokenizer == null ) {
            $targetTokenizer = $defaultTokenizer;
        }

        return str_replace(
                array( "@@@SOURCE@@@", "@@@TARGET@@@", "@@@TOK_SOURCE@@@", "@@@TOK_TARGET@@@" ),
                array( $source, $target, $sourceTokenizer, $targetTokenizer ),
                $omegatFile );


    }

    public function cleanFilePath( &$documentContent ) {

        if ( !function_exists( '_clean' ) ) {
            function _clean( $file ) {
                $file_parts = explode( "\\", $file[ 2 ] );
                $file[ 0 ]  = str_replace( $file[ 2 ], array_pop( $file_parts ), $file[ 0 ] );

                return $file[ 0 ];
            }
        }

        //remove system confidential information
        $documentContent = preg_replace_callback( '|(<file [^>]*?original="([^>]*?)" [^>]*>)|si', '_clean', $documentContent );
        $documentContent = preg_replace_callback( '|(o-path="([^>]*?))"|si', '_clean', $documentContent );
        $documentContent = preg_replace_callback( '|(<value key="SDL:OriginalFilePath">([^<]*?)</value>)|si', '_clean', $documentContent );

    }

    /**
     * Remove the tag mrk if the file is an xlif and if the file is a globalsight file
     *
     * Also, check for encoding and transform utf16 to utf8 and back
     *
     * @param $documentContent
     * @param $path
     *
     * @return string
     */
    public function ifGlobalSightXliffRemoveTargetMarks( $documentContent, $path ) {

        $extension = FilesStorage::pathinfo_fix( $path );
        if ( !DetectProprietaryXliff::isXliffExtension( $extension ) ) {
            return $documentContent;
        }

        $is_utf8          = true;
        $original_charset = 'utf-8'; //not used, useful only to avoid IDE warning for not used variable

        //The file is UTF-16 Encoded
        if ( stripos( substr( $documentContent, 0, 100 ), "<?xml " ) === false ) {

            $is_utf8 = false;
            list( $original_charset, $documentContent ) = CatUtils::convertEncoding( 'UTF-8', $documentContent );

        }

        //avoid in memory copy of very large files if possible
        $detect_result = DetectProprietaryXliff::getInfoByStringData( substr( $documentContent, 0, 1024 ) );

        //clean mrk tags for GlobalSight application compatibility
        //this should be a sax parser instead of in memory copy for every trans-unit
        if ( $detect_result[ 'proprietary_short_name' ] == 'globalsight' ) {

            // Getting Trans-units
            $trans_units = explode( '<trans-unit', $documentContent );

            foreach ( $trans_units as $pos => $trans_unit ) {

                // First element in the XLIFF split is the header, not the first file
                if ( $pos > 0 ) {

                    //remove seg-source tags
                    $trans_unit = preg_replace( '|<seg-source.*?</seg-source>|si', '', $trans_unit );
                    //take the target content
                    $trans_unit = preg_replace( '#<mrk[^>]+>|</mrk>#si', '', $trans_unit );

                    $trans_units[ $pos ] = $trans_unit;

                }

            } // End of trans-units

            $documentContent = implode( '<trans-unit', $trans_units );

        }

        if ( !$is_utf8 ) {
            list( $__utf8, $documentContent ) = CatUtils::convertEncoding( $original_charset, $documentContent );
        }

        return $documentContent;

    }

    private function getOutputContentsWithZipFiles( $output_content ) {

        $zipFiles         = array();
        $newOutputContent = array();

        //group files by zip archive
        foreach ( $output_content as $idFile => $fileInformations ) {

            //If this file comes from a ZIP, add it to $zipFiles
            if ( isset( $fileInformations[ 'zipfilename' ] ) ) {
                $zipFileName = $fileInformations[ 'zipfilename' ];

                $zipFiles[ $zipFileName ][] = $fileInformations;
                unset( $output_content[ $idFile ] );

            }

        }

        unset( $idFile );
        unset( $fileInformations );

        //for each zip file index, compose zip again, save it to a temporary location and add it into output_content
        foreach ( $zipFiles as $zipFileName => $internalFile ) {

            foreach ( $internalFile as $__idx => $fileInformations ) {
                $zipFiles[ $zipFileName ][ $__idx ][ 'output_filename' ] = $fileInformations[ 'zipinternalPath' ] . DIRECTORY_SEPARATOR . $fileInformations[ 'output_filename' ];

                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'zipinternalPath' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'zipfilename' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'source' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'target' ] );
                unset( $zipFiles[ $zipFileName ][ $__idx ][ 'out_xliff_name' ] );
            }

            $internalFile = $zipFiles[ $zipFileName ];
            $internalFile = $this->getOutputContentsWithZipFiles( $internalFile );

            foreach ( $internalFile as $key => $iFile ) {
                $internalFile[ $key ] = new ZipContentObject( $iFile );
            }

            $zip = $this->reBuildZipContent( $zipFileName, $internalFile );

            $newOutputContent[] = new ZipContentObject( array(
                    'output_filename'  => $zipFileName,
                    'document_content' => null,
                    'input_filename'   => $zip,
            ) );
        }

        foreach ( $output_content as $idFile => $content ) {

            //this is true only for files that are not inside a zip ( normal uploaded files )
            if ( isset( $output_content[ $idFile ][ 'out_xliff_name' ] ) ) {
                //rename the key to make this compatible with ZipContentObject
                $output_content[ $idFile ][ 'input_filename' ] = $output_content[ $idFile ][ 'out_xliff_name' ];
                //remove the other invalid keys
                unset( $output_content[ $idFile ][ 'out_xliff_name' ] );
                unset( $output_content[ $idFile ][ 'source' ] );
                unset( $output_content[ $idFile ][ 'target' ] );

            }

            $output_content[ $idFile ] = new ZipContentObject( $output_content[ $idFile ] );

        }

        $newOutputContent = array_merge( $newOutputContent, $output_content );

        return $newOutputContent;
    }

    /**
     * @param $zipFileName
     * @param $internalFiles ZipContentObject[]
     *
     * @return string
     */
    public function reBuildZipContent( $zipFileName, $internalFiles ) {

        $fs      = new FilesStorage();
        $zipFile = $fs->getOriginalZipPath( $this->jobInfo[ 'create_date' ], $this->jobInfo[ 'id_project' ], $zipFileName );

        $tmpFName = tempnam( INIT::$TMP_DOWNLOAD . '/' . $this->id_job . '/', "ZIP" );
        copy( $zipFile, $tmpFName );

        $zip = new ZipArchiveExtended();
        if ( $zip->open( $tmpFName ) ) {


            $zip->createTree();

            //rebuild the real name of files in the zip archive
            foreach ( $zip->treeList as $filePath ) {

                $realPath = str_replace(
                        array(
                                ZipArchiveExtended::INTERNAL_SEPARATOR,
                                FilesStorage::pathinfo_fix( $tmpFName, PATHINFO_BASENAME )
                        ),
                        array( DIRECTORY_SEPARATOR, "" ),
                        $filePath );
                $realPath = ltrim( $realPath, "/" );

                //remove the tmx from the original zip ( we want not to be exported as preview )
                if( FilesStorage::pathinfo_fix( $realPath, PATHINFO_EXTENSION ) == 'tmx' ) {
                    $zip->deleteName( $realPath );
                    continue;
                }

                //fix the file names inside the zip file, so we compare with our files
                // and if matches we can substitute them with the converted ones
                $fileName_fixed = array_pop( explode( DIRECTORY_SEPARATOR, str_replace( " ", "_", $realPath ) ) );
                foreach ( $internalFiles as $index => $internalFile ) {
                    $__ourFileName = array_pop( explode( DIRECTORY_SEPARATOR, $internalFile->output_filename ) );
                    if( $__ourFileName == $fileName_fixed ) {
                        $zip->deleteName( $realPath );
                        if( FilesStorage::pathinfo_fix( $realPath, PATHINFO_EXTENSION ) == 'pdf' ) $realPath .= '.docx';
                        $zip->addFromString( $realPath, $internalFile->getContent() );
                    }
                }

            }

            $zip->close();

        }

        return $tmpFName;

    }

}
