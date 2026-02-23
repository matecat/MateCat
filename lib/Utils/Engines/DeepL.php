<?php

namespace Utils\Engines;

use DomainException;
use Exception;
use Model\Projects\MetadataDao;
use Utils\Engines\DeepL\DeepLApiClient;
use Utils\Engines\DeepL\DeepLApiException;
use Utils\Engines\Results\MTResponse;
use Utils\Engines\Results\MyMemory\Matches;

class DeepL extends AbstractEngine
{

    private ?string $apiKey = null;

    public function setApiKey(?string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @return DeepLApiClient
     * @throws Exception
     */
    protected function _getClient(): DeepLApiClient
    {
        $this->apiKey = $this->engineRecord->extra_parameters['DeepL-Auth-Key'] ?? null;

        if ($this->apiKey === null) {
            throw new Exception("API ket not set");
        }

        return DeepLApiClient::newInstance($this->apiKey);
    }

    /**
     * @param mixed $rawValue
     * @param array $parameters
     * @param null $function
     *
     * @return MTResponse[]
     * @throws Exception
     */
    protected function _decode(mixed $rawValue, array $parameters = [], $function = null): array
    {
        $rawValue = json_decode($rawValue, true);

        if (($rawValue['responseStatus'] ?? 200) == 403) {
            /*
            [
                'error' =>
                    [
                        'code' => 0,
                        'message' => '  - Server Error (http status 403)',
                        'response' => '{"message":"This account is not allowed to access the API. You can find more info in our docs: https://developers.deepl.com/docs/getting-started/auth"}',
                    ],
                'responseStatus' => 403,
            ];
            */
            $error = json_decode($rawValue['error']['response'], true);
            throw new Exception($error['message']);
        }

        $translation = $rawValue['translations'][0]['text'];
        $translation = html_entity_decode($translation, ENT_QUOTES | 16);
        $source = $parameters['source_lang'];
        $target = $parameters['target_lang'];
        $segment = $parameters['text'][0];

        return (new Matches([
            'source' => $source,
            'target' => $target,
            'raw_segment' => $segment,
            'raw_translation' => $translation,
            'match' => "85%",
            'created-by' => $this->getMTName(),
            'create-date' => date("Y-m-d"),
        ]))->getMatches(1);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function get(array $_config)
    {
        $source = explode("-", $_config['source']);
        $target = explode("-", $_config['target']);

        $extraParams = $this->getEngineRecord()->extra_parameters;

        if (!isset($extraParams['DeepL-Auth-Key'])) {
            throw new Exception("DeepL API key not set");
        }

        // glossaries (only for DeepL)
        $metadataDao = new MetadataDao();
        // null coalescing operator is used to avoid errors when validating the engine for the first time
        $deepLFormality = $metadataDao->setCacheTTL(86400)->get($_config['pid'], 'deepl_formality');
        $deepLIdGlossary = $metadataDao->setCacheTTL(86400)->get($_config['pid'], 'deepl_id_glossary');
        $deepLEngineType = $metadataDao->setCacheTTL(86400)->get($_config['pid'], 'deepl_engine_type');

        $parameters = [
            'text' => [
                $_config['segment'],
            ],
            'source_lang' => $source[0],
            'target_lang' => $target[0],

            // glossaries (only for DeepL)
            'formality' => $deepLFormality?->value ?? null,
            'glossary_id' => $deepLIdGlossary?->value ?? null,
            'model_type' => $deepLEngineType?->value ?? null
        ];

        $this->_setAdditionalCurlParams(
            [
                CURLOPT_HTTPHEADER => [
                    'Authorization: DeepL-Auth-Key ' . $extraParams['DeepL-Auth-Key'],
                    'Content-Type: application/json'
                ],
            ]
        );

        $this->call("translate_relative_url", $parameters, true, true);

        return $this->result;
    }

    /**
     * @inheritDoc
     */
    public function set(mixed $_config)
    {
        throw new DomainException("Method " . __FUNCTION__ . " not implemented.");
    }

    /**
     * @inheritDoc
     */
    public function update(mixed $_config)
    {
        throw new DomainException("Method " . __FUNCTION__ . " not implemented.");
    }

    /**
     * @inheritDoc
     */
    public function delete(mixed $_config): bool
    {
        throw new DomainException("Method " . __FUNCTION__ . " not implemented.");
    }

    /**
     * @return array
     * @throws DeepLApiException
     * @throws Exception
     */
    public function glossaries(): array
    {
        return $this->_getClient()->allGlossaries();
    }

    /**
     * @param string $id
     *
     * @return array
     * @throws DeepLApiException
     * @throws Exception
     */
    public function getGlossary(string $id): array
    {
        return $this->_getClient()->getGlossary($id);
    }

    /**
     * @param string $id
     *
     * @return array
     * @throws DeepLApiException
     * @throws Exception
     */
    public function deleteGlossary(string $id): array
    {
        return $this->_getClient()->deleteGlossary($id);
    }

    /**
     * @param array $data
     *
     * @return array
     * @throws DeepLApiException
     * @throws Exception
     */
    public function createGlossary(array $data): array
    {
        return $this->_getClient()->createGlossary($data);
    }

    /**
     * @param string $id
     *
     * @return array
     * @throws DeepLApiException
     * @throws Exception
     */
    public function getGlossaryEntries(string $id): array
    {
        return $this->_getClient()->getGlossaryEntries($id);
    }

    /**
     * @inheritDoc
     */
    public function getConfigurationParameters(): array
    {
        return [
            'enable_mt_analysis',
            'deepl_formality',
            'deepl_id_glossary',
            'deepl_engine_type',
        ];
    }
}
    