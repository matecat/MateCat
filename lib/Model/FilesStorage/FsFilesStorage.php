<?php

namespace Model\FilesStorage;

use Exception;
use InvalidArgumentException;
use Matecat\XliffParser\Utils\Files as XliffFiles;
use Model\FilesStorage\Exceptions\FileSystemException;
use Random\RandomException;
use UnexpectedValueException;
use Utils\Registry\AppConfig;

/**
 * Class FsFilesStorage
 *
 * INDEX
 * -------------------------------------------------------------------------
 * 1. CACHE PACKAGE
 * 2. PROJECT
 * 3. QUEUE
 * 4. FAST ANALYSIS
 * 5. ZIP ARCHIVES HANDLING
 * 6. GENERAL METHODS
 *
 * @package FilesStorage
 */
class FsFilesStorage extends AbstractFilesStorage
{

    /**
     * @param FilesystemAdapter|null $filesystem
     *
     * @throws \TypeError
     */
    public function __construct(?FilesystemAdapter $filesystem = null)
    {
        parent::__construct($filesystem);
        $this->filesDir = AppConfig::$FILES_REPOSITORY;
        $this->cacheDir = AppConfig::$CACHE_REPOSITORY;
    }

    protected function ensureDirectoryExists(string $path): bool
    {
        if (!$this->filesystem->fileExists($path)) {
            return $this->filesystem->mkdir($path, 0755, true);
        }

        return true;
    }

    /**
     **********************************************************************************************
     * 1. CACHE PACKAGE
     **********************************************************************************************
     */

    /**
     * Writes $source to $dest in the cache without ever rewriting the file already stored under $dest.
     *
     * Projects hard-link the cache files, and a content-hash name is written again by every upload of the same
     * content: a copy onto $dest would truncate and rewrite the inode those projects point at. The bytes go to a
     * temporary name in the same directory first, then a rename swaps them in atomically, under a new inode, so the
     * files already linked keep the bytes they were created with. The cache readers skip the temporary name.
     *
     * @param string $source
     * @param string $dest
     *
     * @return bool
     * @throws RandomException
     */
    private function copyIntoCache(string $source, string $dest): bool
    {
        $tmp = dirname($dest) . DIRECTORY_SEPARATOR . self::TEMPORARY_FILE_PREFIX . bin2hex(random_bytes(8));

        if ($this->filesystem->copy($source, $tmp) && $this->filesystem->rename($tmp, $dest)) {
            return true;
        }

        if ($this->filesystem->fileExists($tmp)) {
            $this->filesystem->unlink($tmp);
        }

        return false;
    }

    /**
     * @param string $hash
     * @param string $lang
     * @param string|null $originalPath
     * @param string $xliffPath
     *
     * @return bool
     * @throws FileSystemException
     * @throws Exception
     */
    public function makeCachePackage(string $hash, string $lang, ?string $originalPath, string $xliffPath): bool
    {
        $cacheTree = implode(DIRECTORY_SEPARATOR, static::composeCachePath($hash));

        //don't save in cache when a specified filter version is forced
        if (AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION !== false && $this->filesystem->fileExists($this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang)) {
            return true;
        }

        //create cache dir structure
        $this->ensureDirectoryExists($this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang);
        $cacheDir = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang . DIRECTORY_SEPARATOR . "package";

        $this->ensureDirectoryExists($cacheDir);
        $this->ensureDirectoryExists($cacheDir . DIRECTORY_SEPARATOR . "orig");
        $this->ensureDirectoryExists($cacheDir . DIRECTORY_SEPARATOR . "work");

        //if it's not a xliff as original
        if (!$originalPath) {
            //if there is not an original path this is an unconverted file: the original does not exist, the xliff is stored as it is
            $xliffDestination = $cacheDir . DIRECTORY_SEPARATOR . "work" . DIRECTORY_SEPARATOR . static::getNativeXliffCacheFileName($hash, $xliffPath);
        } else {
            //move original, under a name taken from the content hash: the same content always lands on the same file
            $file_name = static::getCacheFileName($hash, $originalPath);

            $outcome1 = $this->copyIntoCache($originalPath, $cacheDir . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . $file_name);

            if (!$outcome1) {
                // Original directory deleted!!!
                // CLEAR ALL CACHE

                $cacheDirToDelete = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang;

                // check if cache dir exists
                if (!$this->filesystem->fileExists($cacheDirToDelete)) {
                    throw new FileSystemException($cacheDirToDelete . ' directory does not exists. Maybe there is a problem with folder permissions.');
                }

                $this->filesystem->deleteDir($cacheDirToDelete);

                return false;
            }

            $file_extension = '.sdlxliff';

            //set naming for converted xliff
            $xliffDestination = $cacheDir . DIRECTORY_SEPARATOR . "work" . DIRECTORY_SEPARATOR . $file_name . $file_extension;
        }

        //move converted xliff
        //In Unix you can't rename or move between filesystems,
        //Instead you must copy the file from one source location to the destination location, then delete the source.
        $outcome2 = $this->copyIntoCache($xliffPath, $xliffDestination);

        if (!$outcome2) {
            //Original directory deleted!!!
            //CLEAR ALL CACHE - FATAL

            $cacheDirToDelete = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang;

            // check if cache dir exists
            if (!$this->filesystem->fileExists($cacheDirToDelete)) {
                throw new FileSystemException($cacheDirToDelete . ' directory does not exists. Maybe there is a problem with folder permissions.');
            }

            $this->filesystem->deleteDir($this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang);

            return false;
        }

        $this->filesystem->unlink($xliffPath);

        return true;
    }

