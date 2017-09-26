<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 22/09/17
 * Time: 16.29
 *
 */

class ZipArchiveReference {

    public function getFileStreamPointerInfo( Projects_ProjectStruct $project, $fileName ){

        $fs     = new \FilesStorage();
        $jobs   = $project->getJobs( 60 * 60 );
        $files  = Files_FileDao::getByJobId( $jobs[ 0 ]->id, 60 * 60 );

        $zipName = explode( ZipArchiveExtended::INTERNAL_SEPARATOR, $files[ 0 ]->filename );
        $zipName = $zipName[ 0 ];

        $originalZipPath = $fs->getOriginalZipPath( $project->create_date, $project->id, $zipName );

        $zip = new ZipArchive();
        $zip->open( $originalZipPath );

        $internalFileIndex = $zip->locateName( $fileName, ZipArchive::FL_NOCASE|ZipArchive::FL_NODIR );
        $internalFileName = $zip->getNameIndex( $internalFileIndex, ZipArchive::FL_UNCHANGED );
        $filePointer = $zip->getStream( $internalFileName );

        $extension = FilesStorage::pathinfo_fix( $fileName, PATHINFO_EXTENSION );
        $mimeType = array_keys( array_filter( INIT::$MIME_TYPES, function( $extensionList ) use ( $extension ) {
            if( array_search( $extension, $extensionList ) !== false ) return true;
            return false;
        } ) )[ 0 ];

        return [ 'fileName' => $fileName, 'stream' => $filePointer, 'mime_type' => $mimeType ];

    }

}