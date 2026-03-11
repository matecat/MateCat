<?php

namespace Model\ProjectManager;

use ArrayObject;
use Closure;
use Exception;
use Model\DataAccess\IDatabase;
use Model\TmKeyManagement\MemoryKeyDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;
use Utils\TmKeyManagement\TmKeyStruct;
use Utils\TMS\TMSFile;
use Utils\TMS\TMSService;

/**
 * Encapsulates TM key validation, key-ring management, and TMX upload logic
 * that was previously embedded in {@see ProjectManager}.
 *
 * This class is responsible for:
 *  - Validating private TM keys via MyMemory API
 *  - Inserting new TM keys into the user's key-ring when they are not yet associated
 *  - Uploading TMX files to MyMemory
 *  - Polling for TMX upload completion status
 *
 * All mutations to projectStructure are performed on the ArrayObject passed
 * to the public methods, which is the same mutable structure used by ProjectManager.
 */
class TmKeyService
{
    use LogsMessages;

    private TMSService $tmxServiceWrapper;
    private IDatabase $dbHandler;

    /**
     * Callback to download a single file from S3 queue storage.
     * Signature: function(string $fileName): void
     *
     * @var Closure(string): void
     */
    private Closure $s3QueueFileDownloader;

    public function __construct(
        TMSService    $tmxServiceWrapper,
        IDatabase     $dbHandler,
        MatecatLogger $logger,
        Closure       $s3QueueFileDownloader,
    ) {
        $this->tmxServiceWrapper    = $tmxServiceWrapper;
        $this->dbHandler            = $dbHandler;
        $this->logger               = $logger;
        $this->s3QueueFileDownloader = $s3QueueFileDownloader;
    }

    /**
     * Validate each private TM key via the MyMemory API and insert any new keys
     * into the user's key-ring. If a TMX file name is provided and the key has
     * no explicit name, the TMX file name is used as the key name.
     *
     * On validation failure, the error is recorded in projectStructure and
     * the method returns early (callers should check for errors).
     */
    public function setPrivateTMKeys(ArrayObject $projectStructure, ?string $firstTMXFileName = ''): void
    {
        foreach ($projectStructure['private_tm_key'] as $_tmKey) {
            try {
                $keyExists = $this->tmxServiceWrapper->checkCorrectKey($_tmKey['key']);

                if (!isset($keyExists) || $keyExists === false) {
                    $this->log(__METHOD__ . " -> TM key is not valid.");

                    throw new Exception("TM key is not valid: " . $_tmKey['key'], -4);
                }
            } catch (Exception $e) {
                $this->addProjectError($projectStructure, $e->getCode(), $e->getMessage());

                return;
            }
        }

        //check if the MyMemory keys provided by the user are already associated to him.
        $userMemoryKeys = $this->getKeyringOwnerKeys($projectStructure['uid']);
        $userTmKeys     = [];
        $memoryKeysToBeInserted = [];

        //extract user tm keys
        foreach ($userMemoryKeys as $_memoKey) {
            $userTmKeys[] = $_memoKey->tm_key->key;
        }

        foreach ($projectStructure['private_tm_key'] as $_tmKey) {
            if (!in_array($_tmKey['key'], $userTmKeys)) {
                $newMemoryKey     = new MemoryKeyStruct();
                $newTmKey         = new TmKeyStruct();
                $newTmKey->key    = $_tmKey['key'];
                $newTmKey->tm     = true;
                $newTmKey->glos   = true;

                // THIS IS A NEW KEY and must be inserted into the user keyring
                // So, if a TMX file is present in the list of uploaded files, and the Key name provided is empty
                // assign TMX name to the key

                // NOTE 2025-05-08: Replace {{pid}} with project ID for new keys created with empty name
                $newTmKey->name = (!empty($_tmKey['name']) ? str_replace("{{pid}}", $projectStructure['id_project'], $_tmKey['name']) : $firstTMXFileName);

                $newMemoryKey->tm_key = $newTmKey;
                $newMemoryKey->uid    = $projectStructure['uid'];

                $memoryKeysToBeInserted[] = $newMemoryKey;
            } else {
                $this->log('skip insertion');
            }
        }
        try {
            $this->createMemoryKeyDao()->createList($memoryKeysToBeInserted);
        } catch (Exception $e) {
            $this->log($e->getMessage(), $e);
        }
    }