    /**
     * Rebuild the filename that will be taken from disk in the cache directory
     *
     * @param string $hash
     * @param string $lang
     *
     * @return false|string
     */
    public function getOriginalFromCache(string $hash, string $lang): false|string
    {
        //compose path
        $cacheTree = implode(DIRECTORY_SEPARATOR, static::composeCachePath($hash));

        $path = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "orig";

        //return file
        $filePath = $this->getSingleFileInPath($path, $hash . '.');

        //an unconverted xliff is never stored in orig dir; look for it in xliff dir
        if (!$filePath) {
            $filePath = $this->getXliffFromCache($hash, $lang);
        }

        return $filePath;
    }

    /**
     * @param string $hash
     * @param string $lang
     *
     * @return false|string
     */
    public function getXliffFromCache(string $hash, string $lang): false|string
    {
        $cacheTree = implode(DIRECTORY_SEPARATOR, static::composeCachePath($hash));

        //compose path
        $path = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "work";

        //return file
        return $this->getSingleFileInPath($path, $hash . '.');
    }

    /**
     **********************************************************************************************
     * 2. PROJECT
     **********************************************************************************************
     */

    /**
     * @param string $dateHashPath
     * @param string $lang
     * @param string $idFile
     * @param string|null $newFileName
     *
     * @return bool
     * @throws UnexpectedValueException
     * @throws Exception
     */
    public function moveFromCacheToFileDir(string $dateHashPath, string $lang, string $idFile, ?string $newFileName = null): bool
    {
        [$datePath, $hash] = explode(DIRECTORY_SEPARATOR, $dateHashPath);
        $cacheTree = implode(DIRECTORY_SEPARATOR, static::composeCachePath($hash));

        //destination dir
        $fileDir = $this->filesDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $idFile;
        $cacheDir = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang . DIRECTORY_SEPARATOR . "package";

        $this->logger->debug($fileDir);
        $this->logger->debug($cacheDir);

        $res = true;
        //check if it doesn't exist
        if (!$this->filesystem->isDir($fileDir)) {
            //make files' directory structure
            $res &= $this->ensureDirectoryExists($fileDir);
            $res &= $this->ensureDirectoryExists($fileDir . DIRECTORY_SEPARATOR . "package");
            $res &= $this->ensureDirectoryExists($fileDir . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "orig");
            $res &= $this->ensureDirectoryExists($fileDir . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "work");
            $res &= $this->ensureDirectoryExists($fileDir . DIRECTORY_SEPARATOR . "orig");
            $res &= $this->ensureDirectoryExists($fileDir . DIRECTORY_SEPARATOR . "xliff");
        }

        //make links from cache to files
        //BUG: this stuff may not work if FILES and CACHES are on different filesystems
        //orig, suppress error because of xliff files have not original one
        $origDir = $cacheDir . DIRECTORY_SEPARATOR . "orig";
        $this->logger->debug($origDir);

        // a package written before the storage names were hashed can hold a legacy file next to the hashed one:
        // take the hashed one, which is the file the segments are read from
        $origFilePath = $this->getSingleFileInPath($origDir, $hash . '.');
        if (is_string($origFilePath) && $this->filesystem->isFile($origFilePath)) {
            // the file the cache already stores is never hashed again
            $origFileName = !empty($newFileName) ? static::getStorageFileName($newFileName) : static::basename_fix($origFilePath);
            $res &= $this->filesystem->link($origFilePath, $fileDir . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . $origFileName);
        }

        //work
        /*
         * Force the new filename if it is provided
         */
        $d = $cacheDir . DIRECTORY_SEPARATOR . "work";
        $this->logger->debug($d);
        $convertedFilePath = $this->getSingleFileInPath($d, $hash . '.');

        if (!is_string($convertedFilePath)) {
            throw new UnexpectedValueException('Internal error: Failed to create/copy the file on disk from cache.', -13);
        }

        $this->logger->debug($convertedFilePath);

        // a native xliff keeps the name the cache stores it under, which is never hashed again
        $convertedFileName = static::basename_fix($convertedFilePath);
        if (!empty($newFileName) && !XliffFiles::isXliff($newFileName)) {
            /** @var string $convertedExtension */
            $convertedExtension = static::pathinfo_fix($convertedFilePath, PATHINFO_EXTENSION);
            $convertedFileName = static::getStorageFileName($newFileName) . "." . $convertedExtension;
        }

        $this->logger->debug($convertedFilePath);

        $dest = $fileDir . DIRECTORY_SEPARATOR . "xliff" . DIRECTORY_SEPARATOR . $convertedFileName;

        $this->logger->debug($dest);

        $res &= $this->filesystem->link($convertedFilePath, $dest);

        if (!$res) {
            throw new UnexpectedValueException('Internal error: Failed to create/copy the file on disk from cache.', -13);
        }

        return (bool)$res;
    }

