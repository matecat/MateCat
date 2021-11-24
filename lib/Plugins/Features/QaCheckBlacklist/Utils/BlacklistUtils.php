<?php

namespace Features\QaCheckBlacklist\Utils;

use Features\QaCheckBlacklist\AbstractBlacklist;
use Features\QaCheckBlacklist\BlacklistFromTextFile;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use FilesStorage\S3FilesStorage;

class BlacklistUtils
{
    /**
     * @param $filePath
     * @param $id_job
     * @param $job_password
     *
     * @throws \Exception
     */
    public static function save($filePath, $id_job, $job_password)
    {
        $fs = FilesStorageFactory::create();
        $fs->saveBlacklistFile($filePath, $id_job, $job_password);
    }

    /**
     * @param string $id_job
     * @param string $job_password
     *
     * @return bool
     * @throws \Exception
     */
    public static function checkIfExists($id_job, $job_password) {

        $isFsOnS3 = AbstractFilesStorage::isOnS3();
        if ( $isFsOnS3 ) {
            $blacklistFilePath   = 'glossary'. DIRECTORY_SEPARATOR . $id_job . DIRECTORY_SEPARATOR . $job_password . DIRECTORY_SEPARATOR . 'blacklist.txt';
            $s3Client            = S3FilesStorage::getStaticS3Client();

            return $s3Client->hasItem( [
                    'bucket' => \INIT::$AWS_STORAGE_BASE_BUCKET,
                    'key' => $blacklistFilePath,
            ] );
        }

        return file_exists(\INIT::$BLACKLIST_REPOSITORY . DIRECTORY_SEPARATOR . $id_job . DIRECTORY_SEPARATOR . $job_password . DIRECTORY_SEPARATOR . 'blacklist.txt');
    }

    /**
     * @param \Jobs_JobStruct $job
     *
     * @return AbstractBlacklist
     * @throws \Exception
     */
    public static function getAbstractBlacklist(\Jobs_JobStruct $job)
    {
        if(false === self::checkIfExists($job->id, $job->password)){
            return new BlacklistFromTextFile( $job->getProject()->getFirstOriginalZipPath(),  $job->id ) ;
        }

        $isFsOnS3 = AbstractFilesStorage::isOnS3();
        if ( $isFsOnS3 ) {
            $blacklistFilePath   = 'glossary'. DIRECTORY_SEPARATOR . $job->id . DIRECTORY_SEPARATOR . $job->password . DIRECTORY_SEPARATOR . 'blacklist.txt';
            $s3Client            = S3FilesStorage::getStaticS3Client();

            $s3Params = [
                    'bucket' => \INIT::$AWS_STORAGE_BASE_BUCKET,
                    'key' => $blacklistFilePath,
                    'save_as' => "/tmp/" . md5($job->id . $job->password . 'blacklist').'.txt'
            ];

            $s3Client->downloadItem( $s3Params );
            $blacklistFilePath = $s3Params[ 'save_as' ];
        } else {
            $blacklistFilePath = \INIT::$BLACKLIST_REPOSITORY . DIRECTORY_SEPARATOR . $job->id . DIRECTORY_SEPARATOR . $job->password . DIRECTORY_SEPARATOR . 'blacklist.txt';
        }

        return new BlacklistFromTextFile( $blacklistFilePath,  $job->id ) ;
    }
}