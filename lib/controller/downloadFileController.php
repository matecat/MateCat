<?php

set_time_limit( 180 );
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/CatUtils.php";
include_once INIT::$UTILS_ROOT . "/FileFormatConverter.php";
include_once( INIT::$UTILS_ROOT . '/XliffSAXTranslationReplacer.class.php' );
include_once( INIT::$UTILS_ROOT . '/DetectProprietaryXliff.php' );


class downloadFileController extends downloadController {

    /**
     * @var string
     */
    protected $id_job;

    /**
     * @var
     */
    protected $password;

    /**
     * @var
     */
    protected $fname;

    /**
     * @var
     */
    protected $download_type;

    /**
     * @var
     */
    protected $jobInfo;

    /**
     * @var bool
     */
    protected $forceXliff;

    /**
     * @var
     */
    protected $downloadToken;

    const FILES_CHUNK_SIZE = 3;

    public function __construct() {

        parent::__construct();
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

        $this->filename   = $this->fname;
        $this->forceXliff = ( isset( $__postInput[ 'forceXliff' ] ) && !empty( $__postInput[ 'forceXliff' ] ) && $__postInput[ 'forceXliff' ] == 1 );

        if ( empty( $this->id_job ) ) {
            $this->id_job = "Unknown";
        }
    }

