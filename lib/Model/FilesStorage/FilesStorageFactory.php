<?php

namespace Model\FilesStorage;

use Exception;
use Utils\Registry\AppConfig;

class FilesStorageFactory
{
    /**
     * @return AbstractFilesStorage
     * @throws Exception
     */
    public static function create(): AbstractFilesStorage
    {
        $storageMethod = !empty(AppConfig::$FILE_STORAGE_METHOD) ? AppConfig::$FILE_STORAGE_METHOD : 'fs';

        if ($storageMethod === 'fs') {
            return new FsFilesStorage();
        }

        return new S3FilesStorage();
    }
}