    /**
     * Rebuild the filename that will be taken from disk in files directory
     *
     * @param string $id
     * @param string $dateHashPath
     *
     * @return false|string
     */
    public function getOriginalFromFileDir(string $id, string $dateHashPath): false|string
    {
        [$datePath,] = explode(DIRECTORY_SEPARATOR, $dateHashPath);

        //compose path
        $path = $this->filesDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . "orig";

        //return file
        $filePath = $this->getSingleFileInPath($path);

        //an unconverted xliff is never stored in orig dir; look for it in xliff dir
        if (!$filePath) {
            $filePath = $this->getXliffFromFileDir($id, $dateHashPath);
        }

        return $filePath;
    }

    /**
     * @param string $id
     * @param string $dateHashPath
     *
     * @return false|string
     */
    public function getXliffFromFileDir(string $id, string $dateHashPath): false|string
    {
        [$datePath,] = explode(DIRECTORY_SEPARATOR, $dateHashPath);

        //compose path
        $path = $this->filesDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . "xliff";

        //return file
        return $this->getSingleFileInPath($path);
    }

    /**
     * @param string $dirToScan
     *
     * @return array{conversionHashes: array<string, mixed>, zipHashes: list<string>}
     */
    public function getHashesFromDir(string $dirToScan): array
    {
        //fetch cache links, created by converter, from a directory
        $linkFiles = $this->filesystem->scandir($dirToScan) ?: [];
        $zipFilesHash = [];
        $filesHashInfo = [];
        //remove dir hardlinks, as uninteresting, as well as regular files; only hash-links
        foreach ($linkFiles as $k => $linkFile) {
            if (str_contains($linkFile, self::ORIGINAL_ZIP_PLACEHOLDER)) {
                $zipFilesHash[] = $linkFile;
                unset($linkFiles[$k]);
            } elseif (str_contains($linkFile, '.') or !str_contains($linkFile, self::OBJECTS_SAFE_DELIMITER)) {
                unset($linkFiles[$k]);
            } else {
                $filesHashInfo['sha'][] = $linkFile;
                $filesHashInfo['fileName'][$linkFile] = $this->filesystem->file(
                    $dirToScan . DIRECTORY_SEPARATOR . $linkFile,
                    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
                );
            }
        }

        return [
            'conversionHashes' => $filesHashInfo,
            'zipHashes' => $zipFilesHash
        ];
    }

    /**
     **********************************************************************************************
     * 3. QUEUE
     **********************************************************************************************
     */

