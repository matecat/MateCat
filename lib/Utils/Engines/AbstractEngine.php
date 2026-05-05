<?php

namespace Utils\Engines;

use CURLFile;
use DomainException;
use Exception;
use RuntimeException;
use TypeError;
use Model\Engines\Structs\EngineStruct;
use Model\Engines\Structs\GoogleTranslateStruct;
use Model\FeaturesBase\FeatureSet;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use stdClass;
use Utils\Constants\EngineConstants;
use Utils\Engines\Results\ErrorResponse;
use Utils\Engines\Results\MTResponse;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\MyMemory\Matches;
use Utils\Engines\Results\TMSAbstractResponse;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;
use Utils\Network\MultiCurlHandler;
use Utils\Registry\AppConfig;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 25/02/15
 * Time: 11.59
 *
 */
abstract class AbstractEngine implements EngineInterface
{

    /**
     * @var EngineStruct
     */
    protected EngineStruct $engineRecord;

    protected string $className;

    /** @var array<string, mixed> */
    protected array $_config = [];

    protected mixed $result = [];

    /** @var array<string, mixed> */
    protected array $error = [];

    /** @var array<int, mixed> */
    protected array $curl_additional_params = [];

    protected bool $_isAnalysis = false;
    protected bool $_skipAnalysis = true;

    /**
     * @var bool True if the engine can receive contributions through a `set/update` method.
     */
    protected bool $_isAdaptiveMT = false;

    /**
     * @var bool
     */
    protected bool $logging = true;
    protected string $content_type = 'xml';

    protected ?FeatureSet $featureSet = null;
    protected ?int $mt_penalty = null;

    const int GET_REQUEST_TIMEOUT = 10;
    protected MatecatLogger $logger;

    /**
     * @param EngineStruct $engineRecord
     *
     * @throws Exception
     * @throws TypeError
     */
    public function __construct(EngineStruct $engineRecord)
    {
        $this->engineRecord = $engineRecord;
        $this->className = get_class($this);

        $this->curl_additional_params = [
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => AppConfig::MATECAT_USER_AGENT . AppConfig::$BUILD_NUMBER,
            CURLOPT_CONNECTTIMEOUT => 10, // a timeout to call itself should not be too much higher :D
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ];

        $this->featureSet = new FeatureSet();
        /**
         * Set the initial value to a specific log file, if not already initialized by the Executor.
         * This is useful when engines are used outside the TaskRunner context
         * @see \Utils\TaskRunner\Executor::__construct()
         */
        $this->logger = LoggerFactory::getLogger('engines');
    }

    /**
     * @param int|null $mt_penalty
     *
     * @return $this
     */
    public function setMTPenalty(?int $mt_penalty = null): AbstractEngine
    {
        $this->mt_penalty = $mt_penalty;

        return $this;
    }

