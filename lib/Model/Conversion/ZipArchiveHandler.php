<?php

namespace Model\Conversion;

use Exception;
use Model\Conversion\MimeTypes\MimeTypes;
use Model\FilesStorage\AbstractFilesStorage;
use RuntimeException;
use Utils\Registry\AppConfig;
use ZipArchive;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 19/06/15
 * Time: 13.43
 */
class ZipArchiveHandler extends ZipArchive
{

    const string REFERENCE_FOLDER = '__reference';
    const string META_FOLDER      = '__meta';
    const string PREVIEWS_FOLDER  = '__previews';

    const int    MAX_VISITED_DEPTH             = 5;
    const int    MAX_VISITED_FOLDERS_PER_DEPTH = 10;
    const int    MAX_FOLDERS                   = 100;
    const string INTERNAL_SEPARATOR            = "___SEP___";

    const string ARRAY_FILES_PREFIX = "@@_prefix_@@";

    public array $tree     = [];
    public array $treeList = [];

    protected static int $MAX_FILES;

    public function message($code): string
    {
        return match ($code) {
            0 => 'No error',
            1 => 'Multi-disk zip archives not supported',
            2 => 'Renaming temporary file failed',
            3 => 'Closing zip archive failed',
            4 => 'Seek error',
            5 => 'Read error',
            6 => 'Write error',
            7 => 'CRC error',
            8 => 'Containing zip archive was closed',
            9 => 'No such file',
            10 => 'File already exists',
            11 => 'Can\'t open file',
            12 => 'Failure to create temporary file',
            13 => 'Zlib error',
            14 => 'Malloc failure',
            15 => 'Entry has been changed',
            16 => 'Compression method not supported',
            17 => 'Premature EOF',
            18 => 'Invalid argument',
            19 => 'Not a zip archive',
            20 => 'Internal error',
            21 => 'Zip archive inconsistent',
            22 => 'Can\'t remove file',
            23 => 'Entry has been deleted',
            default => 'An unknown error has occurred(' . intval($code) . ')',
        };
    }

    public function isDir(string $path): bool
    {
        return substr($path, -1) == DIRECTORY_SEPARATOR;
    }

    /**
     * @throws Exception
     */
    public function createTree(): void
    {
        self::$MAX_FILES = AppConfig::$MAX_NUM_FILES;

        $Tree              = [];
        $path2numOfFolders = [];
        $filePaths         = [];

        $numOfFolders = 0;
        $numOfFiles   = 0;

        for ($i = 0; $i < $this->numFiles; $i++) {
            $path = $this->getNameIndex($i);

            $pathBySlash = array_values(explode('/', $path));

            if ($pathBySlash[ 0 ] == '__MACOSX') {
                continue;
            }

            if ($pathBySlash[ 0 ] == self::REFERENCE_FOLDER) {
                continue;
            }

            if ($pathBySlash[ 0 ] == self::META_FOLDER) {
                continue;
            }

            if ($pathBySlash[ 0 ] == self::PREVIEWS_FOLDER) {
                continue;
            }

            if (end($pathBySlash) == '.DS_Store') {
                continue;
            }

            $pathBySlash = array_map([ZipArchiveHandler::class, 'treeKey'], $pathBySlash);

            $pathWithoutFile = $pathBySlash;
            $fileName        = array_pop($pathWithoutFile);
            array_pop($pathWithoutFile);
            //remove the last element, which is the file name, and the second last, which is the folder name
            $pathWithoutFile = implode(DIRECTORY_SEPARATOR, $pathWithoutFile);

            if ($pathWithoutFile != "" && !isset($path2numOfFolders[ $pathWithoutFile ])) {
                $path2numOfFolders[ $pathWithoutFile ] = 0;
            }

            if ($pathWithoutFile != "" && $fileName == self::ARRAY_FILES_PREFIX) {    //this is the path of a directory: add directory count
                $path2numOfFolders[ $pathWithoutFile ]++;
                $numOfFolders++;
            } else {
                $numOfFiles++;
            }

            $c = count($pathBySlash);

            if ($c > self::MAX_VISITED_DEPTH + 1) { //+1 makes the algo ignore the file, whether it exists or not
                throw new Exception("Max allowed depth exceeded.", -1);
            }

            if ($numOfFiles > self::$MAX_FILES || $numOfFolders > self::MAX_FOLDERS) {
                throw new Exception("Max number of files or folders exceeded.", -2);
            }


            $folderCanBeVisited = true;
            //check that every ancestor folder has a number of folders below the allowed threshold
            foreach ($path2numOfFolders as $file_path => $number) {
                if (@strpos($pathWithoutFile, $file_path) > -1 && $number > self::MAX_VISITED_FOLDERS_PER_DEPTH) {
                    $folderCanBeVisited = false;
                    break;
                }
            }

            if (!$folderCanBeVisited) {
                throw new Exception("Max number of folders per depth exceeded.", -3);
            }

            if ($fileName != "" && $fileName != self::ARRAY_FILES_PREFIX) {
                $filePaths[] = $path;
            }

            $temp = &$Tree;
            for ($j = 0; $j < $c - 1; $j++) {
                $count       = 1;
                $originalKey = str_replace(self::ARRAY_FILES_PREFIX, "", $pathBySlash[ $j ], $count);
                if (!isset($temp[ $originalKey ])) {
                    $temp[ $originalKey ] = [];
                }
                $temp = &$temp[ $originalKey ];
            }

            $last_originalKey = str_replace(self::ARRAY_FILES_PREFIX, "", $pathBySlash[ $c - 1 ], $count);
            if ($this->isDir($path)) {
                $temp[ $last_originalKey ] = [];
            } else {
                $temp[] = $last_originalKey;
            }
        }

        $this->tree     = $Tree;
        $this->treeList = array_unique($filePaths);
        $this->treeList = str_replace(DIRECTORY_SEPARATOR, self::INTERNAL_SEPARATOR, $this->treeList);
        $this->treeList = array_map([ZipArchiveHandler::class, 'prependZipFileName'], $this->treeList);
    }

