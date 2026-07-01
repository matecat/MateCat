<?php

namespace Matecat\Core\Workers\TMAnalysisV2;

use Model\DataAccess\IDatabase;
use Model\FeaturesBase\FeatureSet;
use Utils\Engines\AbstractEngine;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Logger\LoggerFactory;

class FakeMTEngine extends AbstractEngine
{
    /**
     * Static canned translation data for testing.
     * Tests set this before EnginesFactory creates instances.
     *
     * @var array
     */
    public static array $cannedTranslation = [];

    /**
     * Override constructor to skip HTTP client initialization.
     * Store the engine record but don't call parent's HTTP setup.
     *
     * @param $engineRecord
     */
    public function __construct($engineRecord, IDatabase $database)
    {
        // Store the engine record directly without calling parent constructor
        // This avoids HTTP client initialization
        $this->engineRecord = $engineRecord;
        $this->className = get_class($this);
        $this->database = $database;

        // Initialize minimal required properties
        $this->curl_additional_params = [];
        $this->featureSet = new FeatureSet($database);
        $this->logger = LoggerFactory::getLogger('FakeMTEngine');
        $this->_config = [
            'q' => null,
            'source' => null,
            'target' => null,
        ];
    }

    /**
     * Return canned translation wrapped in a GetMemoryResponse.
     *
     * Uses FakeGetMemoryResponse to bypass the MyMemory parsing pipeline
     * while satisfying the EngineInterface return type contract.
     *
     * @param array $_config
     *
     * @return GetMemoryResponse
     */
    public function get(array $_config): GetMemoryResponse
    {
        $response = new FakeGetMemoryResponse(self::$cannedTranslation);
        $response->featureSet($this->featureSet);

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

    /**
     * @inheritDoc
     */
    protected function _decode(mixed $rawValue, array $parameters = [], $function = null): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public static function getConfigurationParameters(): array
    {
        return [];
    }
}
