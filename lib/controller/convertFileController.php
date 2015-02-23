<?php

set_time_limit( 0 );

include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/CatUtils.php";
include_once INIT::$UTILS_ROOT . "/FileFormatConverter.php";

class convertFileController extends ajaxController {

	protected $file_name;
	protected $source_lang;
	protected $target_lang;
	protected $segmentation_rule;

	protected $cache_days = 10;

	protected $intDir;
	protected $errDir;

	public function __construct() {

		parent::__construct();

		$filterArgs = array(
				'file_name'         => array(
					'filter' => FILTER_SANITIZE_STRING,
					'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
					),
				'source_lang'       => array(
					'filter' => FILTER_SANITIZE_STRING,
					'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
					),
				'target_lang'       => array(
					'filter' => FILTER_SANITIZE_STRING,
					'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
					),
				'segmentation_rule' => array(
					'filter' => FILTER_SANITIZE_STRING,
					'flags'  => array( FILTER_FLAG_STRIP_LOW, FILTER_FLAG_STRIP_HIGH )
					)
				);

		$postInput = filter_input_array( INPUT_POST, $filterArgs );

		$this->file_name   = $postInput[ 'file_name' ];
		$this->source_lang = $postInput[ "source_lang" ];
		$this->target_lang = $postInput[ "target_lang" ];
		$this->segmentation_rule = $postInput[ "segmentation_rule" ];

		if( $this->segmentation_rule == "") $this->segmentation_rule = null;


		$this->intDir = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $_COOKIE[ 'upload_session' ];
		$this->errDir = INIT::$STORAGE_DIR . DIRECTORY_SEPARATOR . 'conversion_errors' . DIRECTORY_SEPARATOR . $_COOKIE[ 'upload_session' ];

	}

