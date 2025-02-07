<?php

use FilesStorage\FilesStorageFactory;
use FilesStorage\FsFilesStorage;
use FilesStorage\S3FilesStorage;
use TestHelpers\AbstractTest;

class FilesStorageFactoryTest extends AbstractTest {

    /**
     * @test
     */
    public function test_it_initialize_S3FilesStorage() {
        $fileStorage = FilesStorageFactory::create();


        $this->assertInstanceOf( S3FilesStorage::class, $fileStorage );
    }

    /**
     * @test
     */
    public function test_it_initialize_FilesStorage() {
        INIT::$FILE_STORAGE_METHOD = 'fs';

        $fileStorage = FilesStorageFactory::create();

        $this->assertInstanceOf( FsFilesStorage::class, $fileStorage );
    }
}