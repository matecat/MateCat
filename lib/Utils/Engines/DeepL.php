<?php

namespace Utils\Engines;

use DomainException;
use Exception;
use Model\Jobs\JobSettingsResolver;
use Model\Jobs\JobsMetadataMarshaller;
use TypeError;
use Utils\Engines\DeepL\DeepLApiClient;
use Utils\Engines\DeepL\DeepLApiException;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
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
     * @throws TypeError
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
     * @param array<string, mixed> $parameters
     * @param null $function
     *
     * @return GetMemoryResponse
     * @throws Exception
     * @throws TypeError
     */
    protected function _decode(mixed $rawValue, array $parameters = [], $function = null): GetMemoryResponse
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

        $match = new Matches([
            'source' => $source,
            'target' => $target,
            'raw_segment' => $segment,
            'raw_translation' => $translation,
            'match' => "85%",
            'created-by' => $this->getMTName(),
            'create-date' => date("Y-m-d"),
        ]);
        $match->featureSet($this->featureSet);

        $response = new GetMemoryResponse(null);
        $response->matches = [$match];

        return $response;
    }

    /**
     * @inheritDoc
     * @param array<string, mixed> $_config
     * @throws Exception
     * @throws TypeError
     */
    public function get(array $_config): GetMemoryResponse
    {
        $source = explode("-", $_config['source']);
        $target = explode("-", $_config['target']);

        $extraParams = $this->getEngineRecord()->extra_parameters;

        if (!isset($extraParams['DeepL-Auth-Key'])) {
            throw new Exception("DeepL API key not set");
        }

        // Settings (formality, glossary, engine type) live on the job so the project owner can
        // change them after creation, and fall back to project metadata for projects created
        // before the move. Resolved in one pass rather than one lookup per key: this runs once per
        // segment on the MT path.
        // A key missing from both scopes stays null, which is also what happens while validating
        // the engine for the first time (no job and no real project yet).
        $settings = (new JobSettingsResolver($this->database))->resolveManyFromEngineConfig(
            $_config,
            [
                JobsMetadataMarshaller::DEEPL_FORMALITY->value,
                JobsMetadataMarshaller::DEEPL_ID_GLOSSARY->value,
                JobsMetadataMarshaller::DEEPL_ENGINE_TYPE->value,
            ]
        );

        $deepLFormality = $settings[JobsMetadataMarshaller::DEEPL_FORMALITY->value] ?? null;
        $deepLIdGlossary = $settings[JobsMetadataMarshaller::DEEPL_ID_GLOSSARY->value] ?? null;
        $deepLEngineType = $settings[JobsMetadataMarshaller::DEEPL_ENGINE_TYPE->value] ?? null;

        $parameters = [
            'text' => [
                $_config['segment'],
            ],
            'source_lang' => $source[0],
            'target_lang' => $target[0],

            // glossaries (only for DeepL)
            'formality' => $deepLFormality,
            'glossary_id' => $deepLIdGlossary,
            'model_type' => $deepLEngineType
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

        return $this->_getResultAsGetMemoryResponse();
    }

    /**
     * @inheritDoc
     * @param mixed $_config
     * @throws DomainException
     */
    public function set(mixed $_config)
    {
        throw new DomainException("Method " . __FUNCTION__ . " not implemented.");
    }

    /**
     * @inheritDoc
     * @param mixed $_config
     * @throws DomainException
     */
    public function update(mixed $_config)
    {
        throw new DomainException("Method " . __FUNCTION__ . " not implemented.");
    }

    /**
     * @inheritDoc
     * @param mixed $_config
     * @throws DomainException
     */
    public function delete(mixed $_config): bool
    {
        throw new DomainException("Method " . __FUNCTION__ . " not implemented.");
    }

    /**
     * @return array<string, mixed>
     * @throws DeepLApiException
     * @throws Exception
     * @throws TypeError
     */
    public function glossaries(): array
    {
        return $this->_getClient()->allGlossaries();
    }

    /**
     * @param string $id
     *
     * @return array<string, mixed>
     * @throws DeepLApiException
     * @throws Exception
     * @throws TypeError
     */
    public function getGlossary(string $id): array
    {
        return $this->_getClient()->getGlossary($id);
    }

    /**
     * @param string $id
     *
     * @return array<string, mixed>
     * @throws DeepLApiException
     * @throws Exception
     * @throws TypeError
     */
    public function deleteGlossary(string $id): array
    {
        return $this->_getClient()->deleteGlossary($id);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     * @throws DeepLApiException
     * @throws Exception
     * @throws TypeError
     */
    public function createGlossary(array $data): array
    {
        return $this->_getClient()->createGlossary($data);
    }

    /**
     * @param string $id
     *
     * @return array<string, mixed>
     * @throws DeepLApiException
     * @throws Exception
     * @throws TypeError
     */
    public function getGlossaryEntries(string $id): array
    {
        return $this->_getClient()->getGlossaryEntries($id);
    }

    /**
     * @inheritDoc
     */
    public static function getConfigurationParameters(): array
    {
        return [
            'enable_mt_analysis',
            'deepl_formality',
            'deepl_id_glossary',
            'deepl_engine_type',
        ];
    }
}
    
