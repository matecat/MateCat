<?php

namespace Tests\Unit\Workers\TMAnalysisV2\FakeEngines;

use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

class FakeTMEngine extends MyMemory
{
    /**
     * Static canned matches data for testing.
     * Tests set this before EnginesFactory creates instances.
     *
     * @var array
     */
    public static array $cannedMatches = [];

    /**
     * Override constructor to skip HTTP client initialization.
     * Store the engine record but don't call parent's HTTP setup.
     *
     * @param $engineRecord
     */
    public function __construct($engineRecord)
    {
        // Store the engine record directly without calling parent constructor
        // This avoids HTTP client initialization and type validation
        $this->engineRecord = $engineRecord;
        $this->className = get_class($this);

        // Initialize minimal required properties
        $this->curl_additional_params = [];
        $this->featureSet = null;
        $this->logger = null;
        $this->_config = [
            'dataRefMap' => [],
            'segment' => null,
            'translation' => null,
            'tnote' => null,
            'source' => null,
            'target' => null,
            'email' => null,
            'prop' => null,
            'get_mt' => 1,
            'id_user' => null,
            'num_result' => 3,
            'mt_only' => false,
            'isConcordance' => false,
            'isGlossary' => false,
        ];
    }

    /**
     * Return canned matches from static property.
     *
     * @param array $_config
     *
     * @return GetMemoryResponse
     */
    public function get(array $_config): GetMemoryResponse
    {
        $response = new GetMemoryResponse([
            'matches' => self::$cannedMatches,
            'responseStatus' => 200,
            'responseDetails' => 'OK',
        ]);

        return $response;
    }

    /**
     * No-op set method.
     *
     * @param $_config
     *
     * @return bool
     */
    public function set($_config): bool
    {
        return true;
    }

    /**
     * No-op update method.
     *
     * @param $_config
     *
     * @return bool
     */
    public function update($_config): bool
    {
        return true;
    }

    /**
     * No-op delete method.
     *
     * @param $_config
     *
     * @return bool
     */
    public function delete($_config): bool
    {
        return true;
    }
}
