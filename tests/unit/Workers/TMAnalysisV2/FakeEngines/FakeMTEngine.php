<?php

namespace Tests\Unit\Workers\TMAnalysisV2\FakeEngines;

use Utils\Engines\AbstractEngine;

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
    public function __construct($engineRecord)
    {
        // Store the engine record directly without calling parent constructor
        // This avoids HTTP client initialization
        $this->engineRecord = $engineRecord;
        $this->className = get_class($this);

        // Initialize minimal required properties
        $this->curl_additional_params = [];
        $this->featureSet = null;
        $this->logger = null;
        $this->_config = [
            'q' => null,
            'source' => null,
            'target' => null,
        ];
    }

    /**
     * Return canned translation from static property.
     *
     * @param array $_config
     *
     * @return array
     */
    public function get(array $_config): array
    {
        return self::$cannedTranslation;
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
