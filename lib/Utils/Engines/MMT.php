<?php

namespace Utils\Engines;

use DomainException;
use Exception;
use Model\DataAccess\Database;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Jobs\MetadataDao;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use ReflectionException;
use RuntimeException;
use SplFileObject;
use TypeError;
use Utils\Constants\EngineConstants;
use Utils\Engines\MMT\MMTServiceApi;
use Utils\Engines\MMT\MMTServiceApiException;
use Utils\Engines\MMT\MMTServiceApiRequestException;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\MyMemory\Matches;
use Utils\Registry\AppConfig;
use Utils\TmKeyManagement\TmKeyManager;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 17/05/16
 * Time: 13:11
 *
 * @property int $id
 */
class MMT extends AbstractEngine
{

    /**
     * @inheritdoc
     * @see AbstractEngine::$_isAdaptiveMT
     * @var bool
     */
    protected bool $_isAdaptiveMT = true;

    protected array $_config = [
        'segment' => null,
        'translation' => null,
        'newsegment' => null,
        'newtranslation' => null,
        'source' => null,
        'target' => null,
        'langpair' => null,
        'email' => null,
        'keys' => null,
        'mt_context' => null,
        'id_user' => null
    ];

    /**
     * @var bool
     */
    protected bool $_skipAnalysis = false;

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function __construct($engineRecord)
    {
        parent::__construct($engineRecord);

        if ($this->getEngineRecord()->type != EngineConstants::MT) {
            throw new Exception(
                "Engine {$this->getEngineRecord()->id} is not a MT engine, found {$this->getEngineRecord()->type} -> {$this->getEngineRecord()->class_load}"
            );
        }
    }

    /**
     * Get MMTServiceApi client
     *
     * @return MMTServiceApi
     */
    protected function _getClient(): MMTServiceApi
    {
        $extraParams = $this->getEngineRecord()->getExtraParamsAsArray();
        $license = $extraParams['MMT-License'];

        return MMTServiceApi::newInstance()
            ->setIdentity("Matecat", ltrim(AppConfig::$BUILD_NUMBER, 'v'))
            ->setLicense($license);
    }

    /**
     * Get the available languages in MMT
     *
     * @return array<string, mixed>|null
     * @throws MMTServiceApiException
     */
    public function getAvailableLanguages(): ?array
    {
        $client = $this->_getClient();

        return $client->getAvailableLanguages();
    }

    /**
     * @param array<string, mixed> $_config
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function get(array $_config): GetMemoryResponse
    {
        // This is needed because Lara uses an SDK for the API, and the SDK does not support the 'skipAnalysis' parameter
        if ($this->_isAnalysis && $this->_skipAnalysis) {
            return new GetMemoryResponse(null);
        }

        $client = $this->_getClient();
        $metadataDao = new ProjectsMetadataDao();

        $glossaries = null;

        if (!empty($_config['id_project'])) {
            $glossaries = $metadataDao->setCacheTTL(86400)->get($_config['id_project'], 'mmt_glossaries');
        }

        if ($glossaries !== null) {
            $mmtGlossariesArray = json_decode($glossaries->value, true);
            $ignore_glossary_case = $metadataDao->setCacheTTL(86400)->get(
                $_config['id_project'],
                'mmt_ignore_glossary_case'
            );

            $_config['glossaries'] = implode(",", $mmtGlossariesArray);

            if ($ignore_glossary_case !== null) {
                $_config['ignore_glossary_case'] = $ignore_glossary_case->value;
            }
        }

        $_config = $this->configureContribution($_config);

        try {
            $translation = $client->translate(
                $_config['source'],
                $_config['target'],
                $_config['segment'],
                $_config['mt_context'] ?? null,
                $_config['keys'],
                $_config['job_id'] ?? null,
                static::GET_REQUEST_TIMEOUT,
                $_config['priority'] ?? null,
                $_config['session'] ?? null,
                $_config['glossaries'] ?? null,
                $_config['ignore_glossary_case'] ?? null,
                $_config['include_score'] ?? null,
                $_config['mt_qe_engine_id'] ?? '2'
            );

            if ($translation === null || !isset($translation['translation'])) {
                return $this->GoogleTranslateFallback($_config);
            }

            $match = new Matches([
                'source' => $_config['source'],
                'target' => $_config['target'],
                'raw_segment' => $_config['segment'],
                'raw_translation' => $translation['translation'],
                'match' => $this->getStandardMtPenaltyString(),
                'created-by' => $this->getMTName(),
                'create-date' => date("Y-m-d"),
                'score' => $translation['score'] ?? null
            ]);
            $match->featureSet($this->featureSet);

            $response = new GetMemoryResponse(null);
            $response->matches = [$match];

            return $response;
        } catch (Exception) {
            return $this->GoogleTranslateFallback($_config);
        }
    }

    /**
     * @param array<string> $_keys
     *
     * @return array<string>
     */
    protected function _reMapKeyList(array $_keys): array
    {
        return array_map(function ($key) {
            return 'x_mm-' . $key;
        }, $_keys);
    }

