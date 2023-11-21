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
        if($this->apiKey === null){
            throw new Exception("API ket not set");
        }

        return DeepLApiClient::newInstance($this->apiKey);
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

    protected function _decode($rawValue)
    {
        // TODO: Implement _decode() method.
    }

    /**
     * @inheritDoc
     */
    public function get($_config)
    {
        // TODO: Implement get() method.
    }

    /**
     * @inheritDoc
     */
    public function set($_config)
    {
        // TODO: Implement set() method.
    }

    /**
     * @inheritDoc
     */
    public function update($_config)
    {
        // TODO: Implement update() method.
    }

    /**
     * @inheritDoc
     */
    public function delete($_config)
    {
        // TODO: Implement delete() method.
    }
}