<?php

use FilesStorage\AbstractFilesStorage;

/**
 * User: domenico
 * Date: 23/10/13
 * Time: 11.48
 *
 */
class DetectProprietaryXliff {

    protected static $fileType = array();

    protected static function _reset() {
        self::$fileType = array(
                'info'                   => array(),
                'proprietary'            => false,
                'proprietary_name'       => null,
                'proprietary_short_name' => null
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

            $info = AbstractFilesStorage::pathinfo_fix( $fullPathToFile );

            if ( is_file( $fullPathToFile ) ) {
                $file_pointer = fopen( "$fullPathToFile", 'r' );
                // Checking Requirements (By specs, I know that xliff version is in the first 1KB)
                $stringData = fread( $file_pointer, 1024 );
                fclose( $file_pointer );
            }

        } elseif ( !empty( $stringData ) && !empty( $fullPathToFile ) ) {
            //we want to check extension and content
            $info = AbstractFilesStorage::pathinfo_fix( $fullPathToFile );

        }

        self::$fileType[ 'info' ] = $info;

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

    protected static function _checkSDL( $tmp ) {
        if ( isset( $tmp[ 0 ] ) ) {
            if ( stripos( $tmp[ 0 ], 'sdl:version' ) !== false ) {
                //little trick, we consider not proprietary Sdlxliff files because we can handle them
                self::$fileType[ 'proprietary' ]            = false;
                self::$fileType[ 'proprietary_name' ]       = 'SDL Studio ';
                self::$fileType[ 'proprietary_short_name' ] = 'trados';
                self::$fileType[ 'converter_version' ]      = 'legacy';
            }
        }

    }

    protected static function _checkGlobalSight( $tmp ) {
        if ( isset( $tmp[ 0 ] ) ) {
            if ( stripos( $tmp[ 0 ], 'globalsight' ) !== false ) {
                self::$fileType[ 'proprietary' ]            = true;
                self::$fileType[ 'proprietary_name' ]       = 'GlobalSight Download File';
                self::$fileType[ 'proprietary_short_name' ] = 'globalsight';
                self::$fileType[ 'converter_version' ]      = 'legacy';
            }
        }
    }

    protected static function _checkMateCATConverter( $tmp ) {
        if ( isset( $tmp[ 0 ] ) ) {
            preg_match( '#tool-id\s*=\s*"matecat-converter(\s+([^"]+))?"#i', $tmp[ 0 ], $matches );
            if ( !empty( $matches ) ) {
                self::$fileType[ 'proprietary' ]            = false;
                self::$fileType[ 'proprietary_name' ]       = 'MateCAT Converter';
                self::$fileType[ 'proprietary_short_name' ] = 'matecat_converter';
                if ( $matches[ 2 ] ) {
                    self::$fileType[ 'converter_version' ] = $matches[ 2 ];
                } else {
                    // First converter release didn't specify version
                    self::$fileType[ 'converter_version' ] = '1.0';
                }
            }
        }
    }

    public static function getInfoByStringData( $stringData ) {

        self::_reset();

        $tmp = self::isXliff( $stringData );

        self::_checkSDL( $tmp );
        self::_checkGlobalSight( $tmp );
        self::_checkMateCATConverter( $tmp );

        return self::$fileType;

    }

    public static function getMemoryFileType( $pathInfo = array() ) {

        if ( empty( $pathInfo ) ) {
            if ( empty( self::$fileType[ 'info' ] ) ) {
                return false;
            }
        } else {
            self::$fileType[ 'info' ] = $pathInfo;
        }

        switch ( strtolower( self::$fileType[ 'info' ][ 'extension' ] ) ) {
            case 'tmx':
                return 'tmx';
                break;
            case 'g':
                return 'glossary';
                break;
            default:
                return false;
                break;
        }

    }

    public static function isTMXFile( $pathInfo = array() ) {

        if ( self::getMemoryFileType( $pathInfo ) == 'tmx' ) {
            return true;
        }

        return false;

    }

    public static function isGlossaryFile( $pathInfo = array() ) {

        if ( self::getMemoryFileType( $pathInfo ) == 'glossary' ) {
            return true;
        }

        return false;

    }

    public static function isXliffExtension( $pathInfo = array() ) {

        if ( empty( $pathInfo ) ) {
            if ( empty( self::$fileType[ 'info' ] ) ) {
                return false;
            }
        } else {
            self::$fileType[ 'info' ] = $pathInfo;
        }

        switch ( strtolower( self::$fileType[ 'info' ][ 'extension' ] ) ) {
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

    public static function fileMustBeConverted( $fullPath, $enforceOnXliff ) {

        $_convert = true;

        $fileType = self::getInfo( $fullPath );

        if ( self::isXliffExtension() || DetectProprietaryXliff::getMemoryFileType() ) {

            if ( !empty( INIT::$FILTERS_ADDRESS ) ) {

                //conversion enforce
                if ( !$enforceOnXliff ) {

                    //if file is not proprietary AND Enforce is disabled
                    //we take it as is
                    if ( !$fileType[ 'proprietary' ] || DetectProprietaryXliff::getMemoryFileType() ) {
                        $_convert = false;
                        //ok don't convert a standard sdlxliff
                    }
                } else {
                    //if conversion enforce is active
                    //we force all xliff files but not files produced by SDL Studio because we can handle them
                    if (
                            $fileType[ 'proprietary_short_name' ] == 'matecat_converter'
                            || $fileType[ 'proprietary_short_name' ] == 'trados'
                            || DetectProprietaryXliff::getMemoryFileType()
                    ) {
                        $_convert = false;
                        //ok don't convert a standard sdlxliff
                    }
                }
            } elseif ( $fileType[ 'proprietary' ] ) {

                /**
                 * Application misconfiguration.
                 * upload should not be happened, but if we are here, raise an error.
                 * @see upload.class.php
                 * */

                $_convert = -1;
                //stop execution
            } elseif ( !$fileType[ 'proprietary' ] ) {
                $_convert = false;
                //ok don't convert a standard sdlxliff
            }
        }

        return $_convert;

    }

}
