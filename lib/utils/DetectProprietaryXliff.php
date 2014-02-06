<?php
/**
 * User: domenico
 * Date: 23/10/13
 * Time: 11.48
 * 
 */


class DetectProprietaryXliff {

    protected static $fileType = array(
        'info'             => array(),
        'proprietary'      => false,
        'proprietary_name' => null
    );

    public static function getInfo( $fullPathToFile ) {

        /**
         * Conversion Enforce
         *
         * Check extensions no more sufficient, we want check content
         * if this is a proprietary file
         *
         */
        $info = pathinfo( $fullPathToFile );
        if ( ( $info[ 'extension' ] == 'xliff' ) || ( $info[ 'extension' ] == 'sdlxliff' ) || ( $info[ 'extension' ] == 'xlf' ) ) {


            if ( !file_exists( $fullPathToFile ) ) {
                throw new Exception( "File " . $fullPathToFile . " not found..." );
            }

            $file_pointer = fopen("$fullPathToFile", 'r');
            // Checking Requirements (By specs, I know that xliff version is in the first 1KB)
            $file_content = fread($file_pointer, 1024);
            fclose($file_pointer);

            $tmp = self::isXliff( $file_content );

            self::_checkIdiom( $tmp );

        }
        self::$fileType['info'] = $info;
        return self::$fileType;

    }

    public static function isXliff( $stringData ){

        $stringData = substr( $stringData, 0, 1024 );

        preg_match('|<xliff\s.*?version\s?=\s?["\'](.*?)["\'](.*?)>|si', $stringData, $tmp);

        if( !empty($tmp) ){
            return $tmp;
        }

        return false;

    }

    protected static function _checkIdiom( $tmp ){

        //idiom Check
        if ( isset($tmp[2]) && stripos( $tmp[2], 'idiominc.com' ) !== false ) {
            self::$fileType['proprietary'] = true;
            self::$fileType['proprietary_name'] = 'idiom world server';
        }

    }

    public static function getInfoByStringData( $stringData ) {

        $tmp = self::isXliff( $stringData );

        //idiom Check
        self::_checkIdiom( $tmp );

        return self::$fileType;

    }

}