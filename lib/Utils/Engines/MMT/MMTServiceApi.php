<?php
/**
 * Created by PhpStorm.
 * User: davide
 * Date: 03/10/17
 * Time: 14:14
 */

namespace Utils\Engines\MMT;

use CURLFile;
use Utils\Logger\LoggerFactory;
use Utils\Logger\MatecatLogger;
use Utils\Network\MultiCurlHandler;

class MMTServiceApi
{

    const string DEFAULT_BASE_URL = 'https://api.modernmt.com';

    private string $baseUrl;
    private ?string $license = null;
    private int $client = 0;
    private ?string $platform = null;
    private ?string $platformVersion = null;
    private MatecatLogger $logger;

    /**
     * @param string|null $baseUrl
     *
     * @return MMTServiceApi
     */
    public static function newInstance(?string $baseUrl = null): MMTServiceApi
    {
        $baseUrl = $baseUrl == null ? self::DEFAULT_BASE_URL : rtrim($baseUrl, "/");

        return new static($baseUrl);
    }

    /**
     * MMTServiceApi constructor.
     *
     * @param string $baseUrl
     */
    private function __construct(string $baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->logger = LoggerFactory::getLogger('engines');
    }

    /**
     * @param string $platform the platform name (i.e. "Matecat")
     * @param string $platformVersion the platform version (i.e. "1.10.7")
     *
     * @return MMTServiceApi
     */
    public function setIdentity(string $platform, string $platformVersion): MMTServiceApi
    {
        $this->platform = $platform; // 'Matecat'
        $this->platformVersion = $platformVersion; //Ex: 2.22.24

        return $this;
    }

    /**
     * @param string $license
     *
     * @return MMTServiceApi
     */
    public function setLicense(string $license): MMTServiceApi
    {
        $this->license = $license;

        return $this;
    }

    /**
     * @param int $client
     *
     * @return MMTServiceApi
     */
    public function setClient(int $client): MMTServiceApi
    {
        $this->client = $client;

        return $this;
    }

    /* - Instance --------------------------------------------------------------------------------------------------- */

    /**
     * @return ?array
     * @throws MMTServiceApiException
     */
    public function getAvailableLanguages(): ?array
    {
        return $this->send('GET', "$this->baseUrl/languages");
    }

    /* - User ------------------------------------------------------------------------------------------------------- */

    /**
     * @param $name
     * @param $email
     * @param $stripeToken
     *
     * @return ?array
     * @throws MMTServiceApiException
     */
    public function signup($name, $email, $stripeToken): ?array
    {
        return $this->send('POST', "$this->baseUrl/users", [
            'name' => $name,
            'email' => $email,
            'stripe_token' => $stripeToken
        ]);
    }

    /**
     * @return ?array
     * @throws MMTServiceApiException
     */
    public function me(): ?array
    {
        return $this->send('GET', "$this->baseUrl/users/me");
    }

    /**
     * Get the Quality Estimation of a translation
     *
     * @param string $source
     * @param string $target
     * @param string $sentence
     * @param string $translation
     * @param string $mt_qe_engine_id
     *
     * @return ?array
     * @throws MMTServiceApiException
     */
    public function qualityEstimation(string $source, string $target, string $sentence, string $translation, string $mt_qe_engine_id): ?array
    {
        return $this->send('GET', "$this->baseUrl/translate/qe", [
            "source" => $source,
            "target" => $target,
            "sentence" => $sentence,
            "translation" => $translation,
            'purfect_version' => $mt_qe_engine_id
        ]);
    }


    /* Memory ------------------------------------------------------------------------------------------------------- */

    /**
     * @return ?array
     * @throws MMTServiceApiException
     */
    public function getAllMemories(): ?array
    {
        return $this->send('GET', "$this->baseUrl/memories");
    }

