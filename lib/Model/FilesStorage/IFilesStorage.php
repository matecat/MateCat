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
     * @return bool
     * @throws FileSystemException
     */
    public function makeCachePackage(string $hash, string $lang, ?string $originalPath, string $xliffPath): bool;

    /**
     * Rebuild the filename that will be taken from disk in the cache directory
     *
     * @param string $hash
     * @param string $lang
     *
     * @return false|string
     */
    public function getOriginalFromCache(string $hash, string $lang): false|string;

    /**
     * @param string $hash
     * @param string $lang
     *
     * @return false|string
     */
    public function getXliffFromCache(string $hash, string $lang): false|string;

    /**
     * @param string $dirToScan
     *
     * @return array{conversionHashes: array<string, mixed>, zipHashes: list<string>}
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
     * @return bool
     */
    public function moveFromCacheToFileDir(string $dateHashPath, string $lang, string $idFile, ?string $newFileName = null): bool;

    /**
     * Rebuild the filename that will be taken from disk in files directory
     *
     * @param string $id
     * @param string $dateHashPath
     *
     * @return false|string
     */
    public function getOriginalFromFileDir(string $id, string $dateHashPath): false|string;

    /**
     * @param string $id
     * @param string $dateHashPath
     *
     * @return false|string
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
     * @param string $uploadSession
     *
     * @return void
     */
    public static function moveFileFromUploadSessionToQueuePath(string $uploadSession): void;

    /**
     * Deletes the queue directory (and any associated converted-files directory).
     *
     * @param string $uploadDir
     *
     * @return void
     *
     * @throws Exception
     */
    public function deleteQueue(string $uploadDir): void;

    /**
     **********************************************************************************************
     * 4. FAST ANALYSIS
     **********************************************************************************************
     */

    /**
     * Stores a serialized file to fast analysis storage
     *
     * @param string $id_project
     * @param array<string|int, mixed> $segments_metadata
     *
     * @return void
     * @throws \UnexpectedValueException
     * @throws \ReflectionException
     */
    public static function storeFastAnalysisFile(string $id_project, array $segments_metadata = []): void;

    /**
     * Gets a serialized file from fast analysis storage
     *
     * @param int $id_project
     *
     * @return array<string|int, mixed>
     * @throws \UnexpectedValueException
     * @throws \ReflectionException
     */
    public static function getFastAnalysisData(int $id_project): array;

    /**
     * Deletes a serialized file from fast analysis storage
     *
     * @param string $id_project
     *
     * @return bool
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
     * @return bool
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
