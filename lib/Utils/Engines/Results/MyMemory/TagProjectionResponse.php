<?php

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 17/05/16
 * Time: 11:49
 */

namespace Utils\Engines\Results\MyMemory;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Utils\Engines\Results\TMSAbstractResponse;

class TagProjectionResponse extends TMSAbstractResponse
{
    /** @var array<string, mixed> */
    private array $rawResponse;
    /** @var array<string, mixed> */
    private array $dataRefMap;

    /**
     * @param array<string, mixed> $response
     * @param array<string, mixed> $dataRefMap
     */
    public function __construct(array $response, array $dataRefMap = [])
    {
        $this->rawResponse = $response;
        $this->dataRefMap = $dataRefMap;
    }

    /**
     * @throws Exception
     */
    private function applyFiltering(): void
    {
        $featureSet = $this->featureSet ?? throw new \LogicException('FeatureSet must be set before filtering');
        /** @var MateCatFilter $Filter */
        $Filter = MateCatFilter::getInstance($featureSet, null, null, $this->dataRefMap);
        $this->responseData = isset($this->rawResponse['data']['translation'])
            ? $Filter->fromLayer1ToLayer2($this->rawResponse['data']['translation'])
            : '';
    }

    /**
     * @throws \TypeError
     * @throws Exception
     */
    public static function getInstance(mixed $result, ?FeatureSet $featureSet = null, ?array $dataRefMap = [], ?int $id_project = null): static
    {
        /** @var static $instance */
        $instance = parent::getInstance($result, $featureSet, $dataRefMap, $id_project);
        $instance->applyFiltering();

        return $instance;
    }
}