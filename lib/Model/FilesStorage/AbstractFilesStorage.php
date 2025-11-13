<?php

namespace Model\FilesStorage;

use DirectoryIterator;
use Exception;
use Model\DataAccess\Database;
use PDO;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

/**
 * Class FsFilesStorage
 *
 * INDEX
 * -------------------------------------------------------------------------
 * 1. FILE HANDLING ON FILE SYSTEM
 * 2. CACHE PACKAGE HELPERS
 * 3. PROJECT
 * 4. ZIP ARCHIVES HANDLING
 * 5. MISC
 *
 * @package FilesStorage
 */
abstract class AbstractFilesStorage implements IFilesStorage
{

    const string ORIGINAL_ZIP_PLACEHOLDER = "__##originalZip##";
    const string OBJECTS_SAFE_DELIMITER   = '__';

    protected string $filesDir;
    protected string $cacheDir;
    protected string $zipDir;

    protected ?MatecatLogger $logger;

    /**
     * @param string|null $zip
     */
    public function __construct(?string $zip = null)
    {
        $this->logger = LoggerFactory::getLogger('files');
        if ($zip) {
            $this->zipDir = $zip;
        } else {
            $this->zipDir = AppConfig::$ZIP_REPOSITORY;
        }
    }

    /**
     **********************************************************************************************
     * 1. FILE HANDLING ON FILE SYSTEM
     **********************************************************************************************
     */

    /**
     * @param string $path
     *
     * @return string
     */
    public static function basename_fix(string $path): string
    {
        $rawPath = explode(DIRECTORY_SEPARATOR, $path);

        return array_pop($rawPath);
    }

    /**
     * PHP Pathinfo is not UTF-8 aware, so we rewrite it.
     * It returns an array with complete info about a path
     * <code>
     * [
     *    'dirname' => PATHINFO_DIRNAME,
     *    'basename' => PATHINFO_BASENAME,
     *    'extension' => PATHINFO_EXTENSION,
     *    'filename' => PATHINFO_FILENAME
     * ]
     * </code>
     *
     * @param string $path
     * @param int    $options
     *
     * @return array|string
     */
    public static function pathinfo_fix(string $path, int $options = 15): array|string
    {
        $rawPath = explode(DIRECTORY_SEPARATOR, $path);

        $basename         = array_pop($rawPath);
        $dirname          = implode(DIRECTORY_SEPARATOR, $rawPath);
        $explodedFileName = explode(".", $basename);
        $extension        = strtolower(array_pop($explodedFileName));
        $filename         = implode(".", $explodedFileName);

        $return_array = [];

        $flagMap = [
                'dirname'   => PATHINFO_DIRNAME,
                'basename'  => PATHINFO_BASENAME,
                'extension' => PATHINFO_EXTENSION,
                'filename'  => PATHINFO_FILENAME
        ];

        $fieldValues = [
                'dirname'   => $dirname,
                'basename'  => $basename,
                'extension' => $extension,
                'filename'  => $filename
        ];

        // foreach flag, add in $return_array the corresponding field,
        // obtained by variable name correspondence
        foreach ($flagMap as $field => $i) {
            //binary AND
            if (($options & $i) > 0) {
                //variable substitution: $field can be one between 'dirname', 'basename', 'extension', 'filename'
                $return_array[ $field ] = $fieldValues[ $field ];
            }
        }

        if (count($return_array) == 1) {
            $return_array = array_pop($return_array);
        }

        return $return_array;
    }

    /**
     * @param string $path
     *
     * @return bool|string
     */
    public function getSingleFileInPath(string $path): false|string
    {
        //check if it actually exists
        $filePath = false;
        $files    = [];
        try {
            $files = new DirectoryIterator($path);
        } catch (Exception) {
            //directory does not exist
            LoggerFactory::doJsonLog("Directory $path does not exists. If you are creating a project check the source language.");
        }

        foreach ($files as $file) {
            if ($file->isDot()) {
                continue;
            }

            //get the remaining file (it's the only file in dir)
            $filePath = $path . DIRECTORY_SEPARATOR . $file->getFilename();
            //no need to loop anymore
            break;
        }

        return $filePath;
    }

    /**
     * @return string
     */
    public static function getStorageCachePath(): string
    {
        if (AbstractFilesStorage::isOnS3()) {
            return S3FilesStorage::CACHE_PACKAGE_FOLDER;
        }

        return AppConfig::$CACHE_REPOSITORY;
    }

    /**
     * Delete a hash from the upload directory if the hash is changed
     *
     * @param string $uploadDirPath
     * @param string $linkFile
     *
     * @return bool
     */
    public function deleteHashFromUploadDir(string $uploadDirPath, string $linkFile): bool
    {
        [$shaSum,] = explode("|", $linkFile);
        [$shaSum,] = explode("_", $shaSum); // remove the segmentation rule from hash to clean all reverse index maps

        $iterator = new DirectoryIterator($uploadDirPath);

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot() || $fileInfo->isDir()) {
                continue;
            }