    /**
     * @param MemoryKeyStruct[] $keyList
     *
     * @return array<string>
     */
    protected function _reMapKeyStructsList(array $keyList): array
    {
        return array_map(function ($kStruct) {
            return 'x_mm-' . ($kStruct->tm_key->key ?? '');
        }, $keyList);
    }

    /**
     * @param array<string, mixed> $_config
     * @return bool
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function set($_config): bool
    {
        $client = $this->_getClient();
        $_keys = $this->_reMapKeyList($_config['keys'] ?? []);

        try {
            $client->addToMemoryContent(
                $_keys,
                $_config['source'],
                $_config['target'],
                $_config['segment'],
                $_config['translation'],
                $_config['session']
            );
        } catch (MMTServiceApiRequestException $e) {
            // MMT license expired/changed (401), or the account is deleted (403), or whatever HTTP exception
            $this->logger->debug($e->getMessage());

            return true;
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $_config
     *
     * @return bool
     * @throws Exception
     */
    public function update($_config): bool
    {
        $client = $this->_getClient();
        $_keys = $this->_reMapKeyList($_config['keys'] ?? []);

        try {
            $client->updateMemoryContent(
                $_config['tuid'],
                $_keys,
                $_config['source'],
                $_config['target'],
                $_config['segment'],
                $_config['translation'],
                $_config['session']
            );
        } catch (Exception) {
            return false; // requeue
        }

        return true;
    }

    /**
     * @param mixed $_config
     * @throws DomainException
     */
    public function delete(mixed $_config): never
    {
        throw new DomainException("Method " . __FUNCTION__ . " not implemented.");
    }

    /**
     * @throws MMTServiceApiException
     */
    public function memoryExists(MemoryKeyStruct $memoryKey): ?array
    {
        $client = $this->_getClient();

        try {
            $response = $client->getMemory('x_mm-' . trim($memoryKey->tm_key->key ?? ''));
        } catch (MMTServiceApiRequestException) {
            return null;
        }

        return $response;
    }


    /**
     *
     * @param string $filePath
     * @param string $memoryKey
     * @param UserStruct $user *
     *
     * @return void
     * @throws MMTServiceApiException
     * @throws Exception
     */
    public function importMemory(string $filePath, string $memoryKey, UserStruct $user): void
    {
        $client = $this->_getClient();
        $response = $client->getMemory('x_mm-' . trim($memoryKey)); //Throw an exception if the key is not synced

        if (empty($response)) {
            return;
        }

        $fp_out = gzopen("$filePath.gz", 'wb9');

        if (!$fp_out) {
            $fp_out = null;
            throw new RuntimeException('IOException. Unable to create temporary file.');
        }

        $tmpFileObject = new SplFileObject($filePath, 'r');

        while (!$tmpFileObject->eof()) {
            gzwrite($fp_out, $tmpFileObject->fgets());
        }

        $tmpFileObject = null;
        gzclose($fp_out);

        $client->importIntoMemoryContent('x_mm-' . trim($memoryKey), "$filePath.gz", 'gzip');
        $fp_out = null;
    }

