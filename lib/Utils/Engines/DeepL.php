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

    protected function _decode($rawValue)
    {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
    }

    /**
     * @inheritDoc
     */
    public function get($_config)
    {
        try {
            $client = $this->_getClient();
            $result = $client->translate(
                $_config['segment'],
                $_config['source'],
                $_config['target'],
                $_config['formality'] ? $_config['formality'] : null,
                $_config['idGlossary'] ? $_config['idGlossary'] : null
            );

            return $this->formatMatches($_config, $result);

        } catch ( Exception $e ) {
            return $this->GoogleTranslateFallback( $_config );
        }
    }

    /**
     * @param $_config
     * @param $result
     * @return array
     */
    private function formatMatches($_config, $result)
    {
        $matches = [];

        if(!isset($result['translations'])){
            return $matches;
        }

        foreach ($result['translations'] as $translation){
            $matches[] = [
                'id'               => 0,
                'create_date'      => '0000-00-00',
                'segment'          => $_config['segment'],
                'raw_segment'      => $_config['segment'],
                'translation'      => $translation['text'],
                'source_note'      => '',
                'target_note'      => '',
                'raw_translation'  => $translation['text'],
                'quality'          => 0,
                'reference'        => '',
                'usage_count'      => 0,
                'subject'          => '',
                'created_by'       => '',
                'last_updated_by'  => '',
                'last_update_date' => '0000-00-00',
                'match'            => 0,
                'memory_key'       => '',
                'ICE'              => false,
                'tm_properties'    => [],
                'target'           => $_config['target'],
                'source'           => $_config['source'],
            ];
        }

        return $matches;
    }

    /**
     * @inheritDoc
     */
    public function set($_config)
    {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
    }

    /**
     * @inheritDoc
     */
    public function update($_config)
    {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
    }

    /**
     * @inheritDoc
     */
    public function delete($_config)
    {
        throw new DomainException( "Method " . __FUNCTION__ . " not implemented." );
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