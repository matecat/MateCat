<?php

namespace FilesStorage;

use Exception;
use INIT;

class FilesStorageFactory
{
    /**
     * @return AbstractFilesStorage
     * @throws Exception
     */
    public static function create()
    {
        $storageMethod = !empty( INIT::$FILE_STORAGE_METHOD ) ? INIT::$FILE_STORAGE_METHOD : 'fs';

        if($storageMethod === 'fs'){
            return new FsFilesStorage();
        }

        return new S3FilesStorage();
    }
}