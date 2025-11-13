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

    const array ALLOWED_MODEL_TYPES = [
            "latency_optimized",
            "quality_optimized",
            "prefer_quality_optimized",
    ];

    private ?string $apiKey = null;

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @return DeepLApiClient
     * @throws Exception
     */
    protected function _getClient(): DeepLApiClient
    {
        if ($this->apiKey === null) {
            throw new Exception("API ket not set");
        }

        return DeepLApiClient::newInstance($this->apiKey);
    }

    /**
     * @param mixed $rawValue
     * @param array $parameters
     * @param null  $function
     *
     * @return MTResponse[]
     * @throws Exception
     */
    protected function _decode(mixed $rawValue, array $parameters = [], $function = null): array
    {
        $rawValue    = json_decode($rawValue, true);
        $translation = $rawValue[ 'translations' ][ 0 ][ 'text' ];
        $translation = html_entity_decode($translation, ENT_QUOTES | 16);
        $source      = $parameters[ 'source_lang' ];
        $target      = $parameters[ 'target_lang' ];
        $segment     = $parameters[ 'text' ][ 0 ];

        return (new Matches([
                'source'          => $source,
                'target'          => $target,
                'raw_segment'     => $segment,
                'raw_translation' => $translation,
                'match'           => "85%",
                'created-by'      => $this->getMTName(),
                'create-date'     => date("Y-m-d"),
        ]))->getMatches(1);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function get(array $_config)
    {
        try {
            $source = explode("-", $_config[ 'source' ]);
            $target = explode("-", $_config[ 'target' ]);

            $extraParams = $this->getEngineRecord()->extra_parameters;

            if (!isset($extraParams[ 'DeepL-Auth-Key' ])) {
                throw new Exception("DeepL API key not set");
            }

            // glossaries (only for DeepL)
            $metadataDao     = new MetadataDao();
            $deepLFormality  = $metadataDao->get($_config[ 'pid' ], 'deepl_formality', 86400);
            $deepLIdGlossary = $metadataDao->get($_config[ 'pid' ], 'deepl_id_glossary', 86400);
            $deepLEngineType = $metadataDao->get($_config[ 'pid' ], 'deepl_engine_type', 86400);

            if ($deepLEngineType !== null and in_array($deepLEngineType->value, self::ALLOWED_MODEL_TYPES)) {
                $_config[ 'model_type' ] = $deepLEngineType->value;
            }

            if ($deepLFormality !== null) {
                $_config[ 'formality' ] = $deepLFormality->value;
            }

            if ($deepLIdGlossary !== null) {
                $_config[ 'idGlossary' ] = $deepLIdGlossary->value;
            }
            // glossaries (only for DeepL)

            $parameters = [
                    'text'        => [
                            $_config[ 'segment' ],
                    ],
                    'source_lang' => $source[ 0 ],
                    'target_lang' => $target[ 0 ],
                    'formality'   => ($_config[ 'formality' ] ?: null),
                    'glossary_id' => ($_config[ 'idGlossary' ] ?: null)
            ];

            if (!empty($_config[ 'model_type' ])) {
                $parameters[ 'model_type' ] = $_config[ 'model_type' ];
            }

            $headers = [
                    'Authorization: DeepL-Auth-Key ' . $extraParams[ 'DeepL-Auth-Key' ],
                    'Content-Type: application/json'
            ];

            $this->_setAdditionalCurlParams(
                    [
                            CURLOPT_POST           => true,
                            CURLOPT_POSTFIELDS     => json_encode($parameters),
                            CURLOPT_HTTPHEADER     => $headers,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HEADER         => false,
                            CURLOPT_SSL_VERIFYPEER => true,
                            CURLOPT_SSL_VERIFYHOST => 2
                    ]
            );

            $this->call("translate_relative_url", $parameters, true);

            return $this->result;
        } catch (Exception $e) {
            return $this->GoogleTranslateFallback($_config);
        }
    }

    /**
     * @inheritDoc
     */
    public function set($_config)
    {
        throw new DomainException("Method " . __FUNCTION__ . " not implemented.");
    }

    /**
     * @inheritDoc
     */
    public function update($_config)
    {
        throw new DomainException("Method " . __FUNCTION__ . " not implemented.");
    }

    /**
     * @inheritDoc
     */
    public function delete($_config): bool
    {
        throw new DomainException("Method " . __FUNCTION__ . " not implemented.");
    }

    /**
     * @return mixed
     * @throws DeepLApiException
     * @throws Exception
     */
    public function glossaries()
    {
        return $this->_getClient()->allGlossaries();
    }

    /**
     * @param string $id
     *
     * @return mixed
     * @throws DeepLApiException
     * @throws Exception
     */
    public function getGlossary(string $id)
    {
        return $this->_getClient()->getGlossary($id);
    }

    /**
     * @param string $id
     *
     * @return mixed
     * @throws DeepLApiException
     * @throws Exception
     */
    public function deleteGlossary(string $id)
    {
        return $this->_getClient()->deleteGlossary($id);
    }

    /**
     * @param array $data
     *
     * @return mixed
     * @throws DeepLApiException
     * @throws Exception
     */
    public function createGlossary(array $data)
    {
        return $this->_getClient()->createGlossary($data);
    }

    /**
     * @param string $id
     *
     * @return mixed
     * @throws DeepLApiException
     * @throws Exception
     */
    public function getGlossaryEntries(string $id)
    {
        return $this->_getClient()->getGlossaryEntries($id);
    }

    /**
     * @inheritDoc
     */
    public function getExtraParams(): array
    {
        return [
                'enable_mt_analysis',
                'deepl_formality',
                'deepl_id_glossary',
                'deepl_engine_type',
        ];
    }
}
    