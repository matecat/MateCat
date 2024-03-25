<?php

namespace Features\QaCheckBlacklist\Utils;

use Chunks_ChunkDao;
use Chunks_ChunkStruct;
use Exception;
use Features\QaCheckBlacklist\AbstractBlacklist;
use Features\QaCheckBlacklist\BlacklistFromTextFile;
use Features\QaCheckBlacklist\BlacklistFromZip;
use FilesStorage\AbstractFilesStorage;
use FilesStorage\FilesStorageFactory;
use FilesStorage\S3FilesStorage;
use Glossary\Blacklist\BlacklistDao;
use INIT;
use Jobs_JobDao;
use Jobs_JobStruct;
use Log;
use Predis\Client;
use Predis\Connection\ConnectionException;
use ReflectionException;
use Translations\WarningDao;

class BlacklistUtils
{
    const CHECK_IF_EXISTS_REDIS_KEY = 'checkIfExistsBlacklist';
    const GET_LIST_REDIS_KEY = 'getAbstractBlacklist';

    /**
     * @var
     */
    private $redis;

    public function __construct( Client $redis) {
        $this->redis = $redis;
    }

    /**
     * @param int $id
     *
     * @throws Exception
     */
    public function delete($id){

        $dao = new BlacklistDao();
        $model = $dao->getById($id);

        $fs = FilesStorageFactory::create();
        $fs->deleteBlacklistFile($model->file_path);
        $dao->deleteById($id);

        $this->clearCached($this->checkIfExistsRedisKey($model->id_job, $model->password));
        $this->clearCached($this->getListRedisKey($model->id_job, $model->password));
        $this->clearCached($this->getJobCacheRedisKey($model->id_job, $model->password));

        // delete all translation_warnings associated to this job
        $chunk = Chunks_ChunkDao::getByIdAndPassword($model->id_job, $model->password);
        $warnings = WarningDao::findByChunkAndScope($chunk, 'blacklist');

        foreach ($warnings as $warning){
            WarningDao::deleteByScope($model->id_job, $warning->id_segment, 'blacklist');
        }
    }

    /**
     * @param $id
     *
     * @return array
     * @throws Exception
     */
    public function getContent($id)
    {
        $dao = new BlacklistDao();
        $model = $dao->getById($id);

        // Set a cache on jobdao requests here because
        // we need only id and password here and they do not changes,
        // btw even if they changes, a new redis value is set.
        $job = Jobs_JobDao::getByIdAndPassword($model->id_job, $model->password, 5 * 60);
        $blacklist = $this->getAbstractBlacklist($job);

        return $blacklist->getWords();
    }

    /**
     * @param                     $filePath
     * @param Chunks_ChunkStruct $chunkStruct
     * @param null                $uid
     *
     * @return mixed
     * @throws ConnectionException
     * @throws ReflectionException
     * @throws Exception
     */
    public function save($filePath, Chunks_ChunkStruct $chunkStruct, $uid = null)
    {
        if(false === $this->checkIfExists($chunkStruct->id, $chunkStruct->password)) {
            $fs = FilesStorageFactory::create();
            $id = $fs->saveBlacklistFile($filePath, $chunkStruct, $uid);

            if($id){
                $this->ensureCached($this->checkIfExistsRedisKey($chunkStruct->id, $chunkStruct->password), 'TRUE');

                return $id;
            }
        }

        $dao = new BlacklistDao();
        $model = $dao->getByJobIdAndPassword($chunkStruct->id, $chunkStruct->password);
        $this->ensureCached($this->checkIfExistsRedisKey($chunkStruct->id, $chunkStruct->password), 'TRUE');

        return $model->id;
    }

    /**
     * @param $jid
     * @param $password
     *
     * @return bool
     */
    public function checkIfExists($jid, $password)
    {
        $keyOnCache = $this->checkIfExistsRedisKey($jid, $password);

        if($this->redis->exists($keyOnCache)){
            return $this->redis->get($keyOnCache) === 'TRUE';
        }

        $dao = new BlacklistDao();
        $model = $dao->getByJobIdAndPassword($jid, $password);
        $checkIfExists = ($model !== null) ? 'TRUE' : 'FALSE';

        $this->ensureCached($keyOnCache, $checkIfExists);

        return $checkIfExists === 'TRUE';
    }

    /**
     * @param Jobs_JobStruct $job
     *
     * @return AbstractBlacklist
     * @throws Exception
     */
    public function getAbstractBlacklist( Jobs_JobStruct $job)
    {
        if(false === $this->checkIfExists($job->id, $job->password)){
            return new BlacklistFromZip( $job->getProject()->getFirstOriginalZipPath(),  $job->id, $job->password ) ;
        }

        $keyOnCache = $this->getListRedisKey($job->id, $job->password);

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
                    'bucket' => INIT::$AWS_STORAGE_BASE_BUCKET,
                    'key' => $blacklistFilePath,
                    'save_as' => "/tmp/glossary/" . md5($job->id . $job->password . 'blacklist').'.txt'
            ];

            $content = $s3Client->openItem([
                    'bucket' => INIT::$AWS_STORAGE_BASE_BUCKET,
                    'key' => $blacklistFilePath,
            ]);

            $s3Client->downloadItem( $s3Params );
            $blacklistFilePath = $s3Params[ 'save_as' ];
        } else {
            $blacklistFilePath = INIT::$BLACKLIST_REPOSITORY . DIRECTORY_SEPARATOR . $job->id . DIRECTORY_SEPARATOR . $job->password . DIRECTORY_SEPARATOR . 'blacklist.txt';
            $content = file_get_contents($blacklistFilePath);
        }

        $blacklistFromTextFile = new BlacklistFromTextFile( $blacklistFilePath, $job->id, $job->password );
        $blacklistFromTextFile->setContent($content);
        $this->ensureCached($keyOnCache, serialize($blacklistFromTextFile));

        return $blacklistFromTextFile;
    }

    /**
     * Ensure cache in Redis
     *
     * @param $key
     * @param $content
     */
    private function ensureCached($key, $content)
    {
        try {
            $this->redis->set( $key, $content );
            $this->redis->expire( $key, 60 * 60 * 24 * 30 ) ; // 1 month
        } catch ( Exception $exception){
            Log::doJsonLog('Error in saving '.$key.' on Redis:' . $exception->getMessage());
            // do nothing
        }
    }

    /**
     * Delete cache key in Redis
     *
     * @param $key
     */
    private function clearCached($key)
    {
        if ( $this->redis->exists( $key ) ) {
            $this->redis->del( $key ) ;
        }
    }

    /**
     * @param string $jid
     * @param string $password
     *
     * @return string
     */
    private function checkIfExistsRedisKey($jid, $password)
    {
        return md5(self::CHECK_IF_EXISTS_REDIS_KEY . '-' .$jid.'-'.$password);
    }

    /**
     * @param string $jid
     * @param string $password
     *
     * @return string
     */
    private function getListRedisKey($jid, $password)
    {
        return md5(self::GET_LIST_REDIS_KEY . '-' .$jid.'-'.$password);
    }

    /**
     * @param $jid
     * @param $password
     *
     * @return string
     */
    private function getJobCacheRedisKey($jid, $password)
    {
        return md5("blacklist:id_job:$jid:password:$password");
    }
}