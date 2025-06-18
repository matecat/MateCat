<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 03/06/25
 * Time: 20:27
 *
 */

namespace API\Commons\Traits;

use Exception;
use FilesStorage\AbstractFilesStorage;
use INIT;
use Matecat\XliffParser\Utils\Files as XliffFiles;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;

trait ScanDirectoryForConvertedFiles {

    /**
     * @throws Exception
     */
    protected function getFilesList( AbstractFilesStorage $fs, array $arFiles, $uploadDir ): array {

        $newArFiles = [];

        foreach ( $arFiles as $__fName ) {
            if ( 'zip' == AbstractFilesStorage::pathinfo_fix( $__fName, PATHINFO_EXTENSION ) ) {

                $fs->cacheZipArchive( sha1_file( $uploadDir . DIRECTORY_SEPARATOR . $__fName ), $uploadDir . DIRECTORY_SEPARATOR . $__fName );

                $linkFiles = scandir( $uploadDir );

                //fetch cache links, created by converter, from upload directory
                foreach ( $linkFiles as $storedFileName ) {
                    //Check if the file begins with the name of the zip file.
                    // If so, then it was stored in the zip file.
                    if ( strpos( $storedFileName, $__fName ) !== false &&
                            substr( $storedFileName, 0, strlen( $__fName ) ) == $__fName ) {
                        //add file name to the file's array
                        $newArFiles[] = $storedFileName;
                    }
                }

            } else { //This file was not in a zip. Add it normally

                if ( file_exists( $uploadDir . DIRECTORY_SEPARATOR . $__fName ) ) {
                    $newArFiles[] = $__fName;
                }
            }
        }

        $arFiles = $newArFiles;
        $arMeta  = [];

        // create array_files_meta
        foreach ( $arFiles as $arFile ) {
            $arMeta[] = $this->getFileMetadata( $uploadDir . DIRECTORY_SEPARATOR . $arFile );
        }

        return [ 'arrayFiles' => $arFiles, 'arrayFilesMeta' => $arMeta ];
    }

    /**
     * @param string $filename
     *
     * @return array
     */
    private function getFileMetadata( string $filename ): array {

        $info          = XliffProprietaryDetect::getInfo( $filename );
        $isXliff       = XliffFiles::isXliff( $filename );
        $isGlossary    = XliffFiles::isGlossaryFile( $filename );
        $isTMX         = XliffFiles::isTMXFile( $filename );
        $getMemoryType = XliffFiles::getMemoryFileType( $filename );

        $mustBeConverted = XliffProprietaryDetect::fileMustBeConverted( $filename, INIT::$FORCE_XLIFF_CONVERSION, INIT::$FILTERS_ADDRESS );

        $metadata                      = [];
        $metadata[ 'basename' ]        = $info[ 'info' ][ 'basename' ];
        $metadata[ 'dirname' ]         = $info[ 'info' ][ 'dirname' ];
        $metadata[ 'extension' ]       = $info[ 'info' ][ 'extension' ];
        $metadata[ 'filename' ]        = $info[ 'info' ][ 'filename' ];
        $metadata[ 'mustBeConverted' ] = $mustBeConverted;
        $metadata[ 'getMemoryType' ]   = $getMemoryType;
        $metadata[ 'isXliff' ]         = $isXliff;
        $metadata[ 'isGlossary' ]      = $isGlossary;
        $metadata[ 'isTMX' ]           = $isTMX;
        $metadata[ 'proprietary' ]     = [
                'proprietary'            => $info[ 'proprietary' ],
                'proprietary_name'       => $info[ 'proprietary_name' ],
                'proprietary_short_name' => $info[ 'proprietary_short_name' ],
        ];

        return $metadata;
    }

}