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

    /**
     * @param array<string, mixed> $response
     * @param array<string, mixed> $dataRefMap
     *
     * @throws Exception
     */
    public function __construct(array $response, array $dataRefMap = [])
    {
        $featureSet = ($this->featureSet !== null) ? $this->featureSet : new FeatureSet();
        /** @var MateCatFilter $Filter */
        $Filter = MateCatFilter::getInstance($featureSet, null, null, $dataRefMap);
        $this->responseData = isset($response['data']['translation']) ? $Filter->fromLayer1ToLayer2($response['data']['translation']) : '';
    }

}