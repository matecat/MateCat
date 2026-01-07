<?php

namespace Model\FilesStorage;

use FilesystemIterator;
use Matecat\XliffParser\Utils\Files as XliffFiles;
use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;
use Model\FilesStorage\Exceptions\FileSystemException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;
use Utils\Registry\AppConfig;
use Utils\Tools\Utils;

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
     * @param string|null $files
     * @param string|null $cache
     * @param string|null $zip
     */
    public function __construct(?string $files = null, ?string $cache = null, ?string $zip = null)
    {
        parent::__construct($zip);
        //override default config
        if ($files) {
            $this->filesDir = $files;
        } else {
            $this->filesDir = AppConfig::$FILES_REPOSITORY;
        }

        if ($cache) {
            $this->cacheDir = $cache;
        } else {
            $this->cacheDir = AppConfig::$CACHE_REPOSITORY;
        }
    }

    protected static function ensureDirectoryExists(string $path): bool
    {
        if (!file_exists($path)) {
            return mkdir($path, 0755, true);
        }

        return true;
    }

    /**
     **********************************************************************************************
     * 1. CACHE PACKAGE
     **********************************************************************************************
     */

    /**
     * @param string $hash
     * @param string $lang
     * @param string|null $originalPath
     * @param string $xliffPath
     *
     * @return bool
     * @throws FileSystemException
     */
    public function makeCachePackage(string $hash, string $lang, ?string $originalPath, string $xliffPath): bool
    {
        $cacheTree = implode(DIRECTORY_SEPARATOR, static::composeCachePath($hash));

        //don't save in cache when a specified filter version is forced
        if (AppConfig::$FILTERS_SOURCE_TO_XLIFF_FORCE_VERSION !== false && file_exists($this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . "|" . $lang)) {
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
            //if there is not an original path this is an unconverted file,
            // the original does not exist
            // detect which type of xliff
            //check also for the extension, if already present do not force
            $force_extension = "";
            $fileType = XliffProprietaryDetect::getInfo($xliffPath);
            if (!$fileType['proprietary'] && $fileType['info']['extension'] != 'sdlxliff') {
                $force_extension = '.sdlxliff';
            }

            //use original xliff
            $xliffDestination = $cacheDir . DIRECTORY_SEPARATOR . "work" . DIRECTORY_SEPARATOR . static::basename_fix($xliffPath) . $force_extension;
        } else {
            //move original
            $raw_file_path = explode(DIRECTORY_SEPARATOR, $originalPath);
            $file_name = array_pop($raw_file_path);

            $outcome1 = copy($originalPath, $cacheDir . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . $file_name);

            if (!$outcome1) {
                // Original directory deleted!!!
                // CLEAR ALL CACHE

                $cacheDirToDelete = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang;

                // check if cache dir exists
                if (!file_exists($cacheDirToDelete)) {
                    throw new FileSystemException($cacheDirToDelete . ' directory does not exists. Maybe there is a problem with folder permissions.');
                }

                Utils::deleteDir($cacheDirToDelete);

                return false;
            }

            $file_extension = '.sdlxliff';

            //set naming for converted xliff
            $xliffDestination = $cacheDir . DIRECTORY_SEPARATOR . "work" . DIRECTORY_SEPARATOR . $file_name . $file_extension;
        }

        //move converted xliff
        //In Unix you can't rename or move between filesystems,
        //Instead you must copy the file from one source location to the destination location, then delete the source.
        $outcome2 = copy($xliffPath, $xliffDestination);

        if (!$outcome2) {
            //Original directory deleted!!!
            //CLEAR ALL CACHE - FATAL

            $cacheDirToDelete = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang;

            // check if cache dir exists
            if (!file_exists($cacheDirToDelete)) {
                throw new FileSystemException($cacheDirToDelete . ' directory does not exists. Maybe there is a problem with folder permissions.');
            }

            Utils::deleteDir($this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang);

            return false;
        }

        unlink($xliffPath);

        return true;
    }

    /**
     * Rebuild the filename that will be taken from disk in the cache directory
     *
     * @param $hash
     * @param $lang
     *
     * @return bool|string
     */
    public function getOriginalFromCache($hash, $lang): false|string
    {
        //compose path
        $cacheTree = implode(DIRECTORY_SEPARATOR, static::composeCachePath($hash));

        $path = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "orig";

        //return file
        $filePath = $this->getSingleFileInPath($path);

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
     * @return bool|string
     */
    public function getXliffFromCache(string $hash, string $lang): false|string
    {
        $cacheTree = implode(DIRECTORY_SEPARATOR, static::composeCachePath($hash));

        //compose path
        $path = $this->cacheDir . DIRECTORY_SEPARATOR . $cacheTree . self::OBJECTS_SAFE_DELIMITER . $lang . DIRECTORY_SEPARATOR . "package" . DIRECTORY_SEPARATOR . "work";

        //return file
        return $this->getSingleFileInPath($path);
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
        if (!is_dir($fileDir)) {
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

        $origFilePath = $this->getSingleFileInPath($origDir);
        $tmpOrigFileName = $origFilePath;
        if (is_file($origFilePath)) {
            /*
             * Force the new filename if it is provided
             */
            if (!empty($newFileName)) {
                $tmpOrigFileName = $newFileName;
            }
            $res &= link($origFilePath, $fileDir . DIRECTORY_SEPARATOR . "orig" . DIRECTORY_SEPARATOR . static::basename_fix($tmpOrigFileName));
        }

        //work
        /*
         * Force the new filename if it is provided
         */
        $d = $cacheDir . DIRECTORY_SEPARATOR . "work";
        $this->logger->debug($d);
        $convertedFilePath = $this->getSingleFileInPath($d);

        $this->logger->debug($convertedFilePath);

        $tmpConvertedFilePath = $convertedFilePath;
        if (!empty($newFileName)) {
            if (!XliffFiles::isXliff($newFileName)) {
                $convertedExtension = static::pathinfo_fix($convertedFilePath, PATHINFO_EXTENSION);
                $tmpConvertedFilePath = $newFileName . "." . $convertedExtension;
            }
        }

        $this->logger->debug($convertedFilePath);

        $dest = $fileDir . DIRECTORY_SEPARATOR . "xliff" . DIRECTORY_SEPARATOR . static::basename_fix($tmpConvertedFilePath);

        $this->logger->debug($dest);

        $res &= link($convertedFilePath, $dest);

        if (!$res) {
            throw new UnexpectedValueException('Internal Error: Failed to create/copy the file on disk from cache.', -13);
        }

        return (bool)$res;
    }

    /**
     * Rebuild the filename that will be taken from disk in files directory
     *
     * @param string $id
     * @param string $dateHashPath
     *
     * @return bool|string
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
     * @return bool|string
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
     * @return array
     */
    public function getHashesFromDir(string $dirToScan): array
    {
        //fetch cache links, created by converter, from a directory
        $linkFiles = scandir($dirToScan) ?: [];
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
                $filesHashInfo['fileName'][$linkFile] = file(
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
     */
    public static function moveFileFromUploadSessionToQueuePath(string $uploadSession): void
    {
        $destination = AppConfig::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession;
        self::ensureDirectoryExists($destination);

        /** @var RecursiveDirectoryIterator $iterator */
        foreach (
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(AppConfig::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            ) as $item
        ) {
            if ($item->isDir()) {
                mkdir($destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            } else {
                $subPathName = $iterator->getSubPathName();

                if (stripos($subPathName, "|") !== false) {
                    // Example: aad03b600_3dc4bf3a2d|it-IT → abc12de006__it-IT - where abc12de006 == sha1(aad03b600_3dc4bf3a2d|it-IT)
                    $short_hash = sha1($subPathName);

                    //XXX check this separator: could be the same for S3 and FS ?
                    $pathParts = explode("|", $iterator->getSubPathName());
                    $lang = array_pop($pathParts);
                    $subPathName = $short_hash . self::OBJECTS_SAFE_DELIMITER . $lang;
                }

                copy($item, $destination . DIRECTORY_SEPARATOR . $subPathName);
            }
        }

        Utils::deleteDir(AppConfig::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uploadSession);
    }

    /**
     **********************************************************************************************
     * 4. FAST ANALYSIS
     **********************************************************************************************
     */

    /**
     * @param string $id_project
     * @param array $segments_metadata
     */
    public static function storeFastAnalysisFile(string $id_project, array $segments_metadata = []): void
    {
        $storedBytes = file_put_contents(AppConfig::$ANALYSIS_FILES_REPOSITORY . DIRECTORY_SEPARATOR . "waiting_analysis_$id_project.ser", serialize($segments_metadata));
        if ($storedBytes === false) {
            throw new UnexpectedValueException('Internal Error: Failed to store segments for fast analysis on disk.', -14);
        }
    }

    /**
     * @param int $id_project
     *
     * @return array
     */
    public static function getFastAnalysisData(int $id_project): array
    {
        $analysisData = unserialize(file_get_contents(AppConfig::$ANALYSIS_FILES_REPOSITORY . DIRECTORY_SEPARATOR . "waiting_analysis_$id_project.ser"));
        if ($analysisData === false) {
            throw new UnexpectedValueException('Internal Error: Failed to retrieve analysis information from disk.', -15);
        }

        return $analysisData;
    }

    /**
     * @param string $id_project
     *
     * @return bool
     */
    public static function deleteFastAnalysisFile(string $id_project): bool
    {
        return unlink(AppConfig::$ANALYSIS_FILES_REPOSITORY . DIRECTORY_SEPARATOR . "waiting_analysis_$id_project.ser");
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
     */
    public function cacheZipArchive(string $hash, string $zipPath): bool
    {
        $thisZipDir = $this->zipDir . DIRECTORY_SEPARATOR . $hash . self::ORIGINAL_ZIP_PLACEHOLDER;

        //ensure old stuff is overwritten
        if (is_dir($thisZipDir)) {
            Utils::deleteDir($thisZipDir);
        }

        //create cache dir structure
        $created = $this->ensureDirectoryExists($thisZipDir);

        if (!$created) {
            return false;
        }

        //move original
        $outcome1 = copy($zipPath, $thisZipDir . DIRECTORY_SEPARATOR . static::basename_fix($zipPath));

        if (!$outcome1) {
            //Original directory deleted!!!
            //CLEAR ALL CACHE
            Utils::deleteDir($this->zipDir . DIRECTORY_SEPARATOR . $hash . self::ORIGINAL_ZIP_PLACEHOLDER);

            return false;
        }

        unlink($zipPath);

        //link this zip to the upload directory by creating a file name as the ash of the zip file
        touch(dirname($zipPath) . DIRECTORY_SEPARATOR . $hash . self::ORIGINAL_ZIP_PLACEHOLDER);

        return true;
    }

    /**
     * @param string $create_date
     * @param string $zipHash
     * @param string $projectID
     *
     * @return bool
     */
    public function linkZipToProject(string $create_date, string $zipHash, string $projectID): bool
    {
        $datePath = $this->getDatePath($create_date);

        $fileName = static::basename_fix($this->getSingleFileInPath($this->zipDir . DIRECTORY_SEPARATOR . $zipHash));

        //destination dir
        $newZipDir = $this->zipDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $projectID;

        //check if it doesn't exist
        //make files' directory structure
        $res = $this->ensureDirectoryExists($newZipDir);
        if (!$res) {
            return false;
        }

        //link original
        $outcome1 = link($this->getSingleFileInPath($this->zipDir . DIRECTORY_SEPARATOR . $zipHash), $newZipDir . DIRECTORY_SEPARATOR . $fileName);

        if (!$outcome1) {
            //Failed to copy the original file zip
            return false;
        }

        Utils::deleteDir($this->zipDir . DIRECTORY_SEPARATOR . $zipHash);

        return true;
    }

    /**
     * @param string $projectDate
     * @param string $projectID
     * @param string $zipName
     *
     * @return string
     */
    public function getOriginalZipPath(string $projectDate, string $projectID, string $zipName): string
    {
        $datePath = date_create($projectDate)->format('Ymd');

        return $this->zipDir . DIRECTORY_SEPARATOR . $datePath . DIRECTORY_SEPARATOR . $projectID . DIRECTORY_SEPARATOR . $zipName;
    }

    /**
     * @param string $projectDate
     * @param string $projectID
     *
     * @return string
     */
    public function getOriginalZipDir(string $projectDate, string $projectID): string
    {
        $datePath = date_create($projectDate)->format('Ymd');

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

