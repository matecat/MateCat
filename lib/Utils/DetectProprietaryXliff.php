<?php
/**
 * User: domenico
 * Date: 23/10/13
 * Time: 11.48
 * 
 */


class DetectProprietaryXliff {

	protected static $fileType = array();

	protected static function _reset(){
		self::$fileType = array(
				'info'                      => array(),
				'proprietary'               => false,
				'proprietary_name'          => null,
                'proprietary_short_name'    => null
			);
	}

	public static function getInfo( $fullPathToFile ) {

		self::_reset();

		/**
		 * Conversion Enforce
		 *
		 * Check extensions no more sufficient, we want check content
		 * if this is a proprietary file
		 *
		 */
		$tmp = self::isXliff( null, $fullPathToFile );
		self::_checkIdiom( $tmp );
		self::_checkSDL( $tmp );
		self::_checkGlobalSight( $tmp );
		self::_checkMateCATConverter( $tmp );
		return self::$fileType;

	}

	public static function isXliff( $stringData = null, $fullPathToFile = null ) {

		self::_reset();

		$info = array();

		if ( !empty ( $stringData ) && empty( $fullPathToFile ) ) {
			$stringData = substr( $stringData, 0, 1024 );

		} elseif ( empty( $stringData ) && !empty( $fullPathToFile ) ) {
			$info         = FilesStorage::pathinfo_fix( $fullPathToFile );
			$file_pointer = fopen( "$fullPathToFile", 'r' );
			// Checking Requirements (By specs, I know that xliff version is in the first 1KB)
			$stringData = fread( $file_pointer, 1024 );
			fclose( $file_pointer );

		} elseif ( !empty( $stringData ) && !empty( $fullPathToFile ) ) {
			//we want to check extension and content
			$info = FilesStorage::pathinfo_fix( $fullPathToFile );

		}

		self::$fileType['info'] = $info;

		//we want to check extension also if file path is specified
		if ( !empty( $info ) && !self::isXliffExtension() ) {
			//THIS IS NOT an xliff
			return false;
		}

//		preg_match( '|<xliff\s.*?version\s?=\s?["\'](.*?)["\'](.*?)>|si', $stringData, $tmp );

		if ( !empty( $stringData ) ) {
			return array( $stringData );
		}

		return false;

	}

	protected static function _checkIdiom( $tmp ){

		//idiom Check
		if( isset($tmp[0]) ){
			if( stripos( $tmp[0], 'idiominc.com' ) !== false ) {
                self::$fileType[ 'proprietary' ]            = true;
                self::$fileType[ 'proprietary_name' ]       = 'idiom world server';
                self::$fileType[ 'proprietary_short_name' ] = 'idiom';
			}
		}

	}

    protected static function _checkSDL( $tmp ){

        //idiom Check
        if( isset($tmp[0]) ){
            if( stripos( $tmp[0], 'sdl:version' ) !== false ) {
                //little trick, we consider not proprietary Sdlxliff files because we can handle them
                self::$fileType[ 'proprietary' ]            = false;
                self::$fileType[ 'proprietary_name' ]       = 'SDL Studio ';
                self::$fileType[ 'proprietary_short_name' ] = 'trados';
            }
        }

    }

    protected static function _checkGlobalSight( $tmp ){
        if( isset($tmp[0]) ){
            if( stripos( $tmp[0], 'globalsight' ) !== false ) {
                self::$fileType[ 'proprietary' ]            = true;
                self::$fileType[ 'proprietary_name' ]       = 'GlobalSight Download File';
                self::$fileType[ 'proprietary_short_name' ] = 'globalsight';
            }
        }
    }

    protected static function _checkMateCATConverter( $tmp ){
        if( isset($tmp[0]) ){
            if( stripos( $tmp[0], 'tool-id="matecat-converter"' ) !== false ) {
                self::$fileType[ 'proprietary' ]            = false;
                self::$fileType[ 'proprietary_name' ]       = 'MateCAT Converter';
                self::$fileType[ 'proprietary_short_name' ] = 'matecat_converter';
            }
        }
    }

    public static function getInfoByStringData( $stringData ) {

		self::_reset();

		$tmp = self::isXliff( $stringData );

		//idiom Check
		self::_checkIdiom( $tmp );
        self::_checkSDL( $tmp );
        self::_checkGlobalSight( $tmp );
        self::_checkMateCATConverter( $tmp );
		return self::$fileType;

	}

	public static function isXliffExtension( $pathInfo = array() ){

        if( empty( $pathInfo ) ){
            if ( empty( self::$fileType['info'] ) ) return false;
        } else {
            self::$fileType['info'] = $pathInfo;
        }

        switch( strtolower( self::$fileType['info']['extension'] ) ){
			case 'xliff':
			case 'sdlxliff':
			case 'tmx':
			case 'xlf':
				return true;
				break;
			default:
				return false;
				break;
		}

	}

	public static function isConversionToEnforce($fullPath){
		$isAConvertedFile = true;

		$fileType = self::getInfo($fullPath);

		if ( self::isXliffExtension() ) {

			if ( INIT::$CONVERSION_ENABLED ) {

				//conversion enforce
				if ( !INIT::$FORCE_XLIFF_CONVERSION ) {

					//ONLY IDIOM is forced to be converted
					//if file is not proprietary like idiom AND Enforce is disabled
					//we take it as is
					if ( !$fileType[ 'proprietary' ] ) {
						$isAConvertedFile = false;
						//ok don't convert a standard sdlxliff
					}
				}
				else {
					//if conversion enforce is active
					//we force all xliff files but not files produced by SDL Studio because we can handle them
					if ( $fileType[ 'proprietary_short_name' ] == 'trados' ) {
						$isAConvertedFile = false;
						//ok don't convert a standard sdlxliff
					}
				}
			}
			elseif ( $fileType[ 'proprietary' ] ) {

				/**
				 * Application misconfiguration.
				 * upload should not be happened, but if we are here, raise an error.
				 * @see upload.class.php
				 * */

				$isAConvertedFile =-1;
				//stop execution
			}
			elseif ( !$fileType[ 'proprietary' ] ) {
				$isAConvertedFile = false;
				//ok don't convert a standard sdlxliff
			}
		}
		return $isAConvertedFile;
	}

}