    /**
     * @throws Exception
     */
    public function syncMemories(array $projectRow, ?array $segments = []): void
    {
        $pid = $projectRow['id'];

        $metadataDao = new ProjectsMetadataDao();
        $context = $metadataDao->setCacheTTL(86400)->get($pid, "mmt_activate_context_analyzer");
        $context_analyzer = $context->value ?? $this->getEngineRecord()->getExtraParamsAsArray()['MMT-context-analyzer'];

        if (!empty($context_analyzer)) {
            if (empty($segments) || !isset($segments[0]['source'], $segments[0]['target'])) {
                return;
            }

            $source = $segments[0]['source'];
            $targets = [];
            $jobLanguages = [];
            foreach (explode(',', $segments[0]['target']) as $jid_Lang) {
                [$jobId, $target] = explode(":", $jid_Lang);
                $jobLanguages[$jobId] = $source . "|" . $target;
                $targets[] = $target;
            }

            $tmp_name = tempnam(sys_get_temp_dir(), 'mmt_cont_req-');
            $tmpFileObject = new SplFileObject(tempnam(sys_get_temp_dir(), 'mmt_cont_req-'), 'w+');
            foreach ($segments as $segment) {
                $tmpFileObject->fwrite($segment['segment'] . "\n");
            }

            try {
                /*
                    $result = Array
                    (
                        [en-US|es-ES] => 1:0.14934476,2:0.08131008,3:0.047170084
                        [en-US|it-IT] =>
                    )
                */
                $result = $this->getContext($tmpFileObject, $source, $targets);
                if ($result === null) {
                    return;
                }

                $jMetadataDao = new MetadataDao();

                Database::obtain()->begin();
                foreach ($result as $langPair => $context) {
                    $jobId = array_search($langPair, $jobLanguages, true);
                    if ($jobId === false) {
                        continue;
                    }

                    $jMetadataDao->setCacheTTL(60 * 60 * 24 * 30)->set(
                        (int)$jobId,
                        "",
                        'mt_context',
                        $context
                    );
                }
                Database::obtain()->commit();
            } catch (Exception $e) {
                $this->logger->debug($e->getMessage());
                $this->logger->debug($e->getTraceAsString());
            } finally {
                unset($tmpFileObject);
                @unlink($tmp_name);
            }
        }

        try {
            //
            // ==============================================
            // send user keys on a project basis
            // ==============================================
            //
            $user = (new UserDao)->getByEmail($projectRow['id_customer']);
            if ($user === null) {
                return;
            }

            // get jobs keys
            $project = ProjectDao::findById($pid);
            if ($project === null) {
                return;
            }

            foreach ($project->getJobs() as $job) {
                $memoryKeyStructs = [];
                $jobKeyList = TmKeyManager::getJobTmKeys($job->tm_keys, 'r', 'tm', $user->uid);

                foreach ($jobKeyList as $memKey) {
                    $memoryKeyStructs[] = new MemoryKeyStruct(
                        [
                            'uid' => $user->uid,
                            'tm_key' => $memKey
                        ]
                    );
                }

                $this->connectKeys($memoryKeyStructs);
            }
        } catch (Exception $e) {
            $this->logger->debug($e->getMessage());
            $this->logger->debug($e->getTraceAsString());
        }
    }

    /**
     *
     * @param $file    SplFileObject
     * @param $source  string
     * @param array<string> $targets
     *
     * @return array<string, mixed>|null
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     * @throws Exception
     * @internal param array $langPairs
     */
    protected function getContext(SplFileObject $file, string $source, array $targets): ?array
    {
        $fileName = $file->getRealPath();
        $file->rewind();

        $fp_out = gzopen("$fileName.gz", 'wb9');

        if (!$fp_out) {
            $fp_out = null;
            @unlink($fileName);
            @unlink("$fileName.gz");
            throw new RuntimeException('IOException. Unable to create temporary file.');
        }

        while (!$file->eof()) {
            gzwrite($fp_out, $file->fgets());
        }

        gzclose($fp_out);

        $client = $this->_getClient();
        $result = $client->getContextVectorFromFile($source, $targets, "$fileName.gz", 'gzip');
        if ($result === null || !isset($result['vectors'])) {
            return null;
        }

        $plainContexts = [];
        foreach ($result['vectors'] as $target => $vector) {
            $plainContexts["$source|$target"] = $vector;
        }

        return $plainContexts;
    }

    /**
     * Call to check the license key validity
     * @return array<string, mixed>|null
     * @throws MMTServiceApiException
     * @throws Exception
     */
    public function checkAccount(): ?array
    {
        try {
            $client = $this->_getClient();
            $this->result = $client->me();

            return $this->result;
        } catch (Exception) {
            throw new Exception("MMT license not valid");
        }
    }

    /**
     * Activate the account and also update/add keys to User MMT data
     *
     * @param MemoryKeyStruct[] $keyList
     *
     * @return array<string, mixed>|null
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     * @throws Exception
     */
    public function connectKeys(array $keyList): ?array
    {
        $keyList = $this->_reMapKeyStructsList($keyList);
        $client = $this->_getClient();

        // Avoid calling MMT if $keyList is empty
        if (!empty($keyList)) {
            $this->result = $client->connectMemories($keyList);
        }

        return $this->result;
    }

    /**
     * @param mixed $rawValue
     * @param array<string, mixed> $parameters
     * @param null $function
     *
     * @return array<string, mixed>
     */
    protected function _decode(mixed $rawValue, array $parameters = [], $function = null): array
    {
        // Not used since MMT works with an external client
        return [];
    }

    /**
     * @param string $name
     * @param string|null $description
     * @param string|null $externalId
     *
     * @return array<string, mixed>|null
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     * @throws Exception
     */
    public function createMemory(string $name, ?string $description = null, ?string $externalId = null): ?array
    {
        $client = $this->_getClient();

        return $client->createMemory($name, $description, $externalId);
    }

    /**
     * Delete a memory associated to an MMT account
     * (id can be an external account)
     *
     * @param array<string, mixed> $memoryKey
     *
     * @return array<string, mixed>
     * @throws MMTServiceApiException
     * @throws Exception
     */
    public function deleteMemory(array $memoryKey): array
    {
        $client = $this->_getClient();

        return $client->deleteMemory(trim((string)($memoryKey['id'] ?? ''))) ?? [];
    }

