<?php

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
class BasicFeatureStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $feature_code ;
    public $options ;

    public function getFullyQualifiedClassName() {
        return Features::getPluginClass( $this->feature_code );
    }

    /**
     * @return \Features\IBaseFeature
     */
    public function toNewObject() {
        $name = Features::getPluginClass( $this->feature_code );
        return new $name($this);
    }

}