    /**
     * @param string $uploadSession
     *
     * @return void
     * @throws UnexpectedValueException
     * @throws Exception
     */
    public function moveFileFromUploadSessionToQueuePath(string $uploadSession): void
    {
        $destination = AppConfig::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession;
        $this->ensureDirectoryExists($destination);

        $iterator = $this->filesystem->iterateDirectoryRecursive(AppConfig::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession);

        /** @var \SplFileInfo $item */
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                /**
                 * RecursiveIteratorIterator at the C level delegates unknown method calls to
                 * the active sub-iterator (RecursiveDirectoryIterator).
                 *
                 * It's PHP SPL's internal "magic proxy" at the C level.
                 * Unlikely to break (it's been like this since PHP 5.1+), but it's not contractual.
                 *
                 * @noinspection PhpUndefinedMethodInspection
                 */
                $this->filesystem->mkdir($destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            } else {
                /**
                 * RecursiveIteratorIterator at the C level delegates unknown method calls to
                 * the active sub-iterator (RecursiveDirectoryIterator).
                 *
                 * It's PHP SPL's internal "magic proxy" at the C level.
                 * Unlikely to break (it's been like this since PHP 5.1+), but it's not contractual.
                 *
                 * @noinspection PhpUndefinedMethodInspection
                 */
                $subPathName = $iterator->getSubPathName();

                if (stripos($subPathName, "|") !== false) {
                    // Example: aad03b600_3dc4bf3a2d|it-IT → abc12de006__it-IT - where abc12de006 == sha1(aad03b600_3dc4bf3a2d|it-IT)
                    $short_hash = sha1($subPathName);

                    /**
                     * RecursiveIteratorIterator at the C level delegates unknown method calls to
                     * the active sub-iterator (RecursiveDirectoryIterator).
                     *
                     * It's PHP SPL's internal "magic proxy" at the C level.
                     * Unlikely to break (it's been like this since PHP 5.1+), but it's not contractual.
                     *
                     * @noinspection PhpUndefinedMethodInspection
                     */
                    //TODO check this separator: could be the same for S3 and FS ?
                    $pathParts = explode("|", $iterator->getSubPathName());
                    $lang = array_pop($pathParts);
                    $subPathName = $short_hash . self::OBJECTS_SAFE_DELIMITER . $lang;
                }

                $this->filesystem->copy((string)$item, $destination . DIRECTORY_SEPARATOR . $subPathName);
            }
        }

