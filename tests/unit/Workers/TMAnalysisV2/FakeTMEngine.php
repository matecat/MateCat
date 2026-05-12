<?php

namespace unit\Workers\TMAnalysisV2;

use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\Results\MyMemory\SetContributionResponse;
use Utils\Engines\Results\MyMemory\UpdateContributionResponse;
use Utils\Logger\LoggerFactory;

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
        $this->logger = LoggerFactory::getLogger('FakeTMEngine');
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
        return new GetMemoryResponse([
            'matches' => self::$cannedMatches,
            'responseStatus' => 200,
            'responseDetails' => 'OK',
        ]);
    }

    /**
     * @param $_config
     *
     * @return SetContributionResponse|null
     */
    public function set($_config): ?SetContributionResponse
    {
        return null;
    }

    /**
     * @param $_config
     *
     * @return UpdateContributionResponse
     */
    public function update($_config): UpdateContributionResponse
    {
        return new UpdateContributionResponse([]);
    }

    /**
     * @param $_config
     *
     * @return bool
     */
    public function delete($_config): bool
    {
        return true;
    }
}