            // remove only the wrong languages, the same code|language must be
            // retained because of the file name append
            if ($fileInfo->getFilename() != $linkFile &&
                    stripos($fileInfo->getFilename(), $shaSum) !== false) {
                unlink($fileInfo->getPathname());
                LoggerFactory::doJsonLog("Deleted Hash " . $fileInfo->getPathname());

                return true;
            }
        }

        return false;
    }

    /**
     * @param string|null $create_date
     *
     * @return string
     */
    public function getDatePath(?string $create_date = null): string
    {
        return date_create($create_date)->format('Ymd');
    }

    /**
     **********************************************************************************************
     * 2. CACHE PACKAGE HELPERS
     **********************************************************************************************
     */

    /**
     * Return an array to build the cache path from a hash
     *
     * @param string $hash
     *
     * @return array
     */
    public static function composeCachePath(string $hash): array
    {
        return [
                'firstLevel'  => $hash[ 0 ] . $hash[ 1 ],
                'secondLevel' => $hash[ 2 ] . $hash[ 3 ],
                'thirdLevel'  => substr($hash, 4)
        ];
    }

    /**
     * @param string $hash
     * @param string $uid
     * @param string $realFileName
     *
     * @return int
     */
    public function linkSessionToCacheForAlreadyConvertedFiles(string $hash, string $uid, string $realFileName): int
    {
        //get upload dir
        $dir = AppConfig::$QUEUE_PROJECT_REPOSITORY . DIRECTORY_SEPARATOR . $uid;

        //create a file in it, which is called as the hash that indicates the location of the cache for storage
        return $this->_linkToCache($dir, $hash, $realFileName);
    }

    /**
     * @param string $hash
     * @param string $uid
     * @param string $realFileName
     *
     * @return int
     */
    public function linkSessionToCacheForOriginalFiles(string $hash, string $uid, string $realFileName): int
    {
        //get upload dir
        $dir = AppConfig::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $uid;

        //create a file in it, which is called as the hash that indicates the location of the cache for storage
        return $this->_linkToCache($dir, $hash, $realFileName);
    }

    /**
     * Appends a string like `$dir.DIRECTORY_SEPARATOR.$hash."|".$lang` (the path in the cache package of a file in the file storage system)
     * on $realFileName file
     *
     * @param string $dir
     * @param string $hash
     * @param string $realFileName
     *
     * @return int
     */
    protected function _linkToCache(string $dir, string $hash, string $realFileName): int
    {
        $filePath     = $dir . DIRECTORY_SEPARATOR . $hash;
        $content      = [];
        $bytesWritten = 0;

        $fp = fopen($filePath, "c+");

        if (flock($fp, LOCK_EX)) {
            $fileRawContent = "";
            while (($buffer = fgets($fp, 4096)) !== false) {
                $fileRawContent .= $buffer;
            }

            if (!empty($fileRawContent)) {
                $content = explode("\n", $fileRawContent);
            }

            ftruncate($fp, 0);
            rewind($fp); // Move the pointer to the beginning

            if (!in_array($realFileName, $content)) {
                $content[] = $realFileName;
            }

            // remove empty lines
            $content = array_filter($content, function ($filename) {
                return !empty($filename);
            });

            $contentString = implode("\n", $content) . "\n";

            $bytesWritten = fwrite($fp, $contentString);
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return $bytesWritten;
    }

    /**
     **********************************************************************************************
     * 3. PROJECT
     **********************************************************************************************
     */

    /**
     * Used when we take the files after the translation (Download)
     *
     * @param int  $id_job
     * @param bool $getXliffPath
     *
     * @return array
     */
    public function getFilesForJob(int $id_job, bool $getXliffPath = true): array
    {
        $query = "SELECT 
              files_job.id_file, 
              files.filename, 
              files.id_project, 
              jobs.source, 
              mime_type, 
              sha1_original_file,
              jobs.create_date
            FROM files_job
            JOIN files ON files.id = files_job.id_file
            JOIN jobs ON jobs.id = files_job.id_job
            WHERE files_job.id_job = :id_job 
            GROUP BY files_job.id_file";

        $db   = Database::obtain();
        $stmt = $db->getConnection()->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(['id_job' => $id_job]);
        $results = $stmt->fetchAll();

        foreach ($results as $k => $result) {
            //try fetching from files dir
            $originalPath = $this->getOriginalFromFileDir($result[ 'id_file' ], $result[ 'sha1_original_file' ]);

            if (!empty($originalPath)) {
                $results[ $k ][ 'originalFilePath' ] = $originalPath;
            }

            //we MUST have the originalFilePath
            if ($getXliffPath) {
                //note that we trust this to succeed on the first try since, at this stage, we already built the file package
                $results[ $k ][ 'xliffFilePath' ] = $this->getXliffFromFileDir($result[ 'id_file' ], $result[ 'sha1_original_file' ]);

                //when we ask for XliffPath ($getXliffPath == true), we are downloading translations
                //  if the original file path is empty means that the file was already a supported xliff type (ex: trados sdlxliff)
                //use the xliff as original
                if (empty($originalPath)) {
                    $results[ $k ][ 'originalFilePath' ] = $results[ $k ][ 'xliffFilePath' ];
                }
            } elseif (empty($originalPath)) {
                //when we do NOT ask for XliffPath ($getXliffPath == false), we are downloading original
                // if the original file path is empty means that the file was already a supported xliff type (ex: trados sdlxliff)
                //// get the original xliff
                $results[ $k ][ 'originalFilePath' ] = $this->getXliffFromFileDir($result[ 'id_file' ], $result[ 'sha1_original_file' ]);
            }

            // this line creates a bug, if the file contains a space at the beginning, we can't download it anymore
            $results[ $k ][ 'mime_type' ] = trim($results[ $k ][ 'mime_type' ]);
        }

        return $results;
    }

    /**
     **********************************************************************************************
     * 4. MISC
     **********************************************************************************************
     */

    /**
     * @return bool
     */
    public static function isOnS3(): bool
    {
        return (AppConfig::$FILE_STORAGE_METHOD === 's3');
    }

}
