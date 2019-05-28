<?php

use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 22/09/17
 * Time: 16.29
 *
 */
class ZipArchiveReference {

    /**
     * @var string
     */
    protected $tempZipFile;

    public function __destruct() {
        @unlink( $this->tempZipFile );
    }

    public function getFileStreamPointerInfo( Projects_ProjectStruct $project, $fileName ) {

        $zip = $this->getZipFilePointer( $project );

        $internalFileIndex = $zip->locateName( $fileName, ZipArchive::FL_NOCASE | ZipArchive::FL_NODIR );
        $internalFileName  = $zip->getNameIndex( $internalFileIndex, ZipArchive::FL_UNCHANGED );
        $filePointer       = $zip->getStream( $internalFileName );

        $extension = AbstractFilesStorage::pathinfo_fix( $fileName, PATHINFO_EXTENSION );
        $mimeType  = array_keys( array_filter( INIT::$MIME_TYPES, function ( $extensionList ) use ( $extension ) {
            if ( array_search( $extension, $extensionList ) !== false ) {
                return true;
            }

            return false;
        } ) )[ 0 ];

        return [ 'fileName' => $fileName, 'stream' => $filePointer, 'mime_type' => $mimeType ];

    }

    public function getDirectoryStreamFilePointer( Projects_ProjectStruct $project, $dirName ) {

        $zip = $this->getZipFilePointer( $project );

        $this->tempZipFile = tempnam( INIT::$TMP_DOWNLOAD . '/' . $project->name . '/', "ZIP" );

        $zipReference = new ZipArchive();
        $zipReference->open( $this->tempZipFile, ZipArchive::CREATE );

        for ( $i = 0; $i < $zip->numFiles; $i++ ) {

            $name = $zip->getNameIndex( $i );

            // Skip files not in $source
            if ( strpos( $name, "{$dirName}/" ) !== 0 || $name == "{$dirName}/" ) {
                continue;
            }

            $zipReference->addFromString( $project->name . $name, $zip->getFromIndex( $i ) );

        }

        $zipReference->close();

        $filePointer = fopen( $this->tempZipFile, 'r' );

        $mimeType = array_keys( array_filter( INIT::$MIME_TYPES, function ( $extensionList ) {
            if ( array_search( 'zip', $extensionList ) !== false ) {
                return true;
            }

            return false;
        } ) )[ 0 ];

        return [ 'fileName' => $project->name . '__reference.zip', 'stream' => $filePointer, 'mime_type' => $mimeType ];

    }

    /**
     * @param Projects_ProjectStruct $project
     *
     * @return ZipArchive
     */
    public function getZipFilePointer( Projects_ProjectStruct $project ) {

        $fs    = FilesStorageFactory::create();
        $files = Files_FileDao::getByProjectId( $project->id, 60 * 60 );

        $zipName = explode( ZipArchiveExtended::INTERNAL_SEPARATOR, $files[ 0 ]->filename );
        $zipName = $zipName[ 0 ];

        $originalZipPath = $fs->getOriginalZipPath( $project->create_date, $project->id, $zipName );

        $zip = new ZipArchive();
        $zip->open( $originalZipPath );

        return $zip;

    }

    public function getListTree( Projects_ProjectStruct $project, $dirName ) {

        $cache_query = '__files_ref_tree:' . $project->id . ':' . $project->password;

        $redisHandler = ( new RedisHandler() )->getConnection();

        $_existingResult = null;
        if ( isset( $redisHandler ) && !empty( $redisHandler ) ) {
            $_existingResult = unserialize( $redisHandler->get( $cache_query ) );

            if ( $_existingResult !== false && $_existingResult !== null ) {
                return $_existingResult;
            }

        }

        $tree = [
                'files'   => [],
                'indexes' => []
        ];

        $zip = $this->getZipFilePointer( $project );
        for ( $i = 0; $i < $zip->numFiles; $i++ ) {

            $name = $zip->getNameIndex( $i );

            // Skip files not in $source
            if ( strpos( $name, "{$dirName}/" ) !== 0 || $name == "{$dirName}/" ) {
                continue;
            }

            $tree[ 'files' ][]   = $name;
            $tree[ 'indexes' ][] = $i;

        }

        if ( isset( $redisHandler ) && !empty( $redisHandler ) ) {
            $redisHandler->setex( $cache_query, 60 * 60 * 24, serialize( $tree ) );
        }

        return $tree;

    }

}