<?php

namespace FilesStorage;

interface IFilesStorage {

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
     * @param      $hash
     * @param      $lang
     * @param bool $originalPath
     * @param      $xliffPath
     *
     * @return mixed
     */
    public function makeCachePackage( $hash, $lang, $originalPath = false, $xliffPath );


    /**
     * Creates the files folder.
     * Directory structure:
     *
     * files
     *    |_YYYYMMDD
     *      |_{id}
     *          |_orig
     *          |_package
     *          |_work
     *
     * @param      $dateHashPath
     * @param      $lang
     * @param      $idFile
     * @param null $newFileName
     *
     * @return mixed
     */
    public function moveFromCacheToFileDir( $dateHashPath, $lang, $idFile, $newFileName = null );
}