    public function setFeatureSet(FeatureSet $fSet = null): void
    {
        if ($fSet != null) {
            $this->featureSet = $fSet;
        }
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setAnalysis(bool $bool = true): AbstractEngine
    {
        $this->_isAnalysis = filter_var($bool, FILTER_VALIDATE_BOOLEAN);

        return $this;
    }

    /**
     * @param bool $bool
     *
     * @return $this
     */
    public function setSkipAnalysis(bool $bool = true): AbstractEngine
    {
        $this->_skipAnalysis = $bool;

        return $this;
    }

    /**
     * Override when some string languages are different
     *
     * @param string $lang
     *
     * @return string
     */
    protected function _fixLangCode(string $lang): string
    {
        $l = explode("-", strtolower(trim($lang)));

        return $l[0];
    }

    /**
     * @return EngineStruct
     */
    public function getEngineRecord(): EngineStruct
    {
        return $this->engineRecord;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        if (property_exists($this->engineRecord, $key)) {
            return $this->engineRecord->$key;
        } elseif (is_array($this->engineRecord->others) && array_key_exists($key, $this->engineRecord->others)) {
            return $this->engineRecord->others[$key];
        } elseif (is_array($this->engineRecord->extra_parameters) && array_key_exists($key, $this->engineRecord->extra_parameters)) {
            return $this->engineRecord->extra_parameters[$key];
        } else {
            return null;
        }
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @throws DomainException
     */
    public function __set(string $key, mixed $value)
    {
        if (property_exists($this->engineRecord, $key)) {
            $this->engineRecord->$key = $value;
        } elseif (is_array($this->engineRecord->others) && array_key_exists($key, $this->engineRecord->others)) {
            $this->engineRecord->others[$key] = $value;
        } elseif (is_array($this->engineRecord->extra_parameters) && array_key_exists($key, $this->engineRecord->extra_parameters)) {
            $this->engineRecord->extra_parameters[$key] = $value;
        } else {
            throw new DomainException("Property $key does not exists in " . get_class($this));
        }
    }

    /**
     * @return list<string>
     */
    abstract public static function getConfigurationParameters(): array;

    /**
     * @param mixed                $rawValue
     * @param array<string, mixed> $parameters
     * @param string|null          $function
     *
     * @return array<string, mixed>|TMSAbstractResponse
     */
    abstract protected function _decode(mixed $rawValue, array $parameters = [], ?string $function = null): array|TMSAbstractResponse;

    /**
     * @param string           $url
     * @param array<int, mixed> $curl_options
     *
     * @return string|bool|null
     */
    public function _call(string $url, array $curl_options = []): string|bool|null
    {
        $mh = new MultiCurlHandler();
        $uniq_uid = uniqid('', true);

        /*
         * Append array elements from the second array
         * to the first array while not overwriting the elements from
         * the first array and not re-indexing
         *
         * Use the + array union operator
         */
        $resourceHash = $mh->createResource(
            $url,
            $this->curl_additional_params + $curl_options,
            $uniq_uid
        );

        if ($resourceHash === null) {
            $mh->multiCurlCloseAll();

            return null;
        }

        $mh->multiExec();

        if ($mh->hasError($resourceHash)) {
            $curl_error = $mh->getError($resourceHash);
            $responseRawValue = $mh->getSingleContent($resourceHash);
            $rawValue = json_encode([
                'error' => [
                    'code' => -(int)$curl_error['errno'],
                    'message' => " {$curl_error[ 'error' ]} - Server Error (http status " . $curl_error['http_code'] . ")",
                    'response' => $responseRawValue
                ],
                'responseStatus' => (int)$curl_error['http_code']
            ]);
        } else {
            $rawValue = $mh->getSingleContent($resourceHash);
        }

        $mh->multiCurlCloseAll();

        if ($this->logging) {
            $log = $mh->getSingleLog($resourceHash);
            if ($this->content_type == 'json' && !$mh->hasError($resourceHash) && is_string($rawValue)) {
                $log['response'] = json_decode($rawValue, true);
            } else {
                $log['response'] = $rawValue;
            }
            $this->logger->debug($log);
        }

        return $rawValue;
    }

    /**
     * @param string               $function
     * @param array<string, mixed> $parameters
     * @param bool                 $isPostRequest
     * @param bool                 $isJsonRequest
     *
     * @return void
     */
    public function call(string $function, array $parameters = [], bool $isPostRequest = false, bool $isJsonRequest = false): void
    {
        if ($this->_isAnalysis && $this->_skipAnalysis) {
            $this->result = [];

            return;
        }

        $this->error = []; // reset last error
        if (!$this->$function) {
            $this->result = [
                'error' => [
                    'code' => -43,
                    'message' => " Bad Method Call. Requested method '$function' not Found."
                ]
            ]; //return a negative number

            return;
        }

        $function = strtolower(trim($function));

        if ($isPostRequest) {
            $url = "{$this->engineRecord['base_url']}/" . $this->$function;
            $curl_opt = [
                CURLOPT_POSTFIELDS => (!$isJsonRequest ? $parameters : json_encode($parameters)),
                CURLINFO_HEADER_OUT => true,
                CURLOPT_TIMEOUT => 120
            ];
        } else {
            $url = "{$this->engineRecord['base_url']}/" . $this->$function . "?";
            $url .= http_build_query($parameters);
            $curl_opt = [
                CURLOPT_HTTPGET => true,
                CURLOPT_TIMEOUT => static::GET_REQUEST_TIMEOUT
            ];
        }

        $rawValue = $this->_call($url, $curl_opt);

        /*
         * $parameters['segment'] is used in MT engines,
         * they do not return the original segment, only the translation.
         * Taken when needed as "variadic function parameter" (func_get_args)
         * 
         * Pass the called $function also
        */
        $this->result = $this->_decode($rawValue, $parameters, $function);
    }

    /**
     * @param array<int, mixed> $curlOptParams
     */
    public function _setAdditionalCurlParams(array $curlOptParams = []): void
    {
        /*
         * Append array elements from the second array to the first array while not
         * overwriting the elements from the first array and not re-indexing.
         *
         * In this case, we cannot use the + array union operator because if there is
         * a file handler in the $curlOptParams, the resource is duplicated, and the
         * reference to the first one is lost.
         * In this way, the CURLOPT_FILE does not work.
         */
        foreach ($curlOptParams as $key => $value) {
            $this->curl_additional_params[$key] = $value;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigStruct(): array
    {
        return $this->_config;
    }

    public function getMtPenalty(): int
    {
        return $this->mt_penalty ?? ($this->engineRecord->penalty ?: 14);
    }

    /**
     * @return string
     */
    public function getStandardMtPenaltyString(): string
    {
        return 100 - $this->getMtPenalty() . "%";
    }

    public function getName(): string
    {
        return $this->engineRecord->name ?? '';
    }

    public function getMTName(string $forceName = ''): string
    {
        if (!empty($forceName)) {
            return "MT-" . $forceName;
        }
        return "MT-" . $this->getName();
    }

    public function isTMS(): bool
    {
        return false;
    }

    public function isAdaptiveMT(): bool
    {
        return $this->_isAdaptiveMT && !$this->isTMS();
    }

    /**
     * @param string $file
     *
     * @return CURLFile
     *
     * @throws RuntimeException
     */
    protected function getCurlFile(string $file): CURLFile
    {
        $resolved = realpath($file);
        if ($resolved === false) {
            throw new RuntimeException("File not found: $file");
        }

        return new CURLFile($resolved);
    }

    /**
     * @param array<string, mixed> $_config
     *
     * @return GetMemoryResponse
     * @throws Exception
     * @throws TypeError
     */
    protected function GoogleTranslateFallback(array $_config): GetMemoryResponse
    {
        try {
            /**
             * Create a record of type GoogleTranslate
             */
            $newEngineStruct = GoogleTranslateStruct::getStruct();

            $newEngineStruct->name = "Generic";
            $newEngineStruct->uid = 0;
            $newEngineStruct->type = EngineConstants::MT;
            $newEngineStruct->extra_parameters['client_secret'] = $_config['secret_key'] ?? null;
            $newEngineStruct->others = [];

            $gtEngine = EnginesFactory::createTempInstance($newEngineStruct);

            /** @var GoogleTranslate $gtEngine */
            return $gtEngine->get($_config);
        } catch (Exception) {
            return new GetMemoryResponse(null);
        }
    }

    /**
     * @param string $filePath
     * @param string $memoryKey
     * @param UserStruct $user
     *
     * @return void
     */
    public function importMemory(string $filePath, string $memoryKey, UserStruct $user)
    {
    }

    /**
     * @param array<string, mixed> $projectRow
     * @param array<string, mixed>|null $segments
     *
     * @return void
     */
    public function syncMemories(array $projectRow, ?array $segments = [])
    {
    }

    /**
     * @param MemoryKeyStruct $memoryKey
     *
     * @return array<string, mixed>|null
     */
    public function memoryExists(MemoryKeyStruct $memoryKey): ?array
    {
        return null;
    }

    /**
     * @param array<string, mixed> $memoryKey
     *
     * @return array<string, mixed>
     */
    public function deleteMemory(array $memoryKey): array
    {
        return [];
    }

    /**
     * @param MemoryKeyStruct $memoryKey
     *
     * @return array<string, mixed>|null
     */
    public function getMemoryIfMine(MemoryKeyStruct $memoryKey): ?array
    {
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
     */
    public function getQualityEstimation(string $source, string $target, string $sentence, string $translation, string $mt_qe_engine_id = 'default'): ?float
    {
        return null;
    }

    /**
     * @param string               $raw_segment
     * @param array<string, mixed> $decoded
     * @param int                  $layerNum
     *
     * @return GetMemoryResponse
     * @throws Exception
     * @throws TypeError
     */
    protected function _composeMTResponseAsMatch(string $raw_segment, array $decoded, int $layerNum = 1): GetMemoryResponse
    {
        $mt_result = new MTResponse($decoded);

        if ($mt_result->error->code < 0) {
            $response = new GetMemoryResponse(null);
            $response->responseStatus = (int)abs($mt_result->error->code);
            $response->error = new ErrorResponse([
                'code' => $mt_result->error->code,
                'message' => $mt_result->error->message ?? '',
            ]);

            return $response;
        }

        $mt_match_res = new Matches([
            'raw_segment' => $raw_segment,
            'raw_translation' => $mt_result->translatedText,
            'match' => $this->getStandardMtPenaltyString(),
            'created-by' => $this->getMTName(),
            'create-date' => date("Y-m-d")
        ]);
        $mt_match_res->featureSet($this->featureSet);

        $response = new GetMemoryResponse(null);
        $response->matches = [$mt_match_res];

        return $response;
    }

    /**
     * Wraps $this->result as GetMemoryResponse.
     *
     * Handles three cases from AbstractEngine::call():
     * - $this->result is already GetMemoryResponse (normal _decode() path)
     * - $this->result is an error array (bad method call early-exit)
     * - $this->result is empty array (skip analysis early-exit)
     *
     * @throws TypeError
     */
    protected function _getResultAsGetMemoryResponse(): GetMemoryResponse
    {
        if ($this->result instanceof GetMemoryResponse) {
            return $this->result;
        }

        $response = new GetMemoryResponse(null);

        if (is_array($this->result) && isset($this->result['error'])) {
            $response->responseStatus = abs((int)($this->result['error']['code'] ?? 500));
            $response->error = new ErrorResponse($this->result['error']);
        }

        return $response;
    }

    /**
     * Validate extra params
     *
     * @param stdClass $extra
     *
     * @return bool
     */
    public function validateConfigurationParams(stdClass $extra): bool
    {
        foreach (array_keys(get_object_vars($extra)) as $key) {
            if (!in_array($key, $this->getConfigurationParameters())) {
                return false;
            }
        }

        return true;
    }
}