    public function extractFilesInTmp(string $tmp_folder): array
    {
        $filesArray = [];
        $fileErrors = [];

        //pre: createTree() must have been called so that $this->treeList is not empty.
        foreach ($this->treeList as $filePath) {
            $realPath = str_replace(
                    [self::INTERNAL_SEPARATOR, AbstractFilesStorage::pathinfo_fix($this->filename, PATHINFO_BASENAME)],
                    [DIRECTORY_SEPARATOR, ""],
                    $filePath
            );

            $realPath = ltrim($realPath, "/");

            $fp = $this->getStream($realPath);

            $tmpFp = fopen($tmp_folder . $filePath, "w");

            if (!$fp) {
                throw new RuntimeException("Unable to extract the file.");
            }

            $sizeExceeded = false;
            $fileSize     = 0;
            while (!feof($fp) && !$sizeExceeded) {
                $realSize = fwrite($tmpFp, fread($fp, 8192));
                $fileSize += $realSize;

                if ($fileSize > AppConfig::$MAX_UPLOAD_FILE_SIZE) {
                    $sizeExceeded = true;
                }
            }

            if ($sizeExceeded) {
                $fileErrors[ $filePath ] = 'Max upload file size exceeded.';
            }

            fclose($fp);
            fclose($tmpFp);

            $filesArray[ $filePath ] = [
                    'size'     => $fileSize,
                    'name'     => $filePath,
                    'tmp_name' => $tmp_folder . $filePath,
            ];
        }

        foreach ($filesArray as $filePath => &$objectFile) {
            $objectFile[ 'error' ] = $fileErrors[ $filePath ] ?? null;
            $objectFile[ 'type' ]  = (new MimeTypes())->guessMimeType($tmp_folder . $filePath);
        }

        return $filesArray;
    }

    private function treeKey(string $key): string
    {
        return self::ARRAY_FILES_PREFIX . $key;
    }

    private function prependZipFileName(string $fName): string
    {
        return AbstractFilesStorage::pathinfo_fix($this->filename, PATHINFO_BASENAME) . self::INTERNAL_SEPARATOR . $fName;
    }

    /**
     * Gets path information for a file unzipped using ZipArchiveExtended.
     *
     * @param $path        string A valid ZipArchiveExtended path. It must be a path that uses the internal separator of this class.
     *
     * @return array|null Returns null if the path is not valid, otherwise it will return the array returned by pathinfo() function, plus a 'zipfilename' key, containing the zip file name.
     */
    public static function zipPathInfo(string $path): ?array
    {
        if (!str_contains($path, self::INTERNAL_SEPARATOR)) {
            return null;
        }
        $path = explode(self::INTERNAL_SEPARATOR, $path);

        $zipFile  = array_shift($path);
        $basename = array_pop($path);

        $filenameInfo = explode(".", $basename);
        $extension    = array_pop($filenameInfo);
        $filename     = implode(".", $filenameInfo);
        $dirname      = implode(DIRECTORY_SEPARATOR, $path);

        return [
                'dirname'     => $dirname,
                'basename'    => $basename,
                'extension'   => $extension,
                'filename'    => $filename,
                'zipfilename' => $zipFile
        ];
    }

    /**
     * @param $internalFileName string
     *
     * @return string
     */
    public static function getFileName(string $internalFileName): string
    {
        $path = explode(self::INTERNAL_SEPARATOR, $internalFileName);

        return implode(DIRECTORY_SEPARATOR, $path);
    }

    /**
     * @param $fileName string
     *
     * @return string
     */
    public static function getInternalFileName(string $fileName): string
    {
        $path = explode(DIRECTORY_SEPARATOR, $fileName);

        return implode(self::INTERNAL_SEPARATOR, $path);
    }


}
