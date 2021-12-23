<?php

namespace Features\QaCheckBlacklist\Utils;

use Features\QaCheckBlacklist\AbstractBlacklist;
use Features\QaCheckBlacklist\BlacklistFromTextFile;
use Features\QaCheckBlacklist\BlacklistFromZip;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use FilesStorage\S3FilesStorage;
use Glossary\Blacklist\BlacklistDao;

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
     * @param int $id
     *
     * @throws \Exception
     */
    public function delete($id){

        $dao = new BlacklistDao();
        $model = $dao->getById($id);

        $fs = FilesStorageFactory::create();
        $fs->deleteBlacklistFile($model->file_path);
        $dao->deleteById($id);

        $this->clearCached(md5('checkIfExistsBlacklist-'.$model->id_job.'-'.$model->password));
        $this->clearCached(md5('getAbstractBlacklist-'.$model->id_job.'-'.$model->password));
    }

    /**
     * @param $id
     *
     * @return array
     * @throws \Exception
     */
    public function getContent($id) {

        $dao = new BlacklistDao();
        $model = $dao->getById($id);

        // Set a cache on jobdao requests here because
        // we need only id and password here and they do not changes,
        // btw even if they changes, a new redis value is set.
        $job = \Jobs_JobDao::getByIdAndPassword($model->id_job, $model->password, 5 * 60);
        $blacklist = $this->getAbstractBlacklist($job);

        return explode("\n", $blacklist->getContent());
    }

    /**
     * @param                     $filePath
     * @param \Chunks_ChunkStruct $chunkStruct
     * @param null                $uid
     *
     * @return mixed
     * @throws \Predis\Connection\ConnectionException
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function save($filePath, \Chunks_ChunkStruct $chunkStruct, $uid = null)
    {
        if(false === $this->checkIfExists($chunkStruct->id, $chunkStruct->password)) {
            $fs = FilesStorageFactory::create();

            return $fs->saveBlacklistFile($filePath, $chunkStruct, $uid);
        }

        $dao = new BlacklistDao();
        $model = $dao->getByJobIdAndPassword($chunkStruct->id, $chunkStruct->password);

        return $model->id;
    }

    /**
     * @param $jid
     * @param $password
     *
     * @return bool
     */
    public function checkIfExists($jid, $password) {

        $keyOnCache = md5('checkIfExistsBlacklist-'.$jid.'-'.$password);

        if($this->redis->exists($keyOnCache)){
            return $this->redis->get($keyOnCache) === 'TRUE';
        }

        $dao = new BlacklistDao();
        $model = $dao->getByJobIdAndPassword($jid, $password);
        $checkIfExists = ($model !== null) ? 'TRUE' : 'FALSE';

        $this->ensureCached($keyOnCache, $checkIfExists);

        return $checkIfExists;
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

            if(!file_exists("/tmp/glossary/")){
                mkdir("/tmp/glossary/", 0755);
            }

            $s3Params = [
                    'bucket' => \INIT::$AWS_STORAGE_BASE_BUCKET,
                    'key' => $blacklistFilePath,
                    'save_as' => "/tmp/glossary/" . md5($job->id . $job->password . 'blacklist').'.txt'
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
        if ( !$this->redis->exists( $key ) ) {
            $this->redis->set( $key, $content );
            $this->redis->expire( $key, 60 * 60 * 24 * 30 ) ; // 1 month
        }
    }

    /**
     * Delete cache key in Redis
     *
     * @param $key
     */
    private function clearCached($key) {
        if ( $this->redis->exists( $key ) ) {
            $this->redis->del( $key ) ;
        }
    }
}