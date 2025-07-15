<?php

use DataAccess\AbstractDaoSilentStruct;
use DataAccess\IDaoStruct;

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

    public $feature_code;
    public $options;

    public function getFullyQualifiedClassName() {
        return Features::getPluginClass( $this->feature_code );
    }

    /**
     * @return \Features\IBaseFeature | null
     */
    public function toNewObject() {
        $name = Features::getPluginClass( $this->feature_code );

        if ( class_exists( $name ) ) {
            return new $name( $this );
        }

        return null;
    }
}