    /**
     * Get all memories associated to an MMT account
     * (id can be an external account)
     *
     * @return array<string, mixed>|null
     * @throws MMTServiceApiException
     */
    public function getAllMemories(): ?array
    {
        $client = $this->_getClient();

        return $client->getAllMemories();
    }

    /**
     * Get a memory associated with an MMT account
     * (id can be an external account)
     *
     * @param string $id
     *
     * @return array<string, mixed>|null
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     * @throws Exception
     */
    public function getMemory(string $id): ?array
    {
        $client = $this->_getClient();

        return $client->getMemory($id);
    }

    /**
     * @param string $id
     * @param string $name
     *
     * @return array<string, mixed>|null
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function updateMemory(string $id, string $name): ?array
    {
        $client = $this->_getClient();

        return $client->updateMemory($id, $name);
    }

    /**
     * @param string $id
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function importGlossary(string $id, array $data): ?array
    {
        $client = $this->_getClient();

        return $client->importGlossary($id, $data);
    }

    /**
     * @param string $id
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function updateGlossary(string $id, array $data): ?array
    {
        $client = $this->_getClient();

        return $client->updateGlossary($id, $data);
    }

    /**
     * @param string $uuid
     *
     * @return array<string, mixed>|null
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function importJobStatus(string $uuid): ?array
    {
        $client = $this->_getClient();

        return $client->importJobStatus($uuid);
    }

    /**
     * @throws MMTServiceApiException
     * @throws Exception
     */
    public function getMemoryIfMine(MemoryKeyStruct $memoryKey): ?array
    {
        //Get the user account, check if the memory exists and, if so, check if the key owner's ID is mine.
        $me = $this->checkAccount();
        $memory = $this->memoryExists($memoryKey);
        if (
            !empty($memory)
            && isset($memory['owner']['user'], $me['id'])
            && (string)$memory['owner']['user'] === (string)$me['id']
        ) {
            return $memory;
        }

        return null;
    }

    /**
     * @param string $source
     * @param string $target
     * @param string $sentence
     * @param string $translation
     * @param string $mt_qe_engine_id
     *
     * @return float|null
     * @throws MMTServiceApiException
     */
    public function getQualityEstimation(
        string $source,
        string $target,
        string $sentence,
        string $translation,
        string $mt_qe_engine_id = '2'
    ): ?float {
        $client = $this->_getClient();
        $qualityEstimation = $client->qualityEstimation($source, $target, $sentence, $translation, $mt_qe_engine_id);
        if ($qualityEstimation === null || !isset($qualityEstimation['score'])) {
            return null;
        }

        return $qualityEstimation['score'];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     * @throws ReflectionException
     * @throws Exception
     */
    private function configureContribution(array $config = []): array
    {
        $id_job = $config['job_id'] ?? null;
        $cacheTtl = 60 * 60 * 24 * 30;

        // Common metadata loading
        if (!empty($id_job)) {
            $metadataDao = new MetadataDao();
            $contextRs = $metadataDao->setCacheTTL($cacheTtl)->getByIdJob($id_job, 'mt_context');

            $mt_context = array_pop($contextRs);
            if (!empty($mt_context)) {
                $config['mt_context'] = $mt_context->value;
            }
        }

        // Common config values
        $config['secret_key'] = self::getG2FallbackSecretKey();

        // Branch-specific values
        if ($id_job && $this->_isAnalysis) {
            $idUsers = $config['id_user'] ?? [];
            $config['keys'] = $this->_reMapKeyList(is_array($idUsers) ? $idUsers : []);
            $config['priority'] = 'background';
        } else {
            //get the Owner Keys from the Job
            $keys = $config['keys'] ?? [];
            $config['keys'] = $this->_reMapKeyList(is_array($keys) ? $keys : []);
            $config['job_id'] = $id_job;
            $config['priority'] = 'normal';
        }

        return $config;
    }

    /**
     * @return string|null
     */
    public static function getG2FallbackSecretKey(): ?string
    {
        $configFilePath = realpath(AppConfig::$ROOT . '/inc/mmt_fallback_key.ini');
        if ($configFilePath === false || !file_exists($configFilePath)) {
            return null;
        }

        $secret_key = parse_ini_file($configFilePath);
        if ($secret_key === false) {
            return null;
        }

        return $secret_key['secret_key'] ?? null;
    }


    /**
     * @inheritDoc
     */
    public static function getConfigurationParameters(): array
    {
        return [
            'enable_mt_analysis',
            'mmt_glossaries',
            'mmt_activate_context_analyzer',
            'mmt_ignore_glossary_case',
        ];
    }
}