    public function doAction() {
        $debug               = array();
        $debug[ 'total' ][ ] = time();

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

        $debug[ 'get_file' ][ ] = time();
        $files_job              = getFilesForJob( $this->id_job, $this->id_file );
        $debug[ 'get_file' ][ ] = time();
        $nonew                  = 0;
        $output_content         = array();

        /*
         * the procedure is now as follows:
         * 1)original file is loaded from DB into RAM and the flushed in a temp file on disk; a file handler is obtained
         * 2)RAM gets freed from original content
         * 3)the file is read chunk by chunk by a stream parser: for each tran-unit that is encountered,
         *     target is replaced (or added) with the corresponding translation among segments
         *     the current string in the buffer is flushed on standard output
         * 4)the temporary file is deleted by another process after some time
         *
         */

        //file array is chuncked. Each chunk will be used for a parallel conversion request.
        $files_job = array_chunk( $files_job, self::FILES_CHUNK_SIZE );
        foreach ( $files_job as $chunk ) {

            $converter = new FileFormatConverter();

            $files_buffer = array();

            foreach ( $chunk as $file ) {

                $mime_type        = $file[ 'mime_type' ];
                $fileID           = $file[ 'id_file' ];
                $current_filename = $file[ 'filename' ];
                $original_xliff   = $file[ 'xliff_file' ];

                //get path
                $path = INIT::$TMP_DOWNLOAD . '/' . $this->id_job . '/' . $fileID . '/' . $current_filename . "_" . uniqid( '', true ) .'.sdlxliff';

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
                $data                       = getSegmentsDownload( $this->id_job, $this->password, $fileID, $nonew );
                $debug[ 'get_segments' ][ ] = time();

                //create a secondary indexing mechanism on segments' array; this will be useful
                //prepend a string so non-trans unit id ( ex: numerical ) are not overwritten
                //clean also not valid xml entities ( charactes with ascii < 32 and different from 0A, 0D and 09
                $regexpEntity = '/&#x(0[0-8BCEF]|1[0-9A-F]|7F);/u';

                //remove binary chars in some xliff files
                $regexpAscii = '/([\x{00}-\x{1F}\x{7F}]{1})/u';

                foreach ( $data as $i => $k ) {
                    $data[ 'matecat|' . $k[ 'internal_id' ] ][ ] = $i;
                    //FIXME: temporary patch
                    $data[ $i ][ 'translation' ] = str_replace( '<x id="nbsp"/>', '&#xA0;', $data[ $i ][ 'translation' ] );
                    $data[ $i ][ 'segment' ]     = str_replace( '<x id="nbsp"/>', '&#xA0;', $data[ $i ][ 'segment' ] );

                    $sanitized_src = preg_replace( $regexpAscii, '', $data[ $i ][ 'segment' ] );
                    $sanitized_trg = preg_replace( $regexpAscii, '', $data[ $i ][ 'translation' ] );

                    $sanitized_src = preg_replace( $regexpEntity, '', $sanitized_src );
                    $sanitized_trg = preg_replace( $regexpEntity, '', $sanitized_trg );
                    if( $sanitized_src != null ){
                        $data[ $i ][ 'segment' ] = $sanitized_src;
                    }
                    if( $sanitized_trg != null ){
                        $data[ $i ][ 'translation' ] = $sanitized_trg;
                    }

                }

                $debug[ 'replace' ][ ] = time();

                //instatiate parser
                $xsp = new XliffSAXTranslationReplacer( $path, $data, Languages::getInstance()->getLangRegionCode( $jobData[ 'target' ] ), $fp );

                //run parsing
                Log::doLog( "work on " . $fileID . " " . $current_filename );
                $xsp->replaceTranslation();
                fclose( $fp );
                unset( $xsp );

                $debug[ 'replace' ][ ] = time();

                $output_xliff = file_get_contents( $path . '.out.sdlxliff' );

                $output_content[ $fileID ][ 'documentContent' ]  = $output_xliff;
                $output_content[ $fileID ][ 'filename' ] = $current_filename;
                unset( $output_xliff );

                if ( $this->forceXliff ) {
                    $file_info_details                        = pathinfo( $output_content[ $fileID ][ 'filename' ] );
                    $output_content[ $fileID ][ 'filename' ] = $file_info_details[ 'filename' ] . ".out.sdlxliff";
                }

                //TODO set a flag in database when file uploaded to know if this file is a proprietary xlf converted
                //TODO so we can load from database the original file blob ONLY when needed
                /**
                 * Conversion Enforce
                 */
                $convertBackToOriginal = true;
                try {

                    //if it is a not converted file ( sdlxliff ) we have an empty field original_file
                    //so we can simplify all the logic with:
                    // is empty original_file? if it is, we don't need conversion back because
                    // we already have an sdlxliff or an accepted file
                    $file[ 'original_file' ] = @gzinflate( $file[ 'original_file' ] );

                    if ( !INIT::$CONVERSION_ENABLED || ( empty( $file[ 'original_file' ] ) && $mime_type == 'sdlxliff' ) || $this->forceXliff ) {
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

                    $output_content[ $fileID ][ 'out_xliff_name' ] = $path . '.out.sdlxliff';
                    $output_content[ $fileID ][ 'source' ]         = $jobData[ 'source' ];
                    $output_content[ $fileID ][ 'target' ]         = $jobData[ 'target' ];

                    $files_buffer [ $fileID ] = $output_content[ $fileID ];

                }
            }

			$debug[ 'do_conversion' ][ ] = time();
			$convertResult               = $converter->multiConvertToOriginal( $files_buffer, $chosen_machine = false );

			foreach ( array_keys( $files_buffer ) as $fileID ) {

				$output_content[ $fileID ][ 'documentContent' ] = $this->removeTargetMarks( $convertResult[ $fileID ] [ 'documentContent' ], $files_buffer[ $fileID ][ 'filename' ] );

				//in case of .strings, they are required to be in UTF-16
				//get extension to perform file detection
				$extension   = pathinfo( $output_content[ $fileID ][ 'filename' ], PATHINFO_EXTENSION );
				if(strtoupper( $extension ) == 'STRINGS'){
					//use this function to convert stuff        
					$encodingConvertedFile=CatUtils::convertEncoding('UTF-16',$output_content[ $fileID ][ 'documentContent' ]);


					//strip previously added BOM
					$encodingConvertedFile[1]=$converter->stripBOM($encodingConvertedFile[1],16);

					//store new content
					$output_content[ $fileID ][ 'documentContent' ]=$encodingConvertedFile[1];

					//trash temporary data
					unset($encodingConvertedFile);
				}
			}

			//            $output_content[ $fileID ][ 'documentContent' ] = $convertResult[ 'documentContent' ];
			unset( $convertResult );
			$debug[ 'do_conversion' ][ ] = time();
		}

		//set the file Name
		$pathinfo       = pathinfo( $this->fname );
		$this->filename = $pathinfo[ 'filename' ] . "_" . $jobData[ 'target' ] . "." . $pathinfo[ 'extension' ];

		//qui prodest to check download type?
		if ( count( $output_content ) > 1 ) {

			if ( $pathinfo[ 'extension' ] != 'zip' ) {
				if ( $this->forceXliff ) {
					$this->filename = $this->id_job . ".zip";
				} else {
					$this->filename = $pathinfo[ 'basename' ] . ".zip";
				}
			}

			$this->composeZip( $output_content, $jobData[ 'source' ] ); //add zip archive content here;

		} else {
			//always an array with 1 element, pop it, Ex: array( array() )
			$output_content = array_pop( $output_content );
			$this->setContent( $output_content );
		}

		$debug[ 'total' ][ ] = time();

		Utils::deleteDir( INIT::$TMP_DOWNLOAD . '/' . $this->id_job . '/' );

	}

	protected function setContent( $output_content ) {

		$this->filename = $this->sanitizeFileExtension( $output_content[ 'filename' ] );
		$this->content  = $output_content[ 'documentContent' ];

	}

	protected function sanitizeFileExtension( $filename ) {

		$pathinfo = pathinfo( $filename );

		if ( strtolower( $pathinfo[ 'extension' ] ) == 'pdf' ) {
			$filename = $pathinfo[ 'basename' ] . ".docx";
		}

		return $filename;

	}

	protected function composeZip( $output_content, $sourceLang ) {

		$file = tempnam( "/tmp", "zipmatecat" );
		$zip  = new ZipArchive();
		$zip->open( $file, ZipArchive::OVERWRITE );

		$rev_index_name = array();

		// Staff with content
		foreach ( $output_content as $f ) {

			$f[ 'filename' ] = $this->sanitizeFileExtension( $f[ 'filename' ] );

			//Php Zip bug, utf-8 not supported
			$fName = preg_replace( '/[^0-9a-zA-Z_\.\-]/u', "_", $f[ 'filename' ] );
			$fName = preg_replace( '/[_]{2,}/', "_", $fName );
			$fName = str_replace( '_.', ".", $fName );

			$nFinfo = pathinfo( $fName );
			$_name  = $nFinfo[ 'filename' ];
			if ( strlen( $_name ) < 3 ) {
				$fName = substr( uniqid(), -5 ) . "_" . $fName;
			}

			if( array_key_exists( $fName, $rev_index_name ) ){
				$fName = uniqid() . $fName;
			}

			$rev_index_name[$fName] = $fName;

			$zip->addFromString( $fName, $f[ 'documentContent' ] );

		}

		// Close and send to users
		$zip->close();
		$zip_content = file_get_contents( "$file" );
		unlink( $file );

		$this->content = $zip_content;

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
	public function removeTargetMarks( $documentContent, $path ){

		$extension = pathinfo( $path );
		if ( !DetectProprietaryXliff::isXliffExtension( $extension ) ){
			return $documentContent;
		}

		$is_utf8 = true;
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
		if( $detect_result['proprietary_short_name'] == 'globalsight' ){

			// Getting Trans-units
			$trans_units = explode( '<trans-unit', $documentContent );

			foreach ($trans_units as $pos => $trans_unit) {

				// First element in the XLIFF split is the header, not the first file
				if ($pos > 0) {

					//remove seg-source tags
					$trans_unit = preg_replace('|<seg-source.*?</seg-source>|si', '', $trans_unit );
					//take the target content
					$trans_unit = preg_replace('#<mrk[^>]+>|</mrk>#si', '', $trans_unit );

					$trans_units[ $pos ] = $trans_unit;

				}

			} // End of trans-units

			$documentContent = implode('<trans-unit',$trans_units);

		}

		if( !$is_utf8 ){
			list( $__utf8, $documentContent ) = CatUtils::convertEncoding( $original_charset, $documentContent );
		}

		return $documentContent;

	}

}