    /**
     * Upload all TMX files found in the project's file list to MyMemory,
     * then poll for each upload to complete (or time out after 30 minutes).
     *
     * TMX files are removed from the project's array_files / array_files_meta
     * after successful upload so they are not processed as translation files.
     *
     * @throws Exception
     */
    public function pushTMXToMyMemory(ArrayObject $projectStructure, string $uploadDir): void
    {
        $memoryFiles = [];

        // If there is no private TM key defined in the project structure,
        // or the nested indexes don't exist, stop and do nothing.
        if (empty($projectStructure['private_tm_key'][0]['key'] ?? null)) {
            return;
        }

        //TMX Management
        if (!empty($projectStructure['array_files'])) {
            foreach ($projectStructure['array_files'] as $pos => $fileName) {
                // get corresponding meta
                $meta = $projectStructure['array_files_meta'][$pos];

                $ext = $meta['extension'];

                try {
                    if ('tmx' == $ext) {
                        $file = new TMSFile(
                            "$uploadDir/$fileName",
                            $projectStructure['private_tm_key'][0]['key'],
                            $fileName,
                            $pos
                        );

                        $memoryFiles[] = $file;

                        if (AppConfig::$FILE_STORAGE_METHOD == 's3') {
                            ($this->s3QueueFileDownloader)($fileName);
                        }

                        $userStruct = $this->getUserByUid($projectStructure['uid']);
                        $this->tmxServiceWrapper->addTmxInMyMemory($file, $userStruct);
                    } else {
                        //don't call the postPushTMX for normal files
                        continue;
                    }
                } catch (Exception $e) {
                    $this->addProjectError($projectStructure, $e->getCode(), $e->getMessage());

                    throw new Exception($e);
                }
            }
        }

        /**
         * @throws Exception
         */
        $this->loopForTMXLoadStatus($projectStructure, $memoryFiles);
    }

    /**
     * Poll MyMemory for TMX upload completion status.
     *
     * Waits up to 30 minutes per file, polling every 3 seconds.
     * After each file completes (or times out), it is removed from the
     * project's array_files and array_files_meta lists.
     *
     * @param TMSFile[] $memoryFiles
     *
     * @throws Exception
     */
    protected function loopForTMXLoadStatus(ArrayObject $projectStructure, array $memoryFiles): void
    {
        $time = strtotime('+30 minutes');

        //TMX Management
        /****************/
        //loop again through files to check for TMX loading
        foreach ($memoryFiles as $file) {
            //is the TM loaded?
            //wait until the current TMX is loaded
            while (true) {
                try {
                    $result = $this->tmxServiceWrapper->tmxUploadStatus($file->getUuid());

                    if ($result['completed'] || strtotime('now') > $time) {
                        //"$fileName" has been loaded into MyMemory"
                        // OR the indexer is down or stopped for maintenance
                        // exit the loop, the import will be executed at a later time
                        break;
                    }

                    //waiting for "$fileName" to be loaded into MyMemory
                    sleep(3);
                } catch (Exception $e) {
                    $this->addProjectError($projectStructure, $e->getCode(), $e->getMessage());

                    $this->log($e->getMessage(), $e);

                    //exit project creation
                    throw new Exception($e);
                }
            }

            unset($projectStructure['array_files'][$file->getPosition()]);
            unset($projectStructure['array_files_meta'][$file->getPosition()]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Internal helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Factory method for MemoryKeyDao.
     * Protected so test subclasses can override for injection.
     */
    protected function createMemoryKeyDao(): MemoryKeyDao
    {
        return new MemoryKeyDao($this->dbHandler);
    }

    /**
     * Get the user's keyring owner keys.
     * Protected so test subclasses can override for injection.
     *
     * @return MemoryKeyStruct[]
     */
    protected function getKeyringOwnerKeys(int $uid): array
    {
        return MemoryKeyDao::getKeyringOwnerKeysByUid($uid);
    }

    /**
     * Get a UserStruct by UID.
     * Protected so test subclasses can override for injection.
     * @throws ReflectionException
     */
    protected function getUserByUid(int $uid): ?UserStruct
    {
        return (new UserDao())->setCacheTTL(60 * 60)->getByUid($uid);
    }

    private function addProjectError(ArrayObject $projectStructure, int $code, string $message): void
    {
        $projectStructure['result']['errors'][] = [
            "code"    => $code,
            "message" => $message,
        ];
    }
}
