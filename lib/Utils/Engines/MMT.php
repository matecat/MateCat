<?php

namespace Utils\Engines;

use DomainException;
use Exception;
use Model\DataAccess\Database;
use Model\Jobs\MetadataDao;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserDao;
use Model\Users\UserStruct;
use Plugins\Features\Mmt as MMTFeature;
use ReflectionException;
use RuntimeException;
use SplFileObject;
use Utils\Constants\EngineConstants;
use Utils\Engines\MMT\MMTServiceApi;
use Utils\Engines\MMT\MMTServiceApiException;
use Utils\Engines\MMT\MMTServiceApiRequestException;
use Utils\Engines\Results\MyMemory\Matches;
use Utils\Registry\AppConfig;
use Utils\TmKeyManagement\TmKeyManager;

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 17/05/16
 * Time: 13:11
 *
 * @property int id
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
     * @return array|null
     * @throws MMTServiceApiException
     */
    public function getAvailableLanguages(): ?array
    {
        $client = $this->_getClient();

        return $client->getAvailableLanguages();
    }

    /**
     * @param array $_config
     *
     * @return array
     * @throws ReflectionException
     * @throws Exception
     */
    public function get(array $_config): array
    {
        // This is needed because Lara uses an SDK for the API, and the SDK does not support the 'skipAnalysis' parameter
        if ($this->_isAnalysis && $this->_skipAnalysis) {
            return [];
        }

        $client = $this->_getClient();
        $_keys = $this->_reMapKeyList($_config['keys'] ?? []);
        $metadataDao = new ProjectsMetadataDao();

        $glossaries = null;

        if (!empty($_config['project_id'])) {
            $glossaries = $metadataDao->setCacheTTL(86400)->get($_config['project_id'], 'mmt_glossaries');
        }

        if ($glossaries !== null) {
            $mmtGlossariesArray = json_decode($glossaries->value, true);
            $ignore_glossary_case = $metadataDao->setCacheTTL(86400)->get(
                $_config['project_id'],
                'mmt_ignore_glossary_case'
            );

            $_config['glossaries'] = implode(",", $mmtGlossariesArray);

            if ($ignore_glossary_case !== null) {
                $_config['ignore_glossary_case'] = $ignore_glossary_case->value;
            }
        }

        $_config = $this->configureAnalysisContribution($_config);

        try {
            $translation = $client->translate(
                $_config['source'],
                $_config['target'],
                $_config['segment'],
                $_config['mt_context'] ?? null,
                $_keys,
                $_config['job_id'] ?? null,
                static::GET_REQUEST_TIMEOUT,
                $_config['priority'] ?? null,
                $_config['session'] ?? null,
                $_config['glossaries'] ?? null,
                $_config['ignore_glossary_case'] ?? null,
                $_config['include_score'] ?? null,
                $_config['mt_qe_engine_id'] ?? '2'
            );

            return (new Matches([
                'source' => $_config['source'],
                'target' => $_config['target'],
                'raw_segment' => $_config['segment'],
                'raw_translation' => $translation['translation'],
                'match' => $this->getStandardMtPenaltyString(),
                'created-by' => $this->getMTName(),
                'create-date' => date("Y-m-d"),
                'score' => $translation['score'] ?? null
            ]))->getMatches(
                1,
                [],
                $_config['source'],
                $_config['target'],
                $_config[MetadataDao::SUBFILTERING_HANDLERS]
            );
        } catch (Exception) {
            return $this->GoogleTranslateFallback($_config);
        }
    }

    /**
     * @param array $_keys
     *
     * @return array
     */
    protected function _reMapKeyList(array $_keys): array
    {
        return array_map(function ($key) {
            return 'x_mm-' . $key;
        }, $_keys);
    }

    /**
     * @param $keyList MemoryKeyStruct[]
     *
     * @return array
     */
    protected function _reMapKeyStructsList(array $keyList): array
    {
        return array_map(function ($kStruct) {
            return 'x_mm-' . $kStruct->tm_key->key;
        }, $keyList);
    }

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
            // MMT license expired/changed (401) or account deleted (403) or whatever HTTP exception
            $this->logger->debug($e->getMessage());

            return true;
        } catch (Exception) {
            return false;
        }

        return true;
    }

    /**
     * @param $_config
     *
     * @return bool
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

    public function delete($_config)
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
            $response = $client->getMemory('x_mm-' . trim($memoryKey->tm_key->key));
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
    public function importMemory(string $filePath, string $memoryKey, UserStruct $user)
    {
        $client = $this->_getClient();
        $response = $client->getMemory('x_mm-' . trim($memoryKey));

        if (empty($response)) {
            return null;
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

        // @TODO ho fatto il fallback
        $metadataDao = new ProjectsMetadataDao();
        $context_analyzer = $metadataDao->get($pid, "mmt_activate_context_analyzer") ? $metadataDao->get(
            $pid,
            "mmt_activate_context_analyzer"
        )->value : $this->getEngineRecord()->getExtraParamsAsArray()['MMT-context-analyzer'];

        if (!empty($context_analyzer)) {
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

                $jMetadataDao = new MetadataDao();

                Database::obtain()->begin();
                foreach ($result as $langPair => $context) {
                    $jMetadataDao->setCacheTTL(60 * 60 * 24 * 30)->set(
                        array_search($langPair, $jobLanguages),
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

            // get jobs keys
            $project = ProjectDao::findById($pid);

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
     * @param $targets string[]
     *
     * @return array|null
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
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

        $plainContexts = [];
        foreach ($result['vectors'] as $target => $vector) {
            $plainContexts["$source|$target"] = $vector;
        }

        return $plainContexts;
    }

    /**
     * Call to check the license key validity
     * @throws MMTServiceApiException
     * @throws Exception
     */
    public function checkAccount()
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
     * @param $keyList MemoryKeyStruct[]
     *
     * @return array|null
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
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
     * @param array $parameters
     * @param null $function
     *
     * @return array
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
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
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
     * @param array $memoryKey
     *
     * @return array
     * @throws MMTServiceApiException
     */
    public function deleteMemory(array $memoryKey): array
    {
        $client = $this->_getClient();

        return $client->deleteMemory(trim($memoryKey['id']));
    }

    /**
     * Get all memories associated to an MMT account
     * (id can be an external account)
     *
     * @return array|null
     * @throws MMTServiceApiException
     */
    public function getAllMemories(): ?array
    {
        $client = $this->_getClient();

        return $client->getAllMemories();
    }

    /**
     * Get a memory associated to an MMT account
     * (id can be an external account)
     *
     * @param string $id
     *
     * @return array|null
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
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
     * @return array|null
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
     * @param array $data
     *
     * @return array|null
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
     * @param array $data
     *
     * @return array|null
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
     * @return array|null
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
     */
    public function getMemoryIfMine(MemoryKeyStruct $memoryKey): ?array
    {
        //Get the user account, check if the memory exists and, if so, check if the key owner's ID is mine.
        $me = $this->checkAccount();
        $memory = $this->memoryExists($memoryKey);
        if (!empty($memory) && $memory['owner']['user'] == $me['id']) {
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

        return $qualityEstimation['score'];
    }

    /**
     * @param array|null $config
     *
     * @return array|null
     * @throws ReflectionException
     */
    private function configureAnalysisContribution(?array $config = []): ?array
    {
        $id_job = $config['job_id'] ?? null;

        if ($id_job and $this->_isAnalysis) {
            $contextRs = (new MetadataDao())->setCacheTTL(60 * 60 * 24 * 30)->getByIdJob($id_job, 'mt_context');
            $mt_context = @array_pop($contextRs);

            if (!empty($mt_context)) {
                $config['mt_context'] = $mt_context->value;
            }

            $config['secret_key'] = MMTFeature::getG2FallbackSecretKey();
            $config['priority'] = 'background';
            $config['keys'] = $config['id_user'] ?? [];
        }

        return $config;
    }

    /**
     * @inheritDoc
     */
    public function getConfigurationParameters(): array
    {
        return [
            'enable_mt_analysis',
            'mmt_glossaries',
            'mmt_activate_context_analyzer',
            'mmt_ignore_glossary_case',
        ];
    }
}