    /**
     * @param string $id
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function getMemoryById(string $id): ?array
    {
        return $this->send('GET', "$this->baseUrl/memories/$id");
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
        return $this->send('POST', "$this->baseUrl/memories", [
            'name' => $name,
            'description' => $description,
            'external_id' => $externalId
        ]);
    }

    /**
     * @param string $id
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function deleteMemory(string $id): ?array
    {
        return $this->send('DELETE', "$this->baseUrl/memories/$id");
    }

    /**
     * @param string $id
     * @param array $data
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function importGlossary(string $id, array $data): ?array
    {
        return $this->send('POST', "$this->baseUrl/memories/$id/glossary", $data, true);
    }

    /**
     * @param string $id
     * @param array $data
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function updateGlossary(string $id, array $data): ?array
    {
        return $this->send('PUT', "$this->baseUrl/memories/$id/glossary", $data);
    }

    /**
     * @param string $uuid
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function importJobStatus(string $uuid): ?array
    {
        return $this->send('GET', "$this->baseUrl/import-jobs/$uuid");
    }

    /**
     * @param string $id
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function getMemory(string $id): ?array
    {
        return $this->send('GET', "$this->baseUrl/memories/$id");
    }

    /**
     * @param string $id
     * @param string $name
     * @param string|null $description
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function updateMemory(string $id, string $name, ?string $description = null): ?array
    {
        return $this->send('PUT', "$this->baseUrl/memories/$id", [
            'name' => $name,
            'description' => $description
        ]);
    }

    /**
     * @param array $externalIds
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function connectMemories(array $externalIds): ?array
    {
        return $this->send('POST', "$this->baseUrl/memories/connect", [
            'external_ids' => implode(',', $externalIds)
        ]);
    }

    /**
     * @param array|string $id
     * @param string $source
     * @param string $target
     * @param string $sentence
     * @param string $translation
     *
     * @param string $session
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function addToMemoryContent(array|string $id, string $source, string $target, string $sentence, string $translation, string $session): ?array
    {
        if (is_array($id)) {
            return $this->send('POST', "$this->baseUrl/memories/content", [
                'memories' => empty($id) ? null : implode(',', $id),
                'source' => $source,
                'target' => $target,
                'sentence' => $sentence,
                'translation' => $translation,
                'session' => $session,
            ]);
        }

        return $this->send('POST', "$this->baseUrl/memories/$id/content", [
            'source' => $source,
            'target' => $target,
            'sentence' => $sentence,
            'translation' => $translation,
            'session' => $session,
        ]);
    }

    /**
     * @param string $uuid
     *
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function jobStatus(string $uuid): void
    {
        $this->send('GET', "$this->baseUrl/import-jobs/$uuid/content", []);
    }

    /**
     * @param string $tuid
     * @param array $memory_keys
     * @param string $source
     * @param string $target
     * @param string $sentence
     * @param string $translation
     * @param string $session
     *
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function updateMemoryContent(string $tuid, array $memory_keys, string $source, string $target, string $sentence, string $translation, string $session): void
    {
        foreach ($memory_keys as $memory) {
            $this->send('PUT', "$this->baseUrl/memories/$memory/content", [
                'tuid' => $tuid,
                'source' => $source,
                'target' => $target,
                'sentence' => $sentence,
                'translation' => $translation,
                'session' => $session
            ]);
        }
    }

    /**
     * @param string $id
     * @param string $tmx
     * @param string|null $compression
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function importIntoMemoryContent(string $id, string $tmx, ?string $compression = null): ?array
    {
        return $this->send('POST', "$this->baseUrl/memories/$id/content", [
            'tmx' => $this->_setCulFileUpload($tmx),
            'compression' => $compression
        ], true);
    }

    /**
     * @param string $uuid
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function getImportJob(string $uuid): ?array
    {
        return $this->send('GET', "$this->baseUrl/import-jobs/$uuid");
    }

    /* Translation -------------------------------------------------------------------------------------------------- */

    /**
     * @param string $source
     * @param array $targets
     * @param string $text
     * @param array|null $hints
     * @param mixed $limit
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function getContextVectorFromText(string $source, array $targets, string $text, ?array $hints = null, ?int $limit = null): ?array
    {
        return $this->send('GET', "$this->baseUrl/context-vector", [
            'source' => $source,
            'targets' => implode(',', $targets),
            'text' => $text,
            'hints' => ($hints ? implode(',', $hints) : null),
            'limit' => $limit
        ]);
    }

    /**
     * @param string $source
     * @param array $targets
     * @param string $file
     * @param string|null $compression
     * @param array|null $hints
     * @param mixed $limit
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function getContextVectorFromFile(string $source, array $targets, string $file, ?string $compression = null, ?array $hints = null, ?int $limit = null): ?array
    {
        return $this->send('GET', "$this->baseUrl/context-vector", [
            'source' => $source,
            'targets' => implode(',', $targets),
            'content' => $this->_setCulFileUpload($file),
            'compression' => $compression,
            'hints' => ($hints ? implode(',', $hints) : null),
            'limit' => $limit
        ], true);
    }

    /**
     * @param string $source
     * @param string $target
     * @param string $text
     * @param string|null $contextVector
     * @param array|null $hints
     * @param string|null $projectId
     * @param int|null $timeout
     * @param string|null $priority
     * @param string|null $session
     * @param string|null $glossaries
     * @param bool|null $ignoreGlossaryCase
     * @param bool|null $include_score
     * @param string|null $mt_qe_engine_id
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    public function translate(
        string $source,
        string $target,
        string $text,
        ?string $contextVector = null,
        ?array $hints = [],
        ?string $projectId = null,
        ?int $timeout = null,
        ?string $priority = null,
        ?string $session = null,
        ?string $glossaries = null,
        ?bool $ignoreGlossaryCase = null,
        ?bool $include_score = null,
        ?string $mt_qe_engine_id = '2'
    ): ?array {
        if (empty($text)) {
            return null;
        }

        $params = [
            'source' => $source,
            'target' => $target,
            'q' => $text,
            'context_vector' => $contextVector,
            'hints' => ($hints ? implode(',', $hints) : null),
            'project_id' => (string)$projectId,
            'timeout' => ($timeout ? ($timeout * 1000) : null),
            'priority' => ($priority ?: 'normal'),
        ];

        if ($session) {
            $params['session'] = $session;
        }

        if ($glossaries) {
            $params['glossaries'] = $glossaries;
        }

        if ($ignoreGlossaryCase) {
            $params['ignore_glossary_case'] = ($ignoreGlossaryCase == 1) ? 'true' : 'false';
        }

        if ($include_score) {
            $params['include_score'] = true;
            $params['purfect_engine_id'] = $mt_qe_engine_id;
        }

        return $this->send('GET', "$this->baseUrl/translate", $params, false, $timeout);
    }

    /* - Low level utils -------------------------------------------------------------------------------------------- */

