<?php

use Model\FilesStorage\FilesStorageFactory;
use Model\FilesStorage\FsFilesStorage;
use Model\FilesStorage\S3FilesStorage;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

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
        AppConfig::$FILE_STORAGE_METHOD = 'fs';

        $fileStorage = FilesStorageFactory::create();

        $this->assertInstanceOf( FsFilesStorage::class, $fileStorage );
    }
}