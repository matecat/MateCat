<?php

namespace Model\FilesStorage;

use Exception;
use Model\FilesStorage\Exceptions\FileSystemException;

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
interface IFilesStorage
{

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
     * @param string $hash
     * @param string $lang
     * @param ?string $originalPath
     * @param string $xliffPath
     *
     * @return mixed
     * @throws FileSystemException
     */
    public function makeCachePackage(string $hash, string $lang, ?string $originalPath, string $xliffPath): bool;

    /**
     * Rebuild the filename that will be taken from disk in the cache directory
     *
     * @param string $hash
     * @param string $lang
     *
     * @return mixed
     */
    public function getOriginalFromCache(string $hash, string $lang): false|string;

    /**
     * @param string $hash
     * @param string $lang
     *
     * @return bool|string
     */
    public function getXliffFromCache(string $hash, string $lang): false|string;

    /**
     * @param string $dirToScan
     *
     * @return mixed
     */
    public function getHashesFromDir(string $dirToScan): array;

    /**
     **********************************************************************************************
     * 2. PROJECT
     **********************************************************************************************
     */

    /**
     * Creates the file's folder.
     * Directory structure:
     *
     * files
     *    |_YYYYMMDD
     *      |_{id}
     *          |_orig
     *          |_package
     *          |_work
     *
     * @param string $dateHashPath
     * @param string $lang
     * @param string $idFile
     * @param string|null $newFileName
     *
     * @return mixed
     */
    public function moveFromCacheToFileDir(string $dateHashPath, string $lang, string $idFile, ?string $newFileName = null): bool;

    /**
     * Rebuild the filename that will be taken from disk in files directory
     *
     * @param string $id
     * @param string $dateHashPath
     *
     * @return bool|string
     */
    public function getOriginalFromFileDir(string $id, string $dateHashPath): false|string;

    /**
     * @param string $id
     * @param string $dateHashPath
     *
     * @return mixed
     */
    public function getXliffFromFileDir(string $id, string $dateHashPath): false|string;

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
    public static function moveFileFromUploadSessionToQueuePath(string $uploadSession): void;

    /**
     **********************************************************************************************
     * 4. FAST ANALYSIS
     **********************************************************************************************
     */

    /**
     * Stores a serialized file to fast analysis storage
     *
     * @param string $id_project
     * @param array $segments_metadata
     *
     * @return void
     */
    public static function storeFastAnalysisFile(string $id_project, array $segments_metadata = []): void;

    /**
     * Gets a serialized file from fast analysis storage
     *
     * @param int $id_project
     *
     * @return array
     */
    public static function getFastAnalysisData(int $id_project): array;

    /**
     * Deletes a serialized file from fast analysis storage
     *
     * @param string $id_project
     *
     * @return mixed
     */
    public static function deleteFastAnalysisFile(string $id_project): bool;

    /**
     **********************************************************************************************
     * 5. ZIP ARCHIVES HANDLING
     **********************************************************************************************
     */

    /**
     * Make a temporary cache copy for the original zip file
     *
     * @param string $hash
     * @param string $zipPath
     *
     * @return bool
     */
    public function cacheZipArchive(string $hash, string $zipPath): bool;

    /**
     * @param string $create_date
     * @param string $zipHash
     * @param string $projectID
     *
     * @return mixed
     */
    public function linkZipToProject(string $create_date, string $zipHash, string $projectID): bool;

    /**
     * @param string $projectDate
     * @param string $projectID
     * @param string $zipName
     *
     * @return string
     */
    public function getOriginalZipPath(string $projectDate, string $projectID, string $zipName): string;

    /**
     * @param string $projectDate
     * @param string $projectID
     *
     * @return string
     */
    public function getOriginalZipDir(string $projectDate, string $projectID): string;

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
     * @throws Exception
     */
    public function transferFiles(string $source, string $destination): bool;

}
