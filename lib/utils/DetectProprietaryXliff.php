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
				'info'             => array(),
				'proprietary'      => false,
				'proprietary_name' => null
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
		return self::$fileType;

	}

	public static function isXliff( $stringData = null, $fullPathToFile = null ) {

		self::_reset();

		$info = array();

		if ( !empty ( $stringData ) && empty( $fullPathToFile ) ) {
			$stringData = substr( $stringData, 0, 1024 );

		} elseif ( empty( $stringData ) && !empty( $fullPathToFile ) ) {
			$info         = pathinfo( $fullPathToFile );
			$file_pointer = fopen( "$fullPathToFile", 'r' );
			// Checking Requirements (By specs, I know that xliff version is in the first 1KB)
			$stringData = fread( $file_pointer, 1024 );
			fclose( $file_pointer );

		} elseif ( !empty( $stringData ) && !empty( $fullPathToFile ) ) {
			//we want to check extension and content
			$info = pathinfo( $fullPathToFile );

		}

		self::$fileType['info'] = $info;

		//we want to check extension also if file path is specified
		if ( !empty( $info ) && !self::isXliffExtension() ) {
			//THIS IS NOT an xliff
			return false;
		}

		preg_match( '|<xliff\s.*?version\s?=\s?["\'](.*?)["\'](.*?)>|si', $stringData, $tmp );

		if ( !empty( $tmp ) ) {
			return $tmp;
		}

		return false;

	}

	protected static function _checkIdiom( $tmp ){

		//idiom Check
		if( isset($tmp[2]) ){
			if( stripos( $tmp[2], 'idiominc.com' ) !== false ) {
				self::$fileType['proprietary'] = true;
				self::$fileType['proprietary_name'] = 'idiom world server';
			}
		}

	}

	public static function getInfoByStringData( $stringData ) {

		self::_reset();

		$tmp = self::isXliff( $stringData );

		//idiom Check
		self::_checkIdiom( $tmp );

		return self::$fileType;

	}

	public static function isXliffExtension(){

		if ( empty( self::$fileType['info'] ) ) return false;

		switch( self::$fileType['info']['extension'] ){
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

}
