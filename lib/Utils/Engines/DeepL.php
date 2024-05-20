<?php

use Engines\DeepL\DeepLApiClient;

class Engines_DeepL extends Engines_AbstractEngine
{
    private $apiKey;

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @return DeepLApiClient
     * @throws Exception
     */
    protected function _getClient()
    {
        if ($this->apiKey === null) {
            throw new Exception("API ket not set");
        }

        return DeepLApiClient::newInstance($this->apiKey);
    }

    /**
     * @param $rawValue
     * @return array
     */
    protected function _decode($rawValue)
    {
        \Log::doJsonLog("PIPPO" . json_encode($rawValue));

        $a = 333;
        $a = 333;
        $a = 333;
        $a = 333;
        $a = 333;
        $a = 333;
        $a = 333;

        return [
//            'id' => 0,
//            'create_date' => '0000-00-00',
//            'segment' => $_config['segment'],
//            'raw_segment' => $_config['segment'],
//            'translation' => $translation,
//            'source_note' => '',
//            'target_note' => '',
//            'raw_translation' => $translation,
//            'quality' => 85,
//            'reference' => '',
//            'usage_count' => 0,
//            'subject' => '',
//            'created_by' => 'MT-DeepL',
//            'last_updated_by' => '',
//            'last_update_date' => '',
//            'match' => 'MT-DeepL',
//            'memory_key' => '',
//            'ICE' => false,
//            'tm_properties' => [],
//            'target' => $_config['target'],
//            'source' => $_config['source'],
        ];
    }

    /**
     * @inheritDoc
     */
    public function get($_config)
    {
        try {
            $source = explode("-", $_config['source']);
            $target = explode("-", $_config['target']);

            $parameters = [
                'segment' => $_config['segment'],
                'source' => $source[0],
                'target' => $target[0],
                'formality' => ($_config['formality'] ? $_config['formality'] : null),
                'idGlossary' => ($_config['idGlossary'] ? $_config['idGlossary'] : null)
            ];

            $headers = [
                'Authorization: DeepL-Auth-Key ' . $this->apiKey,
                'Content-Type: application/json'
            ];

            $this->_setAdditionalCurlParams(
                array(
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($parameters),
                    CURLOPT_HTTPHEADER => $headers
                )
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
    public function delete($_config)
    {
        throw new DomainException("Method " . __FUNCTION__ . " not implemented.");
    }

    /**
     * @return mixed
     * @throws \Engines\DeepL\DeepLApiException
     * @throws Exception
     */
    public function glossaries()
    {
        return $this->_getClient()->allGlossaries();
    }

    /**
     * @param $id
     * @return mixed
     * @throws \Engines\DeepL\DeepLApiException
     * @throws Exception
     */
    public function getGlossary($id)
    {
        return $this->_getClient()->getGlossary($id);
    }

    /**
     * @param $id
     * @return mixed
     * @throws \Engines\DeepL\DeepLApiException
     * @throws Exception
     */
    public function deleteGlossary($id)
    {
        return $this->_getClient()->deleteGlossary($id);
    }

    /**
     * @param $data
     * @return mixed
     * @throws \Engines\DeepL\DeepLApiException
     */
    public function createGlossary($data)
    {
        return $this->_getClient()->createGlossary($data);
    }

    /**
     * @param $id
     * @return mixed
     * @throws \Engines\DeepL\DeepLApiException
     */
    public function getGlossaryEntries($id)
    {
        return $this->_getClient()->getGlossaryEntries($id);
    }
}
    