<?php

namespace Features\QaCheckBlacklist\Utils;

use Features\QaCheckBlacklist\AbstractBlacklist;
use Features\QaCheckBlacklist\BlacklistFromTextFile;
use Features\QaCheckBlacklist\BlacklistFromZip;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use FilesStorage\S3FilesStorage;

class BlacklistUtils
{
    /**
     * @var
     */
    private $redis;

    public function __construct(\Predis\Client $redis) {
        $this->redis = $redis;
    }

    /**
     * @param $filePath
     * @param $id_job
     * @param $job_password
     *
     * @throws \Exception
     */
    public function save($filePath, $id_job, $job_password)
    {
        if(false === $this->checkIfExists($id_job, $job_password)){
            $fs = FilesStorageFactory::create();
            $fs->saveBlacklistFile($filePath, $id_job, $job_password);
        }
    }

    /**
     * @param string $id_job
     * @param string $job_password
     *
     * @return bool
     * @throws \Exception
     */
    public function checkIfExists($id_job, $job_password) {

        $keyOnCache = md5('checkIfExistsBlacklist-'.$id_job.'-'.$job_password);

        if($this->redis->exists($keyOnCache)){
            return $this->redis->get($keyOnCache);
        }

        $isFsOnS3 = AbstractFilesStorage::isOnS3();
        if ( $isFsOnS3 ) {
            $blacklistFilePath   = 'glossary'. DIRECTORY_SEPARATOR . $id_job . DIRECTORY_SEPARATOR . $job_password . DIRECTORY_SEPARATOR . 'blacklist.txt';
            $s3Client            = S3FilesStorage::getStaticS3Client();

            $checkIfExists = $s3Client->hasItem( [
                'bucket' => \INIT::$AWS_STORAGE_BASE_BUCKET,
                'key' => $blacklistFilePath,
            ] );
        } else {
            $checkIfExists = file_exists(\INIT::$BLACKLIST_REPOSITORY . DIRECTORY_SEPARATOR . $id_job . DIRECTORY_SEPARATOR . $job_password . DIRECTORY_SEPARATOR . 'blacklist.txt');
        }

        $this->ensureCached($keyOnCache, $checkIfExists);

        return $keyOnCache;
    }

    /**
     * @param \Jobs_JobStruct $job
     *
     * @return AbstractBlacklist
     * @throws \Exception
     */
    public function getAbstractBlacklist(\Jobs_JobStruct $job)
    {
        if(false === $this->checkIfExists($job->id, $job->password)){
            return new BlacklistFromZip( $job->getProject()->getFirstOriginalZipPath(),  $job->id ) ;
        }

        $keyOnCache = md5('getAbstractBlacklist-'.$job->id.'-'.$job->password);

        if($this->redis->exists($keyOnCache)){
            return unserialize($this->redis->get($keyOnCache));
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

        $blacklistFromTextFile = new BlacklistFromTextFile( $blacklistFilePath,  $job->id );
        $this->ensureCached($keyOnCache, serialize($blacklistFromTextFile));

        return $blacklistFromTextFile;
    }

    /**
     * Ensure cache in Redis
     *
     * @param $key
     * @param $content
     */
    private function ensureCached($key, $content) {
        $redis   = new \Predis\Client( \INIT::$REDIS_SERVERS );

        if ( !$redis->exists( $key ) ) {
            $redis->set( $key, $content );
            $redis->expire( $key, 60 * 60 * 24 * 30 ) ; // 1 month
        }
    }
}