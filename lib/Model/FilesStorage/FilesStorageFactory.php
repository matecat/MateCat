<?php

namespace FilesStorage;

class FilesStorageFactory
{
    /**
     * @return AbstractFilesStorage
     * @throws \Exception
     */
    public static function create()
    {
        $storageMetohd = ( \INIT::$FILE_STORAGE_METHOD ) ? \INIT::$FILE_STORAGE_METHOD : 'fs';

        if($storageMetohd === 'fs'){
            return new FsFilesStorage();
        }

        return new S3FilesStorage();
    }
}