        $this->filesystem->deleteDir(AppConfig::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession);
    }

    /**
     * @inheritDoc
     */
    public function deleteQueue(string $uploadDir): void
    {
        $this->filesystem->deleteDir($uploadDir);
        if ($this->filesystem->isDir($uploadDir . '_converted')) {
            $this->filesystem->deleteDir($uploadDir . '_converted');
        }
    }

    /**
     **********************************************************************************************
     * 4. FAST ANALYSIS
     **********************************************************************************************
     */

    /**
     * @param string $id_project
     * @param array<string|int, mixed> $segments_metadata
     *
     * @return void
     * @throws UnexpectedValueException
     */
    public function storeFastAnalysisFile(string $id_project, array $segments_metadata = []): void
    {
        $storedBytes = $this->filesystem->filePutContents(AppConfig::$ANALYSIS_FILES_REPOSITORY . DIRECTORY_SEPARATOR . "waiting_analysis_$id_project.ser", serialize($segments_metadata));
        if ($storedBytes === false) {
            throw new UnexpectedValueException('Internal error: Failed to store segments for fast analysis on disk.', -14);
        }
    }

    /**
     * @param int $id_project
     *
     * @return array<string|int, mixed>
     * @throws UnexpectedValueException
     */
    public function getFastAnalysisData(int $id_project): array
    {
        $rawContent = $this->filesystem->fileGetContents(AppConfig::$ANALYSIS_FILES_REPOSITORY . DIRECTORY_SEPARATOR . "waiting_analysis_$id_project.ser");
        if ($rawContent === false) {
            throw new UnexpectedValueException('Internal error: Failed to retrieve analysis information from disk.', -15);
        }

        // X2: the .ser blob is untrusted storage; forbid object instantiation to kill the
        // object-injection / gadget-chain surface. The payload is a plain array, so this is
        // transparent; a malformed blob still yields false (suppressed warning) and is handled below.
        $analysisData = @unserialize($rawContent, ['allowed_classes' => false]);
        if ($analysisData === false) {
            throw new UnexpectedValueException('Internal error: Failed to retrieve analysis information from disk.', -15);
        }

        return $analysisData;
    }

    /**
     * @param string $id_project
     *
     * @return bool
     */
    public function deleteFastAnalysisFile(string $id_project): bool
    {
        return $this->filesystem->unlink(AppConfig::$ANALYSIS_FILES_REPOSITORY . DIRECTORY_SEPARATOR . "waiting_analysis_$id_project.ser");
    }

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
     * @throws Exception
     */
    public function cacheZipArchive(string $hash, string $zipPath): bool
    {
        $thisZipDir = $this->zipDir . DIRECTORY_SEPARATOR . $hash . self::ORIGINAL_ZIP_PLACEHOLDER;

        //ensure old stuff is overwritten
        if ($this->filesystem->isDir($thisZipDir)) {
            $this->filesystem->deleteDir($thisZipDir);
        }

        //create cache dir structure
        $created = $this->ensureDirectoryExists($thisZipDir);

        if (!$created) {
            return false;
        }

        //move original
        $outcome1 = $this->filesystem->copy($zipPath, $thisZipDir . DIRECTORY_SEPARATOR . static::getStorageFileName($zipPath));

        if (!$outcome1) {
            //Original directory deleted!!!
            //CLEAR ALL CACHE
            $this->filesystem->deleteDir($this->zipDir . DIRECTORY_SEPARATOR . $hash . self::ORIGINAL_ZIP_PLACEHOLDER);

            return false;
        }

        $this->filesystem->unlink($zipPath);

        //link this zip to the upload directory by creating a file name as the ash of the zip file
        $this->filesystem->touch(dirname($zipPath) . DIRECTORY_SEPARATOR . $hash . self::ORIGINAL_ZIP_PLACEHOLDER);

        return true;
    }

    /**
     * @param string $create_date
     * @param string $zipHash
     * @param string $projectID
     *
     * @return bool
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function linkZipToProject(string $create_date, string $zipHash, string $projectID): bool
    {
        $datePath = $this->getDatePath($create_date);

        $zipFilePath = $this->getSingleFileInPath($this->zipDir . DIRECTORY_SEPARATOR . $zipHash);
        if (!is_string($zipFilePath)) {
            return false;
        }

        $fileName = static::basename_fix($zipFilePath);

        $newZipDir = $this->zipDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $projectID;

        $res = $this->ensureDirectoryExists($newZipDir);
        if (!$res) {
            return false;
        }

        $outcome1 = $this->filesystem->link($zipFilePath, $newZipDir . DIRECTORY_SEPARATOR . $fileName);

        if (!$outcome1) {
            return false;
        }

        $this->filesystem->deleteDir($this->zipDir . DIRECTORY_SEPARATOR . $zipHash);

        return true;
    }

    /**
     * @param string $projectDate
     * @param string $projectID
     * @param string $zipName
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public function getOriginalZipPath(string $projectDate, string $projectID, string $zipName): string
    {
        $date = date_create($projectDate);
        if ($date === false) {
            throw new InvalidArgumentException("Invalid date string: '$projectDate'");
        }

        $zipDir = $this->zipDir . DIRECTORY_SEPARATOR . $date->format('Ymd') . DIRECTORY_SEPARATOR . $projectID;

        $storedPath = $zipDir . DIRECTORY_SEPARATOR . static::getStorageFileName($zipName);
        if ($this->filesystem->fileExists($storedPath)) {
            return $storedPath;
        }

        // a zip stored before the names were hashed keeps its original name
        return $zipDir . DIRECTORY_SEPARATOR . $zipName;
    }

    /**
     * @param string $projectDate
     * @param string $projectID
     *
     * @return string
     * @throws InvalidArgumentException
     */
    public function getOriginalZipDir(string $projectDate, string $projectID): string
    {
        $date = date_create($projectDate);
        if ($date === false) {
            throw new InvalidArgumentException("Invalid date string: '$projectDate'");
        }

        $datePath = $date->format('Ymd');

        return $this->zipDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $projectID;
    }

    /**
     **********************************************************************************************
     * 6. TRANSFER FILES
     **********************************************************************************************
     */

    public function transferFiles(string $source, string $destination): bool
    {
        return true;
    }
}

