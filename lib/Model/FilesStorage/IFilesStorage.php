<?php

namespace FilesStorage;

use FileStorage\Exceptions\FileSystemException;

/**
 * Interface IFilesStorage
 *
 * INDEX
 * -------------------------------------------------------------------------
 * 1. CACHE PACKAGE
 * 2. PROJECT
 * 3. QUEUE
 * 4. FAST ANALYSIS
 *
 * @package FilesStorage
 */
interface IFilesStorage {

    /**
     **********************************************************************************************
     * 1. CACHE PACKAGE
     **********************************************************************************************
     */

    /**
     * Creates the Cache Package.
     * Directory structure:
     *
     * cache
     *    |_sha1+lang
     *      |_package
     *          |_manifest
     *          |_orig
     *          |    |_original file
     *          |_work
     *          |_xliff file
     *
     * @param      $hash
     * @param      $lang
     * @param bool $originalPath
     * @param      $xliffPath
     *
     * @return mixed
     * @throws FileSystemException
     */
    public function makeCachePackage( $hash, $lang, $originalPath = false, $xliffPath );

    /**
     * Rebuild the filename that will be taken from disk in the cache directory
     *
     * @param $hash
     * @param $lang
     *
     * @return mixed
     */
    public function getOriginalFromCache( $hash, $lang );

    /**
     * @param $hash
     * @param $lang
     *
     * @return mixed
     */
    public function getXliffFromCache( $hash, $lang );

    /**
     * @param $dirToScan
     *
     * @return mixed
     */
    public function getHashesFromDir( $dirToScan );

    /**
     **********************************************************************************************
     * 2. PROJECT
     **********************************************************************************************
     */

    /**
     * Creates the files folder.
     * Directory structure:
     *
     * files
     *    |_YYYYMMDD
     *      |_{id}
     *          |_orig
     *          |_package
     *          |_work
     *
     * @param      $dateHashPath
     * @param      $lang
     * @param      $idFile
     * @param null $newFileName
     *
     * @return mixed
     */
    public function moveFromCacheToFileDir( $dateHashPath, $lang, $idFile, $newFileName = null );

    /**
     * Rebuild the filename that will be taken from disk in files directory
     *
     * @param $id
     *
     * @return bool|string
     */
    public function getOriginalFromFileDir( $id, $dateHashPath );

    /**
     * @param $id
     * @param $dateHashPath
     *
     * @return mixed
     */
    public function getXliffFromFileDir( $id, $dateHashPath );

    /**
     **********************************************************************************************
     * 3. QUEUE
     **********************************************************************************************
     */

    /**
     * Moves the files from upload session folder to queue path
     *
     * @param $uploadSession
     *
     * @return mixed
     */
    public static function moveFileFromUploadSessionToQueuePath( $uploadSession );

    /**
     **********************************************************************************************
     * 4. FAST ANALYSIS
     **********************************************************************************************
     */

    /**
     * Stores a serialized file to fast analysis storage
     *
     * @param       $id_project
     * @param array $segments_metadata
     *
     * @return mixed
     */
    public static function storeFastAnalysisFile( $id_project, Array $segments_metadata = [] );

    /**
     * Gets a serialized file from fast analysis storage
     *
     * @param $id_project
     *
     * @return mixed
     */
    public static function getFastAnalysisData( $id_project );

    /**
     * Deletes a serialized file from fast analysis storage
     *
     * @param $id_project
     *
     * @return mixed
     */
    public static function deleteFastAnalysisFile( $id_project );

    /**
     **********************************************************************************************
     * 5. ZIP ARCHIVES HANDLING
     **********************************************************************************************
     */

    /**
     * Make a temporary cache copy for the original zip file
     *
     * @param $hash
     * @param $zipPath
     *
     * @return bool
     */
    public function cacheZipArchive( $hash, $zipPath );

    /**
     * @param $create_date
     * @param $zipHash
     * @param $projectID
     *
     * @return mixed
     */
    public function linkZipToProject( $create_date, $zipHash, $projectID );

    /**
     * @param $projectDate
     * @param $projectID
     * @param $zipName
     *
     * @return string
     */
    public function getOriginalZipPath( $projectDate, $projectID, $zipName );

    /**
     * @param $projectDate
     * @param $projectID
     *
     * @return string
     */
    public function getOriginalZipDir( $projectDate, $projectID );

    /**
     **********************************************************************************************
     * 6. TRANSFER FILES
     **********************************************************************************************
     */

    /**
     * @param string $source
     * @param string $destination
     *
     * @return bool
     * @throws \Exception
     */
    public function transferFiles($source, $destination);

    /**
     **********************************************************************************************
     * 7. BLACKLIST FILE
     **********************************************************************************************
     */

    /**
     * @param /** $filePath
     *
     * @return mixed
     */
    public function deleteBlacklistFile($filePath);

    /**
     * @param string              $filePath
     * @param \Chunks_ChunkStruct $chunkStruct
     * @param                     $uid
     *
     * @return mixed
     */
    public function saveBlacklistFile($filePath, \Chunks_ChunkStruct $chunkStruct, $uid);
}
