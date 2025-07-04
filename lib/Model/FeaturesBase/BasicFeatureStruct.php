<?php

namespace Model\FeaturesBase;

use Features\IBaseFeature;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

/**
 * Class BasicFeatureStruct
 *
 * A BasicFeatureStruct is a feature that is not bound to a specific user. Example of such class is the DQF feature
 * which is enabled application-wide.
 *
 * BasicFeatureStruct can have options injected when the class is instantiated.
 *
 *
 */
class BasicFeatureStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public string $feature_code;
    /**
     * @var array|string
     */
    public $options;

    public function getFullyQualifiedClassName() {
        return FeaturesFactory::getPluginClass( $this->feature_code );
    }

    /**
     * @return IBaseFeature
     */
    public function toNewObject(): IBaseFeature {
        $name = FeaturesFactory::getPluginClass( $this->feature_code );

        return new $name( $this );
    }

}