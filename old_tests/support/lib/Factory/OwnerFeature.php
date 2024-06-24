<?php

class Factory_OwnerFeature extends Factory_Base {

    static function create( $values ) {
        $values = array_merge(array(
            'uid' => 1,
            'feature_code' => 'project_completion',
            'options' => '{}',
            'enabled' => true,
        ), $values );

        $dao = new OwnerFeatures_OwnerFeatureDao( Database::obtain() );
        $struct = new OwnerFeatures_OwnerFeatureStruct( $values );

        return $dao->create( $struct );
    }
}
