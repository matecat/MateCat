<?php

namespace Model\ProjectCreation;

use Closure;
use Exception;
use Model\Concerns\LogsMessages;
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
 * All mutations to projectStructure are performed on the ProjectStructure passed
 * to the public methods, which is the same mutable structure used by ProjectManager.
 */
class TmKeyService
{
    use LogsMessages;

    private const int TMX_POLL_TIMEOUT_MINUTES = 10;
    private const int TMX_POLL_INITIAL_INTERVAL = 3;
    private const int TMX_POLL_MAX_INTERVAL = 30;

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
        TMSService $tmxServiceWrapper,
        IDatabase $dbHandler,
        MatecatLogger $logger,
        Closure $s3QueueFileDownloader,
    ) {
        $this->tmxServiceWrapper = $tmxServiceWrapper;
        $this->dbHandler = $dbHandler;
        $this->logger = $logger;
        $this->s3QueueFileDownloader = $s3QueueFileDownloader;
    }

    /**
     * Validate each private TM key via the MyMemory API and insert any new keys
     * into the user's key-ring. If a TMX file name is provided and the key has
     * no explicit name, the TMX file name is used as the key name.
     *
     * On validation failure, the error is recorded in projectStructure, and
     * the method returns early (callers should check for errors).
     *
     * @param ProjectStructure $projectStructure
     * @param string|null $firstTMXFileName
     */
    public function setPrivateTMKeys(ProjectStructure $projectStructure, ?string $firstTMXFileName = ''): void
    {
        foreach ($projectStructure->private_tm_key as $_tmKey) {
            try {
                $keyExists = $this->tmxServiceWrapper->checkCorrectKey($_tmKey['key']);

                if (!isset($keyExists) || $keyExists === false) {
                    $this->log(__METHOD__ . " -> TM key is not valid.");

                    throw new Exception("TM key is not valid: " . $_tmKey['key'], ProjectCreationError::TM_KEY_INVALID->value);
                }
            } catch (Exception $e) {
                $projectStructure->addError($e->getCode(), $e->getMessage());

                return;
            }
        }

        // uid is guaranteed non-null: both API entry points require authentication
        // via LoginValidator, and UserStruct::isLogged() requires a non-empty uid.
        assert($projectStructure->uid !== null, 'uid must be set by authenticated controller');

        //check if the MyMemory keys provided by the user are already associated with him.
        $userMemoryKeys = $this->getKeyringOwnerKeys($projectStructure->uid);
        $userTmKeys = [];
        $memoryKeysToBeInserted = [];

        //extract user tm keys
        foreach ($userMemoryKeys as $_memoKey) {
            if ($_memoKey->tm_key !== null) {
                $userTmKeys[] = $_memoKey->tm_key->key;
            }
        }

        foreach ($projectStructure->private_tm_key as $_tmKey) {
            if (!in_array($_tmKey['key'], $userTmKeys)) {
                $newMemoryKey = new MemoryKeyStruct();
                $newTmKey = new TmKeyStruct();
                $newTmKey->key = $_tmKey['key'];
                $newTmKey->tm = true;
                $newTmKey->glos = true;

                // THIS IS A NEW KEY and must be inserted into the user keyring
                // So, if a TMX file is present in the list of uploaded files, and the Key name provided is empty,
                // assign TMX name to the key

                // NOTE 2025-05-08: Replace {{pid}} with project ID for new keys created with an empty name
                $newTmKey->name = (!empty($_tmKey['name']) ? (string)str_replace("{{pid}}", (string)$projectStructure->id_project, $_tmKey['name']) : $firstTMXFileName);

                $newMemoryKey->tm_key = $newTmKey;
                $newMemoryKey->uid = $projectStructure->uid;

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
     * after successful upload, so they are not processed as translation files.
     *
     * @param ProjectStructure $projectStructure
     * @param string $uploadDir
     * @throws ReflectionException
     * @throws Exception
     */
    public function pushTMXToMyMemory(ProjectStructure $projectStructure, string $uploadDir): void
    {
        $memoryFiles = [];

        // uid is guaranteed non-null: both API entry points require authentication
        // via LoginValidator, and UserStruct::isLogged() requires a non-empty uid.
        assert($projectStructure->uid !== null, 'uid must be set by authenticated controller');

        // If there is no private TM key defined in the project structure,
        // or the nested indexes don't exist, stop and do nothing.
        if (empty($projectStructure->private_tm_key[0]['key'] ?? null)) {
            return;
        }

        //TMX Management
        if (!empty($projectStructure->array_files)) {
            $userStruct = $this->getUserByUid($projectStructure->uid);
            if ($userStruct === null) {
                throw new Exception("User not found for uid: " . $projectStructure->uid);
            }

            foreach ($projectStructure->array_files as $pos => $fileName) {
                // get corresponding meta
                $meta = $projectStructure->array_files_meta[$pos] ?? null;

                $ext = $meta['extension'] ?? null;

                try {
                    if ('tmx' == $ext) {
                        $file = new TMSFile(
                            "$uploadDir/$fileName",
                            $projectStructure->private_tm_key[0]['key'],
                            $fileName,
                            $pos
                        );

                        $memoryFiles[] = $file;

                        if (AppConfig::$FILE_STORAGE_METHOD == 's3') {
                            ($this->s3QueueFileDownloader)($fileName);
                        }

                        $this->tmxServiceWrapper->addTmxInMyMemory($file, $userStruct);
                    } else {
                        //don't call the postPushTMX for normal files
                        continue;
                    }
                } catch (Exception $e) {
                    $projectStructure->addError($e->getCode(), $e->getMessage());

                    throw $e;
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
     * Waits up to {@see self::TMX_POLL_TIMEOUT_MINUTES} minutes per file,
     * using exponential backoff (starting at {@see self::TMX_POLL_INITIAL_INTERVAL}s,
     * capped at {@see self::TMX_POLL_MAX_INTERVAL}s).
     *
     * After each file completes (or times out), it is removed from the
     * project's array_files and array_files_meta lists.
     * On timeout, a project error is recorded, but the loop continues
     * to the next file.
     *
     * @param ProjectStructure $projectStructure
     * @param TMSFile[] $memoryFiles
     *
     * @throws Exception
     */
    protected function loopForTMXLoadStatus(ProjectStructure $projectStructure, array $memoryFiles): void
    {
        foreach ($memoryFiles as $file) {
            $deadline = strtotime('+' . self::TMX_POLL_TIMEOUT_MINUTES . ' minutes');
            $pollInterval = self::TMX_POLL_INITIAL_INTERVAL;
            $startTime = time();

            while (true) {
                try {
                    $uuid = $file->getUuid();
                    if ($uuid === null) {
                        throw new Exception("TMX file UUID is null for file: " . $file->getName());
                    }
                    $result = $this->tmxServiceWrapper->tmxUploadStatus($uuid);

                    if ($result['completed']) {
                        $elapsed = time() - $startTime;
                        $this->log("TMX import completed for \"{$file->getName()}\" in {$elapsed}s");
                        break;
                    }

                    if (time() > $deadline) {
                        $projectStructure->addError(
                            ProjectCreationError::TMX_IMPORT_TIMEOUT->value,
                            "TMX import timed out after " . self::TMX_POLL_TIMEOUT_MINUTES . " minutes for file: " . $file->getName()
                        );
                        $this->log("TMX import timed out after " . self::TMX_POLL_TIMEOUT_MINUTES . " minutes for file: " . $file->getName());
                        break;
                    }

                    $elapsed = time() - $startTime;
                    $this->log("Waiting for TMX \"{$file->getName()}\" — elapsed {$elapsed}s, next poll in {$pollInterval}s");

                    sleep($pollInterval);
                    $pollInterval = min($pollInterval * 2, self::TMX_POLL_MAX_INTERVAL);
                } catch (Exception $e) {
                    $projectStructure->addError($e->getCode(), $e->getMessage());

                    $this->log($e->getMessage(), $e);

                    //exit project creation
                    throw $e;
                }
            }

            unset($projectStructure->array_files[$file->getPosition()]);
            unset($projectStructure->array_files_meta[$file->getPosition()]);
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
}