	public function doAction() {

		$this->result[ 'code' ]      = 0; // No Good, Default

		if ( empty( $this->file_name ) ) {
			$this->result[ 'code' ]      = -1; // No Good, Default
			$this->result[ 'errors' ][ ] = array( "code" => -1, "message" => "Error: missing file name." );

			return false;
		}

        $this->file_name = html_entity_decode( $this->file_name, ENT_QUOTES );
		$file_path = $this->intDir . DIRECTORY_SEPARATOR . $this->file_name;

		if ( !file_exists( $file_path ) ) {
			$this->result[ 'code' ]      = -6; // No Good, Default
			$this->result[ 'errors' ][ ] = array( "code" => -6, "message" => "Error during upload. Please retry." );

			return -1;
		}

		//get uploaded file from disk
		$original_content = file_get_contents( $file_path );
		$sha1             = sha1( $original_content );


		//if already present in database cache get the converted without convert it again
		if ( INIT::$SAVE_SHASUM_FOR_FILES_LOADED ) {
			$xliffContent = getXliffBySHA1( $sha1, $this->source_lang, $this->target_lang, $this->cache_days, $this->segmentation_rule );
		}


		//XLIFF Conversion management
		//cyclomatic complexity 9999999 ..... but it works, for now.
		try {

			$fileType = DetectProprietaryXliff::getInfo( $file_path );

			if ( DetectProprietaryXliff::isXliffExtension() ) {

				if ( INIT::$CONVERSION_ENABLED ) {

					//conversion enforce
					if ( !INIT::$FORCE_XLIFF_CONVERSION ) {

						//ONLY IDIOM is forced to be converted
						//if file is not proprietary like idiom AND Enforce is disabled
						//we take it as is
						if ( !$fileType[ 'proprietary' ] || $fileType[ 'info' ][ 'extension' ] == 'tmx' ) {
							$this->result[ 'code' ]      = 1; // OK for client
							$this->result[ 'errors' ][ ] = array( "code" => 0, "message" => "OK" );

							return 0; //ok don't convert a standard sdlxliff
						}

					} else {

						//if conversion enforce is active
						//we force all xliff files but not files produced by SDL Studio because we can handle them
						if ( $fileType[ 'proprietary_short_name' ] == 'trados' || $fileType[ 'info' ][ 'extension' ] == 'tmx' ) {
							$this->result[ 'code' ]      = 1; // OK for client
							$this->result[ 'errors' ][ ] = array( "code" => 0, "message" => "OK" );

							return 0; //ok don't convert a standard sdlxliff
						}

					}

				} elseif ( $fileType[ 'proprietary' ] ) {

					unlink( $file_path );
					$this->result[ 'code' ]      = -7; // No Good, Default
					$this->result[ 'errors' ][ ] = array(
							"code"    => -7,
							"message" => 'Matecat Open-Source does not support ' . ucwords( $fileType[ 'proprietary_name' ] ) . '. Use MatecatPro.',
							'debug'   => basename( $this->file_name )
							);

					return -1;

				} elseif ( !$fileType[ 'proprietary' ] ) {

					$this->result[ 'code' ]      = 1; // OK for client
					$this->result[ 'errors' ][ ] = array( "code" => 0, "message" => "OK" );

					return 0; //ok don't convert a standard sdlxliff

				}

			}

		} catch ( Exception $e ) { //try catch not used because of exception no more raised
			$this->result[ 'code' ]      = -8; // No Good, Default
			$this->result[ 'errors' ][ ] = array( "code" => -8, "message" => $e->getMessage() );
			Log::doLog( $e->getMessage() );

			return -1;
		}


		//there is a cached copy of conversion? inflate
		if ( isset( $xliffContent ) && !empty( $xliffContent ) ) {

			$xliffContent = gzinflate( $xliffContent );
			$res          = $this->put_xliff_on_file( $xliffContent, $this->intDir );

			if ( !$res ) {

				//custom error message passed directly to javascript client and displayed as is
				$convertResult[ 'errorMessage' ] = "Error: failed to save converted file from cache to disk";
				$this->result[ 'code' ]          = -101;
				$this->result[ 'errors' ][ ]     = array(
						"code" => -101, "message" => $convertResult[ 'errorMessage' ],
						'debug' => basename( $this->file_name )
						);

			}

			//else whe have to convert it
		} else {

			$original_content_zipped = gzdeflate( $original_content, 5 );
			unset( $original_content );

			$converter = new FileFormatConverter( $this->segmentation_rule );

			if ( strpos( $this->target_lang, ',' ) !== false ) {
				$single_language = explode( ',', $this->target_lang );
				$single_language = $single_language[ 0 ];
			} else {
				$single_language = $this->target_lang;
			}

			$convertResult = $converter->convertToSdlxliff( $file_path, $this->source_lang, $single_language, false, $this->segmentation_rule );

			if ( $convertResult[ 'isSuccess' ] == 1 ) {

				/* try to back convert the file */
				$output_content                     = array();
				$output_content[ 'out_xliff_name' ] = $file_path . '.out.sdlxliff';
				$output_content[ 'source' ]         = $this->source_lang;
				$output_content[ 'target' ]         = $single_language;
				$output_content[ 'content' ]        = $convertResult[ 'xliffContent' ];
				$output_content[ 'filename' ]       = $this->file_name;
				$back_convertResult                 = $converter->convertToOriginal( $output_content );
				/* try to back convert the file */

				if ( $back_convertResult[ 'isSuccess' ] == false ) {
					//custom error message passed directly to javascript client and displayed as is
					$convertResult[ 'errorMessage' ] = "Error: there is a problem with this file, it cannot be converted back to the original one.";
					$this->result[ 'code' ]          = -110;
					$this->result[ 'errors' ][ ]     = array(
							"code" => -110, "message" => $convertResult[ 'errorMessage' ],
							'debug' => basename( $this->file_name )
							);

					return false;
				}

				//$uid = $convertResult['uid']; // va inserito nel database
				$xliffContent       = $convertResult[ 'xliffContent' ];
				$xliffContentZipped = gzdeflate( $xliffContent, 5 );

				//cache the converted file
				if ( INIT::$SAVE_SHASUM_FOR_FILES_LOADED ) {
					$res_insert = insertFileIntoMap( $sha1, $this->source_lang, $this->target_lang, $original_content_zipped, $xliffContentZipped,$this->segmentation_rule );
					if ( $res_insert < 0 ) {
						//custom error message passed directly to javascript client and displayed as is
						$convertResult[ 'errorMessage' ] = "Error: File too large";
						$this->result[ 'code' ]          = -102;
						$this->result[ 'errors' ][ ]     = array(
								"code" => -102, "message" => $convertResult[ 'errorMessage' ],
								'debug' => basename( $this->file_name )
								);

						return;
					}
				}

				unset ( $xliffContentZipped );

				$res = $this->put_xliff_on_file( $xliffContent, $this->intDir );
				if ( !$res ) {

					//custom error message passed directly to javascript client and displayed as is
					$convertResult[ 'errorMessage' ] = "Error: failed to save file on disk";
					$this->result[ 'code' ]          = -103;
					$this->result[ 'errors' ][ ]     = array(
							"code" => -103, "message" => $convertResult[ 'errorMessage' ],
							'debug' => basename( $this->file_name )
							);

					return false;

				}

			} else {

				$file = pathinfo( $this->file_name );

				switch ( $file[ 'extension' ] ) {
					case 'docx':
						$defaultError = "Conversion error. Try opening and saving the document with a new name. If this does not work, try converting to DOC.";
						break;
					case 'doc':
					case 'rtf':
						$defaultError = "Conversion error. Try opening and saving the document with a new name. If this does not work, try converting to DOCX.";
						break;
					case 'inx':
						$defaultError = "Conversion Error. Try to commit changes in InDesign before importing.";
						break;
					default:
						$defaultError = "Conversion error. Try opening and saving the document with a new name.";
						break;
				}

				if (
						stripos( $convertResult[ 'errorMessage' ], "failed to create SDLXLIFF." ) !== false ||
						stripos( $convertResult[ 'errorMessage' ], "COM target does not implement IDispatch" ) !== false
				   ) {
					$convertResult[ 'errorMessage' ] = "Error: failed importing file.";

				} elseif ( stripos( $convertResult[ 'errorMessage' ], "Unable to open Excel file - it may be password protected" ) !== false ) {
					$convertResult[ 'errorMessage' ] = $convertResult[ 'errorMessage' ] . " Try to remove protection using the Unprotect Sheet command on Windows Excel.";

				} elseif ( stripos( $convertResult[ 'errorMessage' ], "The document contains unaccepted changes" ) !== false ) {
					$convertResult[ 'errorMessage' ] = "The document contains track changes. Accept all changes before uploading it.";

				} elseif ( stripos( $convertResult[ 'errorMessage' ], "Error: Could not find file" ) !== false ||
						stripos( $convertResult[ 'errorMessage' ], "tw4winMark" ) !== false
						) {
					$convertResult[ 'errorMessage' ] = $defaultError;

				} elseif ( stripos( $convertResult[ 'errorMessage' ], "Attempted to read or write protected memory" ) !== false ) {
					$convertResult[ 'errorMessage' ] = $defaultError;

				} elseif ( stripos( $convertResult[ 'errorMessage' ], "The document was created in Microsoft Word 97 or earlier" ) ) {
					$convertResult[ 'errorMessage' ] = $defaultError;

				} elseif ( $file[ 'extension' ] == 'csv' && empty( $convertResult[ 'errorMessage' ] ) ) {
					$convertResult[ 'errorMessage' ] = "This CSV file is not eligible to be imported due internal wrong format. Try to convert in TXT using UTF8 encoding";

				} elseif ( empty( $convertResult[ 'errorMessage' ] ) ) {
					$convertResult[ 'errorMessage' ] = "Failed to convert file. Internal error. Please Try again.";

				} elseif ( stripos( $convertResult[ 'errorMessage' ], "DocumentFormat.OpenXml.dll") !==false ) {
					//this error is triggered on DOCX when converter's parser can't decode some regions of the file
					$convertResult[ 'errorMessage' ] = "Conversion error. Try opening and saving the document with a new name. If this does not work, try converting to DOC.";
				}

				//custom error message passed directly to javascript client and displayed as is
				$this->result[ 'code' ]      = -100;
				$this->result[ 'errors' ][ ] = array(
						"code" => -100, "message" => $convertResult[ 'errorMessage' ], "debug" => $file[ 'basename' ]
						);

			}

		}
	}

	private function put_xliff_on_file( $xliffContent ) {

		if ( !is_dir( $this->intDir . "_converted" ) ) {
			mkdir( $this->intDir . "_converted" );
		};

		$result = file_put_contents( "$this->intDir" . "_converted" . DIRECTORY_SEPARATOR . $this->file_name . ".sdlxliff", $xliffContent );

		//$result = number of bytes written
		if ( $result ) {
			$this->result[ 'code' ] = 1;

			return true;
		} else {
			return false;
		}

	}

}

?>
