<?php

namespace Engines;

use Engines\DeepL\DeepLApiClient;
use Engines_AbstractEngine;

class DeepL extends Engines_AbstractEngine
{
    /**
     * @return DeepLApiClient
     */
    protected function _getClient()
    {
        return DeepLApiClient::newInstance();
    }

    /**
     * @return mixed
     * @throws DeepL\DeepLApiException
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