    /**
     * @param string $file
     *
     * @return CURLFile
     */
    protected function _setCulFileUpload(string $file): CURLFile
    {
        return new CURLFile(realpath($file));
    }

    /**
     * @param string $method
     * @param string $url
     * @param array|null $params
     * @param bool $multipart
     * @param int|null $timeout
     *
     * @return ?array
     * @throws MMTServiceApiException
     * @throws MMTServiceApiRequestException
     */
    protected function send(string $method, string $url, ?array $params = null, ?bool $multipart = false, ?int $timeout = null): ?array
    {
        if ($params) {
            $params = array_filter($params, function ($value) {
                return $value !== null;
            });
        }

        if (empty($params)) {
            $params = null;
        }

        $headers = ["X-HTTP-Method-Override: $method"];

        if ($multipart) {
            $headers[] = 'Content-Type: multipart/form-data';
        } else {
            $headers[] = 'Content-Type: application/json';
            if ($params) {
                $params = json_encode($params);
                $headers[] = strlen($params);
            }
        }

        if ($this->license) {
            $headers[] = "MMT-ApiKey: $this->license";
        }
        if ($this->client > 0) {
            $headers[] = "MMT-ApiClient: $this->client";
        }
        if ($this->platform) {
            $headers[] = "MMT-Platform: $this->platform";
        }
        if ($this->platformVersion) {
            $headers[] = "MMT-PlatformVersion: $this->platformVersion";
        }

        $handler = new MultiCurlHandler($this->logger);
        $handler->verbose = true;

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ];

        if (count($headers) > 0) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        // Every API call MUST be a POST
        // (X-HTTP-Method-Override will override the method)
        $options[CURLOPT_POST] = 1;

        if ($params) {
            $options[CURLOPT_POSTFIELDS] = $params;
        }

        if ($timeout !== null) {
            $options[CURLOPT_TIMEOUT] = $timeout;
        }

        $resourceHashId = $handler->createResource($url, $options);
        $handler->multiExec();
        $handler->multiCurlCloseAll();

        if ($handler->hasError($resourceHashId)) {
            if ($handler->getError($resourceHashId)['errno'] == 28) {
                throw new MMTServiceApiException("TimeoutException", 500, "Unable to contact upstream server ({$handler->getError( $resourceHashId )[ 'errno' ]})");
            } elseif ($handler->getError($resourceHashId)['http_code']) {
                throw new MMTServiceApiRequestException("ServiceException", $handler->getError($resourceHashId)['http_code'], "Get denied ({$handler->getError( $resourceHashId )[ 'http_code' ]})");
            } else {
                throw new MMTServiceApiException("ConnectionException", 500, "Unable to contact upstream server ({$handler->getError( $resourceHashId )[ 'errno' ]})");
            }
        }

        $result = $handler->getSingleContent($resourceHashId);
        $log = $handler->getSingleLog($resourceHashId);
        $log['response'] = $result;

        $this->logger->debug($log);

        return $this->parse($result);
    }

    /**
     * @param string $body
     *
     * @return mixed|null
     * @throws MMTServiceApiException
     */
    private function parse(string $body): ?array
    {
        $json = json_decode($body, true);

        if (json_last_error() != JSON_ERROR_NONE) {
            throw new MMTServiceApiException("ConnectionException", 500, "Unable to decode server response: '$body'");
        }

        $status = $json["status"];
        if (!(200 <= $status and $status < 300)) {
            throw MMTServiceApiException::fromJSONResponse($json);
        }

        return $json['data'] ?? null;
    }

}
